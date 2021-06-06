<?php

/**
 * @wordpress-plugin
 * Plugin Name:       kivicare-api
 * Plugin URI:        kivicare-api
 * Description:       Kivicare api mobile plugin
 * Version:           2.1.0
 * Author:            Iqonic
 * Author URI:        https://iqonic.design
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       kivicare-api
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
use Includes\baseClasses\KCActivate;
use Includes\baseClasses\KCDeactivate;

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'KIVICARE_API_VERSION', '2.1.0' );

defined( 'ABSPATH' ) or die( 'Something went wrong' );

// Require once the Composer Autoload
if ( file_exists( dirname( __FILE__ ) . '/vendor/autoload.php' ) ) {
	require_once dirname( __FILE__ ) . '/vendor/autoload.php';
} else {
	die( 'Something went wrong' );
}

if (!defined('KIVICARE_API_DIR'))
{
	define('KIVICARE_API_DIR', plugin_dir_path(__FILE__));
}

if (!defined('KIVICARE_API_DIR_URI'))
{
	define('KIVICARE_API_DIR_URI', plugin_dir_url(__FILE__));
}


if (!defined('KIVICARE_API_NAMESPACE'))
{
	define('KIVICARE_API_NAMESPACE', "kivicare");
}

if (!defined('KIVICARE_API_PREFIX'))
{
	define('KIVICARE_API_PREFIX', "iq_");
}

if (!defined('KIVI_CARE_PREFIX'))
{
	define('KIVI_CARE_PREFIX', "kiviCare_");
}
if (!defined('JWT_AUTH_SECRET_KEY'))
{
	define('JWT_AUTH_SECRET_KEY', 'your-top-secrect-key');
}
if (!defined('JWT_AUTH_CORS_ENABLE'))
{
	define('JWT_AUTH_CORS_ENABLE', true);
}

require_once( ABSPATH . 'wp-admin/includes/image.php' );
require_once( ABSPATH . 'wp-admin/includes/file.php' );
require_once( ABSPATH . 'wp-admin/includes/media.php' );

/**
 * The code that runs during plugin activation
 */
register_activation_hook( __FILE__, [ KCActivate::class, 'activate'] );

/**
 * The code that runs during plugin deactivation
 */
register_deactivation_hook( __FILE__, [KCDeactivate::class, 'init'] );


( new KCActivate )->init();