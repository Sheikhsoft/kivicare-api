<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCPatientController extends KCBase {

	public $module = 'patient';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-list', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getPatientList' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/save-medical-history', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'saveMedicalHistory' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete-medical-history', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'deleteMedicalHistory' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete-patient', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'deletePatient' ],
				'permission_callback' => '__return_true',
			));

			if( $this->isKiviCareProOnName() )
			{
				register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/upload-patient-report', array(
					'methods'             => WP_REST_Server::ALLMETHODS,
					'callback'            => [ $this, 'uploadPatientReport' ],
					'permission_callback' => '__return_true',
				));

				register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-patient-report', array(
					'methods'             => WP_REST_Server::ALLMETHODS,
					'callback'            => [ $this, 'getPatientReport' ],
					'permission_callback' => '__return_true',
				));

				register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete-patient-report', array(
					'methods'             => WP_REST_Server::ALLMETHODS,
					'callback'            => [ $this, 'deletePatientReport' ],
					'permission_callback' => '__return_true',
				));
			}
		});
	}

	public function getPatientList( $request ) {

		global $wpdb;
 
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$parameters = $request->get_params();
		$patient_encounter_table	= $wpdb->prefix. 'kc_' . 'patient_encounters';
        $patientCount = collect(get_users( [
            'role' => $this->getPatientRole(),
		] ));
		
		$patientCounts = count($patientCount);

       $args['role']    = $this->getPatientRole();
       $args['number']  = (isset($parameters['limit']) && $parameters['limit'] != '' ) ? $parameters['limit'] : 10;
       $args['paged']   = (isset($parameters['page']) && $parameters['page'] != '' ) ? $parameters['page'] : 1;
       $args['orderby'] = 'ID';
       $args['order']   = 'DESC';

       $patients = collect(get_users( $args ));

       if ( ! count( $patients ) ) {
            $patients['message'] = 'No patient found';
            return comman_message_response($patients);
        }

        $pateint_data = [];

        foreach ( $patients as $key => $patient ) {
			$user_meta = get_user_meta( $patient->ID, 'basic_data', true );

			$pateint_data[ $key ]['ID']              = $patient->ID;
			$pateint_data[ $key ]['display_name']    = $patient->data->display_name;
			$pateint_data[ $key ]['user_email']      = $patient->data->user_email;
			$pateint_data[ $key ]['user_status']     = $patient->data->user_status;
			$pateint_data[ $key ]['user_registered'] = $patient->data->user_registered;

			if ( $user_meta !== null ) {
				$basic_data                    = json_decode( $user_meta );
				$pateint_data[ $key ]['mobile_number'] = $basic_data->mobile_number;
				$pateint_data[ $key ]['blood_group']   = $basic_data->blood_group;
				$pateint_data[ $key ]['gender']   = $basic_data->gender;
                $pateint_data[ $key ]['profile_image'] = property_exists( $basic_data, 'profile_image') ? wp_get_attachment_url( $basic_data->profile_image ) : null;
			}
			
			$pateint_data[ $key ]['patient_added_by'] = get_user_meta( $patient->ID, 'patient_added_by', true );

			$encounters_count = $wpdb->get_results( " SELECT count(*) AS count FROM {$patient_encounter_table} WHERE patient_id = {$patient->ID} ",OBJECT);
			$pateint_data[ $key]['total_encounter'] = $encounters_count[0]->count;
        }
		$response['total'] = $patientCounts;
        $response['data'] = $pateint_data;
        return comman_custom_response($response);
  
	}

	public function saveMedicalHistory( $request ) {

		global $wpdb;
 
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$parameters = $request->get_params();

		$rules = [
			"encounter_id"	=> 'required',
			"type"			=> 'required',
			"title"			=> 'required'
		];

		$parameters = $request->get_params();

        $validation = kcaValidateRequest( $rules, $parameters );
        
        if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}

		$user_id = $data['user_id'];

		$patient_encounter_table	= $wpdb->prefix. 'kc_' . 'patient_encounters';
		$medical_history_table  	= $wpdb->prefix. 'kc_' . 'medical_history';

		$encounter_data = $wpdb->get_row( "SELECT * FROM {$patient_encounter_table} WHERE id = {$parameters["encounter_id"]}", OBJECT );

		$temp = [
			"encounter_id" 	=> $parameters["encounter_id"],
			"type"         	=> $parameters["type"],
			"patient_id"	=> $encounter_data->patient_id,
    		"title"        	=> $parameters["title"]
		];
        $id = (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;

		if ( $id == null) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$temp['added_by']   = $user_id;
			$encounter_id       = $wpdb->insert( $medical_history_table, $temp );
			$message            = 'Medical history has been saved successfully';
			$id = $wpdb->insert_id;

		} else {
			$status       = $wpdb->update( $medical_history_table , $temp, array( 'id' => $id ) );
			$message      = 'Medical history has been updated successfully';
		}
		$medical_data = $wpdb->get_row("SELECT * FROM {$medical_history_table} WHERE id = {$id}", OBJECT );
        
        return comman_custom_response ( $medical_data );
	}

	public function deleteMedicalHistory( $request ) {

		global $wpdb;
 
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$medical_history_table = $wpdb->prefix. 'kc_' . 'medical_history';
		$parameters = $request->get_params();

		$results = $wpdb->delete( $medical_history_table , array ('id' => $parameters['id']) );

		if ( $results ) {
			$message = 'Medical history has been deleted successfully';
		} else {
			$message = 'Medical history delete failed';
		}

		return comman_message_response( $message );

	}

	public function deletePatient($request)
	{
		global $wpdb;

		$appointment_table 	= $wpdb->prefix. 'kc_' . 'appointments';
		$encounter_table	= $wpdb->prefix. 'kc_' . 'patient_encounters';
		$medical_history_table = $wpdb->prefix. 'kc_' . 'medical_history';
		$custom_fielddata_table = $wpdb->prefix . 'kc_' . 'custom_fields_data';

		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$parameters = $request->get_params();

		$patient_id = $parameters['patient_id'];

		$wpdb->delete( $appointment_table , [ 'patient_id' => $patient_id ] );
		$wpdb->delete( $encounter_table , [ 'patient_id' => $patient_id ] );
		$wpdb->delete( $medical_history_table , [ 'patient_id' => $patient_id ] );
		$wpdb->delete( $custom_fielddata_table , [ 'module_type' => 'patient_module' , 'module_id' => $patient_id ]);
		require_once(ABSPATH.'wp-admin/includes/user.php');
		$results = wp_delete_user( $patient_id );
		$status = 200;
		if ( $results ) {
			$message = 'Patient has been deleted successfully';
		} else {
			$message = 'Data not found';
			$status = 400;
		}

		return comman_message_response($message, $status);
	}

	public function getPatientReport( $request )
	{

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }

        $userid = $data['user_id'];

        $parameters = $request->get_params();
		if( $this->isKiviCareProOnName() )
		{
			$response = apply_filters('kcapro_get_patient_report', $parameters);
		}
        return comman_custom_response($response);

    }

	public function uploadPatientReport($request)
	{
		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		if( $this->isKiviCareProOnName() )
		{
			$parameters = $request->get_params();
			$parameters['upload_report'] = $_FILES['upload_report'];
			$response = apply_filters('kcapro_upload_patient_report', $parameters);
		}
		
		return comman_custom_response( $response );
	}

	public function deletePatientReport($request)
	{
		global $wpdb;

		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

		if( $this->isKiviCareProOnName() )
		{
			$parameters = $request->get_params();
			$message = apply_filters('kcapro_delete_patient_report', $parameters);
		}

		return comman_message_response( $message );
	}
}