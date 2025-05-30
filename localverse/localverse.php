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

require LOCALVERSE_PLUGIN_DIR . 'includes/user-roles.php';

/**
 * The code that runs during plugin activation.
 */
function activate_localverse() {
    LocalVerse_User_Roles::add_roles_on_activation();
    // Ensure CPTs are registered before flushing rewrite rules if they aren't already.
    // For now, assuming CPT registration happens on 'init'.
    // If CPTs are defined, they should be registered here or ensure 'init' has run.
    // Then, flush rewrite rules.
    flush_rewrite_rules();
    // Other activation code here.
}
register_activation_hook( __FILE__, 'activate_localverse' );

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_localverse() {
    LocalVerse_User_Roles::remove_roles_on_deactivation();
    flush_rewrite_rules();
    // Other deactivation code here.
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
