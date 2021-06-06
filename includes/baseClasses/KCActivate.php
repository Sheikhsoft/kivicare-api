<?php

namespace Includes\baseClasses;


class KCActivate extends KCBase {

	public static function activate() {
		( new KCGetDependency( 'jwt-authentication-for-wp-rest-api' ) )->getPlugin();
		( new KCGetDependency( 'kivicare-clinic-management-system' ) )->getPlugin();
		
	}

	public function init() {

		if ( isset( $_REQUEST['page'] ) && strpos($_REQUEST['page'], "app-configuration") !== false ) {
			// Enqueue Admin-side assets...
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueueStyles' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueueScripts' ) );

        }

		// API handle
		( new KCApiHandler() )->init();

		// Action to change authentication api response ...
		add_filter( 'jwt_auth_token_before_dispatch', array($this, 'jwtAuthenticationResponse'), 10, 2 );


	}
	
	public function enqueueStyles() {
        wp_enqueue_style( 'kc_bootstrap_css', KIVICARE_API_DIR_URI . 'assets/css/bootstrap.min.css' );
        wp_enqueue_style('kc_admin_panel_css', KIVICARE_API_DIR_URI . 'admin/css/kivicare-api-admin.css');
    }

	public function enqueueScripts() {
        wp_enqueue_script( 'kc_bootstrap_js', KIVICARE_API_DIR_URI . 'assets/js/bootstrap.min.js', [ 'jquery' ], false, true );
	}

	public function jwtAuthenticationResponse( $data, $user ) {

		$user_info = get_userdata( $user->ID );
		$basic_data  = get_user_meta( $user->ID, "basic_data" , true );

		$module_config = kcaGetModules();

		// if( isset($user->user_status) && (int)$user->user_status === 4 ) {
		// 	$message = 'Login has been disabled. please contact you system administrator.';
			
		// 	return comman_message_response($message,401);
		// }
		$profile = (array) json_decode( $basic_data );

		if ( array_key_exists( 'profile_image', $profile ) && !empty($profile['profile_image']) ) {
			$profile['profile_image'] = wp_get_attachment_url( $profile['profile_image'] );
		}else {
			$profile['profile_image'] = null;
		}
		$data['first_name'] = $user_info->first_name;
		$data['last_name']  = $user_info->last_name;
		$data['user_id']    = $user->ID;
		$data['role']		= getUserRole( $user->ID );
		
		if ( $data['role'] == 'doctor' || $data['role'] == 'receptionist') {
			$clinic_data['clinic'] = getUserClinicMapping($data );
			$data = array_merge( $data, $clinic_data);
		}else{
			$clinic_data['clinic'] = kcaGetDefaultClinic();
			$data = array_merge( $data, $clinic_data);
		}
		$data['isTeleMedActive'] = $this->teleMedAddOnName();

		if($data['role'] == 'doctor')
		{
			
			if ($this->teleMedAddOnName()) {
				$config_data = kcaGetZoomConfig($user->ID);
				$data = array_merge($data,$config_data);
			}
			
		}

		$data['isKiviCareProOnName'] = $this->isKiviCareProOnName();

		if($data['isKiviCareProOnName']){
			$enableEncounter = json_decode(get_option(KIVI_CARE_PREFIX.'enocunter_modules'));
			$enablePrescription = json_decode(get_option(KIVI_CARE_PREFIX.'prescription_module'));
			
			$data['enocunter_modules'] = isset($enableEncounter->encounter_module_config) ? $enableEncounter->encounter_module_config : [];
			$data['prescription_module'] = isset($enablePrescription->prescription_module_config) ? $enablePrescription->prescription_module_config : [];

			$get_googlecal_config = get_option( KIVI_CARE_PREFIX . 'google_cal_setting',true);
			$data['is_enable_google_cal'] = $get_googlecal_config['enableCal'] == 1 ? 'on' : 'off';

			$get_patient_cal_config= get_option( KIVI_CARE_PREFIX . 'patient_cal_setting',true);
			$data['is_patient_enable'] = $get_patient_cal_config == 1 ? 'on' : 'off';
			$data['google_client_id'] = trim($get_googlecal_config['client_id']);
			
			if ( $data['role'] == 'doctor' || $data['role'] == 'receptionist') {
				$doctor_enable = get_user_meta($data['user_id'], KIVI_CARE_PREFIX.'google_cal_connect',true);
				
				$data['is_enable_doctor_gcal'] = ( $doctor_enable == 'off' || empty($doctor_enable) ) ? 'off' : 'on';
			}
		}
		$data = array_merge( $data, (array) $module_config );
		$data = (object) array_merge( $data, $profile ); //(array) json_decode( $basic_data ) );
		return $data;
	}

}


