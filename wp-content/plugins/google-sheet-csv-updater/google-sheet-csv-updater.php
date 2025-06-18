<?php
/**
 * Plugin Name:       Google Sheet CSV Updater
 * Plugin URI:        https://github.com/your-repo/google-sheet-csv-updater
 * Description:       Allows using a Google Sheet CSV to auto-update website content based on a unique ID.
 * Version:           1.1.0
 * Author:            Jules (AI Assistant) & You
 * Author URI:
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       google-sheet-csv-updater
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Plugin Constants
define( 'GSCU_VERSION', '1.1.0' );
define( 'GSCU_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GSCU_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GSCU_PLUGIN_FILE', __FILE__ );

// Core Logic Constants
define( 'GSCU_ERROR_NO_URL', 'gscu_error_no_url' );
define( 'GSCU_ERROR_FETCH_FAILED', 'gscu_error_fetch_failed' );
define( 'GSCU_ERROR_INVALID_RESPONSE', 'gscu_error_invalid_response' );
define( 'GSCU_ERROR_EMPTY_BODY', 'gscu_error_empty_body' );
define( 'GSCU_ERROR_PARSE_FAILED', 'gscu_error_parse_failed' );
define( 'GSCU_SUCCESS_NO_DATA', 'gscu_success_no_data' );
define( 'GSCU_ERROR_NO_HEADERS', 'gscu_error_no_headers' );
define( 'GSCU_UNIQUE_ID_META_KEY', '_gscu_unique_id' );
define( 'GSCU_LOGS_OPTION_KEY', 'gscu_sync_logs' );
define( 'GSCU_MAX_LOG_ENTRIES', 50 );
define( 'GSCU_CRON_HOOK', 'gscu_cron_sync_event' ); // Cron hook name


// Include class files
require_once GSCU_PLUGIN_DIR . 'includes/class-gscu-admin.php';
require_once GSCU_PLUGIN_DIR . 'includes/class-gscu-frontend.php';
require_once GSCU_PLUGIN_DIR . 'includes/class-gscu-sync.php';
require_once GSCU_PLUGIN_DIR . 'includes/class-gscu-cron.php';

/**
 * Initialize the plugin and its components.
 */
function gscu_init() {
    new GSCU_Admin();
    new GSCU_Frontend();
    new GSCU_Cron();
}
add_action( 'plugins_loaded', 'gscu_init' );

/**
 * Load plugin text domain for internationalization.
 */
function gscu_load_textdomain_main() {
    load_plugin_textdomain(
        'google-sheet-csv-updater',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
}
add_action( 'plugins_loaded', 'gscu_load_textdomain_main' );

/**
 * Plugin deactivation hook.
 * Clears scheduled cron events.
 */
function gscu_plugin_deactivate() {
    if (class_exists('GSCU_Cron')) {
        GSCU_Cron::clear_scheduled_events();
    }
}
register_deactivation_hook( GSCU_PLUGIN_FILE, 'gscu_plugin_deactivate' );

/**
 * Plugin activation hook. (Optional)
 * Can be used to schedule cron events if settings already permit.
 */
// function gscu_plugin_activate() {
//     if (class_exists('GSCU_Cron')) {
//         GSCU_Cron::schedule_or_clear_events(); // Check current settings and schedule if needed
//     }
// }
// register_activation_hook( GSCU_PLUGIN_FILE, 'gscu_plugin_activate' );

// Note: All previous global functions have been moved into their respective classes.
// The main plugin file is now primarily for defining constants, loading files,
// initializing the core components, and handling activation/deactivation.
