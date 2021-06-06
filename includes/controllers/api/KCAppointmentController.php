<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCAppointmentController extends KCBase {

    public $module = 'appointment';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-appointment', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'get_appointment' ],
				'permission_callback' => '__return_true',
			));


			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-appointment-slot', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'get_appointment_slots' ],
				'permission_callback' => '__return_true',
			));
			
			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/update-status', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'updateStatus' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/save', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'saveAppointment' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'deleteAppointment' ],
				'permission_callback' => '__return_true',
			));
			
		});
    }

    public function get_appointment ( $request ) {
		
		global $wpdb;

		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

		$role = $data['role'];
		$userid = $data['user_id'];
		
		$parameters = $request->get_params();

		$parameters['isKiviCareProOnName'] = $this->isKiviCareProOnName();
		$appointment_list = kcaAppointmentList( $parameters , $data );

		return comman_custom_response($appointment_list);
	}
	
	public function get_appointment_slots($request) {

		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

		$paramaters = $request->get_params();

		$rules = [
			'date'      => 'required',
			'clinic_id' => 'required',
			'doctor_id' => 'required',
		];

		$message = [
			'clinic_id' => esc_html__('Clinic is required', 'kca-lang'),
			'doctor_id' => esc_html__('Doctor is required', 'kca-lang'),
		];

		$validation = kcaValidateRequest( $rules, $paramaters, $message );

		if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}
	}

    public function updateStatus($request) {
        global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }
		$userid = $data['user_id'];
        $parameters = $request->get_params();
        $appointment_table = $wpdb->prefix. 'kc_' . 'appointments';
        $rules  = [
			'appointment_id'     => 'required',
			'appointment_status' => 'required',
		];

        $validation = kcaValidateRequest( $rules, $parameters );
        
        if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}

		$wpdb->update( $appointment_table , [ 'status' => $parameters['appointment_status'] ], array( 'id' => $parameters['appointment_id'] ) );

		if ( $parameters['appointment_status'] === '2' || $parameters['appointment_status'] === '4' ) {
			// Create Encounter
			$result = $this->createEncounter( $parameters['appointment_id'] , $userid );

		}

		if ($parameters['appointment_status'] === '3' || $parameters['appointment_status'] === '0' ) {
			// Close Encounter
			$result = $this->closeEncounter( $parameters['appointment_id'] );
		}
		$status_code = 200;
		$message = "Appointment status has been updated successfully";
		if ( !$result ) {
			$message = "Appointment status update failed";
			$status_code = 400;
		}

		return comman_message_response ( $message , $status_code);

    }

    public function createEncounter($appointment_id, $userid) {
        global $wpdb;

        $appointment_id = (isset($appointment_id) && $appointment_id != '' ) ? $appointment_id : null;
        
        $appointment_table  = $wpdb->prefix. 'kc_' . 'appointments';
        $encounter_table    = $wpdb->prefix. 'kc_' . 'patient_encounters';
		$service_table		= $wpdb->prefix. 'kc_' . 'services';
		$service_mapping_table = $wpdb->prefix. 'kc_' . 'service_doctor_mapping';
		$appointment_service_table	= $wpdb->prefix. 'kc_' . 'appointment_service_mapping';
        $query = " SELECT * FROM {$appointment_table} WHERE id = {$appointment_id}";
        $appointment = $wpdb->get_row( $query, OBJECT );
        
        $encounter = $wpdb->get_results( " SELECT * FROM {$encounter_table} WHERE appointment_id = {$appointment_id}" , OBJECT );
        
        if ( $encounter === [] ) {
            $temp = [
                "appointment_id" => $appointment_id,
                "encounter_date" => date('Y-m-d'),
                "clinic_id" => $appointment->clinic_id,
                "doctor_id" => $appointment->doctor_id,
                "patient_id" => $appointment->patient_id,
                "description" => $appointment->description,
                "added_by" => $userid,
                "status"  => 1,
                "created_at" => current_time( 'Y-m-d H:i:s' )
            ];

			$encounter_result = $wpdb->insert( $encounter_table , $temp );
/*
			$config = kcaGetModules();

			$modules = collect($config->module_config)->where('name','billing')->where('status', 1)->count();

		if($modules > 0){*/
			$appointment_services = $wpdb->get_results("SELECT service_id FROM {$appointment_service_table} WHERE appointment_id = {$appointment_id} ", OBJECT);
			
			$service_ids = collect($appointment_services)->pluck('service_id')->implode(',');
			$service_data = $wpdb->get_results(" SELECT * FROM {$service_mapping_table} WHERE doctor_id = {$appointment->doctor_id} AND service_id IN ($service_ids)  ", OBJECT);

			
			$total_amount = 0;
			foreach( $service_data as $service ){
				$total_amount = $total_amount + $service->charges;
				$bill_item_data[] = [
					'id'		=> "",
					'item_id'	=> $service->service_id,
					'price'		=> $service->charges,
					'qty'		=> 1,
					'bill_id'	=> "",
				];
			}

			// print_r($bill_item_data);die;
			$bill_data = [
				'encounter_id'  => $wpdb->insert_id,
				'appointment_id'=> $appointment_id,
				'total_amount'  => $total_amount,
				'discount'      => 0,
				'actual_amount' => $total_amount,
				'status'        => ( isset($parameters['status']) && $parameters['status'] != '' ) ? $parameters['status'] : 0,
				'payment_status'=> ( isset($parameters['payment_status']) && $parameters['payment_status'] != '' ) ? $parameters['payment_status'] : null,
				'created_at'	=> current_time( 'Y-m-d H:i:s' ),
			];
			$bill_data['billItems'] = $bill_item_data;
			kcaGenerateBill($bill_data);
		// }
			return true;
        }else {
			return false;	
		}
	}

	public function closeEncounter( $appointment_id ) {
		global $wpdb;

        $appointment_id = (isset($appointment_id) && $appointment_id != '' ) ? $appointment_id : null;
        
		$encounter_table = $wpdb->prefix. 'kc_' . 'patient_encounters';
		
		$encounter = $wpdb->get_row(" SELECT * FROM {$encounter_table} WHERE appointment_id = {$appointment_id}" , OBJECT );

		if ($encounter !== []) {
			$wpdb->update( $encounter_table , [ 'status' => '0' ], [ 'id' => $encounter->id ] );
			return true;
		}
		return false;
	}

	public function saveAppointment($request) {
		global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }

        $parameters = $request->get_params();
		$appointment_table 		= $wpdb->prefix. 'kc_' . 'appointments';
		$service_table 		= $wpdb->prefix. 'kc_' . 'services';
		$appointment_service_table	= $wpdb->prefix. 'kc_' . 'appointment_service_mapping';
		$encounter_table   		= $wpdb->prefix. 'kc_' . 'patient_encounters';
		$clinic_session_table 	= $wpdb->prefix. 'kc_' . 'clinic_sessions';
		$role 	= $data['role'];
		$userid = $data['user_id'];

		/* 
		if ( $role == 'doctor' ) {
			$parameters['doctor_id'] = $userid;
		}

		if ( $role == 'patient' ) {
			$parameters['patient_id'] = $userid;
		}
		*/
		$rules = [
			'appointment_start_date' => 'required',
			'appointment_start_time' => 'required',
			'visit_type'             => 'required',
			'clinic_id'              => 'required',
			'doctor_id'              => 'required',
			'patient_id'             => 'required',
			'status'                 => 'required',
		];

		$message = [
			'status'     => 'Status is required',
			'patient_id' => 'Patient is required',
			'clinic_id'  => 'Clinic is required',
			'doctor_id'  => 'Doctor is required',
			'visit_type' => 'Service type is required'
		];

        $validation = kcaValidateRequest( $rules, $parameters ,$message );
        
        if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}
		$day = strtolower(date('D', strtotime($parameters['appointment_start_time'])));

		$clinic_session = $wpdb->get_row(" SELECT * FROM {$clinic_session_table} WHERE clinic_id = {$parameters['clinic_id']} AND doctor_id = {$parameters['doctor_id']} AND day = '{$day}' " , OBJECT );
		
		$time_slot = isset($clinic_session->time_slot) ? $clinic_session->time_slot : 15;

		$end_time             = strtotime( "+" . $time_slot . " minutes", strtotime( $parameters['appointment_start_time'] ) );
		$appointment_end_time = date( 'H:i:s', $end_time );
		$appointment_date     = date( 'Y-m-d', strtotime( $parameters['appointment_start_date'] ) );

		$temp = [
			'appointment_start_date' => $appointment_date,
			'appointment_start_time' => date( 'H:i:s', strtotime( $parameters['appointment_start_time'] ) ),
			'appointment_end_date'   => $appointment_date,
			'appointment_end_time'   => $appointment_end_time,
			'clinic_id'              => $parameters['clinic_id'],
			'doctor_id'              => $parameters['doctor_id'],
			'patient_id'             => $parameters['patient_id'],
			'description'            => $parameters['description'],
			'status'                 => $parameters['status']
		];

		if ( isset( $parameters['id'] ) && $parameters['id'] !== "" ) {
			$appointment_id = $parameters['id'];
			$wpdb->update( $appointment_table, $temp, array( 'id' => $appointment_id ) );

			$encounter_data = [
				'encounter_date'	=> $appointment_date,
				'patient_id'		=> $parameters['patient_id'],
				'doctor_id'			=> $parameters['doctor_id'],
				'clinic_id'			=> $parameters['clinic_id'],
				'description'		=> $parameters['description']
			];
			$wpdb->update ( $encounter_table , $encounter_data , ['appointment_id' => $appointment_id] );

			$wpdb->delete($appointment_service_table , ['appointment_id' => $appointment_id] );
			$message = 'Appointment has been updated successfully';

		} else {

			$temp['created_at'] = current_time('Y-m-d H:i:s');
			$wpdb->insert( $appointment_table, $temp );
			$appointment_id = $wpdb->insert_id;
			$message = 'Appointment has been saved successfully';
		}

		// email send
		if($parameters['status'] == 1) {
			$patient_data = get_userdata($parameters['patient_id']);
			$user_email_param = array(
				'user_email' => $patient_data->user_email,
				'appointment_date' => $appointment_date,
				'appointment_time' => date( 'H:i:s', strtotime( $parameters['appointment_start_time'] ) ),
				'email_template_type' => $this->getPluginPrefix() . 'book_appointment'
			);

			kcaSendEmail($user_email_param);
			
			$doctor_data = get_userdata($parameters['doctor_id']);
			if(isset($parameters["doctor_id"])) {
				$doctor_mail_param = array(
					'user_email' => $doctor_data->user_email,
					'appointment_date' => $appointment_date,
					'appointment_time' => date( 'H:i:s', strtotime( $parameters['appointment_start_time'] ) ),
					'patient_name'=> $patient_data->display_name,
					'email_template_type' => $this->getPluginPrefix() . 'doctor_book_appointment'
				);
				kcaSendEmail($doctor_mail_param);
			}

			if( $this->isKiviCareProOnName() )
			{
				$get_sms_config  = get_user_meta($userid,'sms_config_data',true);
				$get_sms_config = json_decode($get_sms_config);
				if( $get_sms_config->enableSMS == 1){
					$response = apply_filters('kcpro_send_sms', [
						'appointment_id' => $appointment_id,
						'current_user' => $userid,
					]);
				}
			}
		}

		foreach( $parameters['visit_type'] as $service ) {

			$service_data = $wpdb->get_row("SELECT * FROM {$service_table} WHERE id = {$service} ", OBJECT);
			
			if ( $service_data != null ){
				if ($service_data->name === 'telemed') {
	
					if ($this->teleMedAddOnName()) {
						$parameters['appointment_id'] = $appointment_id;
						$parameters['time_slot'] = $time_slot;
						
						$res_data = kctCreateAppointmentMeeting($parameters);
	
						if (!$res_data['status']) {
							$message = $res_data['message'];
						}
					}
				}
			}
			
			$service_data = [
				'appointment_id'=> $appointment_id,
				'service_id'	=> $service,
				'created_at' 	=> isset( $parameters['id'] ) ? current_time('Y-m-d H:i:s') : null,
				'status'		=> 1
			];
			$wpdb->insert( $appointment_service_table,$service_data );
		}

		if ( $parameters['status'] === '2' || $parameters['status'] === '4' ) {
			$this->createEncounter( $appointment_id , $userid );
		}
		if( $this->isKiviCareProOnName() )
		{
			$response = apply_filters('kcpro_save_appointment_event', [
				'appoinment_id'	=> $appointment_id,
			]);
		}

		if(getUserRole ( $data['user_id'] ) === 'patient') {
			if($this->teleMedAddOnName() && isWooCommerceActive()) {
				
				if( (float)KIVI_CARE_TELEMED_VERSION >= (float)'2.0.0' ) {
					if (get_option( KIVI_CARE_PREFIX . 'woocommerce_payment') === 'on') {
						if($appointment_id) {

							// woocommerce telemed cart response
							$res_data = apply_filters('kct_woocommerce_add_to_cart', [
								'appointment_id' => $appointment_id,
								'doctor_id' => $parameters['doctor_id'],
								'patient_id' => $parameters['patient_id']
							]);

							$res_data['message'] = $message;
							return comman_custom_response($res_data);
						}
					}
				}
			}elseif($this->isKiviCareProOnName() && isWooCommerceActive()) {
				
				if (get_option( KIVI_CARE_PREFIX . 'woocommerce_payment') === 'on') {
					if($appointment_id) {

						// woocommerce pro cart response
						$res_data = apply_filters('kcpro_woocommerce_add_to_cart', [
							'appointment_id' => $appointment_id,
							'doctor_id' => $parameters['doctor_id'],
							'patient_id' => $parameters['patient_id']
						]);

						$res_data['message'] = $message;
						return comman_custom_response($res_data);
					}
				}
			}
		}

		return comman_message_response( $message );
	}

	public function deleteAppointment( $request ) {

		global $wpdb;
 
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$appointment_table = $wpdb->prefix. 'kc_' . 'appointments';
		$appointment_service_table = $wpdb->prefix. 'kc_' . 'appointment_service_mapping';
		$encounter_table	= $wpdb->prefix. 'kc_' . 'patient_encounters';
		$parameters = $request->get_params();
		
		if ($this->teleMedAddOnName()) {
			apply_filters('kct_delete_appointment_meeting', $parameters);
		}
		
		$results = $wpdb->delete( $appointment_table , array ('id' => $parameters['id']) );

		if ( $results ) {
			$wpdb->delete( $appointment_service_table , array ('appointment_id' => $parameters['id']) );
			$wpdb->delete( $encounter_table , array ('appointment_id' => $parameters['id']) );
			if( $this->isKiviCareProOnName() ){
				apply_filters('kcpro_remove_appointment_event', ['appoinment_id' => $parameters['id']]);
			}
			$message = 'Appointment has been deleted successfully';
		} else {
			$message = 'Appointment delete failed';
		}

		return comman_message_response( $message );
		
	}

}