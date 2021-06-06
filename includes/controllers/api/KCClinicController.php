<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCClinicController extends KCBase {

	public $module = 'clinic';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-list', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getClinicList' ],
				'permission_callback' => '__return_true',
			));

		});
	}

	public function getClinicList( $request ) {

		global $wpdb;
 
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$parameters = $request->get_params();
        
        $limit  = (isset($parameters['limit']) && $parameters['limit'] != '' ) ? $parameters['limit'] : 10;
        $page   = (isset($parameters['page']) && $parameters['page'] != '' ) ? $parameters['page'] : 1;
        $offset = ( $page - 1 ) *  $limit;

        $clinic_table = $wpdb->prefix . 'kc_' . 'clinics';

        if ( $data['role'] == 'doctor' ) {
            $table = $wpdb->prefix . 'kc_' . 'doctor_clinic_mappings';
            $condition = " {$table}.doctor_id = {$data['user_id']} ";
        }

        if ( $data['role'] == 'receptionist' ) {
            $table = $wpdb->prefix . 'kc_' . 'receptionist_clinic_mappings';
            $condition = " {$table}.receptionist_id = {$data['user_id']} ";
        }
        if ( isset($table) && $table != null ){
            $query = " SELECT clinics.id AS clinic_id,
                            clinics.name AS clinic_name,
                            clinics.email AS clinic_email,
                            clinics.extra AS extra,
                            clinics.city AS city,
                            clinics.country AS country,
                            clinics.status AS status,
                            clinics.clinic_logo AS clinic_logo,
                            clinics.profile_image AS profile_image
                            FROM {$table}
                                LEFT JOIN {$clinic_table} clinics
                                    ON {$table}.clinic_id = clinics.id 
                                WHERE {$condition} ";
        } else {
            $query = " SELECT clinics.id AS clinic_id,
                    clinics.name AS clinic_name,
                    clinics.email AS clinic_email,
                    clinics.extra AS extra,
                    clinics.city AS city,
                    clinics.country AS country,
                    clinics.status AS status,
                    clinics.clinic_logo AS clinic_logo,
                    clinics.profile_image AS profile_image
                FROM {$clinic_table} As clinics";
        }

        $clinic_count = $wpdb->get_results($query, OBJECT);
        $query = $query. " ORDER BY clinics.id ASC LIMIT {$limit} OFFSET {$offset} ";
        $clinic_data = $wpdb->get_results($query, OBJECT);
        
        if ( !empty($clinic_data) && count($clinic_data) > 0 )
        {
            $clinic_data = collect($clinic_data)->map(function ($clinic){
                $clinic->extra = json_decode($clinic->extra);
                $clinic->profile_image = (wp_get_attachment_url($clinic->profile_image) !== false) ? wp_get_attachment_url($clinic->profile_image) : null;
                $clinic->clinic_logo = (wp_get_attachment_url($clinic->clinic_logo) !== false) ? wp_get_attachment_url($clinic->clinic_logo) : null;
                return $clinic;
            });
        }

        $response['total'] = count($clinic_count);
		$response['data']  = $clinic_data;
        
        return comman_custom_response($response);
	}
}