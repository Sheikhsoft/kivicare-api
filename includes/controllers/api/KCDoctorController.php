<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_User;
use WP_REST_Server;

class KCDoctorController extends KCBase {

    public $module = 'doctor';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-list', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getDoctorList' ],
				'permission_callback' => '__return_true',
			));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/appointment-time-slot', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getAppointmentSlots' ],
				'permission_callback' => '__return_true',
            ));
            
            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/doctor-details', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getDoctorDetails' ],
				'permission_callback' => '__return_true',
			));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/add-doctor', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'addDoctor' ],
				'permission_callback' => '__return_true',
			));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/save-zoom-configuration', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'saveZoomConfiguration' ],
				'permission_callback' => '__return_true',
			));

            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-zoom-configuration', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getZoomConfiguration' ],
				'permission_callback' => '__return_true',
			));
            
            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete-doctor', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'delete' ],
				'permission_callback' => '__return_true',
			));
            
		});
    }
    
    public function getDoctorList( $request ) {

        global $wpdb;
        
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
        $clinic_session_table = $wpdb->prefix. 'kc_' . 'clinic_sessions';
        $doctor_clinic_map_table = $wpdb->prefix. 'kc_' . 'doctor_clinic_mappings';
		$parameters = $request->get_params();

        $doctorsCount = get_users([
            'role' => $this->getDoctorRole(),
        ]);

        $doctorsCount = count($doctorsCount);

        $args['role'] = $this->getDoctorRole();
        $args['number']  = (isset($parameters['limit']) && $parameters['limit'] != '' ) ? $parameters['limit'] : 10;
        $args['paged']   = (isset($parameters['page']) && $parameters['page'] != '' ) ? $parameters['page'] : 1;
        $args['orderby'] = 'ID';
        $args['order'] = 'DESC';

        $doctors = get_users($args);

        if (!count($doctors)) {
            $doctors['message'] = 'No doctors found';
            return comman_message_response($doctors);
        }
        
        $doctor_data = [];
        $clinic_id = (isset($parameters['clinic_id']) && $parameters['clinic_id'] != '' ) ? $parameters['clinic_id'] : defaultClinic();

        $doctor_list = collect($doctors)->map( function ($doctor) use($wpdb,$doctor_clinic_map_table,$clinic_id, $data, $parameters) {
            
            if( $this->isKiviCareProOnName() && !array_key_exists('clinic_id', $parameters) )
            {
                if( $data['role']  == 'patient'){
                    $query = $wpdb->get_results(" SELECT * FROM {$doctor_clinic_map_table} WHERE doctor_id = {$doctor->ID} ");
                }else{
                    $query = $wpdb->get_row(" SELECT * FROM {$doctor_clinic_map_table} WHERE clinic_id = {$clinic_id} AND doctor_id = {$doctor->ID} ");
                }
                if( !empty($query) ){
                    return $doctor;
                }
            }else{
                $query = $wpdb->get_row(" SELECT * FROM {$doctor_clinic_map_table} WHERE clinic_id = {$clinic_id} AND doctor_id = {$doctor->ID} ");
    
                if ( isset($query))  {
                    return $doctor;
                }
            }
        });

        $doctors = $doctor_list->filter()->values();
        foreach ($doctors as $key => $doctor) {
            
            $user_meta = get_user_meta($doctor->ID, 'basic_data', true);
            
            $doctor_data[$key]['ID'] = $doctor->ID;
            $doctor_data[$key]['display_name'] = $doctor->data->display_name;
            $doctor_data[$key]['user_email'] = $doctor->data->user_email;
            $doctor_data[$key]['user_status'] = $doctor->data->user_status;
              
            if ($user_meta != null) {
                $basic_data = json_decode($user_meta);
                $doctor_data[$key]['mobile_number'] = $basic_data->mobile_number;
                $doctor_data[$key]['gender'] = $basic_data->gender;
                $doctor_data[$key]['specialties'] = collect($basic_data->specialties)->pluck('label')->implode(',');
                $doctor_data[$key]['no_of_experience'] = property_exists( $basic_data, 'no_of_experience' ) ? $basic_data->no_of_experience : 0;
                $doctor_data[$key]['profile_image'] = property_exists( $basic_data, 'profile_image') ? wp_get_attachment_url( $basic_data->profile_image ) : null;
            }
            $clinic_data = getUserClinicMapping(['user_id'=> $doctor->ID, 'role' => 'doctor' ]);
            if( $this->isKiviCareProOnName() )
            {
                if( $data['role']  == 'patient' ){
                    
				    $clinic_id = collect($clinic_data)->pluck('clinic_id')->implode(',');
                }
                $doctor_data[$key]['clinic_id'] = collect($clinic_data)->pluck('clinic_id');
                $doctor_data[$key]['clinic_name'] = collect($clinic_data)->pluck('clinic_name')->implode(',');
            }else{
                $doctor_data[$key]['clinic_id'] = collect($clinic_data)->where('clinic_id',$clinic_id)->pluck('clinic_id');
                $doctor_data[$key]['clinic_name'] = collect($clinic_data)->where('clinic_id',$clinic_id)->pluck('clinic_name')->implode(',');
            }
            $clinic_session = $wpdb->get_results("SELECT DISTINCT day FROM {$clinic_session_table} WHERE clinic_id IN ($clinic_id) AND doctor_id = {$doctor->ID}",OBJECT);
            $doctor_data[$key]['available'] = ( count($clinic_session) > 0) ? collect($clinic_session)->pluck('day')->implode(',') : null;
        }
        $response['total'] = count($doctors);
        $response['data'] = $doctor_data;
        return comman_custom_response($response);

    } 

    public function getAppointmentSlots( $request ) {

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

        $parameters = $request->get_params();

        if($data['role'] == 'doctor') {
            $parameters['doctor_id']    =   $data['user_id'];
        }
        
        $response = kcaGetTimeSlots([
            'doctor_id' => $parameters['doctor_id'],
            'clinic_id' => $parameters['clinic_id'],
            'date' 	=> $parameters['date'],
            'appointment_id' => $parameters['appointment_id']
        ],false);

        return comman_custom_response($response);

     }


    public function getDoctorDetails( $request ) {

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
        }

        $parameters = $request->get_params();

        $validation = kcaValidateRequest([
			'id' 	=> 'required'
		], $parameters);
		
		if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}

        $id = (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;

        $users = get_userdata( $id );

        $basic_data = get_user_meta($id, 'basic_data', true);

        $user_data = [
			"first_name" 	=> $users->first_name,
			"last_name" 	=> $users->last_name,
			"user_email" 	=> $users->user_email,
			"user_login" 	=> $users->user_login,
		];

        $response = (object) array_merge( $user_data, (array) json_decode( $basic_data ) );
        return comman_custom_response($response);
    }

    public function addDoctor($request)
    {
        global $wpdb;
		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

		$reqArr = $request->get_params();

		$users_table = $wpdb->prefix . 'users';
        $doctor_clinic_map_table = $wpdb->prefix. 'kc_' . 'doctor_clinic_mappings';
		$service_table	= $wpdb->prefix. 'kc_' . 'services';
		$service_mapping_table    = $wpdb->prefix. 'kc_' . 'service_doctor_mapping';
		$validation = kcaValidateRequest([
			'first_name' 	=> 'required',
			'last_name' 	=> 'required',
			'user_email' 	=> 'email|required',
			'mobile_number' => 'required',
			'dob'           => 'required',
			'gender'        => 'required',
		], $reqArr);
		
		if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}
        $username = kcaGenerateUsername($reqArr['first_name']);
        $password = kcaGenerateString(12);

        $reqArr['user_login'] = $username;
        $reqArr['user_pass'] = $password;
        $res = wp_insert_user($reqArr);

        if (isset($res->errors)) {
			return comman_message_response(kcaGetErrorMessage($res),400);
		}

        wp_update_user([
			'ID' => $res,
			'first_name' => $reqArr['first_name'],
			'last_name' => $reqArr['last_name'],
            'display_name' => $reqArr['first_name']." ".$reqArr['last_name']
		]);
		
		$users = get_userdata( $res );
		
		$users->set_role($this->getDoctorRole());
        
        $userid = $res;

		$temp = [
			'mobile_number' => $reqArr['mobile_number'],
			'gender'        => $reqArr['gender'],
			'dob'           => $reqArr['dob'],
			'address'       => $reqArr['address'],
			'city'          => $reqArr['city'],
			'country'       => $reqArr['country'],
			'postal_code'   => $reqArr['postal_code'],
		];

		$profile_data  = (array) json_decode ( get_user_meta( $userid, 'basic_data', true ) );
		if( isset($_FILES['profile_image']) && $_FILES['profile_image'] != null ){
			$temp['profile_image'] = media_handle_upload( 'profile_image', 0 );
		}else{
			if ( array_key_exists( 'profile_image', $profile_data ) && !empty($profile_data['profile_image'] ) ) {
				$temp['profile_image'] = $profile_data['profile_image'];
			}
		}		

        $temp['qualifications'] = json_decode ( $reqArr['qualifications'] , true );
        $temp['specialties'] =  json_decode ( $reqArr['specialties'] , true );
        $temp['no_of_experience'] = $reqArr['no_of_experience'];
        $temp['price'] = $reqArr['price'];
        $temp['price_type'] = $reqArr['price_type'];
        if (isset($reqArr['price_type']) && $reqArr['price_type'] == "range") {
            $temp['price'] = $reqArr['minPrice'] . '-' . $reqArr['maxPrice'];
        }
        $temp['video_price'] = isset($reqArr['video_price']) ? $reqArr['video_price'] : 0;

        $clinic_id = $reqArr['clinic_id'];
        $new_temp = [
            'doctor_id' => $userid,
            'clinic_id' => $clinic_id,
            'owner' => 0,
            'created_at' => current_time('Y-m-d H:i:s')
        ];
        $wpdb->insert($doctor_clinic_map_table ,$new_temp);

        if ($this->teleMedAddOnName()) {
            $data['type'] = 'telemed';

            $telemed_service = kcaGetService($data);
            
            if(!empty($telemed_service) && isset($telemed_service)) {

                $telemed_service_id = $telemed_service->id ;

            } else {

                $services = [
                    'type' => 'system_service',
                    'name' => 'telemed',
                    'price' => 0,
                    'status' => 1,
                    'created_at' => current_time('Y-m-d H:i:s')
                ];
            
                $telemed_service_id =  $wpdb->insert($service_table , $services);
            }

            $wpdb->insert($service_mapping_table, [
                'service_id' => $telemed_service_id,
                'clinic_id'  => $clinic_id,
                'doctor_id'  => $userid,
                'charges'    => $temp['video_price']
            ]);

            if($reqArr['enableTeleMed'] === true) {
                apply_filters('kct_save_zoom_configuration', [
                    'user_id' => $userid,
                    'enableTeleMed' => $reqArr['enableTeleMed'],
                    'api_key' => $reqArr['api_key'],
                    'api_secret' => $reqArr['api_secret']
                ]);
            }
        }

        $user_email_param = array(
            'username' => $username,
            'user_email' => $reqArr['user_email'],
            'password' => $password,
            'email_template_type' => 'doctor_registration'
        );

        kcaSendEmail($user_email_param);

        $message = 'Doctor has been saved successfully';
		
		update_user_meta( $userid , 'basic_data', json_encode( $temp ) );
		$basic_data  = get_user_meta( $userid, 'basic_data' , true );
		$users = get_userdata( $userid );
		
		$profile = (array) json_decode( $basic_data );
		
        $profile['custom_fields'] = kcaGetCustomFields( 'doctor_module', $userid );
		
		if ( array_key_exists( 'profile_image', $profile ) && !empty($profile['profile_image']) ) {
			$profile['profile_image'] = wp_get_attachment_url( $profile['profile_image'] );
		}else {
			$profile['profile_image'] = null;
		}

		$user_data = [
			'ID'			=> $users->ID,
			'first_name' 	=> $users->first_name,
			'last_name' 	=> $users->last_name,
			'user_email' 	=> $users->user_email,
			'user_login' 	=> $users->user_login,
			'display_name'  => $users->display_name,
		];
		
		if ($this->teleMedAddOnName()) {
			$config_data = kcaGetZoomConfig($userid);
			$user_data = array_merge($user_data,$config_data);
		}
		
		$response['data'] = (object) array_merge( $user_data, $profile );
		$response['message'] = $message;

		return comman_custom_response($response);
    }


    public function saveZoomConfiguration($request)
    {

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

        $userid = $data['user_id'];
        $reqArr = $request->get_params();

        if($this->teleMedAddOnName())
        {
            apply_filters('kct_save_zoom_configuration', [
                'user_id' => $userid,
                'enableTeleMed' => $reqArr['enableTeleMed'],
                'api_key' => $reqArr['api_key'],
                'api_secret' => $reqArr['api_secret']
            ]);

            // $video_price = $reqArr['video_price'];
            // updateDoctorService($userid, $video_price);
            $response['data'] = kcaGetZoomConfig($userid);
            // $response['data']['video_price'] = $video_price;
            $response['message'] = "Configuration saved successfully";
        } else {
            $response['data'] = (object) [];
            $response['message'] = "Telemed is not active.";
        }

        return comman_custom_response($response);

    }

    public function getZoomConfiguration()
    {

        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

        $userid = $data['user_id'];
        
        if($this->teleMedAddOnName())
        {
            $response['data'] = kcaGetZoomConfig($userid);
            $response['message'] = "Configuration saved successfully";
        } else {
            $response['data'] = (object) [];
            $response['message'] = "Telemed is not active.";
        }

        return comman_custom_response($response);
    }

    public function delete($request)
	{
		global $wpdb;

		$encounter_table	= $wpdb->prefix. 'kc_' . 'patient_encounters';
        $clinic_schedule_table = $wpdb->prefix. 'kc_' . 'clinic_schedule';
        $clinic_session_table = $wpdb->prefix. 'kc_' . 'clinic_sessions';
        $doctor_clinic_map_table = $wpdb->prefix. 'kc_' . 'doctor_clinic_mappings';
        $appointment_table 	= $wpdb->prefix. 'kc_' . 'appointments';
		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$parameters = $request->get_params();

		$doctor_id = $parameters['doctor_id'];

		$wpdb->delete( $clinic_schedule_table , [ 'module_id' => $doctor_id , 'module_type' => 'doctor'] );
		$wpdb->delete( $clinic_session_table , [ 'doctor_id' => $doctor_id ] );
		$wpdb->delete( $doctor_clinic_map_table , [ 'doctor_id' => $doctor_id ] );
		$wpdb->delete( $encounter_table , [ 'doctor_id' => $doctor_id ] );
		$wpdb->delete( $appointment_table , [ 'doctor_id' => $doctor_id ] );
		
        delete_user_meta($doctor_id, 'basic_data');
        delete_user_meta($doctor_id, 'first_name');
        delete_user_meta($doctor_id, 'last_name');
        if ($this->teleMedAddOnName()) {
            apply_filters('kct_delete_patient_meeting', ['doctor_id' => $doctor_id]);
            delete_user_meta($doctor_id, 'zoom_config_data');
        }
		
		require_once(ABSPATH.'wp-admin/includes/user.php');
		$results = wp_delete_user( $doctor_id );
		$status = 200;
		if ( $results ) {
			$message = 'Doctor has been deleted successfully';
		} else {
			$message = 'Data not found';
			$status = 400;
		}

		return comman_message_response($message, $status);
	}
}

   