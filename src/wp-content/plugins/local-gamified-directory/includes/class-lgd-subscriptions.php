<?php
/**
 * Subscription management for the Local Gamified Directory plugin.
 *
 * @package LocalGamifiedDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Handle PayPal subscriptions and premium perks.
 */
class LGD_Subscriptions {

        /**
         * Plugin instance.
         *
         * @var Local_Gamified_Directory
         */
        private $plugin;

        /**
         * Constructor.
         *
         * @param Local_Gamified_Directory $plugin Main plugin instance.
         */
        public function __construct( Local_Gamified_Directory $plugin ) {
                $this->plugin = $plugin;

                add_action( 'admin_menu', array( $this, 'register_settings_page' ) );
                add_action( 'admin_init', array( $this, 'register_settings' ) );
                add_action( 'init', array( $this, 'listen_for_ipn' ) );
                add_shortcode( 'lgd_subscription_button', array( $this, 'render_subscription_button' ) );
        }

        /**
         * Set default options when activating the plugin.
         */
        public static function activate() {
                add_option( 'lgd_paypal_mode', 'sandbox' );
                add_option( 'lgd_paypal_button_id', '' );
                add_option( 'lgd_premium_token_allowance', 100 );
        }

        /**
         * Register the subscriptions settings page.
         */
        public function register_settings_page() {
                add_options_page(
                        __( 'Local Directory Subscriptions', 'local-gamified-directory' ),
                        __( 'Directory Subscriptions', 'local-gamified-directory' ),
                        'manage_options',
                        'lgd-subscriptions',
                        array( $this, 'render_settings_page' )
                );
        }

        /**
         * Register settings for PayPal integration.
         */
        public function register_settings() {
                register_setting( 'lgd_subscriptions', 'lgd_paypal_mode' );
                register_setting( 'lgd_subscriptions', 'lgd_paypal_button_id' );
                register_setting( 'lgd_subscriptions', 'lgd_premium_token_allowance' );
        }

        /**
         * Render the subscription settings page.
         */
        public function render_settings_page() {
                if ( ! current_user_can( 'manage_options' ) ) {
                        return;
                }

                ?>
                <div class="wrap">
                        <h1><?php esc_html_e( 'Directory Subscription Settings', 'local-gamified-directory' ); ?></h1>
                        <form method="post" action="options.php">
                                <?php settings_fields( 'lgd_subscriptions' ); ?>
                                <table class="form-table" role="presentation">
                                        <tr>
                                                <th scope="row"><label for="lgd_paypal_mode"><?php esc_html_e( 'PayPal Mode', 'local-gamified-directory' ); ?></label></th>
                                                <td>
                                                        <select name="lgd_paypal_mode" id="lgd_paypal_mode">
                                                                <?php $mode = get_option( 'lgd_paypal_mode', 'sandbox' ); ?>
                                                                <option value="sandbox" <?php selected( $mode, 'sandbox' ); ?>><?php esc_html_e( 'Sandbox', 'local-gamified-directory' ); ?></option>
                                                                <option value="live" <?php selected( $mode, 'live' ); ?>><?php esc_html_e( 'Live', 'local-gamified-directory' ); ?></option>
                                                        </select>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="lgd_paypal_button_id"><?php esc_html_e( 'PayPal Hosted Button ID', 'local-gamified-directory' ); ?></label></th>
                                                <td>
                                                        <input type="text" name="lgd_paypal_button_id" id="lgd_paypal_button_id" class="regular-text" value="<?php echo esc_attr( get_option( 'lgd_paypal_button_id', '' ) ); ?>" />
                                                        <p class="description"><?php esc_html_e( 'Create a PayPal subscription button and enter the hosted button ID here.', 'local-gamified-directory' ); ?></p>
                                                </td>
                                        </tr>
                                        <tr>
                                                <th scope="row"><label for="lgd_premium_token_allowance"><?php esc_html_e( 'Monthly Token Allowance', 'local-gamified-directory' ); ?></label></th>
                                                <td>
                                                        <input type="number" name="lgd_premium_token_allowance" id="lgd_premium_token_allowance" class="regular-text" value="<?php echo esc_attr( get_option( 'lgd_premium_token_allowance', 100 ) ); ?>" min="0" />
                                                </td>
                                        </tr>
                                </table>
                                <?php submit_button(); ?>
                        </form>
                </div>
                <?php
        }

        /**
         * Render a PayPal subscription button for the front end.
         *
         * @return string
         */
        public function render_subscription_button() {
                if ( ! is_user_logged_in() ) {
                        return sprintf( '<div class="lgd-notice lgd-notice--warning">%s</div>', esc_html__( 'Log in to upgrade your listing.', 'local-gamified-directory' ) );
                }

                $button_id = get_option( 'lgd_paypal_button_id', '' );

                if ( empty( $button_id ) ) {
                        return '<div class="lgd-notice lgd-notice--info">' . esc_html__( 'Subscription payments are not yet configured.', 'local-gamified-directory' ) . '</div>';
                }

                $mode        = get_option( 'lgd_paypal_mode', 'sandbox' );
                $endpoint    = 'sandbox' === $mode ? 'https://www.sandbox.paypal.com/cgi-bin/webscr' : 'https://www.paypal.com/cgi-bin/webscr';
                $user_id     = get_current_user_id();
                $notify_url  = add_query_arg( 'lgd_paypal_listener', '1', home_url( '/' ) );
                $return_url  = wp_get_referer() ? wp_get_referer() : home_url( '/' );

                ob_start();
                ?>
                <form action="<?php echo esc_url( $endpoint ); ?>" method="post">
                        <input type="hidden" name="cmd" value="_s-xclick" />
                        <input type="hidden" name="hosted_button_id" value="<?php echo esc_attr( $button_id ); ?>" />
                        <input type="hidden" name="custom" value="<?php echo esc_attr( $user_id ); ?>" />
                        <input type="hidden" name="notify_url" value="<?php echo esc_url( $notify_url ); ?>" />
                        <input type="hidden" name="return" value="<?php echo esc_url( $return_url ); ?>" />
                        <button type="submit" class="lgd-button lgd-button--primary"><?php esc_html_e( 'Upgrade to Premium', 'local-gamified-directory' ); ?></button>
                </form>
                <?php
                return ob_get_clean();
        }

        /**
         * Listen for PayPal IPN callbacks.
         */
        public function listen_for_ipn() {
                if ( ! isset( $_GET['lgd_paypal_listener'] ) ) {
                        return;
                }

                $raw_post = file_get_contents( 'php://input' );

                if ( empty( $raw_post ) ) {
                        status_header( 400 );
                        exit;
                }

                $response = $this->verify_ipn( $raw_post );

                if ( 'VERIFIED' !== $response ) {
                        status_header( 400 );
                        exit;
                }

                $data = array();
                parse_str( $raw_post, $data );

                $user_id = isset( $data['custom'] ) ? absint( $data['custom'] ) : 0;
                if ( ! $user_id ) {
                        status_header( 200 );
                        exit;
                }

                $txn_type       = isset( $data['txn_type'] ) ? sanitize_text_field( wp_unslash( $data['txn_type'] ) ) : '';
                $payment_status = isset( $data['payment_status'] ) ? sanitize_text_field( wp_unslash( $data['payment_status'] ) ) : '';
                $txn_id         = isset( $data['txn_id'] ) ? sanitize_text_field( wp_unslash( $data['txn_id'] ) ) : ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase

                if ( in_array( $txn_type, array( 'subscr_payment', 'recurring_payment' ), true ) && 'Completed' === $payment_status ) {
                        $this->mark_user_premium( $user_id, $txn_id );
                }

                if ( in_array( $txn_type, array( 'subscr_cancel', 'recurring_payment_suspended', 'recurring_payment_expired' ), true ) ) {
                        $this->revoke_user_premium( $user_id );
                }

                status_header( 200 );
                exit;
        }

        /**
         * Verify IPN payload with PayPal.
         *
         * @param string $raw_post Raw payload.
         *
         * @return string
         */
        private function verify_ipn( $raw_post ) {
                $mode     = get_option( 'lgd_paypal_mode', 'sandbox' );
                $endpoint = 'sandbox' === $mode ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr' : 'https://ipnpb.paypal.com/cgi-bin/webscr';

                $request = wp_remote_post(
                        $endpoint,
                        array(
                                'body'        => 'cmd=_notify-validate&' . $raw_post,
                                'timeout'     => 30,
                                'httpversion' => '1.1',
                                'headers'     => array( 'Connection' => 'Close' ),
                        )
                );

                if ( is_wp_error( $request ) || 200 !== wp_remote_retrieve_response_code( $request ) ) {
                        return 'INVALID';
                }

                return wp_remote_retrieve_body( $request );
        }

        /**
         * Mark a user as premium and award monthly tokens.
         *
         * @param int    $user_id User ID.
         * @param string $txn_id  PayPal transaction ID.
         */
        private function mark_user_premium( $user_id, $txn_id ) {
                update_user_meta( $user_id, '_lgd_is_premium', 1 );
                update_user_meta( $user_id, '_lgd_premium_since', current_time( 'mysql', true ) );
                update_user_meta( $user_id, '_lgd_last_payment_txn', $txn_id );

                $this->set_business_ad_free( $user_id, true );
                $this->grant_premium_tokens( $user_id );
        }

        /**
         * Remove premium perks for a user.
         *
         * @param int $user_id User ID.
         */
        private function revoke_user_premium( $user_id ) {
                delete_user_meta( $user_id, '_lgd_is_premium' );
                $this->set_business_ad_free( $user_id, false );
        }

        /**
         * Grant the monthly token allowance to a premium user.
         *
         * @param int $user_id User ID.
         */
        public function grant_premium_tokens( $user_id ) {
                $allowance    = (int) get_option( 'lgd_premium_token_allowance', 100 );
                $gamification = $this->plugin->get_gamification();

                if ( $allowance > 0 && $gamification ) {
                        $last_txn = get_user_meta( $user_id, '_lgd_last_payment_txn_awarded', true );
                        $current  = get_user_meta( $user_id, '_lgd_last_payment_txn', true );

                        if ( $current && $current !== $last_txn ) {
                                $gamification->add_points( $user_id, $allowance, 'subscription_bonus' );
                                update_user_meta( $user_id, '_lgd_last_payment_txn_awarded', $current );
                        }
                }
        }

        /**
         * Determine if the user is premium.
         *
         * @param int $user_id User ID.
         *
         * @return bool
         */
        public function user_has_premium( $user_id ) {
                return (bool) get_user_meta( $user_id, '_lgd_is_premium', true );
        }

        /**
         * Update ad visibility flags on the user's business listings.
         *
         * @param int  $user_id User ID.
         * @param bool $ad_free Whether ads should be hidden.
         */
        private function set_business_ad_free( $user_id, $ad_free ) {
                $query = new WP_Query(
                        array(
                                'post_type'      => 'business_listing',
                                'posts_per_page' => -1,
                                'meta_query'     => array(
                                        array(
                                                'key'   => Local_Gamified_Directory::META_OWNER_USER,
                                                'value' => $user_id,
                                        ),
                                ),
                                'fields'         => 'ids',
                        )
                );

                if ( empty( $query->posts ) ) {
                        return;
                }

                foreach ( $query->posts as $post_id ) {
                        update_post_meta( $post_id, Local_Gamified_Directory::META_PREFIX . 'ad_free', $ad_free ? 1 : 0 );
                }
        }
}
