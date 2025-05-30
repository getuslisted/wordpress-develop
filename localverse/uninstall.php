<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * @package LocalVerse
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Add uninstall logic here (e.g., delete options, custom tables, etc.).
// Example: delete_option( 'my_plugin_settings' );
?>
