<?php
/**
 * Social login helpers for the Local Gamified Directory plugin.
 *
 * @package LocalGamifiedDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Provide OAuth-based authentication for common providers.
 */
class LGD_Social_Login {

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

	        add_action( 'init', array( $this, 'handle_requests' ) );
	        add_shortcode( 'lgd_social_login', array( $this, 'render_shortcode' ) );
	        add_action( 'login_form', array( $this, 'render_login_buttons' ) );
	}

	/**
	 * Activation handler.
	 */
	public static function activate() {
	        // Nothing required yet.
	}

	/**
	 * Render login buttons on the WordPress login form.
	 */
	public function render_login_buttons() {
	        echo $this->render_buttons(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render social login buttons via shortcode.
	 *
	 * @return string
	 */
	public function render_shortcode() {
	        return $this->render_buttons();
	}

	/**
	 * Determine whether social login is enabled.
	 *
	 * @return bool
	 */
	private function is_enabled() {
	        return $this->plugin->is_feature_enabled( 'social_login' );
	}

	/**
	 * Render button markup.
	 *
	 * @return string
	 */
	private function render_buttons() {
	        if ( ! $this->is_enabled() ) {
	                return '';
	        }

	        $providers = $this->get_enabled_providers();

	        if ( empty( $providers ) ) {
	                return '';
	        }

	        $redirect_to = isset( $_GET['redirect_to'] ) ? rawurldecode( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );
	        $redirect_to = esc_url_raw( $redirect_to );
	        if ( empty( $redirect_to ) ) {
	                $redirect_to = home_url( '/' );
	        }

	        ob_start();
	        ?>
	        <div class="lgd-social-login">
	                <p><?php esc_html_e( 'Or continue with', 'local-gamified-directory' ); ?></p>
	                <div class="lgd-social-login__buttons">
	                        <?php foreach ( $providers as $provider => $label ) : ?>
	                                <a class="lgd-social-login__button lgd-social-login__button--<?php echo esc_attr( $provider ); ?>" href="<?php echo esc_url( $this->get_login_url( $provider, $redirect_to ) ); ?>">
	                                        <?php echo esc_html( $label ); ?>
	                                </a>
	                        <?php endforeach; ?>
	                </div>
	        </div>
	        <?php
	        return ob_get_clean();
	}

	/**
	 * Get enabled providers and their labels.
	 *
	 * @return array
	 */
	private function get_enabled_providers() {
	        $providers = array();

	        if ( get_option( 'lgd_social_google_client_id' ) && get_option( 'lgd_social_google_client_secret' ) ) {
	                $providers['google'] = __( 'Google', 'local-gamified-directory' );
	        }

	        if ( get_option( 'lgd_social_facebook_app_id' ) && get_option( 'lgd_social_facebook_app_secret' ) ) {
	                $providers['facebook'] = __( 'Facebook', 'local-gamified-directory' );
	        }

	        return $providers;
	}

	/**
	 * Build the login URL for a provider.
	 *
	 * @param string $provider Provider key.
	 * @param string $redirect Redirect destination.
	 *
	 * @return string
	 */
	private function get_login_url( $provider, $redirect ) {
	        $args = array(
	                'lgd_social' => $provider,
	                'lgd_action' => 'login',
	                'redirect_to'=> rawurlencode( $redirect ),
	        );

	        return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Handle inbound OAuth requests.
	 */
	public function handle_requests() {
	        if ( ! isset( $_GET['lgd_social'] ) ) {
	                return;
	        }

	        $provider = sanitize_key( wp_unslash( $_GET['lgd_social'] ) );

	        if ( ! in_array( $provider, array( 'google', 'facebook' ), true ) ) {
	                return;
	        }

	        if ( ! $this->is_enabled() ) {
	                return;
	        }

	        $action    = isset( $_GET['lgd_action'] ) ? sanitize_key( wp_unslash( $_GET['lgd_action'] ) ) : 'login';
	        $providers = $this->get_enabled_providers();

	        if ( empty( $providers[ $provider ] ) ) {
	                if ( 'callback' === $action ) {
	                        wp_die( esc_html__( 'This login provider is not currently available.', 'local-gamified-directory' ) );
	                }

	                return;
	        }

	        if ( 'login' === $action ) {
	                $redirect_to = isset( $_GET['redirect_to'] ) ? rawurldecode( wp_unslash( $_GET['redirect_to'] ) ) : home_url( '/' );
	                $redirect_to = esc_url_raw( $redirect_to );
	                if ( empty( $redirect_to ) ) {
	                        $redirect_to = home_url( '/' );
	                }
	                $this->redirect_to_provider( $provider, $redirect_to );
	                exit;
	        }

	        if ( 'callback' === $action ) {
	                $this->process_callback( $provider );
	                exit;
	        }
	}

	/**
	 * Redirect the user to the provider authorization endpoint.
	 *
	 * @param string $provider Provider key.
	 * @param string $redirect Redirect destination.
	 */
	private function redirect_to_provider( $provider, $redirect ) {
	        $providers = $this->get_enabled_providers();

	        if ( empty( $providers[ $provider ] ) ) {
	                wp_die( esc_html__( 'This login provider is not available.', 'local-gamified-directory' ) );
	        }

	        $state = wp_generate_password( 24, false );
	        set_transient( 'lgd_social_state_' . $state, array(
	                'provider' => $provider,
	                'redirect' => esc_url_raw( $redirect ),
	        ), 15 * MINUTE_IN_SECONDS );

	        $callback = add_query_arg(
	                array(
	                        'lgd_social' => $provider,
	                        'lgd_action' => 'callback',
	                ),
	                home_url( '/' )
	        );

	        if ( 'google' === $provider ) {
	                $client_id = get_option( 'lgd_social_google_client_id' );
	                $auth_url  = add_query_arg(
	                        array(
	                                'client_id'     => $client_id,
	                                'redirect_uri'  => $callback,
	                                'response_type' => 'code',
	                                'scope'         => 'openid email profile',
	                                'state'         => $state,
	                                'access_type'   => 'online',
	                        ),
	                        'https://accounts.google.com/o/oauth2/v2/auth'
	                );
	                wp_redirect( $auth_url );
	                exit;
	        }

	        if ( 'facebook' === $provider ) {
	                $client_id = get_option( 'lgd_social_facebook_app_id' );
	                $auth_url  = add_query_arg(
	                        array(
	                                'client_id'    => $client_id,
	                                'redirect_uri' => $callback,
	                                'response_type'=> 'code',
	                                'scope'        => 'email',
	                                'state'        => $state,
	                        ),
	                        'https://www.facebook.com/v17.0/dialog/oauth'
	                );
	                wp_redirect( $auth_url );
	                exit;
	        }
	}

	/**
	 * Process an OAuth callback.
	 *
	 * @param string $provider Provider key.
	 */
	private function process_callback( $provider ) {
	        $providers = $this->get_enabled_providers();

	        if ( empty( $providers[ $provider ] ) ) {
	                wp_die( esc_html__( 'This login provider is not currently available.', 'local-gamified-directory' ) );
	        }

	        $state_key = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
	        $state     = get_transient( 'lgd_social_state_' . $state_key );

	        if ( ! $state || $state['provider'] !== $provider ) {
	                wp_die( esc_html__( 'Invalid login state. Please try again.', 'local-gamified-directory' ) );
	        }

	        delete_transient( 'lgd_social_state_' . $state_key );

	        $code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';

	        if ( empty( $code ) ) {
	                wp_die( esc_html__( 'Missing authorization code.', 'local-gamified-directory' ) );
	        }

	        $callback = add_query_arg(
	                array(
	                        'lgd_social' => $provider,
	                        'lgd_action' => 'callback',
	                ),
	                home_url( '/' )
	        );

	        $token = $this->exchange_code_for_token( $provider, $code, $callback );

	        if ( is_wp_error( $token ) ) {
	                wp_die( esc_html( $token->get_error_message() ) );
	        }

	        if ( empty( $token['access_token'] ) ) {
	                wp_die( esc_html__( 'Missing access token from provider.', 'local-gamified-directory' ) );
	        }

	        $profile = $this->fetch_user_profile( $provider, $token );

	        if ( is_wp_error( $profile ) ) {
	                wp_die( esc_html( $profile->get_error_message() ) );
	        }

	        $user_id = $this->upsert_user( $provider, $profile );

	        if ( is_wp_error( $user_id ) ) {
	                wp_die( esc_html( $user_id->get_error_message() ) );
	        }

	        $remember = apply_filters( 'lgd_social_login_remember', true, $provider, $user_id );
	        wp_set_auth_cookie( $user_id, $remember );
	        $user = get_userdata( $user_id );
	        do_action( 'wp_login', $user->user_login, $user );

	        $redirect = ! empty( $state['redirect'] ) ? $state['redirect'] : home_url( '/' );

	        $activity = $this->plugin->get_activity();
	        if ( $activity ) {
	                $activity->log_event( $user_id, 'social_login', $provider );
	        }

	        wp_safe_redirect( $redirect );
	        exit;
	}

	/**
	 * Exchange the authorization code for an access token.
	 *
	 * @param string $provider Provider key.
	 * @param string $code     Authorization code.
	 * @param string $redirect Callback URL.
	 *
	 * @return array|WP_Error
	 */
	private function exchange_code_for_token( $provider, $code, $redirect ) {
	        if ( 'google' === $provider ) {
	                $response = wp_remote_post(
	                        'https://oauth2.googleapis.com/token',
	                        array(
	                                'body' => array(
	                                        'code'          => $code,
	                                        'client_id'     => get_option( 'lgd_social_google_client_id' ),
	                                        'client_secret' => get_option( 'lgd_social_google_client_secret' ),
	                                        'redirect_uri'  => $redirect,
	                                        'grant_type'    => 'authorization_code',
	                                ),
	                        )
	                );
	        } else {
	                $response = wp_remote_get(
	                        add_query_arg(
	                                array(
	                                        'client_id'     => get_option( 'lgd_social_facebook_app_id' ),
	                                        'client_secret' => get_option( 'lgd_social_facebook_app_secret' ),
	                                        'redirect_uri'  => $redirect,
	                                        'code'          => $code,
	                                ),
	                                'https://graph.facebook.com/v17.0/oauth/access_token'
	                        )
	                );
	        }

	        if ( is_wp_error( $response ) ) {
	                return $response;
	        }

	        $data = json_decode( wp_remote_retrieve_body( $response ), true );
	        if ( empty( $data ) || ! empty( $data['error'] ) ) {
	                $message = isset( $data['error_description'] ) ? $data['error_description'] : __( 'Unable to authenticate with provider.', 'local-gamified-directory' );
	                return new WP_Error( 'lgd_social_token', $message );
	        }

	        return $data;
	}

	/**
	 * Fetch a profile for the authenticated user.
	 *
	 * @param string $provider Provider key.
	 * @param array  $token    Token response.
	 *
	 * @return array|WP_Error
	 */
	private function fetch_user_profile( $provider, $token ) {
	        if ( 'google' === $provider ) {
	                $response = wp_remote_get(
	                        'https://openidconnect.googleapis.com/v1/userinfo',
	                        array(
	                                'headers' => array(
	                                        'Authorization' => 'Bearer ' . sanitize_text_field( $token['access_token'] ),
	                                ),
	                        )
	                );
	        } else {
	                $response = wp_remote_get(
	                        add_query_arg(
	                                array(
	                                        'fields'       => 'id,name,email',
	                                        'access_token' => sanitize_text_field( $token['access_token'] ),
	                                ),
	                                'https://graph.facebook.com/me'
	                        )
	                );
	        }

	        if ( is_wp_error( $response ) ) {
	                return $response;
	        }

	        $data = json_decode( wp_remote_retrieve_body( $response ), true );

	        if ( empty( $data ) || isset( $data['error'] ) ) {
	                $message = isset( $data['error']['message'] ) ? $data['error']['message'] : __( 'Unable to fetch profile information.', 'local-gamified-directory' );
	                return new WP_Error( 'lgd_social_profile', $message );
	        }

	        return $data;
	}

	/**
	 * Create or update the WordPress user for the social profile.
	 *
	 * @param string $provider Provider key.
	 * @param array  $profile  Profile information.
	 *
	 * @return int|WP_Error
	 */
	private function upsert_user( $provider, $profile ) {
	        $email = isset( $profile['email'] ) ? sanitize_email( $profile['email'] ) : '';
	        $id    = isset( $profile['id'] ) ? sanitize_text_field( $profile['id'] ) : '';
	        $name  = isset( $profile['name'] ) ? sanitize_text_field( $profile['name'] ) : $provider . '_' . $id;

	        if ( empty( $email ) ) {
	                $domain = wp_parse_url( home_url(), PHP_URL_HOST );
	                if ( ! $domain ) {
	                        $domain = 'example.com';
	                }
	                $email = sanitize_email( $provider . '+' . $id . '@' . $domain );
	        }

	        $user = get_user_by( 'email', $email );

	        if ( ! $user ) {
	                $username = sanitize_user( strtolower( str_replace( ' ', '.', $name ) ), true );
	                if ( username_exists( $username ) ) {
	                        $username .= '_' . wp_generate_password( 4, false, false );
	                }

	                $user_id = wp_create_user( $username, wp_generate_password( 20, true ), $email );

	                if ( is_wp_error( $user_id ) ) {
	                        return $user_id;
	                }

	                $user = get_user_by( 'id', $user_id );
	        }

	        update_user_meta( $user->ID, '_lgd_social_' . $provider . '_id', $id );

	        return $user->ID;
	}
}
