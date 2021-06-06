<?php
use Includes\baseClasses\KCBase;

function kcaValidationToken () {
	$data = [
		'message' => 'Valid token',
        'status' => true,
	];
	$response = collect((new Jwt_Auth_Public('jwt-auth', '1.1.0'))->validate_token(false));
   
	if ($response->has('errors')) {
		$data['status'] = false;
		$data['message'] = isset(array_values($response['errors'])[0][0]) ? array_values($response['errors'])[0][0] : __("Authorization failed");
	}else {
		$data['user_id'] = $response['data']->user->id;
		$data['role'] = getUserRole ( $data['user_id'] );
    }
	return $data;
}

function getUserRole( $user_id ) {
	$userObj = get_userdata($user_id);

	if ( in_array( KIVI_CARE_PREFIX . "doctor", $userObj->roles )) {
		return 'doctor';
	} elseif (in_array(KIVI_CARE_PREFIX . "clinic_admin", $userObj->roles)) {
        return 'clinic_admin';
    } elseif (in_array(KIVI_CARE_PREFIX . "patient", $userObj->roles)) {
        return 'patient';
    } elseif (in_array(KIVI_CARE_PREFIX . "receptionist", $userObj->roles)) {
		return 'receptionist';
	} elseif (in_array("administrator", $userObj->roles)) {
        return 'administrator';
    } else {
        return null;
    }
}
function kcaValidateRequest($rules, $request, $message = [])
{
	$error_messages = [];
	$required_message = ' field is required';
	$email_message =  ' has invalid email address';

	if (count($rules)) {
		foreach ($rules as $key => $rule) {
			if (strpos($rule, '|') !== false) {
				$ruleArray = explode('|', $rule);
				foreach ($ruleArray as $r) {
					if ($r === 'required') {
						if (!isset($request[$key]) || $request[$key] === "" || $request[$key] === null) {
							$error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $required_message;
						}
					} elseif ($r === 'email') {
						if (isset($request[$key])) {
							if (!filter_var($request[$key], FILTER_VALIDATE_EMAIL) || !is_email($request[$key])) {
								$error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $email_message;
							}
						}
					}
				}
			} else {
				if ($rule === 'required') {
					if (!isset($request[$key]) || $request[$key] === "" || $request[$key] === null) {
						$error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $required_message;
					}
				} elseif ($rule === 'email') {
					if (isset($request[$key])) {
						if (!filter_var($request[$key], FILTER_VALIDATE_EMAIL) || !is_email($request[$key]) ) {
							$error_messages[] = isset($message[$key]) ? $message[$key] : str_replace('_', ' ', $key) . $email_message;
						}
					}
				}
			}

		}
	}

	return $error_messages;
}


function kcaRecursiveSanitizeTextField($array)
{
	$filterParameters = [];
	foreach ($array as $key => $value) {

		if ($value === '') {
			$filterParameters[$key] = null;
		} else {
			if (is_array($value)) {
				$filterParameters[$key] = kcaRecursiveSanitizeTextField($value);
			} else {
				if (preg_match("/<[^<]+>/", $value, $m) !== 0) {
					$filterParameters[$key] = $value;
				} else {
					$filterParameters[$key] = sanitize_text_field($value);
				}
			}
		}

	}

	return $filterParameters;
}

function kcaGetErrorMessage ($response) {
	return isset(array_values($response->errors)[0][0]) ? array_values($response->errors)[0][0] : __("Internal server error");
}

function kcaGenerateString($length_of_string = 10)
{
	// String of all alphanumeric character
	$str_result = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
	return substr(str_shuffle($str_result),0, $length_of_string);
}

function kcaGenerateUsername($first_name)
{

    if (!$first_name || $first_name === "") {
        return "";
    }
    $randomString = kcaGenerateString(6);
    $first_name = str_replace(' ', '_', $first_name);
    return $first_name . '_' . $randomString;
}

function comman_message_response( $message, $status_code = 200)
{
    $response = new WP_REST_Response(array(
            "message" => $message
        )
    );
    $response->set_status($status_code);
    return $response;
}

function comman_custom_response( $res, $status_code = 200 )
{
    $response = new WP_REST_Response($res);
    $response->set_status($status_code);
    return $response;
}

function comman_list_response( $data )
{
    $response = new WP_REST_Response(array(
        "data" => $data
    ));

    $response->set_status(200);
    return $response;
}

function kcaGetCustomFields($module_type, $module_id)
{
	global $wpdb;

	$data = [];
	$custom_fielddata_table = $wpdb->prefix . 'kc_' . 'custom_fields_data';
	$custom_field_table = $wpdb->prefix . 'kc_' . 'custom_fields';
	
	$customData = $wpdb->get_results(" SELECT * FROM {$custom_fielddata_table} WHERE module_type = '{$module_type}' AND module_id = '{$module_id}' ");
    $custom_field_data = [];
    if ($customData !== []) {
        $custom_field_data = json_decode($customData[0]->fields_data);
    }

    if ( $module_type == 'doctor_module' ) {
        $customField = $wpdb->get_results(" SELECT * FROM {$custom_field_table} WHERE module_type = '{$module_type}' "); // AND module_id = '{$module_id}'
    } else {
        $customField = $wpdb->get_results(" SELECT * FROM {$custom_field_table} WHERE module_type = '{$module_type}' ");
    }
    if ($customField !== []) {
        $data = collect($customField)->map(function ( $field ) use( $custom_field_data ) {
            $field->fields = json_decode( $field->fields , true );
            $field_name = $field->fields['name'];
            $field->fields['value'] = isset($custom_field_data->$field_name) ? $custom_field_data->$field_name : '';
            return $field;
        });
    }

    return $data;
}

function kcaGetUserData($user_id) {
	$userObj = new WP_User($user_id);
	$user = $userObj->data;
	$user_data = get_user_meta($userObj->ID, 'basic_data', true);
	if ($user_data) {
		$user_data = json_decode($user_data);
		$user->basicData = $user_data;
	}

	unset($user->user_pass);
	return $user;
}

function kcaGetModules()
{
    $prefix = KIVI_CARE_PREFIX;
    $modules = get_option($prefix . 'modules');
    if ($modules) {
        return json_decode($modules);
    } else {
        return '';
    }
}

function kcaAppointmentList( $parameters , $user_data ) {
	global $wpdb;
    $appointments_table = $wpdb->prefix. 'kc_' . 'appointments';
    $clinics_table = $wpdb->prefix . 'kc_' . 'clinics';
	$static_data_table = $wpdb->prefix. 'kc_' . 'static_data';
	$user_table 	   = $wpdb->prefix. 'users';
    $patient_encounter_table = $wpdb->prefix . 'kc_' . 'patient_encounters';
	$role 	= $user_data['role'];
	$userid = $user_data['user_id'];
	$limit  = (isset($parameters['limit']) && $parameters['limit'] != '' ) ? $parameters['limit'] : 10;
	$page = (isset($parameters['page']) && $parameters['page'] != '' ) ? $parameters['page'] : 1;
	$offset = ( $page - 1 ) *  $limit;

    $kiviCareProAddon = $parameters['isKiviCareProOnName'];
    // $start_date =(isset($parameters['date']) && $parameters['date'] != '' ) ? $parameters['date'] : date( 'Y-m-d' );
    
    $data_filter = '';
    if (isset( $parameters['start']) && isset( $parameters['end'])) {
        $start_date = date('Y-m-d', strtotime($parameters['start']));
        $end_date = date('Y-m-d',strtotime($parameters['end']));
        $data_filter  = " AND {$appointments_table}.appointment_start_date BETWEEN '{$start_date}' AND '{$end_date}' "  ;
    } elseif (isset($parameters['date']) && $parameters['date'] != null && $parameters['status'] !== 'all') {
        $date = $parameters['date'];
        $data_filter  = " AND {$appointments_table}.appointment_start_date = '{$date}' " ;
    }
    else {
        $data_filter = '';
    }

	$query = "SELECT DISTINCT {$appointments_table}.id,{$appointments_table}.*,
				doctors.display_name  AS doctor_name,
				patients.display_name AS patient_name,
				static_data.label AS visit_label,
                encounter.id AS encounter_id,
                {$clinics_table}.name AS clinic_name
				FROM  {$appointments_table}
			LEFT JOIN {$user_table} doctors
				ON {$appointments_table}.doctor_id = doctors.ID 
			LEFT JOIN {$user_table} patients
				ON {$appointments_table}.patient_id = patients.ID
            LEFT JOIN {$clinics_table}
		        ON {$appointments_table}.clinic_id = {$clinics_table}.id
			LEFT JOIN {$static_data_table} static_data	
				ON {$appointments_table}.visit_type = static_data.value
            LEFT JOIN {$patient_encounter_table} encounter
                ON {$appointments_table}.id = encounter.appointment_id
                WHERE 0 = 0 " . $data_filter ;
			
            if( $role == 'doctor' || $role == 'receptionist' )
            {
                if( $kiviCareProAddon )
                {
                    $clinic_id = (isset($parameters['clinic_id']) && $parameters['clinic_id'] != '' ) ? $parameters['clinic_id'] : null;
                    if( $clinic_id != null ) {
                        $query .= " AND {$appointments_table}.clinic_id = {$clinic_id} ";
                    } else {
                        $clinic_data = getUserClinicMapping($user_data);
                        $clinic_ids = collect($clinic_data)->pluck('clinic_id')->implode(',');
                        $query .= " AND {$appointments_table}.clinic_id IN ($clinic_ids) ";
                    }
                }
            }
			if ( $role == 'doctor' ) {
				$query .= " AND {$appointments_table}.doctor_id = {$userid} ";
			} elseif ( $role == 'patient' ) {
				$query .= " AND {$appointments_table}.patient_id = {$userid} ";
			}

			if ( isset( $parameters['patient_id'] ) && $parameters['patient_id'] != null ) {
				$query = $query . " AND {$appointments_table}.patient_id = {$parameters['patient_id']}";
			}
            
            if ( isset( $parameters['visit_type'] ) && $parameters['visit_type'] != null ) {
				$query = $query . " AND {$appointments_table}.visit_type = {$parameters['visit_type']}";
            }
            
			if ( isset( $parameters['status'] ) ) {
				if ( (int)$parameters['status'] === - 1 ) {
                    $time  = current_time('H:i:s');
                    $date = date('Y-m-d');
					$query = $query . " AND {$appointments_table}.appointment_start_date >= '{$date}' AND {$appointments_table}.appointment_start_time > '" . $time . "' ";
				}elseif ( (int)$parameters['status'] === 1 ) {
                    $date = date('Y-m-d');
					$query = $query . " AND {$appointments_table}.appointment_start_date >= '{$date}'" ;
                } elseif ( $parameters['status'] === "all" ) {
					$query = $query . " ";
				} else {
					$query = $query . " AND {$appointments_table}.status = {$parameters['status']} ";
				}
            }
            $query = $query. " ORDER BY {$appointments_table}.appointment_start_date DESC, {$appointments_table}.appointment_start_time ASC";
            
            $total_appointment = $wpdb->get_results($query,OBJECT);
            $query = $query . " LIMIT {$limit} OFFSET {$offset} ";

        $appointment_result = $wpdb->get_results($query,OBJECT);

        if(!empty($appointment_result) && count($appointment_result) > 0)
        {
            $appointment_result = collect($appointment_result)->map(function ($appointment) use($wpdb,$appointments_table){
                $appointments_service_table = $wpdb->prefix . 'kc_' . 'appointment_service_mapping';
                $service_table = $wpdb->prefix . 'kc_' . 'services';

                $query = " SELECT {$appointments_table}.id, {$service_table}.name AS service_name, {$service_table}.id AS service_id
                
                        FROM {$appointments_table}
                        LEFT JOIN {$appointments_service_table} ON {$appointments_table}.id = {$appointments_service_table}.appointment_id
                        JOIN {$service_table} ON {$appointments_service_table}.service_id = {$service_table}.id
                        WHERE 0 = 0 AND {$appointments_table}.id = {$appointment->id} ";

                $service_list = $wpdb->get_results( $query, OBJECT );

                if(!empty($service_list) && count($service_list) > 0) {
                    $appointment->visit_type = $service_list;
                }
                
                $telemed = checkPluginExist();

                if($telemed){
                    $zoom_config_data = get_user_meta($appointment->doctor_id, 'zoom_config_data', true);

                    $enableTeleMed = false;
                    if ($zoom_config_data) {
                        $zoom_config_data = json_decode($zoom_config_data);
                        if (isset($zoom_config_data->enableTeleMed) && (bool)$zoom_config_data->enableTeleMed) {
                            if ($zoom_config_data->api_key !== "" && $zoom_config_data->api_secret !== "") {
                                $enableTeleMed = true;
                            }
                        }
                    }

                    if($enableTeleMed)
                    {
                        $zoom_mapping_table = $wpdb->prefix . 'kc_' . 'appointment_zoom_mappings';
                        $zoom_data = $wpdb->get_row("SELECT * FROM $zoom_mapping_table WHERE appointment_id = {$appointment->id} ", OBJECT);
                        $appointment->zoom_data = $zoom_data;
                    }
                }
                return $appointment;
            });
        }
	$appointment_list['total'] = count( $total_appointment );
	$appointment_list['data'] = $appointment_result;

	return $appointment_list;
}

function kcaGetTimeSlots($data, $only_available_slots = false) {

	global $wpdb;

	$slots = [];

    if (!isset($data['date']) || !isset($data['doctor_id']) || !isset($data['clinic_id'])) {
        return $slots;
	}

	$appointment_day = strtolower(date('l', strtotime($data['date'])));
    $day_short = substr($appointment_day, 0, 3);

    $clinic_sessions_table	= $wpdb->prefix. 'kc_' . 'clinic_sessions';
    $query = "SELECT * FROM {$clinic_sessions_table}  WHERE `doctor_id` = ".$data['doctor_id']." AND `clinic_id` = ".$data['clinic_id']."  AND ( `day` = '{$day_short}' OR `day` = '{$appointment_day}') ";
    $clinic_session = collect($wpdb->get_results($query, OBJECT));

	if (count($clinic_session)) {

		$slot_date = date('Y-m-d', strtotime($data['date']));

		$appointments_table	= $wpdb->prefix. 'kc_' . 'appointments';
		$appointments = $wpdb->get_results("SELECT * FROM {$appointments_table} WHERE appointment_start_date = '{$slot_date}' AND status != 0  ",OBJECT);

		$clinic_schedule_table	= $wpdb->prefix. 'kc_' . 'clinic_schedule';
		$query = "SELECT * FROM {$clinic_schedule_table} WHERE `start_date` <= '{$slot_date}' AND `end_date` >= '{$slot_date}'  AND `status` = 1";
		$results = collect($wpdb->get_results($query, OBJECT));

		$leaves = $results->filter(function ($result) use ($data) {

            if ($result->module_type === "clinic") {
                if ((int) $result->module_id === (int) $data['clinic_id']) {
                    return true;
                }
            } elseif ($result->module_type === "doctor") {
                if ((int)$result->module_id === (int) $data['doctor_id']) {
                    return true;
                }
            } else {
                return false;
            }

            return false;
		});

		if (count($leaves)) {
            return $slots;
        }

        foreach ($clinic_session as $key => $session) {

            $newTimeSlot = "";
			$time_slot = $session->time_slot ;
			$start_time = new \DateTime($session->start_time);
            $time_diff = $start_time->diff(new \DateTime($session->end_time));

            if ($time_diff->h !== 0) {
				$time_diff_min = round(($time_diff->h * 60) / $time_slot);
            } else {
				$time_diff_min = round($time_diff->i / $time_slot);
            }

            for ($i = 0; $i <= $time_diff_min; $i++) {

                if ($i === 0) {
					$newTimeSlot = date('H:i', strtotime($session->start_time));
                } else {
					$newTimeSlot = date('H:i', strtotime('+' . $time_slot . ' minutes', strtotime($newTimeSlot)));
                }

                if (strtotime($newTimeSlot) < strtotime($session->end_time)) {

                    $temp = [
                        'time' => date('h:i A', strtotime($newTimeSlot)),
                        'available' => true
					];

	                $isAvailable = array_filter($appointments, function ($appointment) use ($newTimeSlot, $data) {
                        if ($appointment->appointment_start_time === date('H:i:s', strtotime($newTimeSlot))
                            && (int) $appointment->id !== (int) $data['appointment_id']
                            && (int) $appointment->clinic_id === (int) $data['clinic_id']
                            && (int) $appointment->doctor_id === (int) $data['doctor_id']) {
                            return true;
                        } else {
                            return false;
                        }
                    });

                    if (count($isAvailable)) {
	                    (bool) $temp['available'] = false;
                    }

                    $currentDateTime = current_time('Y-m-d H:i:s');
                    $newDateTime = date('Y-m-d', strtotime($data['date'])) . ' ' . $newTimeSlot . ':00';

                    if (strtotime($newDateTime) < strtotime($currentDateTime)) {
	                   (bool) $temp['available'] = false;
                    }

                    // following condition is for get only available slots
                    if($only_available_slots !== false) {
                        if($temp['available'] !== false) {
							$slots[$key][] = $temp;
                        }
                    } else {
                        $slots[$key][] = $temp;
                    }
                }
            }
        }

	}

	return array_values($slots);

}

function iqonic_post_args($params = null, $filter = null, $post_in = null) {

    $args['post_type'] = 'post';
    $args['post_status'] = 'publish';
    $args['posts_per_page'] = 5;
    $args['paged'] = 1;

    if ($post_in) {
        $args['post__in'] = $post_in;
    }

    if ($params) {
        if (!empty($params['posts_per_page']) && isset($params['posts_per_page'])) {
            $args['posts_per_page'] = $params['posts_per_page'];
        }
        if (!empty($params['paged']) && isset($params['paged'])) {
            $args['paged'] = $params['paged'];
        }
        if (!empty($params['category']) && isset($params['category'])) {
            $args['category'] = $params['category'];
        }
        if (!empty($params['text']) && isset($params['text'])) {
            $args['s'] = $params['text'];
        }
        if (!empty($params['subcategory']) && isset($params['subcategory'])) {
            $args['category'] = $params['subcategory'];
        }

    }

    if ($filter === 'recent') {
        $args['order'] = 'DESC';
        $args['orderby'] = 'post_date';
    }
    
    if ($filter == 'by_category') {
        if ($params) {
            if (!empty($params['category']) && isset($params['category'])) {
                $args['cat'] = $params['category'];
            }
            if (!empty($params['subcategory']) && isset($params['subcategory'])) {
                $args['cat'] = $params['subcategory'];
            }
        }
    }

    return $args;
}

function get_news_data($wp_query = null, $user_id = null)
{

    $temp = array();
    global $post;
    global $wpdb;

	$image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), [300, 300]);	

    $temp = [
        'ID'                => $post->ID,
        'image'             => !empty($image) ? $image[0] : null,
        'post_title'        => get_the_title(),
        'post_content'      => esc_html(get_the_content(null,false,$post->ID)),
        'post_excerpt'      => esc_html(get_the_excerpt()),
        'post_date'         => $post->post_date,
        'post_date_gmt'     => $post->post_date_gmt,
        'readable_date'     => get_the_date(),
        'share_url'         => get_the_permalink(),
        'human_time_diff'   => human_time_diff(get_the_time('U'), current_time('timestamp')) . ' ' . __('ago'),
        'no_of_comments'    => get_comments_number_text(false, false, false, $post->ID),
        'post_author_name'  => get_the_author( 'display_name' , $post->post_author ),

    ];
    return $temp;
}

function kcaSendEmail($data)
{

    global $wpdb;
    $table_name = $wpdb->prefix . 'posts';
	
	$args['post_name'] = strtolower(KIVI_CARE_PREFIX.$data['email_template_type']);
    $args['post_type'] = strtolower(KIVI_CARE_PREFIX.'mail_tmp') ;
	
	$query = "SELECT * FROM $table_name WHERE `post_name` = '" . $args['post_name'] . "' AND `post_type` = '".$args['post_type']."' LIMIT 1 ";
    $check_exist_post = $wpdb->get_results($query, ARRAY_A);
	
	$email_content = $check_exist_post[0]['post_content'];
	$email_content = kcaEmailContentKeyReplace($email_content, $data);

    if (count($check_exist_post) > 0) {
        $small_prefix = strtolower(KIVI_CARE_PREFIX);
        switch ($args['post_name']) {
            case $small_prefix.'doctor_registration':
                $email_title = 'Doctor Registration';
                break;
            case $small_prefix.'patient_registration':
                $email_title = 'Patient Registration';
                break;
            case $small_prefix.'receptionist_registration':
                $email_title = 'Receptionist Registration';
                break;
            case $small_prefix.'book_appointment':
                $email_title = 'Appointment Booking';
                break;
            case $small_prefix.'doctor_book_appointment':
                $email_title = 'New Appointment Booking';
                break;
            case $small_prefix.'zoom_link':
                $email_title = 'Telemed Appointment Booking';
                break;
            default:
                $email_title = 'Welcome To Clinic ';
        }

        $email_status = wp_mail($data['user_email'], $email_title, $email_content);

        if ($email_status) {
            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function kcaEmailContentKeyReplace($content, $data)
{

    $email_template_key = kcaGetEmailTemplateKey();
    $email_content = $content;

    if (count($email_template_key) > 0) {
        foreach ($email_template_key as $item => $value) {
            switch ($value) {
                case '{{user_name}}':
                    if(isset($data['username'])) {
                        $email_content = str_replace($value, $data['username'], $email_content);
                    }
                    break;
                case '{{user_password}}':
                    if(isset($data['password'])) {
                        $email_content = str_replace($value, $data['password'], $email_content);
                    }
                    break;
                case '{{user_email}}':
                    if(isset($data['user_email'])) {
                        $email_content = str_replace($value, $data['user_email'], $email_content);
                    }
                    break;
                case '{{appointment_date}}':
                    if(isset($data['appointment_date'])) {
                        $email_content = str_replace($value, $data['appointment_date'], $email_content);
                    }
                    break;
                case '{{appointment_time}}':
                    if(isset($data['appointment_time'])) {
                        $email_content = str_replace($value, $data['appointment_time'], $email_content);
                    }
                    break;
                case '{{patient_name}}':
                    if(isset($data['patient_name'])) {
                        $email_content = str_replace($value, $data['patient_name'], $email_content);
                    }                  
                    break;
                case '{{zoom_link}}':
                    if(isset($data['zoom_link'])) {
                        $email_content = str_replace($value, $data['zoom_link'], $email_content);
                    }
                    break;
                default:
	                $email_content = $email_content ;
            }
        }
    }

    return $email_content;

}

function kcaGetEmailTemplateKey()
{
    return [
        '{{user_name}}',
        '{{user_password}}',
        '{{user_email}}',
        '{{user_contact}}',
        '{{appointment_date}}',
        '{{appointment_time}}',
        '{{patient_name}}',
        '{{zoom_link}}'
    ];
}

function kcaCancelAppointments ($data) {

	$start_date = $data['start_date'];
	$end_date = $data['end_date'];
	global $wpdb;

	$app_table_name = $wpdb->prefix . 'kc_' . 'appointments';

	$appointment_condition  = " `appointment_start_date` >= '$start_date' AND `appointment_start_date` <= '$end_date' " ;

    $query = "UPDATE {$app_table_name} SET `status` = 0 WHERE  {$appointment_condition} AND `status` = 1 " ;

	if (isset($data['doctor_id'])) {
		$query = $query . " AND doctor_id = " . $data['doctor_id'];
	}

	if (isset($data['clinic_id'])) {
		$query = $query . " AND clinic_id = " . $data['clinic_id'];
	}

	$wpdb->query($query);

}

function getUserClinicMapping( $data ) {
    global $wpdb;

    $clinic_table = $wpdb->prefix . 'kc_' . 'clinics';
    if ( $data['role'] == 'doctor' ) {
        $table = $wpdb->prefix . 'kc_' . 'doctor_clinic_mappings';
        $condition = " {$table}.doctor_id = {$data['user_id']} ";
    }

    if ( $data['role'] == 'receptionist' ) {
        $table = $wpdb->prefix . 'kc_' . 'receptionist_clinic_mappings';

        $condition = " {$table}.receptionist_id = {$data['user_id']} ";
    }
    $query = " SELECT clinics.id AS clinic_id,
                clinics.name AS clinic_name,
                clinics.email AS clinic_email,
                clinics.extra AS extra
            FROM {$table}
                LEFT JOIN {$clinic_table} clinics
                    ON {$table}.clinic_id = clinics.id 
                WHERE {$condition} ";

    $clinic_data = $wpdb->get_results($query);
    
    if ( !empty($clinic_data) && count($clinic_data) > 0 )
    {
        $clinic_data = collect($clinic_data)->map(function ($clinic){
            $clinic->extra = json_decode($clinic->extra);
            return $clinic;
        });
    }
    return $clinic_data;
}

function kcaGetService($data) {

    global $wpdb;
    $service = $wpdb->prefix . 'kc_' . 'services';
    if($data['type'] === 'telemed') {
        $condition  = " AND type = 'system_service' AND name = '{$data['type']}' " ;
    } else {
        $condition  = " AND name = '{$data['type']}' " ;
    }
    
    $service_query = "SELECT * FROM {$service} WHERE 0 = 0 " . $condition ;
    $service_id = $wpdb->get_row($service_query, 'OBJECT');
    if($service_id) {
        return $service_id;
    } else {
        return $data->id = 0 ;
    }
}

function kcaSaveCustomFields($module_id, $module_type, $custom_fields)
{
    global $wpdb;

    $custom_field_data_table = $wpdb->prefix . 'kc_' . 'custom_fields_data';
    $query = "SELECT * FROM {$custom_field_data_table} WHERE module_type = '{$module_type}' AND module_id = {$module_id}";
    $fieldObj = $wpdb->get_row( $query, OBJECT );

    $temp = [
        'module_type' => $module_type,
        'module_id' => $module_id,
        'fields_data' => json_encode($custom_fields)
    ];

    if ($fieldObj === []) {
        $wpdb->insert($custom_field_data_table,$temp);
        $message = 'save successfully';
    } else {
        $wpdb->update($custom_field_data_table,$temp, ['id' => $fieldObj->id]);
        $message = 'updated successfully';
    }

    return $message;
}

function defaultClinic() {
    $default_clinic = get_option('setup_step_1');
    $option_data = json_decode($default_clinic, true);

    return isset($option_data['id'][0]) ? $option_data['id'][0] : 0;
}

function kcaGetDefaultClinic() {
    global $wpdb;
    $clinic_table_name = $wpdb->prefix . 'kc_' . 'clinics';
    $clinic_id = defaultClinic();
    $clinic_query = "SELECT id AS clinic_id, name AS clinic_name, email AS clinic_email, extra AS extra 
        FROM {$clinic_table_name} WHERE `id` = {$clinic_id} ";
    
    $clinic_data = $wpdb->get_results($clinic_query, 'OBJECT');

    if ( !empty($clinic_data) && count($clinic_data) > 0 )
    {
        $clinic_data = collect($clinic_data)->map(function ($clinic){
            $clinic->extra = json_decode($clinic->extra);
            return $clinic;
        });
    }
    return $clinic_data;
}

function kcaRemoveBlankKeyFromArray($data)
{
    foreach($data as $key => $value) {
        if($key === null || $key === '' ) {
            unset($data[$key]);
        }
    }
    return  $data ;
}

function kcaCheckDoctorSession($parameter )
{
    global $wpdb;
    $status = false;

    $clinic_session_table = $wpdb->prefix . 'kc_' . 'clinic_sessions';
    $id = $parameter['id'];
    $clinic_id = $parameter['clinic_id'];
    $doctor_id = $parameter['doctor_id'];

    $days =  "'" . implode( "','", $parameter['day'] ) . "'";
    
    $session_one_start  = date('H:i:s', strtotime($parameter['s_one_start_time']['HH'] . ':' . $parameter['s_one_start_time']['mm']));
    $session_one_end    = date('H:i:s', strtotime($parameter['s_one_end_time']['HH'] . ':' . $parameter['s_one_end_time']['mm']));
    $session_two_start  = date('H:i:s', strtotime($parameter['s_two_start_time']['HH'] . ':' . $parameter['s_two_start_time']['mm']));
    $session_two_end    = date('H:i:s', strtotime($parameter['s_two_end_time']['HH'] . ':' . $parameter['s_two_end_time']['mm']));

    $query = " SELECT * FROM {$clinic_session_table} WHERE doctor_id = {$doctor_id} AND clinic_id = {$clinic_id} AND day IN ($days)  ";
    $session1_condition = ( isset($session_one_start) && isset($session_one_end) && $session_one_start != null && $session_one_end != null );
    if ( $session1_condition )
    {
        $query = $query . " AND start_time >= '{$session_one_start}' AND end_time <= '{$session_one_end}' ";
    }

    if($id != null){
        $query = $query . " AND id != {$id} AND parent_id != {$id} ";
    }

    $result1 = $wpdb->get_results($query,OBJECT);

    if( empty($result1) && count($result1) == 0 ){
        $status = true;
    }
    $session2_condition = ( isset($session_two_start) && isset($session_two_end) && $session_two_start != null && $session_two_end != null );

    if ( $status ){
        $query2 = " SELECT * FROM {$clinic_session_table} WHERE doctor_id = {$doctor_id} AND clinic_id = {$clinic_id} AND day IN ($days)  ";
        if( !$session1_condition || !$session2_condition ){
            $status = true;
        } else {

            $query2 = $query2 ." AND start_time >= '{$session_two_start}' AND end_time <= '{$session_two_end}' ";
            if($id != null){
                $query2 = $query2 . " AND id != {$id} AND parent_id != {$id}";
            }

            $result2 = $wpdb->get_results($query2,OBJECT);

            if( empty($result2) && count($result2) == 0 ){
                $status = true;
            } else {
                $status = false;
            }
        }
    }

    return $status;
    
}

function kcaGenerateBill($parameters)
{
    global $wpdb;

    $bill_table 				= $wpdb->prefix. 'kc_' . 'bills';
    $bill_items_table 		 	= $wpdb->prefix. 'kc_' . 'bill_items';
    $service_table    		 	= $wpdb->prefix. 'kc_' . 'services';
    $patient_encounter_table 	= $wpdb->prefix. 'kc_' . 'patient_encounters';
    $appointment_table	= $wpdb->prefix. 'kc_' . 'appointments';
    $appointment_service_table	= $wpdb->prefix. 'kc_' . 'appointment_service_mapping';
    $service_doctor_mapping_table = $wpdb->prefix. 'kc_' . 'service_doctor_mapping';
    $patient_encounter 			= $wpdb->get_row( "SELECT * FROM {$patient_encounter_table} WHERE id = {$parameters["encounter_id"]}", OBJECT );
    
    if ( empty( $patient_encounter ) ) {
        return comman_message_response( 'No encounter found' , 400 );
    }

    $temp = [
        // 'title'         => $parameters['title'],
        'encounter_id'  => $parameters['encounter_id'],
        'appointment_id'=> $parameters['appointment_id'],
        'total_amount'  => $parameters['total_amount'],
        'discount'      => $parameters['discount'],
        'actual_amount' => $parameters['actual_amount'],
        'status'        => ( isset($parameters['status']) && $parameters['status'] != '' ) ? $parameters['status'] : 0,
        'payment_status'=> ( isset($parameters['payment_status']) && $parameters['payment_status'] != '' ) ? $parameters['payment_status'] : null
    ];

    $id = (isset($parameters['id']) && $parameters['id'] != '' ) ? $parameters['id'] : null;

    if ( $id == null) {

        $temp['created_at'] = current_time( 'Y-m-d H:i:s' );
        $status       		= $wpdb->insert( $bill_table, $temp );
        $bill_id            = $wpdb->insert_id;
        $message			= 'Bill has been generated successfully';

    } else {
        $status	 = $wpdb->update( $bill_table , $temp, array( 'id' => $id ) );
        $bill_id = $id;
        $message = 'Bill has been updated successfully';
    }

    if ( isset( $parameters['billItems'] ) && count( $parameters['billItems'] ) ) {

        // insert bill items
        foreach ( $parameters['billItems'] as $key => $bill_item ) {

            if ((int) $bill_item['item_id'] === 0 ) {

                $service = $wpdb->get_row( "SELECT * FROM {$service_table} WHERE name = '{$bill_item['item_id']}' ", OBJECT );
                
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

                    $service_mapping_data = [
                        'service_id' 	=> $bill_item['item_id'],
                        'doctor_id' 	=> $patient_encounter->doctor_id,
                        'clinic_id'		=> $patient_encounter->clinic_id,
                        'charges'		=> $bill_item['price'],
                        'status'		=> 1,
                        'created_at'	=> current_time('Y-m-d H:i:s')
                    ];
                    $wpdb->insert($service_doctor_mapping_table, $service_mapping_data);
                }
            }
            /*
            if($patient_encounter->appointment_id != null && $patient_encounter->appointment_id != ""){
                $wpdb->delete( $appointment_service_table , [ 'id' => $patient_encounter->appointment_id ]);

                $appointment_service_data = [
                    'appointment_id' => $patient_encounter->appointment_id,
                    'service_id' 	=> $bill_item['item_id'],
                    'status' => 1,
                    'created_at'	=> current_time('Y-m-d H:i:s')
                ];
                $wpdb->insert( $appointment_service_table , $appointment_service_data );
            }
            */
            $_temp = [
                'bill_id' => $bill_id,
                'price'   => $bill_item['price'],
                'qty'     => $bill_item['qty'],
                'item_id' => $bill_item['item_id'],
            ];
            if ( $bill_item['id'] == null ) {
                $_temp['created_at'] = current_time( 'Y-m-d H:i:s' );
                $wpdb->insert( $bill_items_table , $_temp );
            } else {
                $wpdb->update( $bill_items_table , $_temp, array( 'id' => $bill_item['id'] ) );
            }
        }
    }

    if($parameters['payment_status'] == 'paid')
    {
        $bill_data = $wpdb->get_row(" SELECT * FROM {$bill_table} WHERE id = {$bill_id}" , OBJECT );

        if($bill_data != null && $bill_data->appointment_id != null) {
            $wpdb->update( $appointment_table , [ 'status' => 3 ], [ 'id' => $bill_data->appointment_id ] );
        }
    }
    return $message;
}

function kcaGetZoomConfig($user_id) {
	$config_data = apply_filters('kct_get_zoom_configuration', [
        'user_id' => $user_id,
    ]);

    if (isset($config_data['status']) && $config_data['status'] && !empty($config_data['data'])) {
        $user_data['enableTeleMed'] = (bool)$config_data['data']->enableTeleMed;
        $user_data['api_key'] = $config_data['data']->api_key;
        $user_data['api_secret'] = $config_data['data']->api_secret;
        $user_data['zoom_id'] = $config_data['data']->zoom_id;
    }else{
        $user_data['enableTeleMed'] = null;
        $user_data['api_key'] = null;
        $user_data['api_secret'] = null;
        $user_data['zoom_id'] = null;
    }

    return $user_data;
}

function kctCreateAppointmentMeeting($parameters){
    
    $patient_data = get_userdata($parameters['patient_id']);

    $parameters['doctor_id'] = [
        'id' => $parameters['doctor_id']
    ];
    $parameters['patient_id'] = [
        'id' => $parameters['patient_id'],
        'label' => $patient_data->display_name
    ];
    
    $res_data = apply_filters('kct_create_appointment_meeting', $parameters);
    $appointment_date = date( 'Y-m-d', strtotime( $parameters['appointment_start_date'] ) );
    // send zoom link
    $user_email_param['patient_name'] = $patient_data->display_name;
    $user_email_param['appointment_id'] = $parameters['appointment_id'];

    $user_email_param['user_email'] = $patient_data->user_email;
    
    $user_email_param['appointment_date'] = $appointment_date;
    $user_email_param['appointment_time'] = date( 'H:i:s', strtotime( $parameters['appointment_start_time'] ) );
    $user_email_param['email_template_type'] = KIVI_CARE_PREFIX . 'book_appointment'; // $this->getPrefix()
    $resdata = apply_filters('kct_send_zoom_link', $user_email_param );

    return $res_data;
}

function checkPluginExist(){
    if (!function_exists('get_plugins')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    
    $plugins = get_plugins();
    
    foreach ($plugins as $key => $value) {
        if($value['TextDomain'] === 'kiviCare-telemed-addon') {
            return (is_plugin_active($key) ? true : false);
        }
    }
    return false;
}

function isWooCommerceActive() {

    if (!function_exists('get_plugins')) {
        include_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = get_plugins();
    
    foreach ($plugins as $key => $value) {
        if($value['TextDomain'] === 'woocommerce') {
            return (is_plugin_active($key) ? true : false);
        }
    }
    return false ;
}

add_filter( 'user_has_cap', 'iqonic_order_pay_without_login', 9999, 3 );
 
function iqonic_order_pay_without_login( $allcaps, $caps, $args ) {
    if ( isset( $caps[0], $_GET['key'] ) ) {
        if ( $caps[0] == 'pay_for_order' ) {
            $order_id = isset( $args[2] ) ? $args[2] : null;
            $order = wc_get_order( $order_id );
            if ( $order ) {
                $allcaps['pay_for_order'] = true;
            }
        }
    }
    return $allcaps;
}

add_filter( 'woocommerce_prevent_admin_access', 'kivicare_other_role_access', 20, 1 ); 
function kivicare_other_role_access( $prevent_access ) {
    if( current_user_can('read') ) $prevent_access = false;
        return $prevent_access; 
}

function kcaGetClinic($id)
{
    global $wpdb;
    $clinic = $wpdb->prefix . 'kc_' . 'clinics';

    $clinic_query = "SELECT * FROM {$clinic} WHERE id = {$id} ";
    $clinic_data = $wpdb->get_row($clinic_query, 'OBJECT');
    if(!empty($clinic_data)) {
        return $clinic_data;
    } else {
        return null;
    }
}