<?php
/**
 * Gamification utilities for the Local Gamified Directory plugin.
 *
 * @package LocalGamifiedDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Manage points, badges, and leaderboards.
 */
class LGD_Gamification {

        const TABLE_POINTS = 'lgd_user_points';
        const TABLE_LOG    = 'lgd_points_log';

        /**
         * Plugin instance.
         *
         * @var Local_Gamified_Directory
         */
        private $plugin;

        /**
         * Cached points table name.
         *
         * @var string
         */
        private $points_table;

        /**
         * Cached log table name.
         *
         * @var string
         */
        private $log_table;

        /**
         * Forum points daily cap.
         *
         * @var int
         */
        private $forum_daily_cap = 20;

        /**
         * Rank thresholds keyed by minimum point totals.
         *
         * Filter with {@see 'lgd_rank_thresholds'} to customise values.
         *
         * @var array
         */
        private $rank_thresholds = array(
                1000 => 'Expert',
                500  => 'Intermediate',
                0    => 'Beginner',
        );

        /**
         * Constructor.
         *
         * @param Local_Gamified_Directory $plugin Main plugin instance.
         */
        public function __construct( Local_Gamified_Directory $plugin ) {
                global $wpdb;

                $this->plugin       = $plugin;
                $this->points_table = $wpdb->prefix . self::TABLE_POINTS;
                $this->log_table    = $wpdb->prefix . self::TABLE_LOG;

                $this->rank_thresholds = (array) apply_filters( 'lgd_rank_thresholds', $this->rank_thresholds );
                uksort(
                        $this->rank_thresholds,
                        static function( $a, $b ) {
                                return (int) $b <=> (int) $a;
                        }
                );

                add_action( 'init', array( $this, 'register_shortcodes' ) );
                add_action( 'user_register', array( $this, 'handle_user_register' ) );
                add_action( 'wp_login', array( $this, 'handle_user_login' ), 10, 2 );
                add_action( 'bbp_new_topic', array( $this, 'handle_new_topic' ), 10, 4 );
                add_action( 'bbp_new_reply', array( $this, 'handle_new_reply' ), 10, 5 );
                add_action( 'save_post_classified_listing', array( $this, 'handle_classified_save' ), 10, 3 );
                add_action( 'save_post_business_listing', array( $this, 'handle_business_save' ), 10, 3 );
                add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
                add_action( 'admin_init', array( $this, 'handle_admin_adjustment' ) );
        }

        /**
         * Ensure required tables exist on activation.
         */
        public static function activate() {
                global $wpdb;

                $points_table = $wpdb->prefix . self::TABLE_POINTS;
                $log_table    = $wpdb->prefix . self::TABLE_LOG;
                $charset      = $wpdb->get_charset_collate();

                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                dbDelta(
                        "CREATE TABLE {$points_table} (
                        user_id BIGINT(20) UNSIGNED NOT NULL,
                        balance BIGINT(20) NOT NULL DEFAULT 0,
                        updated DATETIME NOT NULL,
                        PRIMARY KEY  (user_id)
                ) {$charset};"
                );

                dbDelta(
                        "CREATE TABLE {$log_table} (
                        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                        user_id BIGINT(20) UNSIGNED NOT NULL,
                        change_amount BIGINT(20) NOT NULL,
                        reason VARCHAR(200) DEFAULT '' NOT NULL,
                        context TEXT NULL,
                        created DATETIME NOT NULL,
                        PRIMARY KEY  (id),
                        KEY user_id (user_id),
                        KEY created (created)
                ) {$charset};"
                );
        }

        /**
         * Register shortcodes for leaderboards.
         */
        public function register_shortcodes() {
                add_shortcode( 'lgd_leaderboard', array( $this, 'render_leaderboard_shortcode' ) );
        }

        /**
         * Award points to new users on registration.
         *
         * @param int $user_id User ID.
         */
        public function handle_user_register( $user_id ) {
                $this->add_points( $user_id, 50, 'registration' );
        }

        /**
         * Award login points once per day.
         *
         * @param string $user_login Username.
         * @param WP_User $user User object.
         */
        public function handle_user_login( $user_login, $user ) {
                $last_login = get_user_meta( $user->ID, '_lgd_last_login_points', true );
                $today      = gmdate( 'Y-m-d' );

                if ( $last_login === $today ) {
                        return;
                }

                update_user_meta( $user->ID, '_lgd_last_login_points', $today );
                $this->add_points( $user->ID, 5, 'daily_login' );
        }

        /**
         * Award points for forum topics.
         */
        public function handle_new_topic( $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $topic_author = 0 ) {
                $user_id = $topic_author ? (int) $topic_author : get_current_user_id();

                if ( ! $user_id ) {
                        return;
                }

                $this->maybe_award_forum_points( $user_id, 5 );
        }

        /**
         * Award points for forum replies.
         */
        public function handle_new_reply( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = array(), $reply_author = 0 ) {
                $user_id = $reply_author ? (int) $reply_author : get_current_user_id();

                if ( ! $user_id ) {
                        return;
                }

                $this->maybe_award_forum_points( $user_id, 2 );
        }

        /**
         * Award points when classifieds are published.
         *
         * @param int     $post_id Post ID.
         * @param WP_Post $post    Post object.
         * @param bool    $update  Whether this is an update.
         */
        public function handle_classified_save( $post_id, $post, $update ) {
                if ( wp_is_post_revision( $post_id ) ) {
                        return;
                }

                if ( 'publish' !== $post->post_status || $update ) {
                        return;
                }

                if ( $post->post_author ) {
                        $this->add_points( $post->post_author, 5, 'classified_publish', array( 'post_id' => $post_id ) );
                }
        }

        /**
         * Award points when a business listing is published.
         *
         * @param int     $post_id Post ID.
         * @param WP_Post $post    Post object.
         * @param bool    $update  Whether this is an update.
         */
        public function handle_business_save( $post_id, $post, $update ) {
                if ( wp_is_post_revision( $post_id ) ) {
                        return;
                }

                if ( 'publish' !== $post->post_status || $update ) {
                        return;
                }

                if ( $post->post_author ) {
                        $this->add_points( $post->post_author, 10, 'business_publish', array( 'post_id' => $post_id ) );
                }
        }

        /**
         * Add an admin screen for manual point adjustments.
         */
        public function register_admin_page() {
                add_users_page(
                        __( 'Gamification', 'local-gamified-directory' ),
                        __( 'Gamification', 'local-gamified-directory' ),
                        'manage_options',
                        'lgd-gamification',
                        array( $this, 'render_admin_page' )
                );
        }

        /**
         * Handle admin adjustments to user points.
         */
        public function handle_admin_adjustment() {
                if ( empty( $_POST['lgd_adjust_points_nonce'] ) ) {
                        return;
                }

                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lgd_adjust_points_nonce'] ) ), 'lgd_adjust_points' ) ) {
                        return;
                }

                $user_id = isset( $_POST['lgd_adjust_user'] ) ? absint( $_POST['lgd_adjust_user'] ) : 0;
                $amount  = isset( $_POST['lgd_adjust_amount'] ) ? intval( $_POST['lgd_adjust_amount'] ) : 0;
                $reason  = isset( $_POST['lgd_adjust_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_adjust_reason'] ) ) : '';

                if ( $user_id && $amount ) {
                        if ( $amount > 0 ) {
                                $this->add_points( $user_id, $amount, $reason ? $reason : 'manual_adjustment' );
                        } else {
                                $this->remove_points( $user_id, abs( $amount ), $reason ? $reason : 'manual_adjustment' );
                        }
                }
        }

        /**
         * Render the admin screen.
         */
        public function render_admin_page() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                $recent = $this->get_points_log( 20 );
                ?>
                <div class="wrap">
                        <h1><?php esc_html_e( 'Gamification Manager', 'local-gamified-directory' ); ?></h1>
                        <form method="post">
                                <?php wp_nonce_field( 'lgd_adjust_points', 'lgd_adjust_points_nonce' ); ?>
                                <table class="form-table" role="presentation">
                                        <tr>
                                                <th scope="row"><label for="lgd_adjust_user"><?php esc_html_e( 'User', 'local-gamified-directory' ); ?></label></th>
                                                <td>
                                                        <?php
                                                        wp_dropdown_users(
                                                                array(
                                                                        'name'              => 'lgd_adjust_user',
                                                                        'show_option_none' => __( 'Select a user', 'local-gamified-directory' ),
                                                                        'class'            => 'regular-text',
                                                                )
                                                        );
                                                        ?>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="lgd_adjust_amount"><?php esc_html_e( 'Points', 'local-gamified-directory' ); ?></label></th>
                                                <td>
                                                        <input type="number" name="lgd_adjust_amount" id="lgd_adjust_amount" class="regular-text" required />
                                                        <p class="description"><?php esc_html_e( 'Use negative values to remove points.', 'local-gamified-directory' ); ?></p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="lgd_adjust_reason"><?php esc_html_e( 'Reason', 'local-gamified-directory' ); ?></label></th>
                                                <td><input type="text" name="lgd_adjust_reason" id="lgd_adjust_reason" class="regular-text" /></td>
                                        </tr>
                                </table>
                                <?php submit_button( __( 'Adjust Points', 'local-gamified-directory' ) ); ?>
                        </form>
                        <h2><?php esc_html_e( 'Recent Point Activity', 'local-gamified-directory' ); ?></h2>
                        <table class="widefat">
                                <thead>
                                        <tr>
                                                <th><?php esc_html_e( 'User', 'local-gamified-directory' ); ?></th>
                                                <th><?php esc_html_e( 'Change', 'local-gamified-directory' ); ?></th>
                                                <th><?php esc_html_e( 'Reason', 'local-gamified-directory' ); ?></th>
                                                <th><?php esc_html_e( 'Date', 'local-gamified-directory' ); ?></th>
                                        </tr>
                                </thead>
                                <tbody>
                                        <?php foreach ( $recent as $entry ) : ?>
                                                <tr>
                                                        <td><?php echo esc_html( $entry->display_name ); ?></td>
                                                        <td><?php echo esc_html( number_format_i18n( $entry->change_amount ) ); ?></td>
                                                        <td><?php echo esc_html( $entry->reason ); ?></td>
                                                        <td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry->created ) ) ); ?></td>
                                                </tr>
                                        <?php endforeach; ?>
                                </tbody>
                        </table>
                </div>
                <?php
        }

        /**
         * Award forum points while respecting the daily cap.
         *
         * @param int $user_id User ID.
         * @param int $amount  Points to add.
         */
        private function maybe_award_forum_points( $user_id, $amount ) {
                $today      = gmdate( 'Y-m-d' );
                $meta_key   = '_lgd_forum_points_' . $today;
                $current    = (int) get_user_meta( $user_id, $meta_key, true );
                $new_total  = $current + $amount;
                $cap_amount = min( $new_total, $this->forum_daily_cap );
                $award      = $cap_amount - $current;

                if ( $award <= 0 ) {
                        return;
                }

                update_user_meta( $user_id, $meta_key, $cap_amount );
                $this->add_points( $user_id, $award, 'forum_activity' );
        }

        /**
         * Add points to a user.
         *
         * @param int    $user_id User ID.
         * @param int    $amount  Amount to add.
         * @param string $reason  Reason code.
         * @param array  $context Optional context data.
         */
        public function add_points( $user_id, $amount, $reason = '', $context = array() ) {
                $amount = (int) $amount;

                if ( $amount <= 0 ) {
                        return;
                }

                $this->update_balance( $user_id, $amount );
                $this->log_change( $user_id, $amount, $reason, $context );
        }

        /**
         * Remove points from a user.
         *
         * @param int    $user_id User ID.
         * @param int    $amount  Amount to remove.
         * @param string $reason  Reason code.
         * @param array  $context Optional context data.
         */
        public function remove_points( $user_id, $amount, $reason = '', $context = array() ) {
                $amount = (int) $amount;

                if ( $amount <= 0 ) {
                        return;
                }

                $this->update_balance( $user_id, -1 * $amount );
                $this->log_change( $user_id, -1 * $amount, $reason, $context );
        }

        /**
         * Retrieve a user's current point balance.
         *
         * @param int $user_id User ID.
         *
         * @return int
         */
        public function get_user_points( $user_id ) {
                global $wpdb;

                $balance = $wpdb->get_var( $wpdb->prepare( "SELECT balance FROM {$this->points_table} WHERE user_id = %d", $user_id ) );

                return $balance ? (int) $balance : 0;
        }

        /**
         * Retrieve the current rank label for a user.
         *
         * @param int $user_id User ID.
         *
         * @return string Filterable rank label.
         */
        public function get_user_rank( $user_id ) {
                $points = $this->get_user_points( $user_id );

                foreach ( $this->rank_thresholds as $threshold => $label ) {
                        if ( $points >= $threshold ) {
                                $translated = __( $label, 'local-gamified-directory' );

                                return apply_filters( 'lgd_user_rank_label', $translated, $user_id, $points, $threshold, $label );
                        }
                }

                return apply_filters( 'lgd_user_rank_label', '', $user_id, $points, null, '' );
        }

        /**
         * Retrieve badges for a user.
         *
         * @param int $user_id User ID.
         *
         * @return array
         */
        public function get_user_badges( $user_id ) {
                $badges = array();

                $published_business = new WP_Query(
                        array(
                                'post_type'      => 'business_listing',
                                'posts_per_page' => 1,
                                'post_status'    => 'publish',
                                'meta_query'     => array(
                                        array(
                                                'key'   => Local_Gamified_Directory::META_OWNER_USER,
                                                'value' => $user_id,
                                        ),
                                ),
                                'fields'         => 'ids',
                        )
                );

                if ( ! empty( $published_business->posts ) ) {
                        $badges[] = __( 'Verified Business', 'local-gamified-directory' );
                }

                $points = $this->get_user_points( $user_id );
                if ( $points >= 1000 ) {
                        $badges[] = __( 'Power User', 'local-gamified-directory' );
                }

                return $badges;
        }

        /**
         * Render the leaderboard shortcode output.
         *
         * @param array $atts Shortcode attributes.
         *
         * @return string
         */
        public function render_leaderboard_shortcode( $atts ) {
                $atts = shortcode_atts(
                        array(
                                'type' => 'all_time',
                                'role' => 'all',
                                'limit' => 10,
                        ),
                        $atts,
                        'lgd_leaderboard'
                );

                $leaders = $this->get_leaderboard( $atts['type'], $atts['role'], (int) $atts['limit'] );

                if ( empty( $leaders ) ) {
                        return '<div class="lgd-notice lgd-notice--info">' . esc_html__( 'No leaderboard data available.', 'local-gamified-directory' ) . '</div>';
                }

                ob_start();
                ?>
                <div class="lgd-leaderboard">
                        <ol>
                                <?php foreach ( $leaders as $leader ) : ?>
                                        <li>
                                                <span class="lgd-leaderboard__name"><?php echo esc_html( $leader->display_name ); ?></span>
                                                <span class="lgd-leaderboard__points"><?php echo esc_html( number_format_i18n( $leader->points ) ); ?></span>
                                        </li>
                                <?php endforeach; ?>
                        </ol>
                </div>
                <?php
                return ob_get_clean();
        }

        /**
         * Retrieve leaderboard data.
         *
         * @param string $type  Leaderboard type.
         * @param string $role  Role filter.
         * @param int    $limit Result limit.
         *
         * @return array
         */
        public function get_leaderboard( $type = 'all_time', $role = 'all', $limit = 10 ) {
                global $wpdb;

                $limit = max( 1, $limit );
                $role  = sanitize_key( $role );
                $type  = sanitize_key( $type );

                $role_join  = '';
                $role_where = '';

                if ( 'all' !== $role ) {
                        $meta_key   = $wpdb->get_blog_prefix() . 'capabilities';
                        $role_join  = " INNER JOIN {$wpdb->usermeta} um ON um.user_id = u.ID AND um.meta_key = %s";
                        $role_where = " AND um.meta_value LIKE %s";
                }

                switch ( $type ) {
                        case 'daily':
                                $since = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
                                $query = "SELECT u.ID, u.display_name, SUM(l.change_amount) AS points
                                FROM {$this->log_table} l
                                INNER JOIN {$wpdb->users} u ON u.ID = l.user_id" . $role_join . "
                                WHERE l.created >= %s" . $role_where . "
                                GROUP BY u.ID
                                ORDER BY points DESC
                                LIMIT %d";
                                $prepared = $this->prepare_leaderboard_query( $query, $role_join, $role_where, $since, $role, $limit );
                                break;
                        case 'weekly':
                                $since   = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
                                $query   = "SELECT u.ID, u.display_name, SUM(l.change_amount) AS points
                                FROM {$this->log_table} l
                                INNER JOIN {$wpdb->users} u ON u.ID = l.user_id" . $role_join . "
                                WHERE l.created >= %s" . $role_where . "
                                GROUP BY u.ID
                                ORDER BY points DESC
                                LIMIT %d";
                                $prepared = $this->prepare_leaderboard_query( $query, $role_join, $role_where, $since, $role, $limit );
                                break;
                        case 'monthly':
                                $since   = gmdate( 'Y-m-d H:i:s', time() - MONTH_IN_SECONDS );
                                $query   = "SELECT u.ID, u.display_name, SUM(l.change_amount) AS points
                                FROM {$this->log_table} l
                                INNER JOIN {$wpdb->users} u ON u.ID = l.user_id" . $role_join . "
                                WHERE l.created >= %s" . $role_where . "
                                GROUP BY u.ID
                                ORDER BY points DESC
                                LIMIT %d";
                                $prepared = $this->prepare_leaderboard_query( $query, $role_join, $role_where, $since, $role, $limit );
                                break;
                        case 'all_time':
                        default:
                                $query = "SELECT u.ID, u.display_name, p.balance AS points
                                FROM {$this->points_table} p
                                INNER JOIN {$wpdb->users} u ON u.ID = p.user_id" . $role_join . "
                                WHERE 1=1" . $role_where . "
                                ORDER BY p.balance DESC
                                LIMIT %d";
                                $prepared = $this->prepare_leaderboard_query( $query, $role_join, $role_where, null, $role, $limit );
                                break;
                }

                return $wpdb->get_results( $prepared );
        }

        /**
         * Prepare a leaderboard query with appropriate bindings.
         *
         * @param string      $query      SQL query template.
         * @param string      $role_join  Role join segment.
         * @param string      $role_where Role where clause segment.
         * @param string|null $since      Date constraint.
         * @param string      $role       Role filter.
         * @param int         $limit      Result limit.
         *
         * @return string
         */
        private function prepare_leaderboard_query( $query, $role_join, $role_where, $since, $role, $limit ) {
                global $wpdb;

                $params = array();

                if ( $role_join ) {
                        $params[] = $wpdb->get_blog_prefix() . 'capabilities';
                }

                if ( null !== $since ) {
                        $params[] = $since;
                }

                if ( $role_where ) {
                        $params[] = '%' . $wpdb->esc_like( '"' . $role . '"' ) . '%';
                }

                $params[] = $limit;

                return $wpdb->prepare( $query, $params );
        }

        /**
         * Update the stored balance for a user.
         *
         * @param int $user_id User ID.
         * @param int $amount  Delta amount.
         */
        private function update_balance( $user_id, $amount ) {
                global $wpdb;

                $wpdb->query(
                        $wpdb->prepare(
                                "INSERT INTO {$this->points_table} ( user_id, balance, updated ) VALUES ( %d, %d, %s )
                                ON DUPLICATE KEY UPDATE balance = balance + VALUES(balance), updated = VALUES(updated)",
                                $user_id,
                                $amount,
                                gmdate( 'Y-m-d H:i:s' )
                        )
                );

                update_user_meta( $user_id, '_lgd_points_balance', $this->get_user_points( $user_id ) );
        }

        /**
         * Record a log entry.
         *
         * @param int    $user_id User ID.
         * @param int    $amount  Change amount.
         * @param string $reason  Reason code.
         * @param array  $context Context data.
         */
        private function log_change( $user_id, $amount, $reason = '', $context = array() ) {
                global $wpdb;

                $wpdb->insert(
                        $this->log_table,
                        array(
                                'user_id'       => $user_id,
                                'change_amount' => $amount,
                                'reason'        => $reason,
                                'context'       => ! empty( $context ) ? wp_json_encode( $context ) : '',
                                'created'       => gmdate( 'Y-m-d H:i:s' ),
                        ),
                        array( '%d', '%d', '%s', '%s', '%s' )
                );
        }

        /**
         * Retrieve recent log entries.
         *
         * @param int $limit Result limit.
         *
         * @return array
         */
        private function get_points_log( $limit = 20 ) {
                global $wpdb;

                $query = $wpdb->prepare(
                        "SELECT l.*, u.display_name FROM {$this->log_table} l INNER JOIN {$wpdb->users} u ON u.ID = l.user_id ORDER BY l.created DESC LIMIT %d",
                        $limit
                );

                return $wpdb->get_results( $query );
        }
}
