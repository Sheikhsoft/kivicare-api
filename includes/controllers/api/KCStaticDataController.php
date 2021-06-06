<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCStaticDataController extends KCBase {

    public $module = 'staticdata';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-list', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getStaticList' ],
				'permission_callback' => '__return_true',
			));
		});
    }

    public function getStaticList( $request) {

        global $wpdb;

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

		$parameters = $request->get_params();
        
        $static_data_table = $wpdb->prefix. 'kc_' . 'static_data';

        if(isset($parameters['type'])) {
            $static_data = $wpdb->get_results("SELECT * FROM $static_data_table WHERE type = '" .$parameters['type'] ."' AND status = 1 ORDER BY id DESC",OBJECT);
        }else{
            $static_data = $wpdb->get_results("SELECT * FROM $static_data_table WHERE status = 1 ORDER BY id DESC",OBJECT);         
        }

        return comman_list_response($static_data);
     
    }


}