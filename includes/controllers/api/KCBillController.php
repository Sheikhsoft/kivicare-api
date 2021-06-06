<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCBillController extends KCBase {

	public $module = 'bill';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/bill-details', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'billDetails' ],
				'permission_callback' => '__return_true',
			));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/add-bill', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'addBill' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete-bill-item', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'deleteBillItem' ],
				'permission_callback' => '__return_true',
            ));
            
		});
    }

    public function billDetails ( $request ) {
		global $wpdb;
 
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$bills_table 		= $wpdb->prefix. 'kc_' . 'bills';
		$bill_items_table 	= $wpdb->prefix. 'kc_' . 'bill_items';
		$clinic_table		= $wpdb->prefix. 'kc_' . 'clinics';
        $encounter_table	= $wpdb->prefix. 'kc_' . 'patient_encounters';
		

		$parameters = $request->get_params();

		$id           = isset( $parameters['id'] ) ? $parameters['id'] : 0;
		$encounter_id = isset( $parameters['encounter_id'] ) ? $parameters['encounter_id'] : 0;

		if ( $encounter_id !== 0 ) {
			$results = $wpdb->get_row(" SELECT * FROM {$bills_table} WHERE encounter_id = {$encounter_id} " , OBJECT );
		} else {
			$results = $wpdb->get_row(" SELECT * FROM {$bills_table} WHERE id = {$id} " , OBJECT );
		}

		$patient_encounter 	= $wpdb->get_row(" SELECT * FROM {$encounter_table} WHERE id = {$results->encounter_id} " , OBJECT );
		$clinic 			= $wpdb->get_row(" SELECT * FROM {$clinic_table} WHERE id = {$patient_encounter->clinic_id} " , OBJECT );
		$clinic->extra = json_decode($clinic->extra);
		$patient_data = kcaGetUserData($patient_encounter->patient_id);

		if ( $results) {
			$temp = [
				'id'               => $results->id,
				'title'            => $results->title,
				'encounter_id'     => $results->encounter_id,
				'total_amount'     => $results->total_amount,
				'discount'         => $results->discount,
				'actual_amount'    => $results->actual_amount,
				'status'           => $results->status,
				'payment_status'   => $results->payment_status,				
				'created_at'       => $results->created_at,
				'billItems'        => [],
				'patientEncounter' => $patient_encounter,
				'clinic'           => $clinic,
				'patient'          => [
					'id' => $patient_data->ID,
					'display_name' => $patient_data->display_name,
					'email' => $patient_data->user_email,
					'gender' => isset($patient_data->basicData->gender) ? $patient_data->basicData->gender : null,
					'dob' => isset($patient_data->basicData->dob) ? $patient_data->basicData->dob : null
				]
			];

			$billItems = $wpdb->get_results(" SELECT * FROM {$bill_items_table} WHERE bill_id = {$results->id} " , OBJECT );

			if ( count( $billItems ) ) {
				$temp['billItems'] = collect($billItems)->map( function ($billItems) {
					global $wpdb;
					$service_table		= $wpdb->prefix. 'kc_' . 'services';
					$service = $wpdb->get_row( " SELECT * FROM {$service_table} WHERE id = {$billItems->item_id} " , OBJECT );

					return [
						'id'      => $billItems->id,
						'bill_id' => $billItems->bill_id,
						'price'   => $billItems->price,
						'qty'     => $billItems->qty,
						'item_id' => $billItems->item_id,
						'label' => $service->name,
						'price' => $service->price,
					];
				});
			}
		}

		return comman_custom_response ( $temp );
	}

	public function addBill ( $request ) {
		global $wpdb;
		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$parameters = $request->get_params();

		$rules = [
			// 'title'         => 'required',
			'encounter_id'  => 'required',
			'total_amount'  => 'required',
			'discount'      => 'required',
			'actual_amount' => 'required',
			'billItems'     => 'required'
		];

		$validation = kcaValidateRequest( $rules, $parameters );

		if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}

		$message = kcaGenerateBill($parameters);
/*
		$user_id = $data['user_id'];
		$bill_table 				= $wpdb->prefix. 'kc_' . 'bills';
		$bill_items_table 		 	= $wpdb->prefix. 'kc_' . 'bill_items';
		$service_table    		 	= $wpdb->prefix. 'kc_' . 'services';
		$patient_encounter_table 	= $wpdb->prefix. 'kc_' . 'patient_encounters';
		$appointment_service_table	= $wpdb->prefix. 'kc_' . 'appointment_service_mapping';
		$service_doctor_mapping_table = $wpdb->prefix. 'kc_' . 'service_doctor_mapping';
		$patient_encounter 			= $wpdb->get_row( "SELECT * FROM {$patient_encounter_table} WHERE id = {$parameters["encounter_id"]}", OBJECT );
		
		if ( empty( $patient_encounter ) ) {
			return comman_message_response( 'No encounter found' , 400 );
		}

		$temp = [
			// 'title'         => $parameters['title'],
			'encounter_id'  => $parameters['encounter_id'],
			'total_amount'  => $parameters['total_amount'],
			'discount'      => $parameters['discount'],
			'actual_amount' => $parameters['actual_amount'],
			'status'        => ( isset($parameters['status']) && $parameters['status'] != '' ) ? $parameters['status'] : 0
		];

		$id = (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;

		if ( $id == null) {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$status       		= $wpdb->insert( $bill_table, $temp );
			$bill_id            = $wpdb->insert_id;
			$message			= 'Bill has been generated successfully'.$bill_id;

		} else {
			$status	 = $wpdb->update( $bill_table , $temp, array( 'id' => $id ) );
			$bill_id = $id;
			$message = 'Bill has been updated successfully';
		}
		
		if ( isset( $parameters['billItems'] ) && count( $parameters['billItems'] ) ) {

		    // insert bill items
            foreach ( $parameters['billItems'] as $key => $bill_item ) {

                if ((int) $bill_item['item_id'] === 0 ) {

					$service = $wpdb->get_row( "SELECT * FROM {$service_table} WHERE type = 'bill_service' AND name = '{$bill_item['item_id']}' ", OBJECT );
                    
                    // here if service not exist then add into service table
					if ( $service ) {
                        $bill_item['item_id'] = $service->id;
					} else {
						$new_service['type']        = 'bill_service';
						$new_service['name']        = strtolower( $bill_item['item_id'] );
						$new_service['price']       = $bill_item['price'];
						$new_service['status']      = 1;
						$new_service['created_at']	= current_time('Y-m-d H:i:s');
						$service_id                 = $wpdb->insert( $service_table, $new_service );
						$bill_item['item_id'] = $wpdb->insert_id;
					}
				}

				$_temp = [
					'bill_id' => $bill_id,
					'price'   => $bill_item['price'],
					'qty'     => $bill_item['qty'],
					'item_id' => $bill_item['item_id'],
				];

				if ( ! isset( $bill_item['id'] ) ) {
					$_temp['created_at'] = current_time( 'Y-m-d H:i:s' );
					$wpdb->insert( $bill_items_table , $_temp );
				} else {
					$wpdb->update( $bill_items_table , $_temp, array( 'id' => $bill_item['id'] ) );
				}
			}
		}
*/
		return comman_message_response( $message );
		
	}

	public function deleteBillItem ( $request ) {
		global $wpdb;
		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$parameters = $request->get_params();
		$bill_items_table = $wpdb->prefix. 'kc_' . 'bill_items';

		$results = $wpdb->delete( $bill_items_table , array ('id' => $parameters['bill_item_id']) );
		$status_code = 200;
		if ( $results ) {
			$message = 'Bill item has been deleted successfully';
		} else {
			$message = 'Bill item delete failed';
			$status_code = 400;
		}

		return comman_message_response( $message , $status_code);

	}
}