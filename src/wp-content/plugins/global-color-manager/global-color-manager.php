<?php
/**
 * Plugin Name: Global Color Manager
 * Description: Detect, inspect, and replace colors on individual pages or globally without modifying theme files.
 * Version: 1.0.0
 * Author: Codex
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: global-color-manager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GCM_VERSION', '1.0.0' );
define( 'GCM_PLUGIN_FILE', __FILE__ );
define( 'GCM_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'GCM_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once GCM_PLUGIN_PATH . 'includes/class-gcm-loader.php';

register_activation_hook( __FILE__, array( 'GCM\\Loader', 'activate' ) );
register_uninstall_hook( __FILE__, array( 'GCM\\Loader', 'uninstall' ) );

add_action(
	'plugins_loaded',
	static function() {
		$loader = new GCM\Loader();
		$loader->init();
	}
);
