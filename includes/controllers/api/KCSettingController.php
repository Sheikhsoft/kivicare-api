<?php

namespace Includes\Controllers\Api;

use Includes\baseClasses\KCBase;
use WP_Error;
use WP_REST_Response;
use WP_REST_Server;

class KCSettingController extends KCBase {

    public $module = 'setting';

	public $nameSpace;

	function __construct() {

		$this->nameSpace = KIVICARE_API_NAMESPACE;

		add_action( 'rest_api_init', function () {
            
            register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/clinic-schedule-list', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'clinicScheduleList' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/save-clinic-schedule', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'saveClinicSchedule' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete-clinic-schedule', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'deleteClinicSchedule' ],
				'permission_callback' => '__return_true',
			));

			
			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/get-doctor-clinic-session', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'getDoctorClinicSessionList' ],
				'permission_callback' => '__return_true',
			));
			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/save-doctor-clinic-session', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'saveDoctorClinicSession' ],
				'permission_callback' => '__return_true',
			));

			register_rest_route( $this->nameSpace . '/api/v1/' . $this->module, '/delete-doctor-clinic-session', array(
				'methods'             => WP_REST_Server::ALLMETHODS,
				'callback'            => [ $this, 'deleteDoctorClinicSession' ],
				'permission_callback' => '__return_true',
			));
			
		});
    }

    public function clinicScheduleList($request) {
        global $wpdb;
		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

		$clinic_schedule_table = $wpdb->prefix . 'kc_' . 'clinic_schedule';

        $parameters = $request->get_params();
        
		$role = $data['role'];
		$userid = $data['user_id'];
		
		if( $role == 'doctor' ) {
			$condition = " WHERE module_id = {$userid} ";
		}
		
		$limit  = (isset($parameters['limit']) && $parameters['limit'] != '' ) ? $parameters['limit'] : 10;
		$page = (isset($parameters['page']) && $parameters['page'] != '' ) ? $parameters['page'] : 1;
		
		$offset = ( $page - 1 ) *  $limit;
		
		$total_clinic_schedule_data = $wpdb->get_results(" SELECT count(*) AS count FROM {$clinic_schedule_table}" , OBJECT );
		if( $role == 'doctor' ) {
			$total_clinic_schedule_data = $wpdb->get_results( "SELECT count(*) AS count FROM {$clinic_schedule_table} {$condition} ", OBJECT );
		}
		$reponse['total'] = $total_clinic_schedule_data['0']->count;

		$clinic_schedule_query = "SELECT * FROM  {$clinic_schedule_table} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset} ";

		if ( $role == 'doctor' ) {
			$clinic_schedule_query = "SELECT * FROM  {$clinic_schedule_table} {$condition} ORDER BY id DESC LIMIT {$limit} OFFSET {$offset} ";

		}
		$reponse['data'] = collect($wpdb->get_results( $clinic_schedule_query));


		return comman_custom_response ( $reponse );
	}
	
	public function saveClinicSchedule($request) {
		global $wpdb;

		$data = kcaValidationToken();

		$parameters = $request->get_params();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}

		$validation = kcaValidateRequest([
			'start_date'	=> 'required',
			'end_date'		=> 'required',
			'module_type'	=> 'required'
		], $parameters);

		if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}

		$table = $wpdb->prefix. 'kc_' . 'clinic_schedule';

		$temp = array(
			'start_date'	=> date( 'Y-m-d', strtotime( $parameters['start_date'] )),
			'end_date'		=> date( 'Y-m-d', strtotime( $parameters['end_date'] )),
			'module_id' 	=> $parameters['module_id'],
			'module_type' 	=> $parameters['module_type'],
			'description'	=> $parameters['description']
		);

		$data = [
			'start_date' => $temp['start_date'],
			'end_date' => $temp['end_date']
		];

		if ($temp['module_type'] === 'doctor') {
			$data['doctor_id'] = $temp['module_id'];
		} else {
			$data['clinic_id'] = $temp['module_id'];
		}

		// Cancel appointment if exist...
		kcaCancelAppointments($data);
		$id = (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;

		if ( $id == null )  {

			$temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			$wpdb->insert( $table,$temp );

			$message = 'Clinic holiday schedule added successfully';

		}
		else {

			$condition = array( 'id' => $id );

			$wpdb->update( $table, $temp, $condition );

			$message = 'Clinic holiday schedule updated successfully';

		}
		return comman_message_response($message);
	}

	public function deleteClinicSchedule ( $request ) {
		global $wpdb;
		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$parameters = $request->get_params();
		$table = $wpdb->prefix. 'kc_' . 'clinic_schedule';

		$results = $wpdb->delete( $table , array ('id' => $parameters['id']) );
		$status_code = 200;
		if ( $results ) {
			$message = 'Clinic holiday deleted successfully';
		} else {
			$message = 'Clinic holiday delete failed';
			$status_code = 400;
		}

		return comman_message_response( $message , $status_code);

	}

	public function getDoctorClinicSessionList($request)
	{
		global $wpdb;
		$data = kcaValidationToken();
		
		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$table = $wpdb->prefix. 'kc_' . 'clinic_sessions';
		$parameters = $request->get_params();

		$clinic_session = [];

        $clinic_id = (isset($parameters['clinic_id']) && $parameters['clinic_id'] != '' ) ? $parameters['clinic_id'] : null;
        $page = (isset($parameters['page']) && $parameters['page'] != '' ) ? $parameters['page'] : 1;
		$limit  = (isset($parameters['limit']) && $parameters['limit'] != '' ) ? $parameters['limit'] : 10;
		$offset = ( $page - 1 ) *  $limit;

		$doctor_id = $data['user_id'];
		
		if ( isset($parameters['doctor_id']) && $parameters['doctor_id'] != null ) {
			$doctor_id = $parameters['doctor_id'];
		}
		if ( $data['role'] == 'doctor' ) {
			$condition = " AND doctor_id = {$doctor_id}";
			if( $this->isKiviCareProOnName() && $clinic_id == null )
			{
				$clinic_data = getUserClinicMapping($data);
				$clinic_id = collect($clinic_data)->pluck('clinic_id')->implode(',');
			}
		} else {
			$condition = " AND 0 = 0";
		}
		$query = "SELECT * FROM {$table} WHERE clinic_id IN ($clinic_id) {$condition}";
		
		// $total_clinic_session = $wpdb->get_results($query,OBJECT);
		// $query = $query . " ORDER BY id ASC LIMIT {$limit} OFFSET {$offset}";
		$clinic_sessions = $wpdb->get_results($query,OBJECT);

		$clinic_sessions = collect($clinic_sessions);

		$clinic_sessions = $clinic_sessions->map(function ($session) {
			$session->day = substr($session->day, 0, 3);
			return  $session ;
		});
		$sessions = [];
		if (count($clinic_sessions)) {
			foreach ($clinic_sessions as $session) {
				$doctor_data = kcaGetUserData($session->doctor_id);

				if( $doctor_data->user_status == 0 ) {
					if ($session->parent_id === null || $session->parent_id === "" ) {
						$days = [];
						$session_doctors = [];
						$sec_start_time = "";
						$sec_end_time = "";

						array_push($days, substr($session->day, 0, 3));

						$all_clinic_sessions  = collect($clinic_sessions);

						$child_session = $all_clinic_sessions->where('parent_id', $session->id);

						$child_session->all();

						if(count($child_session) > 0) {

							foreach ($clinic_sessions as $child_session) {

								if ($child_session->parent_id !== null && (int) $session->id === (int) $child_session->parent_id) {

									array_push($session_doctors, $child_session->doctor_id);
									array_push($days, substr($child_session->day, 0, 3));

									if ($session->start_time !== $child_session->start_time) {
										$sec_start_time = $child_session->start_time;
										$sec_end_time = $child_session->end_time;
									}
								}
							}

						} else {
							array_push($session_doctors, $session->doctor_id);
							array_push($days, substr($session->day, 0, 3));
						}

						$start_time = explode(":",date('H:i',strtotime($session->start_time)));

						$end_time = explode(":",date('H:i',strtotime($session->end_time)));

						$session_doctors = array_unique($session_doctors);

						if (count($session_doctors) === 0 && count($days) === 0) {
							$session_doctors[] = $session->doctor_id;
							$days[] = substr($session->day, 0, 3);
						} else {
							$sec_start_time = $sec_start_time !== "" ? explode(":",date('H:i',strtotime($sec_start_time))) : "";
							$sec_end_time = $sec_end_time !== "" ? explode(":",date('H:i',strtotime($sec_end_time))) : "";
						}

						$new_doctors = [];

						// foreach ($session_doctors as $doctor_id) {
						// 	foreach ($doctors as $doctor) {
						// 		if ((int) $doctor['id'] === (int) $doctor_id) {
						// 			$new_doctors = $doctor;
						// 		}
						// 	}
						// }
						$clinic_detail = kcaGetClinic($session->clinic_id);
						$new_session = [
							'id' => $session->id,
							'clinic_id' => $session->clinic_id,
							'clinic_name' => !empty($clinic_detail) ? $clinic_detail->name : null,
							'doctor_id' => $session->doctor_id,
							'days' => array_values(array_unique($days)),
							'doctors' => $doctor_data->display_name,//$new_doctors,
							'specialties' => collect($doctor_data->basicData->specialties)->pluck('label')->implode(','),
							'time_slot' => $session->time_slot,
							's_one_start_time' => [
								"HH" => $start_time[0],
								"mm" => $start_time[1],
							],
							's_one_end_time' => [
								"HH" => $end_time[0],
								"mm" => $end_time[1],
							],
							's_two_start_time' => [
								"HH" => isset($sec_start_time[0]) ? $sec_start_time[0] : "",
								"mm" => isset($sec_start_time[1]) ? $sec_start_time[1] : "",
							],
							's_two_end_time' => [
								"HH" => isset($sec_end_time[0]) ? $sec_end_time[0] : "",
								"mm" => isset($sec_end_time[1]) ? $sec_end_time[1] : "",
							]
						];

						array_push($sessions, $new_session);

					}
				}
			}
		}
		// $clinic_session_data = collect($clinic_session)->map( function ($session) use( $wpdb, $table, $clinic_id ) {
		// 	$days = [];
		// 	$session_day = $wpdb->get_results("SELECT * FROM {$table} WHERE clinic_id = {$clinic_id} AND parent_id = {$session->id}", OBJECT);
			
		// 	if ( !empty($session_day) && count($session_day) > 0  ) {
		// 		$session->day = collect($session_day)->pluck('day');
		// 	}
		// 	return $session;
		// });
		$reponse['total'] = count($sessions);
		$reponse['data'] = $sessions;
		// $reponse['data'] = $clinic_session_data;

		return comman_custom_response($reponse);
	}
	public function saveDoctorClinicSession($request)
	{
		global $wpdb;
		$data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$parameters = $request->get_params();
		$table = $wpdb->prefix. 'kc_' . 'clinic_sessions';

		$rules = [
			'clinic_id'	=> 'required',
			'doctor_id'	=> 'required',
			'day'		=> 'required'			
		];

		$message = [
			'clinic_id'  => 'Clinic is required',
			'doctor_id'  => 'Doctor is required',
			'day'  => 'Days is required',
		];
		
		$validation = kcaValidateRequest( $rules, $parameters ,$message );
        
        if (count($validation)) {
			return comman_message_response($validation[0] , 400);
		}
		$valid_session = kcaCheckDoctorSession($parameters);
		if(!$valid_session) {
			return comman_message_response( 'You have already in session' , 400);
		}
		$week_day = $parameters['day'];

		$parent_id = 0;
		$id = (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;
		
		$message = 'Session has been added successfully';
		if ( $id != null ) {
			$data = $wpdb->get_row(" SELECT * FROM {$table} WHERE id = {$id}" , OBJECT );

			if ( $data != null ) {
				$wpdb->delete( $table , array('parent_id' => $id) );
				$wpdb->delete( $table , array('id' => $id) );
			}
			$message = 'Session has been updated successfully';
		}
		foreach ( $week_day as $day ) {
			$start_time = date('H:i:s', strtotime($parameters['s_one_start_time']['HH'] . ':' . $parameters['s_one_start_time']['mm']));
			$end_time = date('H:i:s', strtotime($parameters['s_one_end_time']['HH'] . ':' . $parameters['s_one_end_time']['mm']));
			$session_temp = [
				'clinic_id'		=> $parameters['clinic_id'],
				'doctor_id' 	=> $parameters['doctor_id'],
				'day'			=> $day,
				'doctor_id'		=> $parameters['doctor_id'],
				'time_slot'		=> $parameters['time_slot'],
				'start_time'	=> $start_time,
				'end_time'		=> $end_time,
				'parent_id'		=> (int) $parent_id === 0 ? null : (int) $parent_id
			];


			$session_temp['created_at'] = current_time( 'Y-m-d H:i:s' );
			
			if ($parent_id == 0) {
				$wpdb->insert( $table, $session_temp );
				$parent_id = $wpdb->insert_id;
			} else {
				$wpdb->insert( $table, $session_temp );
			}

			if ($parameters['s_two_start_time']['HH'] != null && $parameters['s_two_end_time']['HH'] != null) {
				$session_temp['start_time'] = date('H:i:s', strtotime($parameters['s_two_start_time']['HH'] . ':' . $parameters['s_two_start_time']['mm']));
				$session_temp['end_time'] = date('H:i:s', strtotime($parameters['s_two_end_time']['HH'] . ':' . $parameters['s_two_end_time']['mm']));
				$session_temp['parent_id'] = $parent_id;

				$wpdb->insert( $table, $session_temp );
			}
		}

		return comman_message_response ( $message );
	}

	public function deleteDoctorClinicSession($request)
	{
		global $wpdb;
 
        $data = kcaValidationToken();

		if (!$data['status']) {
			return comman_custom_response($data,401);
		}
		
		$table = $wpdb->prefix. 'kc_' . 'clinic_sessions';
		$parameters = $request->get_params();

		$id = (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;

		$wpdb->delete( $table , array('parent_id' => $id) );
		$results = $wpdb->delete( $table , array ('id' => $id ) );

		if ( $results ) {
			$message = 'Doctor session has been deleted successfully';
		} else {
			$message = 'Doctor session delete failed';
		}

		return comman_message_response( $message );
	}
}