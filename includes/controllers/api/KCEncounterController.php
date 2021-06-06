<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCEncounterController extends KCBase {

    public $module = 'encounter';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-encounter-list', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getEncounterList' ],
			    'permission_callback' => '__return_true',
            ));
            
            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-encounter-detail', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getEncounterDetail' ],
			    'permission_callback' => '__return_true',
            ));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/save', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'saveEncounter' ],
			    'permission_callback' => '__return_true',
            ));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-medical-history', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getMedicalhistory' ],
			    'permission_callback' => '__return_true',
            ));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/close', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'closeEncounter' ],
				'permission_callback' => '__return_true',
			));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'deleteEncounter' ],
				'permission_callback' => '__return_true',
			));
            
		});
    }

    public function getEncounterList($request) {

        global $wpdb;
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }

        $parameters = $request->get_params();

        $patient_encounter_table = $wpdb->prefix . 'kc_' . 'patient_encounters';
		$clinics_table           = $wpdb->prefix . 'kc_' . 'clinics';
        $users_table             = $wpdb->prefix . 'users';
        $role = $data['role'];
        $condition = "";

        $limit  = (isset($parameters['limit']) && $parameters['limit'] != '' ) ? $parameters['limit'] : 10;
        $page = (isset($parameters['page']) && $parameters['page'] != '' ) ? $parameters['page'] : 1;
        $patient_id = (isset($parameters['patient_id']) && $parameters['patient_id'] != '' ) ? $parameters['patient_id'] : null;
        $offset = ( $page - 1 ) *  $limit;

        if ( $role == 'doctor' ) {
            $condition = "doctor_id = {$data['user_id']} ";
        }

        if ( $role == 'patient' ) {
            $condition = "patient_id = {$data['user_id']} ";
        }

        if ( $patient_id != null ) {
            $condition = " patient_id = {$patient_id} ";
        }
        if( $role == 'receptionist' ){
            if ( $patient_id != null ) {
                $condition = " WHERE ".$condition." ORDER BY id DESC LIMIT {$limit} OFFSET {$offset} ";
                $encounters_count = $wpdb->get_results( "SELECT count(*) AS count from {$patient_encounter_table} WHERE patient_id = {$patient_id}", OBJECT );
            } else {
                $condition = " ORDER BY id DESC LIMIT {$limit} OFFSET {$offset} ";
                $encounters_count = $wpdb->get_results( "SELECT count(*) AS count from {$patient_encounter_table} ", OBJECT );
            }
        }else {
            $encounters_count = $wpdb->get_results( "SELECT count(*) AS count from {$patient_encounter_table} WHERE {$condition} ", OBJECT );
            $condition =  " WHERE ".$condition." ORDER BY id DESC LIMIT {$limit} OFFSET {$offset} ";
        }

        $total_record = $encounters_count[0]->count;
        
        $encounter['total'] = $total_record;

        if ( $total_record < 0  ) {
            $encounter['data'] = [];
            return comman_custom_response ( $encounter );
        }
		$encounters = "SELECT {$patient_encounter_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       {$clinics_table}.name AS clinic_name
			FROM  {$patient_encounter_table}
		        LEFT JOIN {$users_table} doctors
		            ON {$patient_encounter_table}.doctor_id = doctors.id
		        JOIN {$users_table} patients
		            ON {$patient_encounter_table}.patient_id = patients.id
		        JOIN {$clinics_table}
		            ON {$patient_encounter_table}.clinic_id = {$clinics_table}.id             
                $condition ";
        
        $encounter['data'] = $wpdb->get_results( $encounters, OBJECT );
        
        // $encounter['data'] = collect($encounters)->forPage($page,$limit)->values();

        return comman_custom_response ( $encounter );

    }

    public function getEncounterDetail ( $request ) {
        global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }
        
        $parameters = $request->get_params();

        $validation = kcaValidateRequest([
            'id' 	=> 'required',
        ],$parameters);

        if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}
        
        $id = $parameters['id'];

        $patient_encounter_table = $wpdb->prefix . 'kc_' . 'patient_encounters';
		$clinics_table           = $wpdb->prefix . 'kc_' . 'clinics';
        $users_table             = $wpdb->prefix . 'users';
        $medical_history_table   = $wpdb->prefix. 'kc_' . 'medical_history';
        $bills_table             = $wpdb->prefix. 'kc_' . 'bills';
        $query = "
			SELECT {$patient_encounter_table}.*,
		       doctors.display_name  AS doctor_name,
		       patients.display_name AS patient_name,
		       patients.user_email AS patient_email,
		       {$clinics_table}.name AS clinic_name
			FROM  {$patient_encounter_table}
		       LEFT JOIN {$users_table} doctors
		              ON {$patient_encounter_table}.doctor_id = doctors.id
              LEFT JOIN {$users_table} patients
		              ON {$patient_encounter_table}.patient_id = patients.id
		       LEFT JOIN {$clinics_table}
		              ON {$patient_encounter_table}.clinic_id = {$clinics_table}.id
            WHERE {$patient_encounter_table}.id = {$id} ";

        $encounter = $wpdb->get_row( $query, OBJECT );
        
        if ( $encounter != null ) {
            $encounter->custom_fields = kcaGetCustomFields( 'patient_encounter_module', $id );
            $config = kcaGetModules();
            $encounter->is_billing = (bool) collect($config->module_config)->where('name','billing')->where('status', 1)->count();
            $bill_data = $wpdb->get_row(" SELECT * FROM {$bills_table} WHERE encounter_id = {$id} " , OBJECT );
            $encounter->bill_id = ( isset($bill_data) && $bill_data->id != null ) ? $bill_data->id : null;
            $encounter->payment_status = ( isset($bill_data) && $bill_data->id != null ) ? $bill_data->payment_status : null;
            
            $isKiviCareProOnName = $this->isKiviCareProOnName();

            if($isKiviCareProOnName){
                $enableEncounter = json_decode(get_option(KIVI_CARE_PREFIX.'enocunter_modules'));
                $enablePrescription = json_decode(get_option(KIVI_CARE_PREFIX.'prescription_module'));
			
                $encounter->enocunter_modules = isset($enableEncounter->encounter_module_config) ? $enableEncounter->encounter_module_config : [];
                $encounter->prescription_module = isset($enablePrescription->prescription_module_config) ? $enablePrescription->prescription_module_config : [];
            }
            
            if( $data['role'] == 'patient' ){
                $encounter->problem     = $wpdb->get_results(" SELECT * from {$medical_history_table} WHERE type = 'problem' AND encounter_id = {$id} ",OBJECT);
                $encounter->observation = $wpdb->get_results(" SELECT * from {$medical_history_table} WHERE type = 'observation' AND encounter_id = {$id} ",OBJECT);
                $encounter->note        = $wpdb->get_results(" SELECT * from {$medical_history_table} WHERE type = 'note' AND encounter_id = {$id} ",OBJECT);

                $authorization = $request->get_header('Authorization');
                $limit  = (isset($parameters['limit']) && $parameters['limit'] != '' ) ? $parameters['limit'] : 10;
                $page = (isset($parameters['page']) && $parameters['page'] != '' ) ? $parameters['page'] : 1;
                $prescription_list = wp_remote_get( get_home_url() . "/wp-json/kivicare/api/v1/prescription/list?encounter_id=".$id."&limit=".$limit."&page=".$page, array(
                    'headers' => array(
                        'Authorization' => $authorization,
                    )
                ));

			    $encounter->prescription = json_decode($prescription_list['body']);
            }
			return $encounter;
		} else {
			return (object) [];
		}
    }

    public function saveEncounter ( $request ) {
        global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }
        
        $rules = [
			'date'       => 'required',
			'clinic_id'  => 'required',
			'doctor_id'  => 'required',
			'patient_id' => 'required',
			'status'     => 'required',
		];

		$message = [
			'status'     => 'Status is required',
			'patient_id' => 'Patient is required',
			'clinic_id'  => 'Clinic is required',
			'doctor_id'  => 'Doctor is required',
        ];
        
        $parameters = $request->get_params();

        $validation = kcaValidateRequest( $rules, $parameters, $message );
        
        if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}

        $encounter_table    = $wpdb->prefix. 'kc_' . 'patient_encounters';
  
        $user_id = $data['user_id'];

        $temp = [
			'encounter_date' => date( 'Y-m-d', strtotime( $parameters['date'] ) ),
			'patient_id'     => $parameters['patient_id'],
			'clinic_id'      => $parameters['clinic_id'],
			'doctor_id'      => $parameters['doctor_id'],
			'description'    => $parameters['description'],
			'status'         => $parameters['status'],
        ];
        $encounter_id = (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;
        if ( $encounter_id == null) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$temp['added_by']   = $user_id;
			$encounter_id       = $wpdb->insert( $encounter_table, $temp );
			$message            = 'Patient encounter has been saved successfully';

		} else {
			$status       = $wpdb->update( $encounter_table , $temp, array( 'id' => $encounter_id ) );
			$message      = 'Patient encounter has been updated successfully';
        }
        
        return comman_message_response ( $message );

    }

    public function getMedicalhistory($request){
        global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }
        $parameters = $request->get_params();

		$rules = [
			"encounter_id"	=> 'required',
			"type"			=> 'required',
		];

        $validation = kcaValidateRequest( $rules, $parameters );
        
        if (count($validation)) {
			return comman_message_response($validation[0] , 400);
        }
        
        $medical_history_table   = $wpdb->prefix. 'kc_' . 'medical_history';

        $type = $parameters['type'];
        $encounter_id = $parameters['encounter_id'];
        $result = $wpdb->get_results(" SELECT * from {$medical_history_table} WHERE type = '{$type}' AND encounter_id = {$encounter_id} ",OBJECT);

        $response['total'] = count($result);
		$response['data']  = $result;
		return comman_custom_response($response);
    }

    public function closeEncounter( $request ) {
		global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }
        $parameters = $request->get_params();

        $encounter_id = (isset($parameters['encounter_id']) && $parameters['encounter_id'] != '' ) ? $parameters['encounter_id'] : null;
        
		$encounter_table = $wpdb->prefix. 'kc_' . 'patient_encounters';
		
		$encounter = $wpdb->get_row(" SELECT * FROM {$encounter_table} WHERE id = {$encounter_id}" , OBJECT );

		if ($encounter !== []) {
			$wpdb->update( $encounter_table , [ 'status' => '0' ], [ 'id' => $encounter->id ] );
			$message = "Patient Encounter has been closed.";
            $status_code = 200;
		}else{
            $message = "Failed to closed Patient Encounter";
            $status_code = 400;
        }
        return comman_message_response($message,$status_code);
	}

    public function deleteEncounter( $request ) {
		global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }
        $parameters = $request->get_params();

        $encounter_id = (isset($parameters['encounter_id']) && $parameters['encounter_id'] != '' ) ? $parameters['encounter_id'] : null;
        
		$encounter_table            = $wpdb->prefix. 'kc_' . 'patient_encounters';
        $appointment_table          = $wpdb->prefix. 'kc_' . 'appointments';
        $appointment_service_table	= $wpdb->prefix. 'kc_' . 'appointment_service_mapping';
        $bills_table                = $wpdb->prefix. 'kc_' . 'bills';
        $bill_items_table           = $wpdb->prefix. 'kc_' . 'bill_items';
		$medical_history_table      = $wpdb->prefix. 'kc_' . 'medical_history';
		$encounter = $wpdb->get_row(" SELECT * FROM {$encounter_table} WHERE id = {$encounter_id}" , OBJECT );

		if ($encounter !== []) {
            $results = $wpdb->delete( $encounter_table , [ 'id' => $encounter_id ] );
            if($results){

                if($encounter->appointment_id != null) {
                    $wpdb->delete( $appointment_table , [ 'id' => $encounter->appointment_id ] );
                    $wpdb->delete( $appointment_service_table , [ 'appointment_id' => $encounter->appointment_id ] );
                    $wpdb->delete( $medical_history_table , [ 'encounter_id' => $encounter_id ] );
                    $bill_data = $wpdb->get_row(" SELECT * FROM {$bills_table} WHERE encounter_id = {$encounter_id}" , OBJECT );
                    if( $bill_data != null ) {
                        $wpdb->delete( $bill_items_table , [ 'bill_id' => $bill_data->id ] );
                        $wpdb->delete( $bills_table , [ 'encounter_id' => $encounter_id ] );
                    }
                }
                $message = "Patient Encounter has been deleted.";
                $status_code = 200;
            } else {
                $status_code = 400;
                $message = "Failed to delete Patient Encounter.";
            }
		}else{
            $message = "Patient Encounter not found.";
            $status_code = 400;
        }
        return comman_message_response($message,$status_code);
	}
}