<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCPrescriptionController extends KCBase {

    public $module = 'prescription';

    public $nameSpace;

    function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/save', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'savePrescription' ],
				'permission_callback' => '__return_true',
            ));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'deletePrescription' ],
				'permission_callback' => '__return_true',
            ));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/list', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getList' ],
				'permission_callback' => '__return_true',
			));

		});
    }

    public function getList( $request ) {

        global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }

        $userid = $data['user_id'];

        $parameters = $request->get_params();

        $limit  = (isset($parameters['limit']) && $parameters['limit'] != '' ) ? $parameters['limit'] : 10;
        $page = (isset($parameters['page']) && $parameters['page'] != '' ) ? $parameters['page'] : 1;
        $offset = ( $page - 1 ) *  $limit;

        // $validation = kcaValidateRequest([
		// 	'encounter_id' 	=> 'required'
        // ], $parameters);

		// if (count($validation)) {
		// 	return comman_message_response($validation[0] , 400);
		// }

        $prescription_table = $wpdb->prefix. 'kc_' . 'prescription';

        $query = "SELECT * FROM $prescription_table";
        
        if(isset($parameters['encounter_id']) && $parameters['encounter_id'] != null){
            $query = $query. " WHERE encounter_id = {$parameters['encounter_id']} ";
        }

        $prescription_count = $wpdb->get_results($query,OBJECT);
        $query = $query. " ORDER BY id ASC LIMIT {$limit} OFFSET {$offset} ";

        $prescription_list = $wpdb->get_results($query,OBJECT);

		// if(count($prescription_list) <= 0) {
		// 	$response = 'Record not found';
        //     return comman_message_response ( $response,400);	
		// } 
        $response['total'] = count($prescription_count);
		$response['data']  = $prescription_list;

        return comman_custom_response($response);

    }

    public function savePrescription( $request ) {

        global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }
        
        $userid = $data['user_id'];
        
        $parameters = $request->get_params();
        
        $validation = kcaValidateRequest([
			'name' 		        => 'required',
			'frequency' 		=> 'required',
			'duration' 	        => 'required',
		], $parameters);

		if (count($validation)) {
			return comman_message_response($validation[0] , 400);
        }
        
        $prescription_table = $wpdb->prefix. 'kc_' . 'prescription';
        
        $patient_encounters_table = $wpdb->prefix. 'kc_' . 'patient_encounters';

        $patient_encounters = "SELECT * FROM {$patient_encounters_table} WHERE id = ".$parameters['encounter_id']. ""; 
 
        $patient_encounter = $wpdb->get_row($patient_encounters,OBJECT);
        $patient_id        = $patient_encounter->patient_id;
        
        $temp = array(
            'encounter_id'      => $parameters['encounter_id'],
            'patient_id'        => $patient_id,
			'name' 		        => $parameters['name'],
			'frequency' 		=> $parameters['frequency'],
			'duration' 	        => $parameters['duration'],
            'instruction'	    => $parameters['instruction']
        );
     
        $id	= (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;

        if($id == null) {

            $temp['created_at'] = current_time( 'Y-m-d H:i:s' );
            $temp['added_by']   = $userid;

            $wpdb->insert( $prescription_table,$temp);
            $id = $wpdb->insert_id;
            $message = 'Prescription has been added successfully';
        } else {

            $wpdb->update($prescription_table,$temp,array( 'id' => $id ));

            $message = 'Prescription has been updated successfully';

        }
        $prescription_data = $wpdb->get_row("SELECT * FROM {$prescription_table} WHERE id = {$id}", OBJECT );
        return comman_custom_response($prescription_data);
    }

    public function deletePrescription( $request ) {

        global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }
        
        $parameters = $request->get_params();

        $prescription_table = $wpdb->prefix. 'kc_' . 'prescription';

        $results = $wpdb->delete( $prescription_table , array ('id' => $parameters['id']) );

		if ( $results ) {
			$message = 'Prescription has been deleted successfully';
		} else {
			$message = 'Prescription delete failed';
		}

		return comman_message_response( $message );

    }
    
}