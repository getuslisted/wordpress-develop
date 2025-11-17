<?php
/**
 * Cleanup routines when uninstalling the Local Gamified Directory plugin.
 *
 * @package LocalGamifiedDirectory
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom plugin tables.
$tables = array(
	'lgd_user_points',
	'lgd_points_log',
	'lgd_user_sanctions',
	'lgd_activity_log',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

// Remove plugin options.
$wpdb->query(
	$wpdb->prepare(
	        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
	        $wpdb->esc_like( 'lgd_' ) . '%'
	)
);

$option_keys = array(
	'lgd_feature_flags',
	'lgd_feature_role_map',
	'lgd_abuse_threshold',
	'lgd_abuse_window_minutes',
	'lgd_activity_retention_days',
	'lgd_social_google_client_id',
	'lgd_social_google_client_secret',
	'lgd_social_facebook_app_id',
	'lgd_social_facebook_app_secret',
);

foreach ( $option_keys as $option_key ) {
	delete_option( $option_key );
}

// Remove plugin-specific user and post meta.
$meta_like = $wpdb->esc_like( '_lgd_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", $meta_like ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $meta_like ) );

$user_meta_keys = array(
	'_lgd_disabled_features',
	'_lgd_enabled_features',
	'_lgd_account_status',
	'_lgd_suspended_until',
	'_lgd_suspension_reason',
);

foreach ( $user_meta_keys as $meta_key ) {
	delete_metadata( 'user', 0, $meta_key, '', true );
}

// Remove custom roles.
remove_role( 'business_owner' );
remove_role( 'community_member' );

// Remove capabilities that may have been added to core roles.
$roles = array( 'administrator', 'editor', 'author', 'contributor', 'subscriber' );
$business_caps = array(
	'read_business_listing',
	'read_business_listings',
	'edit_business_listing',
	'edit_business_listings',
	'edit_published_business_listings',
	'create_business_listings',
	'delete_business_listing',
	'delete_business_listings',
);

$class_caps = array(
	'read_classified_listing',
	'edit_classified_listing',
	'edit_classified_listings',
	'publish_classified_listings',
	'create_classified_listings',
	'delete_classified_listing',
	'delete_classified_listings',
);

$ad_caps = array(
	'read_lgd_ad',
	'read_private_lgd_ads',
	'edit_lgd_ad',
	'edit_lgd_ads',
	'edit_private_lgd_ads',
	'edit_published_lgd_ads',
	'create_lgd_ads',
	'delete_lgd_ad',
	'delete_lgd_ads',
	'delete_private_lgd_ads',
	'delete_published_lgd_ads',
);

$caps_to_remove = array_merge( $business_caps, $class_caps, $ad_caps );

foreach ( $roles as $role_key ) {
	$role = get_role( $role_key );

	if ( ! $role ) {
		continue;
	}

	foreach ( $caps_to_remove as $cap ) {
		if ( $role->has_cap( $cap ) ) {
			$role->remove_cap( $cap );
		}
	}
}

// Clear scheduled events created by the plugin.
wp_clear_scheduled_hook( 'lgd_expire_classifieds' );
wp_clear_scheduled_hook( 'lgd_purge_activity_logs' );
