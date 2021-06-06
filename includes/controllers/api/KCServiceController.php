<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCServiceController extends KCBase {

	public $module = 'service';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-list', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getList' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/add-service', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'addService' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete-service', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'deleteService' ],
				'permission_callback' => '__return_true',
			));
		});
	}

	public function addService( $request ) {

		global $wpdb;

		$data = kcaValidationToken();

		$parameters = $request->get_params();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

		$validation = kcaValidateRequest([
			'type' 		=> 'required',
			'name' 		=> 'required',
			// 'price' 	=> 'required',
			'status' 	=> 'required'
		], $parameters);

		if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}

		$table    = $wpdb->prefix. 'kc_' . 'services';
		$mapping_table    = $wpdb->prefix. 'kc_' . 'service_doctor_mapping';

		$temp = array(
			'type' 		=> $parameters['type'],
			'name' 		=> $parameters['name'],
			'status'	=> $parameters['status']
		);

		$service_id = (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;

		if ( $service_id == null )  {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$result = $wpdb->insert( $table,$temp );

			$id = $wpdb->insert_id;
			$message = 'Service has been added successfully';

		} else {

			$condition = array( 'id' => $service_id );

			$id = $service_id;
			$wpdb->update( $table, $temp, $condition );

			$message = 'Service has been updated successfully';

		}

		if ( $service_id == null ) {
			foreach ($parameters['doctor_id'] as $key => $val) {
				$temp_data = [
					'service_id' => $id,
					'clinic_id'  => $parameters['clinic_id'],
					'doctor_id'  => $val,
					'charges'	 => $parameters['charges'],
					'status'	 => $parameters['status']
				];

				$wpdb->insert( $mapping_table , $temp_data );
			}
		} else {
			foreach ($parameters['doctor_id'] as $key => $val) {
				$temp_data = [
					'service_id' => $id,
					'clinic_id'  => $parameters['clinic_id'],
					'doctor_id'  => $val,
					'charges'	 => $parameters['charges'],
					'status'	 => $parameters['status']
				];
				$wpdb->update( $mapping_table , $temp_data, array ('service_id' => $id , 'doctor_id' => $val ) );
			}
		}

		return comman_message_response($message);
		
	}

	public function getList( $request ) {

		global $wpdb;

		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

		$parameters = $request->get_params();

		$table	= $wpdb->prefix. 'kc_' . 'services';
		$mapping_table    = $wpdb->prefix. 'kc_' . 'service_doctor_mapping';

		$search	= (isset($parameters['name']) && $parameters['name'] != '' ) ? $parameters['name'] : null;
		$limit  = (isset($parameters['limit']) && $parameters['limit'] != '' ) ? $parameters['limit'] : 10;
        $page = (isset($parameters['page']) && $parameters['page'] != '' ) ? $parameters['page'] : 1;
		$offset = ( $page - 1 ) *  $limit;
		
		$service_ids = (isset($parameters['service_ids']) && $parameters['service_ids'] != '' ) ? $parameters['service_ids'] : null;
		$doctor_id = (isset($parameters['doctor_id']) && $parameters['doctor_id'] != '' ) ? $parameters['doctor_id'] : null;
		if ( $data['role'] == 'doctor' ) {
			$condition = " {$mapping_table}.doctor_id = {$data['user_id']} ";
			$doctor_id = $data['user_id'];
		} elseif ($doctor_id != null) {
			$condition = " {$mapping_table}.doctor_id = {$doctor_id} ";
		} else {
			$condition = " 0 = 0";
		}
		if( $doctor_id != null ) {
			
			if ($this->teleMedAddOnName()) {
				$zoom_config_data = get_user_meta($doctor_id, 'zoom_config_data', true);
				$zoom_config_data = json_decode($zoom_config_data);
				if(isset($zoom_config_data) && $zoom_config_data->enableTeleMed == 1){
					$condition = $condition;
				}else{
					$condition = $condition." AND {$table}.type != 'system_service' ";
				}
			}else{
				$condition = $condition." AND {$table}.type != 'system_service' ";
			}
			
		}else {
			$condition = $condition." AND {$table}.type != 'system_service' ";
		}

		if($search == null) {
			$query = "SELECT {$table}.id,{$table}.`type`,{$table}.`name`, {$mapping_table}.`status`,
						{$mapping_table}.`service_id`,{$mapping_table}.`doctor_id`,
						{$mapping_table}.`charges` , {$mapping_table}.id as `mapping_table_id`
				FROM {$mapping_table} 
				LEFT JOIN {$table} 
					ON {$mapping_table}.`service_id` = {$table}.id 
				WHERE {$condition} ";		
		} else {
			$query = "SELECT {$table}.id,{$table}.`type`,{$table}.`name`,{$table}.`status`,
						{$mapping_table}.`service_id`,{$mapping_table}.`doctor_id`,
						{$mapping_table}.`charges` , {$mapping_table}.id as `mapping_table_id`
				FROM {$mapping_table} 
				LEFT JOIN {$table} 
					ON {$mapping_table}.`service_id` = {$table}.id 
				WHERE {$condition} AND {$table}.name LIKE '{$search}%' " ;	
		}

		if( $service_ids != null && !empty($service_ids) ){
			$service_ids = implode( ',' , json_decode($service_ids) );
			$query = $query . " AND {$table}.id IN ($service_ids) ";
		}
		$total_record = $wpdb->get_results( $query , OBJECT );
		$query = $query ." ORDER BY mapping_table_id DESC";// LIMIT {$limit} OFFSET {$offset} ";
		$service_list = $wpdb->get_results($query,OBJECT);
	
		// if(count($service_list) <= 0) {
		// 	$response = 'Record not found';
        //     return comman_message_response ( $response,400);	
		// } 

		$response['total'] = count($total_record);
		$response['data']  = $service_list;
		return comman_custom_response($response);

	}

	public function deleteService( $request ) {

        global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }
        
        $parameters = $request->get_params();

		$table = $wpdb->prefix. 'kc_' . 'services';
		$mapping_table = $wpdb->prefix. 'kc_' . 'service_doctor_mapping';

		$results = $wpdb->delete( $mapping_table , array ('service_id' => $parameters['id'], 'doctor_id' => $parameters['doctor_id']) );
        // $results = $wpdb->delete( $table , array ('id' => $parameters['id']) );

		if ( $results ) {
			$message = 'Service has been deleted successfully';
		} else {
			$message = 'Service delete failed';
		}

		return comman_message_response( $message );

    }

}