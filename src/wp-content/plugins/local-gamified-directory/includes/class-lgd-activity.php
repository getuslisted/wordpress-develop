<?php
/**
 * Activity tracking for the Local Gamified Directory plugin.
 *
 * @package LocalGamifiedDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Log and inspect user activity for rankings and abuse detection.
 */
class LGD_Activity {

        /**
         * Database table name suffix.
         */
        const TABLE = 'lgd_activity_log';

        /**
         * Plugin instance.
         *
         * @var Local_Gamified_Directory
         */
        private $plugin;

        /**
         * Constructor.
         *
         * @param Local_Gamified_Directory $plugin Plugin instance.
         */
        public function __construct( Local_Gamified_Directory $plugin ) {
                $this->plugin = $plugin;

                add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
                add_action( 'init', array( $this, 'maybe_handle_tracked_redirect' ) );
                add_action( 'lgd_purge_activity_logs', array( $this, 'purge_old_logs' ) );
        }

        /**
         * Run activation tasks.
         */
        public static function activate() {
                self::create_table();
        }

        /**
         * Create the activity log table if necessary.
         */
        private static function create_table() {
                global $wpdb;

                $table_name      = $wpdb->prefix . self::TABLE;
                $charset_collate = $wpdb->get_charset_collate();

                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                $sql = "CREATE TABLE {$table_name} (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        user_id bigint(20) unsigned NOT NULL DEFAULT 0,
                        event varchar(100) NOT NULL,
                        object_type varchar(100) NULL,
                        object_id bigint(20) unsigned NULL,
                        details longtext NULL,
                        ip_address varchar(45) NULL,
                        user_agent text NULL,
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (id),
                        KEY event (event),
                        KEY user_id (user_id),
                        KEY created_at (created_at)
                ) {$charset_collate};";

                dbDelta( $sql );
        }

        /**
         * Register REST API routes.
         */
        public function register_rest_routes() {
                if ( ! $this->plugin->is_feature_enabled( 'activity_tracking' ) ) {
                        return;
                }
                register_rest_route(
                        'lgd/v1',
                        '/activity',
                        array(
                                'methods'             => 'POST',
                                'callback'            => array( $this, 'handle_activity_request' ),
                                'permission_callback' => array( $this, 'rest_permission_check' ),
                                'args'                => array(
                                        'event'       => array(
                                                'type'     => 'string',
                                                'required' => true,
                                        ),
                                        'object_type' => array(
                                                'type'     => 'string',
                                                'required' => false,
                                        ),
                                        'object_id'   => array(
                                                'type'     => 'integer',
                                                'required' => false,
                                        ),
                                        'details'     => array(
                                                'type'     => 'object',
                                                'required' => false,
                                        ),
                                ),
                        )
                );
        }

        /**
         * Check REST permissions.
         *
         * @return bool
         */
        public function rest_permission_check() {
                if ( ! $this->plugin->is_feature_enabled( 'activity_tracking' ) ) {
                        return false;
                }

                return is_user_logged_in();
        }

        /**
         * Handle an activity submission via REST.
         *
         * @param WP_REST_Request $request Request object.
         *
         * @return WP_REST_Response
         */
        public function handle_activity_request( WP_REST_Request $request ) {
                if ( ! $this->plugin->is_feature_enabled( 'activity_tracking' ) ) {
                        return new WP_REST_Response( array( 'error' => __( 'Activity tracking is disabled.', 'local-gamified-directory' ) ), 403 );
                }

                $user_id    = get_current_user_id();
                $event      = sanitize_key( $request->get_param( 'event' ) );
                $object_id  = absint( $request->get_param( 'object_id' ) );
                $objectType = sanitize_key( $request->get_param( 'object_type' ) );
                $details    = $request->get_param( 'details' );

                if ( empty( $event ) ) {
                        return new WP_REST_Response( array( 'error' => __( 'Event is required.', 'local-gamified-directory' ) ), 400 );
                }

                if ( ! is_array( $details ) ) {
                        $details = array();
                }

                $this->log_event( $user_id, $event, $objectType, $object_id, $details );

                return new WP_REST_Response( array( 'success' => true ), 200 );
        }

        /**
         * Handle redirect-based tracking events.
         */
        public function maybe_handle_tracked_redirect() {
                if ( ! $this->plugin->is_feature_enabled( 'activity_tracking' ) ) {
                        return;
                }

                if ( ! isset( $_GET['lgd_track_event'] ) ) {
                        return;
                }

                $event     = sanitize_key( wp_unslash( $_GET['lgd_track_event'] ) );
                $object_id = isset( $_GET['lgd_track_object'] ) ? absint( $_GET['lgd_track_object'] ) : 0;
                $type      = isset( $_GET['lgd_track_type'] ) ? sanitize_key( wp_unslash( $_GET['lgd_track_type'] ) ) : '';
                $nonce     = isset( $_GET['lgd_track_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['lgd_track_nonce'] ) ) : '';
                $target    = isset( $_GET['lgd_track_target'] ) ? base64_decode( sanitize_text_field( wp_unslash( $_GET['lgd_track_target'] ) ), true ) : '';

                if ( false === $target ) {
                        $target = '';
                }

                if ( empty( $event ) || empty( $target ) ) {
                        return;
                }

                if ( $object_id && ! wp_verify_nonce( $nonce, 'lgd_track_' . $event . '_' . $object_id ) ) {
                        wp_die( esc_html__( 'Invalid tracking request.', 'local-gamified-directory' ), esc_html__( 'Tracking error', 'local-gamified-directory' ), 403 );
                }

                $user_id = get_current_user_id();

                $this->log_event( $user_id, $event, $type, $object_id, array( 'source' => 'redirect' ) );

                $safe_target = wp_validate_redirect( $target, home_url( '/' ) );
                wp_safe_redirect( $safe_target );
                exit;
        }

        /**
         * Log an activity event.
         *
         * @param int    $user_id    User ID.
         * @param string $event      Event key.
         * @param string $objectType Object type.
         * @param int    $object_id  Object ID.
         * @param array  $details    Additional details.
         */
        public function log_event( $user_id, $event, $objectType = '', $object_id = 0, $details = array() ) {
                if ( ! $this->plugin->is_feature_enabled( 'activity_tracking' ) ) {
                        return;
                }

                global $wpdb;

                $table = $wpdb->prefix . self::TABLE;

                $ip_address = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
                $user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_textarea_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
                $details    = ! empty( $details ) ? wp_json_encode( $details ) : null;

                $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $table,
                        array(
                                'user_id'    => $user_id,
                                'event'      => $event,
                                'object_type'=> $objectType,
                                'object_id'  => $object_id,
                                'details'    => $details,
                                'ip_address' => $ip_address,
                                'user_agent' => $user_agent,
                                'created_at' => current_time( 'mysql', true ),
                        ),
                        array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
                );

                if ( $user_id ) {
                        $this->maybe_flag_abuse( $user_id, $event );
                }
        }

        /**
         * Possibly flag abuse based on high-frequency events.
         *
         * @param int    $user_id User ID.
         * @param string $event   Event name.
         */
        private function maybe_flag_abuse( $user_id, $event ) {
                $threshold = (int) get_option( LGD_Admin::OPTION_ABUSE_THRESHOLD, 50 );
                $window    = (int) get_option( LGD_Admin::OPTION_ABUSE_WINDOW, 60 );

                if ( $threshold <= 0 || $window <= 0 ) {
                        return;
                }

                $transient_key = 'lgd_abuse_' . $user_id . '_' . $event;
                $count         = (int) get_transient( $transient_key );
                $count++;

                set_transient( $transient_key, $count, $window * MINUTE_IN_SECONDS );

                if ( $count < $threshold ) {
                        return;
                }

                $already_flagged = get_transient( 'lgd_abuse_flagged_' . $user_id . '_' . $event );
                if ( $already_flagged ) {
                        return;
                }

                set_transient( 'lgd_abuse_flagged_' . $user_id . '_' . $event, 1, $window * MINUTE_IN_SECONDS );

                $reason = sprintf(
                        /* translators: 1: Event name. */
                        __( 'Automated warning: unusual %s activity detected.', 'local-gamified-directory' ),
                        $event
                );

                $admin = $this->plugin->get_admin();
                if ( $admin ) {
                        $admin->issue_warning( $user_id, $reason, true );
                }
        }

        /**
         * Generate a tracked URL for link clicks.
         *
         * @param string $event     Event key.
         * @param string $type      Object type.
         * @param int    $object_id Object ID.
         * @param string $target    Target URL.
         *
         * @return string
         */
        public function get_tracked_url( $event, $type, $object_id, $target ) {
                $args = array(
                        'lgd_track_event'  => sanitize_key( $event ),
                        'lgd_track_type'   => sanitize_key( $type ),
                        'lgd_track_object' => absint( $object_id ),
                        'lgd_track_target' => base64_encode( esc_url_raw( $target ) ),
                );

                if ( $object_id ) {
                        $args['lgd_track_nonce'] = wp_create_nonce( 'lgd_track_' . sanitize_key( $event ) . '_' . absint( $object_id ) );
                }

                return add_query_arg( $args, home_url( '/' ) );
        }

        /**
         * Retrieve recent events.
         *
         * @param int $limit Number of records.
         *
         * @return array
         */
        public function get_recent_events( $limit = 50 ) {
                global $wpdb;

                $table = $wpdb->prefix . self::TABLE;
                $limit = absint( $limit );
                if ( $limit <= 0 ) {
                        $limit = 50;
                }

                return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        }

        /**
         * Purge activity logs older than the retention policy.
         */
        public function purge_old_logs() {
                $retention = (int) get_option( LGD_Admin::OPTION_ACTIVITY_RETENTION, 90 );

                if ( $retention <= 0 ) {
                        return;
                }

                global $wpdb;

                $cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $retention * DAY_IN_SECONDS ) );
                $table  = $wpdb->prefix . self::TABLE;

                $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        }
}
