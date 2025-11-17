<?php
/**
 * Admin tools and settings for the Local Gamified Directory plugin.
 *
 * @package LocalGamifiedDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Provide settings screens, feature controls, and abuse management.
 */
class LGD_Admin {

        /**
         * Option key that stores feature toggles.
         */
        const OPTION_FEATURE_FLAGS = 'lgd_feature_flags';

        /**
         * Option key that stores allowed role mappings per feature.
         */
        const OPTION_FEATURE_ROLE_MAP = 'lgd_feature_role_map';

        /**
         * Option key for abuse thresholds.
         */
        const OPTION_ABUSE_THRESHOLD = 'lgd_abuse_threshold';

        /**
         * Option key for abuse window in minutes.
         */
        const OPTION_ABUSE_WINDOW = 'lgd_abuse_window_minutes';

        /**
         * Option key for activity log retention.
         */
        const OPTION_ACTIVITY_RETENTION = 'lgd_activity_retention_days';

        /**
         * Option key storing contextual help overrides.
         */
        const OPTION_HELP_TEXTS = 'lgd_help_texts';

        /**
         * Option key toggling contextual help interfaces.
         */
        const OPTION_HELP_ENABLED = 'lgd_help_enabled';

        /**
         * Option key storing the support message displayed beneath forms.
         */
        const OPTION_SUPPORT_MESSAGE = 'lgd_support_message';

        /**
         * Option key storing the support link URL.
         */
        const OPTION_SUPPORT_LINK = 'lgd_support_link';

        /**
         * Meta key storing disabled features for a user.
         */
        const META_DISABLED_FEATURES = '_lgd_disabled_features';

        /**
         * Meta key storing explicitly enabled features for a user.
         */
        const META_ENABLED_FEATURES = '_lgd_enabled_features';

        /**
         * Meta key storing the account status for a user.
         */
        const META_ACCOUNT_STATUS = '_lgd_account_status';

        /**
         * Meta key storing the suspension expiration date.
         */
        const META_SUSPENDED_UNTIL = '_lgd_suspended_until';

        /**
         * Meta key storing the suspension reason.
         */
        const META_SUSPENSION_REASON = '_lgd_suspension_reason';

        /**
         * Database table for sanctions.
         */
        const TABLE_SANCTIONS = 'lgd_user_sanctions';

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

                add_action( 'admin_menu', array( $this, 'register_menu' ) );
                add_action( 'admin_init', array( $this, 'register_settings' ) );
                add_action( 'show_user_profile', array( $this, 'render_user_fields' ) );
                add_action( 'edit_user_profile', array( $this, 'render_user_fields' ) );
                add_action( 'personal_options_update', array( $this, 'save_user_fields' ) );
                add_action( 'edit_user_profile_update', array( $this, 'save_user_fields' ) );
                add_filter( 'authenticate', array( $this, 'block_suspended_users' ), 50, 3 );
        }

        /**
         * Perform activation logic.
         */
        public static function activate() {
                self::maybe_add_default_options();
                self::create_sanctions_table();
        }

        /**
         * Add default options for feature flags and thresholds.
         */
        private static function maybe_add_default_options() {
                if ( false === get_option( self::OPTION_FEATURE_FLAGS, false ) ) {
                        $defaults = array();
                        foreach ( Local_Gamified_Directory::instance()->get_features() as $feature => $data ) {
                                $defaults[ $feature ] = true;
                        }
                        add_option( self::OPTION_FEATURE_FLAGS, $defaults );
                }

                if ( false === get_option( self::OPTION_FEATURE_ROLE_MAP, false ) ) {
                        add_option( self::OPTION_FEATURE_ROLE_MAP, array() );
                }

                if ( false === get_option( self::OPTION_ABUSE_THRESHOLD, false ) ) {
                        add_option( self::OPTION_ABUSE_THRESHOLD, 50 );
                }

                if ( false === get_option( self::OPTION_ABUSE_WINDOW, false ) ) {
                        add_option( self::OPTION_ABUSE_WINDOW, 60 );
                }

                if ( false === get_option( self::OPTION_ACTIVITY_RETENTION, false ) ) {
                        add_option( self::OPTION_ACTIVITY_RETENTION, 90 );
                }

                if ( false === get_option( self::OPTION_HELP_ENABLED, false ) ) {
                        add_option( self::OPTION_HELP_ENABLED, 1 );
                }

                if ( false === get_option( self::OPTION_HELP_TEXTS, false ) ) {
                        $defaults = array();
                        if ( class_exists( 'Local_Gamified_Directory' ) ) {
                                $defaults = Local_Gamified_Directory::instance()->get_default_help_texts();
                        }
                        add_option( self::OPTION_HELP_TEXTS, $defaults );
                }

                if ( false === get_option( self::OPTION_SUPPORT_MESSAGE, false ) ) {
                        add_option(
                                self::OPTION_SUPPORT_MESSAGE,
                                __( 'Need assistance? Hover over the question marks for quick guidance or reach out below.', 'local-gamified-directory' )
                        );
                }

                if ( false === get_option( self::OPTION_SUPPORT_LINK, false ) ) {
                        add_option( self::OPTION_SUPPORT_LINK, '' );
                }
        }

        /**
         * Create the sanctions database table.
         */
        private static function create_sanctions_table() {
                global $wpdb;

                $table_name      = $wpdb->prefix . self::TABLE_SANCTIONS;
                $charset_collate = $wpdb->get_charset_collate();

                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                $sql = "CREATE TABLE {$table_name} (
                        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        user_id bigint(20) unsigned NOT NULL,
                        type varchar(20) NOT NULL,
                        reason text NULL,
                        created_by bigint(20) unsigned NULL,
                        status varchar(20) NOT NULL DEFAULT 'open',
                        created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        expires_at datetime NULL,
                        PRIMARY KEY (id),
                        KEY user_id (user_id),
                        KEY type (type),
                        KEY status (status),
                        KEY created_at (created_at)
                ) {$charset_collate};";

                dbDelta( $sql );
        }

        /**
         * Register the primary plugin settings page.
         */
        public function register_menu() {
                add_menu_page(
                        __( 'Local Directory', 'local-gamified-directory' ),
                        __( 'Local Directory', 'local-gamified-directory' ),
                        'manage_options',
                        'lgd-admin',
                        array( $this, 'render_settings_page' ),
                        'dashicons-admin-multisite',
                        58
                );
        }

        /**
         * Register settings handled by the settings page.
         */
        public function register_settings() {
                register_setting( 'lgd_admin_general', self::OPTION_FEATURE_FLAGS, array( $this, 'sanitize_feature_flags' ) );
                register_setting( 'lgd_admin_general', self::OPTION_FEATURE_ROLE_MAP, array( $this, 'sanitize_feature_role_map' ) );
                register_setting( 'lgd_admin_general', self::OPTION_ABUSE_THRESHOLD, array( $this, 'sanitize_positive_int' ) );
                register_setting( 'lgd_admin_general', self::OPTION_ABUSE_WINDOW, array( $this, 'sanitize_positive_int' ) );
                register_setting( 'lgd_admin_general', self::OPTION_ACTIVITY_RETENTION, array( $this, 'sanitize_positive_int' ) );

                register_setting( 'lgd_admin_social', 'lgd_social_google_client_id', 'sanitize_text_field' );
                register_setting( 'lgd_admin_social', 'lgd_social_google_client_secret', 'sanitize_text_field' );
                register_setting( 'lgd_admin_social', 'lgd_social_facebook_app_id', 'sanitize_text_field' );
                register_setting( 'lgd_admin_social', 'lgd_social_facebook_app_secret', 'sanitize_text_field' );

                register_setting( 'lgd_admin_assistance', self::OPTION_HELP_ENABLED, array( $this, 'sanitize_checkbox' ) );
                register_setting( 'lgd_admin_assistance', self::OPTION_HELP_TEXTS, array( $this, 'sanitize_help_texts' ) );
                register_setting( 'lgd_admin_assistance', self::OPTION_SUPPORT_MESSAGE, array( $this, 'sanitize_support_message' ) );
                register_setting( 'lgd_admin_assistance', self::OPTION_SUPPORT_LINK, array( $this, 'sanitize_support_link' ) );
        }

        /**
         * Sanitize feature flag values.
         *
         * @param array $input Raw value.
         *
         * @return array
         */
        public function sanitize_feature_flags( $input ) {
                $input    = is_array( $input ) ? $input : array();
                $features = Local_Gamified_Directory::instance()->get_features();
                $clean    = array();

                foreach ( $features as $feature => $data ) {
                        $clean[ $feature ] = ! empty( $input[ $feature ] );
                }

                return $clean;
        }

        /**
         * Sanitize role mapping input.
         *
         * @param array $input Raw value.
         *
         * @return array
         */
        public function sanitize_feature_role_map( $input ) {
                if ( ! is_array( $input ) ) {
                        return array();
                }

                $sanitized = array();
                $features  = Local_Gamified_Directory::instance()->get_features();

                foreach ( $features as $feature => $data ) {
                        if ( empty( $input[ $feature ] ) ) {
                                continue;
                        }

                        $allowed = array();
                        foreach ( (array) $input[ $feature ] as $role ) {
                                $role = sanitize_key( $role );
                                if ( get_role( $role ) ) {
                                        $allowed[] = $role;
                                }
                        }

                        if ( ! empty( $allowed ) ) {
                                $sanitized[ $feature ] = array_values( array_unique( $allowed ) );
                        }
                }

                return $sanitized;
        }

        /**
         * Ensure numeric input is a positive integer.
         *
         * @param mixed $value Raw value.
         *
         * @return int
         */
        public function sanitize_positive_int( $value ) {
                $value = absint( $value );
                if ( $value <= 0 ) {
                        $value = 1;
                }

                return $value;
        }

        /**
         * Sanitize checkbox values to either 1 or 0.
         *
         * @param mixed $value Raw value.
         * @return int
         */
        public function sanitize_checkbox( $value ) {
                return ! empty( $value ) ? 1 : 0;
        }

        /**
         * Sanitize contextual help overrides.
         *
         * @param array $input Raw submitted values.
         * @return array
         */
        public function sanitize_help_texts( $input ) {
                $clean    = array();
                $contexts = $this->plugin->get_help_contexts();

                if ( ! is_array( $input ) ) {
                        $input = array();
                }

                foreach ( $contexts as $key => $context ) {
                        if ( empty( $input[ $key ] ) ) {
                                continue;
                        }

                        $value = wp_unslash( $input[ $key ] );
                        if ( ! is_string( $value ) ) {
                                continue;
                        }

                        $value = trim( $value );

                        if ( '' === $value ) {
                                continue;
                        }

                        $clean[ $key ] = wp_kses_post( $value );
                }

                return $clean;
        }

        /**
         * Sanitize the support message shown beneath forms.
         *
         * @param string $value Raw value.
         * @return string
         */
        public function sanitize_support_message( $value ) {
                if ( empty( $value ) ) {
                        return '';
                }

                $value = is_string( $value ) ? wp_unslash( $value ) : '';
                $value = trim( $value );

                if ( '' === $value ) {
                        return '';
                }

                return sanitize_textarea_field( $value );
        }

        /**
         * Sanitize the optional support link.
         *
         * @param string $value Raw value.
         * @return string
         */
        public function sanitize_support_link( $value ) {
                if ( empty( $value ) ) {
                        return '';
                }

                $value = is_string( $value ) ? wp_unslash( $value ) : '';
                $value = trim( $value );

                if ( '' === $value ) {
                        return '';
                }

                return esc_url_raw( $value );
        }

        /**
         * Render the admin settings interface with tabs.
         */
        public function render_settings_page() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                $active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
                $tabs       = array(
                        'general'     => __( 'Controls', 'local-gamified-directory' ),
                        'abuse'       => __( 'Abuse Management', 'local-gamified-directory' ),
                        'social'      => __( 'Social Login', 'local-gamified-directory' ),
                        'assistance'  => __( 'Guided Help', 'local-gamified-directory' ),
                );
                ?>
                <div class="wrap">
                        <h1><?php esc_html_e( 'Local Directory Administration', 'local-gamified-directory' ); ?></h1>
                        <h2 class="nav-tab-wrapper">
                                <?php foreach ( $tabs as $tab => $label ) : ?>
                                        <?php $active = $tab === $active_tab ? ' nav-tab-active' : ''; ?>
                                        <a class="nav-tab<?php echo esc_attr( $active ); ?>" href="<?php echo esc_url( add_query_arg( 'tab', $tab ) ); ?>"><?php echo esc_html( $label ); ?></a>
                                <?php endforeach; ?>
                        </h2>
                        <?php settings_errors( 'lgd-admin' ); ?>
                        <div class="lgd-admin-tab lgd-admin-tab--<?php echo esc_attr( $active_tab ); ?>">
                                <?php
                                switch ( $active_tab ) {
                                        case 'abuse':
                                                $this->render_abuse_tab();
                                                break;
                                        case 'social':
                                                $this->render_social_tab();
                                                break;
                                        case 'assistance':
                                                $this->render_assistance_tab();
                                                break;
                                        case 'general':
                                        default:
                                                $this->render_general_tab();
                                                break;
                                }
                                ?>
                        </div>
                </div>
                <?php
        }

        /**
         * Render the guided help tab.
         */
        private function render_assistance_tab() {
                $help_enabled    = (bool) get_option( self::OPTION_HELP_ENABLED, 1 );
                $stored_texts    = get_option( self::OPTION_HELP_TEXTS, array() );
                $support_message = get_option( self::OPTION_SUPPORT_MESSAGE, '' );
                $support_link    = get_option( self::OPTION_SUPPORT_LINK, '' );
                $defaults        = $this->plugin->get_default_help_texts();
                $contexts        = $this->plugin->get_help_contexts();

                if ( ! is_array( $stored_texts ) ) {
                        $stored_texts = array();
                }
                ?>
                <form method="post" action="options.php">
                        <?php settings_fields( 'lgd_admin_assistance' ); ?>
                        <table class="form-table" role="presentation">
                                <tbody>
                                        <tr>
                                                <th scope="row"><?php esc_html_e( 'Contextual help', 'local-gamified-directory' ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_HELP_ENABLED ); ?>" value="1" <?php checked( $help_enabled ); ?> />
                                                                <?php esc_html_e( 'Display inline question mark tooltips across public forms and dashboards.', 'local-gamified-directory' ); ?>
                                                        </label>
                                                </td>
                                        </tr>
                                        <?php foreach ( $contexts as $key => $context ) :
                                                $value       = isset( $stored_texts[ $key ] ) ? $stored_texts[ $key ] : '';
                                                $placeholder = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
                                        ?>
                                        <tr>
                                                <th scope="row"><label for="lgd_help_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $context['label'] ); ?></label></th>
                                                <td>
                                                        <textarea id="lgd_help_<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( self::OPTION_HELP_TEXTS ); ?>[<?php echo esc_attr( $key ); ?>]" rows="3" class="large-text" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
                                                        <p class="description"><?php esc_html_e( 'Leave blank to use the default guidance.', 'local-gamified-directory' ); ?><?php if ( ! empty( $context['description'] ) ) : ?> <?php echo esc_html( $context['description'] ); ?><?php endif; ?></p>
                                                </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <tr>
                                                <th scope="row"><label for="lgd_support_message"><?php esc_html_e( 'Support message', 'local-gamified-directory' ); ?></label></th>
                                                <td>
                                                        <textarea id="lgd_support_message" name="<?php echo esc_attr( self::OPTION_SUPPORT_MESSAGE ); ?>" rows="3" class="large-text"><?php echo esc_textarea( $support_message ); ?></textarea>
                                                        <p class="description"><?php esc_html_e( 'Shown beneath forms and dashboards to guide users toward self-service help.', 'local-gamified-directory' ); ?></p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="lgd_support_link"><?php esc_html_e( 'Support link', 'local-gamified-directory' ); ?></label></th>
                                                <td>
                                                        <input type="url" id="lgd_support_link" name="<?php echo esc_attr( self::OPTION_SUPPORT_LINK ); ?>" class="regular-text" value="<?php echo esc_attr( $support_link ); ?>" placeholder="https://" />
                                                        <p class="description"><?php esc_html_e( 'Optional URL that points to your help center or knowledge base.', 'local-gamified-directory' ); ?></p>
                                                </td>
                                        </tr>
                                </tbody>
                        </table>
                        <?php submit_button(); ?>
                </form>
                <?php
        }

        /**
         * Render the general controls tab.
         */
        private function render_general_tab() {
                $features    = $this->plugin->get_features();
                $flags       = get_option( self::OPTION_FEATURE_FLAGS, array() );
                $role_map    = get_option( self::OPTION_FEATURE_ROLE_MAP, array() );
                $roles       = wp_roles()->roles;
                $abuse_limit = (int) get_option( self::OPTION_ABUSE_THRESHOLD, 50 );
                $abuse_time  = (int) get_option( self::OPTION_ABUSE_WINDOW, 60 );
                $retention   = (int) get_option( self::OPTION_ACTIVITY_RETENTION, 90 );
                ?>
                <form method="post" action="options.php">
                        <?php settings_fields( 'lgd_admin_general' ); ?>
                        <table class="form-table" role="presentation">
                                <tbody>
                                <?php foreach ( $features as $feature => $data ) :
                                        $enabled = ! empty( $flags[ $feature ] );
                                        $allowed_roles = isset( $role_map[ $feature ] ) ? (array) $role_map[ $feature ] : array();
                                        ?>
                                        <tr>
                                                <th scope="row"><?php echo esc_html( $data['label'] ); ?></th>
                                                <td>
                                                        <label>
                                                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_FEATURE_FLAGS ); ?>[<?php echo esc_attr( $feature ); ?>]" value="1" <?php checked( $enabled ); ?> />
                                                                <?php echo esc_html( $data['description'] ); ?>
                                                        </label>
                                                        <p class="description"><?php esc_html_e( 'Allowed roles', 'local-gamified-directory' ); ?>:</p>
                                                        <fieldset>
                                                                <?php foreach ( $roles as $role_key => $role_data ) : ?>
                                                                        <label style="display:inline-block;margin-right:1em;">
                                                                                <input type="checkbox" name="<?php echo esc_attr( self::OPTION_FEATURE_ROLE_MAP ); ?>[<?php echo esc_attr( $feature ); ?>][]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $allowed_roles, true ) ); ?> />
                                                                                <?php echo esc_html( translate_user_role( $role_data['name'] ) ); ?>
                                                                        </label>
                                                                <?php endforeach; ?>
                                                        </fieldset>
                                                </td>
                                        </tr>
                                <?php endforeach; ?>
                                        <tr>
                                                <th scope="row"><label for="lgd_abuse_threshold"><?php esc_html_e( 'Abuse threshold', 'local-gamified-directory' ); ?></label></th>
                                                <td>
                                                        <input type="number" id="lgd_abuse_threshold" name="<?php echo esc_attr( self::OPTION_ABUSE_THRESHOLD ); ?>" value="<?php echo esc_attr( $abuse_limit ); ?>" min="1" />
                                                        <p class="description"><?php esc_html_e( 'Number of identical events allowed within the window before a warning is issued.', 'local-gamified-directory' ); ?></p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="lgd_abuse_window_minutes"><?php esc_html_e( 'Abuse window (minutes)', 'local-gamified-directory' ); ?></label></th>
                                                <td>
                                                        <input type="number" id="lgd_abuse_window_minutes" name="<?php echo esc_attr( self::OPTION_ABUSE_WINDOW ); ?>" value="<?php echo esc_attr( $abuse_time ); ?>" min="1" />
                                                        <p class="description"><?php esc_html_e( 'Timeframe used when evaluating activity bursts.', 'local-gamified-directory' ); ?></p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="lgd_activity_retention_days"><?php esc_html_e( 'Activity retention (days)', 'local-gamified-directory' ); ?></label></th>
                                                <td>
                                                        <input type="number" id="lgd_activity_retention_days" name="<?php echo esc_attr( self::OPTION_ACTIVITY_RETENTION ); ?>" value="<?php echo esc_attr( $retention ); ?>" min="7" />
                                                        <p class="description"><?php esc_html_e( 'Older activity entries are purged automatically after this many days.', 'local-gamified-directory' ); ?></p>
                                                </td>
                                        </tr>
                                </tbody>
                        </table>
                        <?php submit_button(); ?>
                </form>
                <?php
        }

        /**
         * Render the abuse management tab.
         */
        private function render_abuse_tab() {
                $recent_sanctions = $this->get_recent_sanctions();
                ?>
                <h2><?php esc_html_e( 'Manual Sanctions', 'local-gamified-directory' ); ?></h2>
                <form method="post">
                        <?php wp_nonce_field( 'lgd_issue_sanction', 'lgd_issue_sanction_nonce' ); ?>
                        <table class="form-table" role="presentation">
                                <tr>
                                        <th scope="row"><label for="lgd_sanction_user"><?php esc_html_e( 'User', 'local-gamified-directory' ); ?></label></th>
                                        <td>
                                                <?php
                                                wp_dropdown_users(
                                                        array(
                                                                'name'              => 'lgd_sanction_user',
                                                                'id'                => 'lgd_sanction_user',
                                                                'class'             => 'regular-text',
                                                                'show_option_none'  => __( 'Select a user', 'local-gamified-directory' ),
                                                                'option_none_value' => '',
                                                                'capability'        => array(),
                                                        )
                                                );
                                                ?>
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row"><?php esc_html_e( 'Action', 'local-gamified-directory' ); ?></th>
                                        <td>
                                                <label><input type="radio" name="lgd_sanction_type" value="warning" checked /> <?php esc_html_e( 'Warning', 'local-gamified-directory' ); ?></label>
                                                <label style="margin-left:1em;"><input type="radio" name="lgd_sanction_type" value="suspension" /> <?php esc_html_e( 'Suspend', 'local-gamified-directory' ); ?></label>
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row"><label for="lgd_sanction_reason"><?php esc_html_e( 'Reason', 'local-gamified-directory' ); ?></label></th>
                                        <td>
                                                <textarea id="lgd_sanction_reason" name="lgd_sanction_reason" rows="4" class="large-text"></textarea>
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row"><label for="lgd_sanction_duration"><?php esc_html_e( 'Suspension length (days)', 'local-gamified-directory' ); ?></label></th>
                                        <td>
                                                <input type="number" id="lgd_sanction_duration" name="lgd_sanction_duration" value="7" min="1" />
                                                <p class="description"><?php esc_html_e( 'Used only when suspending a user.', 'local-gamified-directory' ); ?></p>
                                        </td>
                                </tr>
                        </table>
                        <?php submit_button( __( 'Apply sanction', 'local-gamified-directory' ) ); ?>
                </form>
                <?php
                $this->maybe_handle_manual_sanction();
                settings_errors( 'lgd-admin' );
                ?>

                <h2><?php esc_html_e( 'Recent sanctions', 'local-gamified-directory' ); ?></h2>
                <table class="widefat striped">
                        <thead>
                                <tr>
                                        <th><?php esc_html_e( 'User', 'local-gamified-directory' ); ?></th>
                                        <th><?php esc_html_e( 'Type', 'local-gamified-directory' ); ?></th>
                                        <th><?php esc_html_e( 'Reason', 'local-gamified-directory' ); ?></th>
                                        <th><?php esc_html_e( 'Status', 'local-gamified-directory' ); ?></th>
                                        <th><?php esc_html_e( 'Created', 'local-gamified-directory' ); ?></th>
                                </tr>
                        </thead>
                        <tbody>
                                <?php if ( empty( $recent_sanctions ) ) : ?>
                                        <tr><td colspan="5"><?php esc_html_e( 'No sanctions recorded yet.', 'local-gamified-directory' ); ?></td></tr>
                                <?php else : ?>
                                        <?php foreach ( $recent_sanctions as $sanction ) :
                                                $user = get_userdata( $sanction->user_id );
                                                ?>
                                                <tr>
                                                        <td><?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Unknown user', 'local-gamified-directory' ); ?></td>
                                                        <td><?php echo esc_html( ucfirst( $sanction->type ) ); ?></td>
                                                        <td><?php echo esc_html( $sanction->reason ); ?></td>
                                                        <td><?php echo esc_html( ucfirst( $sanction->status ) ); ?></td>
                                                        <td><?php echo esc_html( get_date_from_gmt( $sanction->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
                                                </tr>
                                        <?php endforeach; ?>
                                <?php endif; ?>
                        </tbody>
                </table>
                <?php
                $activity = $this->plugin->get_activity();
                if ( $activity ) {
                        $events = $activity->get_recent_events( 20 );
                        ?>
                        <h2><?php esc_html_e( 'Recent activity events', 'local-gamified-directory' ); ?></h2>
                        <table class="widefat striped">
                                <thead>
                                        <tr>
                                                <th><?php esc_html_e( 'User', 'local-gamified-directory' ); ?></th>
                                                <th><?php esc_html_e( 'Event', 'local-gamified-directory' ); ?></th>
                                                <th><?php esc_html_e( 'Object', 'local-gamified-directory' ); ?></th>
                                                <th><?php esc_html_e( 'Recorded', 'local-gamified-directory' ); ?></th>
                                        </tr>
                                </thead>
                                <tbody>
                                        <?php if ( empty( $events ) ) : ?>
                                                <tr><td colspan="4"><?php esc_html_e( 'No activity has been recorded yet.', 'local-gamified-directory' ); ?></td></tr>
                                        <?php else : ?>
                                                <?php foreach ( $events as $event ) :
                                                        $user = $event->user_id ? get_userdata( $event->user_id ) : null;
                                                        ?>
                                                        <tr>
                                                                <td><?php echo $user ? esc_html( $user->display_name ) : esc_html__( 'Guest', 'local-gamified-directory' ); ?></td>
                                                                <td><?php echo esc_html( $event->event ); ?></td>
                                                                <td><?php echo esc_html( $event->object_type ); ?><?php echo $event->object_id ? ' #' . absint( $event->object_id ) : ''; ?></td>
                                                                <td><?php echo esc_html( get_date_from_gmt( $event->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
                                                        </tr>
                                                <?php endforeach; ?>
                                        <?php endif; ?>
                                </tbody>
                        </table>
                        <?php
                }
        }

        /**
         * Render the social login settings tab.
         */
        private function render_social_tab() {
                ?>
                <form method="post" action="options.php">
                        <?php settings_fields( 'lgd_admin_social' ); ?>
                        <table class="form-table" role="presentation">
                                <tr>
                                        <th scope="row"><label for="lgd_social_google_client_id"><?php esc_html_e( 'Google Client ID', 'local-gamified-directory' ); ?></label></th>
                                        <td>
                                                <input type="text" id="lgd_social_google_client_id" name="lgd_social_google_client_id" class="regular-text" value="<?php echo esc_attr( get_option( 'lgd_social_google_client_id', '' ) ); ?>" />
                                                <p class="description"><?php esc_html_e( 'Enter the OAuth 2.0 client ID from your Google Cloud Console.', 'local-gamified-directory' ); ?></p>
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row"><label for="lgd_social_google_client_secret"><?php esc_html_e( 'Google Client Secret', 'local-gamified-directory' ); ?></label></th>
                                        <td>
                                                <input type="text" id="lgd_social_google_client_secret" name="lgd_social_google_client_secret" class="regular-text" value="<?php echo esc_attr( get_option( 'lgd_social_google_client_secret', '' ) ); ?>" />
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row"><label for="lgd_social_facebook_app_id"><?php esc_html_e( 'Facebook App ID', 'local-gamified-directory' ); ?></label></th>
                                        <td>
                                                <input type="text" id="lgd_social_facebook_app_id" name="lgd_social_facebook_app_id" class="regular-text" value="<?php echo esc_attr( get_option( 'lgd_social_facebook_app_id', '' ) ); ?>" />
                                                <p class="description"><?php esc_html_e( 'Enter the App ID from your Facebook developer account.', 'local-gamified-directory' ); ?></p>
                                        </td>
                                </tr>
                                <tr>
                                        <th scope="row"><label for="lgd_social_facebook_app_secret"><?php esc_html_e( 'Facebook App Secret', 'local-gamified-directory' ); ?></label></th>
                                        <td>
                                                <input type="text" id="lgd_social_facebook_app_secret" name="lgd_social_facebook_app_secret" class="regular-text" value="<?php echo esc_attr( get_option( 'lgd_social_facebook_app_secret', '' ) ); ?>" />
                                        </td>
                                </tr>
                        </table>
                        <?php submit_button(); ?>
                </form>
                <p><?php esc_html_e( 'Ensure the redirect URI is set to your site URL with ?lgd_social=provider&lgd_action=callback appended.', 'local-gamified-directory' ); ?></p>
                <?php
        }

        /**
         * Block suspended users during authentication.
         *
         * @param null|WP_User|WP_Error $user  Existing user.
         * @param string                $username Username.
         * @param string                $password Password.
         *
         * @return WP_User|WP_Error
         */
        public function block_suspended_users( $user, $username, $password ) {
                if ( $user instanceof WP_User ) {
                        if ( $this->plugin->is_user_suspended( $user->ID ) ) {
                                return new WP_Error( 'lgd_account_suspended', __( '<strong>Error:</strong> Your account is currently suspended. Please contact support.', 'local-gamified-directory' ) );
                        }
                }

                return $user;
        }

        /**
         * Render additional fields on the user profile screen.
         *
         * @param WP_User $user Current user object.
         */
        public function render_user_fields( WP_User $user ) {
                if ( ! current_user_can( 'promote_users' ) ) {
                        return;
                }

                $features          = $this->plugin->get_features();
                $disabled          = (array) get_user_meta( $user->ID, self::META_DISABLED_FEATURES, true );
                $enabled           = (array) get_user_meta( $user->ID, self::META_ENABLED_FEATURES, true );
                $status            = get_user_meta( $user->ID, self::META_ACCOUNT_STATUS, true );
                $suspended_until   = get_user_meta( $user->ID, self::META_SUSPENDED_UNTIL, true );
                $suspension_reason = get_user_meta( $user->ID, self::META_SUSPENSION_REASON, true );
                ?>
                <h2><?php esc_html_e( 'Local Directory Controls', 'local-gamified-directory' ); ?></h2>
                <table class="form-table" role="presentation">
                        <tr>
                                <th><label for="lgd_account_status"><?php esc_html_e( 'Account status', 'local-gamified-directory' ); ?></label></th>
                                <td>
                                        <select name="lgd_account_status" id="lgd_account_status">
                                                <option value="active" <?php selected( 'suspended' !== $status ); ?>><?php esc_html_e( 'Active', 'local-gamified-directory' ); ?></option>
                                                <option value="suspended" <?php selected( 'suspended', $status ); ?>><?php esc_html_e( 'Suspended', 'local-gamified-directory' ); ?></option>
                                        </select>
                                </td>
                        </tr>
                        <tr>
                                <th><label for="lgd_suspended_until"><?php esc_html_e( 'Suspended until', 'local-gamified-directory' ); ?></label></th>
                                <td>
                                        <input type="date" name="lgd_suspended_until" id="lgd_suspended_until" value="<?php echo esc_attr( $suspended_until ); ?>" />
                                        <p class="description"><?php esc_html_e( 'Leave blank for an indefinite suspension.', 'local-gamified-directory' ); ?></p>
                                </td>
                        </tr>
                        <tr>
                                <th><label for="lgd_suspension_reason"><?php esc_html_e( 'Suspension reason', 'local-gamified-directory' ); ?></label></th>
                                <td>
                                        <textarea name="lgd_suspension_reason" id="lgd_suspension_reason" rows="3" class="regular-text"><?php echo esc_textarea( $suspension_reason ); ?></textarea>
                                </td>
                        </tr>
                        <tr>
                                <th><?php esc_html_e( 'Disabled features', 'local-gamified-directory' ); ?></th>
                                <td>
                                        <?php foreach ( $features as $feature => $data ) : ?>
                                                <label style="display:block;">
                                                        <input type="checkbox" name="lgd_disabled_features[]" value="<?php echo esc_attr( $feature ); ?>" <?php checked( in_array( $feature, $disabled, true ) ); ?> />
                                                        <?php echo esc_html( $data['label'] ); ?>
                                                </label>
                                        <?php endforeach; ?>
                                </td>
                        </tr>
                        <tr>
                                <th><?php esc_html_e( 'Explicitly enabled features', 'local-gamified-directory' ); ?></th>
                                <td>
                                        <?php foreach ( $features as $feature => $data ) : ?>
                                                <label style="display:block;">
                                                        <input type="checkbox" name="lgd_enabled_features[]" value="<?php echo esc_attr( $feature ); ?>" <?php checked( in_array( $feature, $enabled, true ) ); ?> />
                                                        <?php echo esc_html( $data['label'] ); ?>
                                                </label>
                                        <?php endforeach; ?>
                                </td>
                        </tr>
                </table>
                <?php
        }

        /**
         * Save the additional user profile fields.
         *
         * @param int $user_id User ID.
         */
        public function save_user_fields( $user_id ) {
                if ( ! current_user_can( 'promote_users' ) ) {
                        return;
                }

                check_admin_referer( 'update-user_' . $user_id );

                $status          = isset( $_POST['lgd_account_status'] ) ? sanitize_key( wp_unslash( $_POST['lgd_account_status'] ) ) : 'active';
                $suspended_until = isset( $_POST['lgd_suspended_until'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_suspended_until'] ) ) : '';
                $reason          = isset( $_POST['lgd_suspension_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lgd_suspension_reason'] ) ) : '';
                $disabled        = isset( $_POST['lgd_disabled_features'] ) ? array_map( 'sanitize_key', (array) $_POST['lgd_disabled_features'] ) : array();
                $enabled         = isset( $_POST['lgd_enabled_features'] ) ? array_map( 'sanitize_key', (array) $_POST['lgd_enabled_features'] ) : array();

                if ( 'suspended' === $status ) {
                        update_user_meta( $user_id, self::META_ACCOUNT_STATUS, 'suspended' );
                        update_user_meta( $user_id, self::META_SUSPENDED_UNTIL, $suspended_until );
                        update_user_meta( $user_id, self::META_SUSPENSION_REASON, $reason );
                } else {
                        delete_user_meta( $user_id, self::META_ACCOUNT_STATUS );
                        delete_user_meta( $user_id, self::META_SUSPENDED_UNTIL );
                        delete_user_meta( $user_id, self::META_SUSPENSION_REASON );
                }

                update_user_meta( $user_id, self::META_DISABLED_FEATURES, array_values( array_unique( $disabled ) ) );
                update_user_meta( $user_id, self::META_ENABLED_FEATURES, array_values( array_unique( $enabled ) ) );
        }

        /**
         * Retrieve recent sanctions for display.
         *
         * @return array
         */
        private function get_recent_sanctions() {
                global $wpdb;

                $table = $wpdb->prefix . self::TABLE_SANCTIONS;

                return $wpdb->get_results( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 25" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.PreparedSQL.NotPrepared
        }

        /**
         * Handle manual sanctions submitted via the admin form.
         */
        private function maybe_handle_manual_sanction() {
                if ( empty( $_POST['lgd_issue_sanction_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lgd_issue_sanction_nonce'] ) ), 'lgd_issue_sanction' ) ) {
                        return;
                }

                if ( ! current_user_can( 'promote_users' ) ) {
                        return;
                }

                $user_id  = isset( $_POST['lgd_sanction_user'] ) ? absint( $_POST['lgd_sanction_user'] ) : 0;
                $type     = isset( $_POST['lgd_sanction_type'] ) ? sanitize_key( wp_unslash( $_POST['lgd_sanction_type'] ) ) : 'warning';
                $reason   = isset( $_POST['lgd_sanction_reason'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lgd_sanction_reason'] ) ) : '';
                $duration = isset( $_POST['lgd_sanction_duration'] ) ? absint( $_POST['lgd_sanction_duration'] ) : 7;

                if ( ! $user_id ) {
                        add_settings_error( 'lgd-admin', 'lgd-missing-user', __( 'Please select a user.', 'local-gamified-directory' ) );
                        return;
                }

                if ( 'suspension' === $type ) {
                        $this->suspend_user( $user_id, $reason, $duration );
                        add_settings_error( 'lgd-admin', 'lgd-suspension-added', __( 'The user has been suspended.', 'local-gamified-directory' ), 'updated' );
                } else {
                        $this->issue_warning( $user_id, $reason );
                        add_settings_error( 'lgd-admin', 'lgd-warning-added', __( 'Warning recorded.', 'local-gamified-directory' ), 'updated' );
                }
        }

        /**
         * Issue a warning to a user.
         *
         * @param int    $user_id   Target user ID.
         * @param string $reason    Warning reason.
         * @param bool   $automated Whether the warning originated from the abuse monitor.
         */
        public function issue_warning( $user_id, $reason = '', $automated = false ) {
                global $wpdb;

                $table = $wpdb->prefix . self::TABLE_SANCTIONS;

                $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $table,
                        array(
                                'user_id'    => $user_id,
                                'type'       => 'warning',
                                'reason'     => $reason,
                                'created_by' => get_current_user_id(),
                                'status'     => 'open',
                                'created_at' => current_time( 'mysql', true ),
                        ),
                        array( '%d', '%s', '%s', '%d', '%s', '%s' )
                );

                $user = get_userdata( $user_id );
                if ( $user && ! empty( $user->user_email ) ) {
                        $subject = __( 'Directory warning issued', 'local-gamified-directory' );
                        $message = sprintf(
                                /* translators: 1: User display name, 2: Reason text. */
                                __( "Hello %1\$s,\n\nA moderator has recorded a warning on your account.%2\$s\n\nPlease ensure that you follow the community guidelines.", 'local-gamified-directory' ),
                                $user->display_name,
                                $reason ? sprintf( __( '\n\nReason: %s', 'local-gamified-directory' ), $reason ) : ''
                        );
                        $this->plugin->send_email( $user->user_email, $subject, $message );
                }

                $admin_email = get_option( 'admin_email' );
                if ( $admin_email ) {
                        $subject = __( 'Directory user warning recorded', 'local-gamified-directory' );
                        $message = sprintf(
                                /* translators: 1: User display name, 2: Reason text. */
                                __( 'A warning has been recorded for %1$s.%2$s', 'local-gamified-directory' ),
                                $user ? $user->display_name : __( 'User ID', 'local-gamified-directory' ) . ' ' . $user_id,
                                $reason ? ' ' . sprintf( __( 'Reason: %s', 'local-gamified-directory' ), $reason ) : ''
                        );
                        if ( $automated ) {
                                $subject = __( 'Automated abuse warning triggered', 'local-gamified-directory' );
                        }
                        $this->plugin->send_email( $admin_email, $subject, $message );
                }
        }

        /**
         * Suspend a user for a given number of days.
         *
         * @param int    $user_id  User ID.
         * @param string $reason   Reason for suspension.
         * @param int    $duration Number of days.
         */
        public function suspend_user( $user_id, $reason = '', $duration = 7 ) {
                $until = gmdate( 'Y-m-d', time() + ( absint( $duration ) * DAY_IN_SECONDS ) );

                update_user_meta( $user_id, self::META_ACCOUNT_STATUS, 'suspended' );
                update_user_meta( $user_id, self::META_SUSPENDED_UNTIL, $until );
                update_user_meta( $user_id, self::META_SUSPENSION_REASON, $reason );

                global $wpdb;

                $table = $wpdb->prefix . self::TABLE_SANCTIONS;

                $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
                        $table,
                        array(
                                'user_id'    => $user_id,
                                'type'       => 'suspension',
                                'reason'     => $reason,
                                'created_by' => get_current_user_id(),
                                'status'     => 'open',
                                'created_at' => current_time( 'mysql', true ),
                                'expires_at' => $until ? $until . ' 23:59:59' : null,
                        ),
                        array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
                );

                $user = get_userdata( $user_id );
                if ( $user && ! empty( $user->user_email ) ) {
                        $subject = __( 'Directory account suspended', 'local-gamified-directory' );
                        $message = sprintf(
                                /* translators: 1: User display name, 2: Date, 3: Reason text. */
                                __( "Hello %1\$s,\n\nYour account has been suspended until %2\$s.%3\$s\n\nContact the site administrator if you believe this is in error.", 'local-gamified-directory' ),
                                $user->display_name,
                                $until,
                                $reason ? sprintf( __( '\n\nReason: %s', 'local-gamified-directory' ), $reason ) : ''
                        );
                        $this->plugin->send_email( $user->user_email, $subject, $message );
                }

                $admin_email = get_option( 'admin_email' );
                if ( $admin_email ) {
                        $subject = __( 'Directory user suspended', 'local-gamified-directory' );
                        $message = sprintf(
                                /* translators: 1: User display name, 2: Reason text. */
                                __( '%1$s has been suspended.%2$s', 'local-gamified-directory' ),
                                $user ? $user->display_name : __( 'User ID', 'local-gamified-directory' ) . ' ' . $user_id,
                                $reason ? ' ' . sprintf( __( 'Reason: %s', 'local-gamified-directory' ), $reason ) : ''
                        );
                        $this->plugin->send_email( $admin_email, $subject, $message );
                }
        }
}
