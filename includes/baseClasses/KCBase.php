<?php

/**
 * @package  KivicarePlugin
 */

namespace Includes\baseClasses;

class KCBase {

	public $plugin_path;

	public $nameSpace;

	public $plugin_url;

	public $plugin;

	public $dbConfig;

	private $pluginPrefix;

	public $plugin_version;


	public function __construct() {

		$this->plugin_path = plugin_dir_path( dirname( __FILE__, 2 ) );
		$this->plugin_url  = plugin_dir_url( dirname( __FILE__, 2 ) );

		$this->nameSpace    = KIVICARE_API_NAMESPACE;
		$this->pluginPrefix = KIVICARE_API_PREFIX;

		$this->plugin_version = KIVICARE_API_VERSION;

		$this->plugin = plugin_basename( dirname( __FILE__, 3 ) ) . '/kivicare-api.php';

		$this->dbConfig = [
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
			'db'   => DB_NAME,
			'host' => DB_HOST
		];

	}

	public function get_namespace() {
		return $this->nameSpace;
	}

	protected function getPluginPrefix() {
		return $this->pluginPrefix;
	}

	protected function getPrefix() {
		return KIVI_CARE_PREFIX;
	}

	protected function getClinicAdminRole() {
		return KIVI_CARE_PREFIX . "clinic_admin";
	}

	protected function getDoctorRole() {
		return KIVI_CARE_PREFIX . "doctor";
	}

	protected function getPatientRole() {
		return KIVI_CARE_PREFIX . "patient";
	}

	protected function teleMedAddOnName() {

		$plugin = checkPluginExist();
		return $plugin;
	}
	
	protected function isTeleMedActive () {

		if (!function_exists('get_plugins')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();

        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'kiviCare-telemed-addon') {
				return true ;
            }
		}
		
        return false ;
	}

	protected function isKiviCareProOnName() {
        
		if (!function_exists('get_plugins')) {
			include_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugins = get_plugins();
		
        foreach ($plugins as $key => $value) {
            if($value['TextDomain'] === 'kiviCare-clinic-&-patient-management-system-pro') {
                return (is_plugin_active($key) ? true : false);
            }
        }
		return false;
	}
}
