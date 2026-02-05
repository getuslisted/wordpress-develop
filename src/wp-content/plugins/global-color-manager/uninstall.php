<?php
/**
 * Uninstall plugin.
 *
 * @package GlobalColorManager
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'gcm_global_replacements' );
delete_option( 'gcm_global_index' );

global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_gcm_page_replacements'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gcm_detect_post_%' OR option_name LIKE '_transient_timeout_gcm_detect_post_%'" );
