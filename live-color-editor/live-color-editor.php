<?php
/**
 * Plugin Name:       Live Color Editor
 * Plugin URI:        https://example.com/
 * Description:       A plugin to find and replace colors on your WordPress site live.
 * Version:           1.0.0
 * Author:            Jules
 * Author URI:        https://example.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       live-color-editor
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'LCE_VERSION', '1.0.0' );
define( 'LCE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_live_color_editor() {
    require_once LCE_PLUGIN_DIR . 'includes/class-activator.php';
    LCE_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_live_color_editor() {
    require_once LCE_PLUGIN_DIR . 'includes/class-deactivator.php';
    LCE_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_live_color_editor' );
register_deactivation_hook( __FILE__, 'deactivate_live_color_editor' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require LCE_PLUGIN_DIR . 'includes/class-main.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_live_color_editor() {

    $plugin = new LCE_Main();
    $plugin->run();

}
run_live_color_editor();
