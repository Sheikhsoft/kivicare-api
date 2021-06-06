<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCCustomFieldController extends KCBase {

    public $module = 'custom';

    public $nameSpace;
    
    function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/field-save', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'save_custom_field' ],
				'permission_callback' => '__return_true',
            ));
            
            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-custom-field', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'get_custom_field' ],
				'permission_callback' => '__return_true',
            ));
            
            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/save-custom-data', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'save_custom_data' ],
				'permission_callback' => '__return_true',
            ));
            
		});
    }
    
    
    public function save_custom_field( $request ) {

        global $wpdb;

        $parameters = $request->get_params();

        $custom_fields_table = $wpdb->prefix. 'kc_' . 'custom_fields';

        $validation = kcaValidateRequest([
			'module_type'   =>  'required',
			'type'          =>  'required',
            'label'         =>  'required',
        ], $parameters);
        
		if (count($validation)) {
			return comman_message_response($validation[0] , 400);
        }

        $custom_id = (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;

        $field = [
            'label'         =>  $parameters['label'],
            'type'          =>  $parameters['type'],
            'name'          =>  str_replace( ' ', '_', $parameters['label'] ),
            'options'       =>  $parameters['options'],
            'isRequired'    =>  $parameters['isRequired'],
            'status'        =>  $parameters['status'],
            'priority'      =>  $parameters['priority'],
            'placeholder'   =>  $parameters['placeholder'],
        ];

        $temp = [
			'module_type' => $parameters['module_type'],
            'fields'      => json_encode($field),
			'status'      => $parameters['status']
        ];
    

        if ($custom_id == null) {

            $temp['module_id']  = $parameters['module_id'];
            $temp['created_at'] = current_time( 'Y-m-d H:i:s' );
            $results = $wpdb->insert($custom_fields_table,$temp);

            if ($results) {
                $response = 'Custom field has been saved successfully';
            } else {
                $response = 'Custom field has been saved failed';
            }

        } else {

            $results =	$wpdb->update( $custom_fields_table, $temp, array( 'id' => $custom_id ) );  

            if ($results) {
                $response = 'Custom field has been updated successfully';
            } else {
                $response = 'Custom field has been update failed';
            }

        }

      return comman_message_response($response);
    }

    public function get_custom_field( $request ) {

        global $wpdb;

        $data = kcaValidationToken();

    	if (!$data['status']) {
			return comman_custom_response($data,401);
        }

        $role = $data['role'];

        $user_id = $data['user_id'];

        $parameters = $request->get_params();

        $module_type = (isset($parameters['module_type']) && $parameters['module_type'] != '' ) ? $parameters['module_type'] : null;

        $custom_field_table = $wpdb->prefix . 'kc_' . 'custom_fields';

        if($role == 'doctor') {
            if( $module_type == 'doctor_module' ) {
                $condition = " 0 = 0 ";
            } else {
                $condition = " module_id = {$user_id} ";
            }
            $query = "SELECT * FROM {$custom_field_table} WHERE module_type = '{$module_type}' AND {$condition} "; //AND ( status = 1

            $customField = $wpdb->get_results($query,OBJECT);

            $fields = collect($customField)->map(function ( $field) {
                $field->fields = json_decode( $field->fields , true );
                return $field;
            });

        }

        if ($role == 'receptionist') {

            $query = "SELECT * FROM {$custom_field_table} WHERE status = 1 ";

            $customField = $wpdb->get_results($query,OBJECT);

            $fields = collect($customField)->map(function ( $field) {
                $field->fields = json_decode( $field->fields , true );
                return $field;
            });
        }

       return comman_custom_response($fields);
    }

    public function save_custom_data ( $request ) {

        global $wpdb;
        
        $parameters = $request->get_params();
        
        $custom_fields_data_table = $wpdb->prefix. 'kc_' . 'custom_fields_data';
        
        $validation = kcaValidateRequest([
            'module_id' 	=> 'required',
            'module_type' 	=> 'required',
        ], $parameters);
        
        
        if (count($validation)) {
            return comman_message_response($validation[0] , 400);
        }
        
        $module_id      = $parameters['module_id'];
        $module_type    = $parameters['module_type'];
        $field_data =  $wpdb->get_row("SELECT * FROM {$custom_fields_data_table} WHERE module_id = {$module_id} AND module_type = '{$module_type}' " ,OBJECT );

        $parameters['fields_data'] = kcaRemoveBlankKeyFromArray($parameters['fields_data']);
        $temp = [
            "module_type"   => $module_type,
            "module_id"     => $module_id,
            "fields_data"   => json_encode( $parameters['fields_data'] )
        ];

        if ( $field_data == null ) {
            $temp['created_at'] = current_time( 'Y-m-d H:i:s' );
            $wpdb->insert( $custom_fields_data_table,$temp );
            $message = 'Field data has been saved succesfully';
        } else {
            $condition = array( 'module_id' => $module_id , 'module_type' => $module_type );

            $wpdb->update( $custom_fields_data_table,$temp , $condition );
            $message = 'Field data has been updated succesfully';
        }
        
        return comman_message_response($message);
    }
        
}