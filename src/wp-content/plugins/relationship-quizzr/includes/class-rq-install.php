<?php
/**
 * Installation and cleanup logic for Relationship Quizzr.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles plugin activation, deactivation, and uninstall.
 */
class RQ_Install {

    /**
     * Run on plugin activation.
     */
    public static function activate() {
        self::create_tables();
        self::register_post_types();
        flush_rewrite_rules();

        if ( ! wp_next_scheduled( 'rq_check_subscription_expirations' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'rq_check_subscription_expirations' );
        }

        if ( false === get_option( 'relationship_quizzr_settings' ) ) {
            update_option(
                'relationship_quizzr_settings',
                array(
                    'paypal_client_id'     => '',
                    'paypal_client_secret' => '',
                    'individual_price'     => 9.99,
                    'couple_price'         => 14.99,
                    'moderation_required'  => true,
                    'adult_category_lock'  => true,
                )
            );
        }
    }

    /**
     * Run on plugin deactivation.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'rq_check_subscription_expirations' );
        flush_rewrite_rules();
    }

    /**
     * Run on plugin uninstall.
     */
    public static function uninstall() {
        global $wpdb;

        wp_clear_scheduled_hook( 'rq_check_subscription_expirations' );

        $tables = array(
            $wpdb->prefix . 'rq_connections',
            $wpdb->prefix . 'rq_quiz_answers',
            $wpdb->prefix . 'rq_points',
            $wpdb->prefix . 'rq_referral_logs',
        );

        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        }

        delete_option( 'relationship_quizzr_settings' );
    }

    /**
     * Register custom post types during activation.
     */
    protected static function register_post_types() {
        $post_types = new RQ_Post_Types();
        $post_types->register();
        $post_types->register_taxonomies();
        $post_types->register_default_terms();
    }

    /**
     * Create custom tables.
     */
    protected static function create_tables() {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset_collate = $wpdb->get_charset_collate();

        $connections = "CREATE TABLE {$wpdb->prefix}rq_connections (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            connection_hash varchar(64) NOT NULL,
            user_one bigint(20) unsigned NOT NULL,
            user_two bigint(20) unsigned DEFAULT NULL,
            email varchar(190) DEFAULT NULL,
            relationship_type varchar(40) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY connection_hash (connection_hash),
            KEY user_one (user_one),
            KEY user_two (user_two)
        ) {$charset_collate};";

        $quiz_answers = "CREATE TABLE {$wpdb->prefix}rq_quiz_answers (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            quiz_id bigint(20) unsigned NOT NULL,
            question_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            connection_id bigint(20) unsigned DEFAULT NULL,
            answer longtext DEFAULT NULL,
            score decimal(6,2) DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY quiz_id (quiz_id),
            KEY question_id (question_id),
            KEY user_id (user_id),
            KEY connection_id (connection_id)
        ) {$charset_collate};";

        $points = "CREATE TABLE {$wpdb->prefix}rq_points (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            points int NOT NULL,
            context varchar(190) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id)
        ) {$charset_collate};";

        $referral_logs = "CREATE TABLE {$wpdb->prefix}rq_referral_logs (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            referrer_id bigint(20) unsigned NOT NULL,
            referee_id bigint(20) unsigned DEFAULT NULL,
            referral_code varchar(16) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            billing_period varchar(25) DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY referrer_id (referrer_id),
            KEY referral_code (referral_code)
        ) {$charset_collate};";

        dbDelta( $connections );
        dbDelta( $quiz_answers );
        dbDelta( $points );
        dbDelta( $referral_logs );
    }
}
