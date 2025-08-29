<?php
/**
 * Fired during plugin activation.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/includes
 * @author     Jules <you@example.com>
 */
class LCE_Activator {

    /**
     * Short Description. (use period)
     *
     * Long Description.
     *
     * @since    1.0.0
     */
    public static function activate() {
        self::create_activity_log_table();
    }

    /**
     * Create the activity log table.
     */
    private static function create_activity_log_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lce_activity_log';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            old_color varchar(255) DEFAULT '' NOT NULL,
            new_color varchar(255) DEFAULT '' NOT NULL,
            action_type varchar(50) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            timestamp datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
}
