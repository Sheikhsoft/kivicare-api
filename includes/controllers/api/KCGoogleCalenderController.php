<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCGoogleCalenderController extends KCBase
{
    public $module = 'google-calendar';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

        add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/connect-doctor', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'connectDoctor' ],
				'permission_callback' => '__return_true',
			));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/disconnect-doctor', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'disConnectDoctor' ],
				'permission_callback' => '__return_true',
			));
        });
    }
    public function connectDoctor($request)
    {
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
        $parameters = $request->get_params();

        $response = apply_filters('kcpro_connect_doctor', [
            'id'    => $data['user_id'],
            'code'  => $parameters['code'],
        ]);
        
        if($response['status']){
            $status_code = 200;
        }else{
            $status_code = 400;
        }
        return comman_custom_response($response,$status_code);
    }

    public function disConnectDoctor($request)
    {
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
        $parameters = $request->get_params();
        
        $response = apply_filters('kcpro_disconnect_doctor', [
            'id' => $data['user_id']
        ]);

        if($response['status']){
            $status_code = 200;
        }else{
            $status_code = 400;
        }
        return comman_custom_response($response,$status_code);
    }
}