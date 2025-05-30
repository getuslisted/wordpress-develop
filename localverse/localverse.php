<?php
/**
 * Plugin Name:       LocalVerse
 * Plugin URI:        https://example.com/plugins/localverse/
 * Description:       A comprehensive local directory platform for WordPress.
 * Version:           1.0.0
 * Author:            Your Name or Company
 * Author URI:        https://example.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       localverse
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Currently plugin version.
 */
define( 'LOCALVERSE_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 */
function activate_localverse() {
    // Activation code here.
}
register_activation_hook( __FILE__, 'activate_localverse' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_localverse() {
    // Deactivation code here.
}
register_deactivation_hook( __FILE__, 'deactivate_localverse' );

/**
 * Begins execution of the plugin.
 */
require plugin_dir_path( __FILE__ ) . 'includes/core.php';

/**
 * Begins execution of the plugin.
 *
 * Instantiates the core plugin class and calls its run method.
 *
 * @since    0.1.0
 */
function run_localverse() {
    $plugin = new LocalVerse_Core();
    // $plugin->run(); // This will be uncommented and used once the loader is implemented.
    // For now, necessary initializations like post type registration will be called
    // directly or via temporary hooks from within LocalVerse_Core constructor or a dedicated method.
}
add_action( 'plugins_loaded', 'run_localverse' );
?>
