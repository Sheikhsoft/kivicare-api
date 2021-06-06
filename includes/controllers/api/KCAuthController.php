<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCAuthController extends KCBase
{

	public $module = 'auth';

	public $nameSpace;

	function __construct()
	{

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action('rest_api_init', function () {

			register_rest_route($this->nameSpace . '/api/v1/' . $this->module, '/registration', array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [$this, 'createUser'],
				'permission_callback' => '__return_true',
			));
		});
	}

	public function createUser($request)
	{

		$reqArr = $request->get_params();

		$validation = kcaValidateRequest([
			'first_name' 	=> 'required',
			'last_name' 	=> 'required',
			'user_email' 	=> 'email|required',
			'mobile_number' => 'required',
			'dob'           => 'required',
			'gender'        => 'required',
		], $reqArr);


		if (count($validation)) {
			return comman_message_response($validation[0], 400);
		}
		$reqArr['user_login'] = kcaGenerateUsername($reqArr['first_name']);
		$reqArr['user_pass'] = $reqArr["mobile_number"];
		$res = wp_insert_user($reqArr);

		if (isset($res->errors)) {
			return comman_message_response(kcaGetErrorMessage($res), 400);
		}

		wp_update_user([
			'ID' => $res,
			'first_name' => $reqArr['first_name'],
			'last_name' => $reqArr['last_name']
		]);

		$users = get_userdata($res);

		$users->set_role($this->getPatientRole());

		if ($users) {

			$user_email_param = array(
				'username'            => $users->user_login,
				'user_email'          => $users->user_email,
				'password'            => $reqArr['user_pass'],
				'email_template_type' => 'patient_register'
			);
			kcaSendEmail($user_email_param);
		}
		$temp = [
			"mobile_number" => $reqArr["mobile_number"],
			"gender"        => $reqArr["gender"],
			"dob"           => $reqArr["dob"],
			"address"       => $reqArr["address"],
			"city"          => $reqArr["city"],
			// "state"         => $reqArr["state"],
			"country"       => $reqArr["country"],
			"postal_code"   => $reqArr["postal_code"],
			"blood_group"   => $reqArr["blood_group"],
		];
		$data = kcaValidationToken();
		$added_by = null;
		if ($data['status']) {
			$added_by = $data['user_id'];
		}
		update_user_meta($users->ID, "basic_data", json_encode($temp));
		update_user_meta($users->ID, "patient_added_by", $added_by);

		$basic_data  = get_user_meta($users->ID, "basic_data", true);

		$user_data = [
			"first_name" 	=> $users->first_name,
			"last_name" 	=> $users->last_name,
			"user_email" 	=> $users->user_email,
			"user_login" 	=> $users->user_login,
		];
		$response["data"] = (object) array_merge($user_data, (array) json_decode($basic_data));
		$response["message"] = __("Register succesfully");
		return comman_custom_response($response);
	}
}
