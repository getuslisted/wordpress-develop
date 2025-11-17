<?php
/**
 * Tests for the Local Gamified Directory plugin.
 *
 * @group plugins
 * @group local-gamified-directory
 */
class Tests_LocalGamifiedDirectory extends WP_UnitTestCase {

	/**
	 * Plugin singleton.
	 *
	 * @var Local_Gamified_Directory
	 */
	protected static $plugin;

	/**
	 * Ensure the plugin is loaded for each test run.
	 */
	public function set_up() {
		parent::set_up();

		$this->ensure_plugin_loaded();
		$this->reset_feature_flags();

		add_filter( 'pre_wp_mail', array( $this, 'short_circuit_mail' ), 10, 2 );
	}

	/**
	 * Reset globals after each test.
	 */
	public function tear_down() {
		remove_filter( 'pre_wp_mail', array( $this, 'short_circuit_mail' ), 10 );
		remove_filter( 'wp_die_handler', array( $this, 'filter_wp_die_handler' ) );

		global $wpdb;
		$this->maybe_truncate_table( $wpdb->prefix . 'lgd_activity_log' );
		$this->maybe_truncate_table( $wpdb->prefix . 'lgd_user_sanctions' );

		parent::tear_down();
	}

	/**
	 * Prevent outbound email during tests.
	 *
	 * @param null|bool $short_circuit Short-circuit value.
	 * @param array     $atts          Mail arguments.
	 * @return bool
	 */
	public function short_circuit_mail( $short_circuit, $atts ) {
		return true;
	}

	/**
	 * Truncate a table if it exists.
	 *
	 * @param string $table_name Fully qualified table name.
	 */
	protected function maybe_truncate_table( $table_name ) {
		global $wpdb;

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table_name ) ) );

		if ( $exists === $table_name ) {
			$wpdb->query( "TRUNCATE TABLE {$table_name}" );
		}
	}

	/**
	 * Ensure the plugin bootstrap has run.
	 */
	protected function ensure_plugin_loaded() {
		if ( ! class_exists( 'Local_Gamified_Directory' ) ) {
			require_once ABSPATH . 'wp-content/plugins/local-gamified-directory/local-gamified-directory.php';
		}

		if ( ! self::$plugin ) {
			self::$plugin = Local_Gamified_Directory::instance();
			self::$plugin->bootstrap_modules();

			LGD_Admin::activate();
			LGD_Activity::activate();
			LGD_Gamification::activate();
			LGD_Social_Login::activate();
		}
	}

	/**
	 * Reset feature flags to enabled by default.
	 */
	protected function reset_feature_flags() {
		$flags = array();
		foreach ( self::$plugin->get_features() as $feature => $data ) {
			$flags[ $feature ] = true;
		}

		update_option( LGD_Admin::OPTION_FEATURE_FLAGS, $flags );
	}

	/**
	 * Create an overrides array for feature flags.
	 *
	 * @param array $overrides Feature overrides.
	 * @return array
	 */
	protected function feature_flags_with( array $overrides ) {
		$flags = array();
		foreach ( self::$plugin->get_features() as $feature => $data ) {
			$flags[ $feature ] = true;
		}

		return array_merge( $flags, $overrides );
	}

	/**
	 * Provide a custom wp_die handler for assertions.
	 *
	 * @return callable
	 */
	public function filter_wp_die_handler() {
		return array( $this, 'wp_die_handler' );
	}

	/**
	 * Throw an exception when wp_die is triggered.
	 *
	 * @param string|array $message Message passed to wp_die.
	 *
	 * @throws Exception Always throws to allow assertions.
	 */
	public function wp_die_handler( $message ) {
		if ( is_array( $message ) && isset( $message['message'] ) ) {
			$message = $message['message'];
		}

		throw new Exception( (string) $message );
	}

	/**
	 * Intercept outbound HTTP requests for OAuth tests.
	 *
	 * @param false|array|WP_Error $preempt Preempt value.
	 * @param array                $args    Request arguments.
	 * @param string               $url     Target URL.
	 * @return array
	 */
	public function mock_http_error( $preempt, $args, $url ) {
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode(
				array(
					'error'             => 'invalid_grant',
					'error_description' => 'Denied for testing.',
				)
			),
			'response' => array(
				'code'    => 400,
				'message' => 'Bad Request',
			),
		);
	}

	/**
	 * Activity logging should trigger automated warnings once the threshold is reached.
	 */
	public function test_activity_log_triggers_warning_when_threshold_met() {
		$activity = self::$plugin->get_activity();
		$this->assertNotNull( $activity );

		$admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin_id );

		$user_id = self::factory()->user->create();

		update_option( LGD_Admin::OPTION_ABUSE_THRESHOLD, 1 );
		update_option( LGD_Admin::OPTION_ABUSE_WINDOW, 60 );

		delete_transient( 'lgd_abuse_' . $user_id . '_test_event' );
		delete_transient( 'lgd_abuse_flagged_' . $user_id . '_test_event' );

		$activity->log_event( $user_id, 'test_event' );

		global $wpdb;
		$table = $wpdb->prefix . LGD_Admin::TABLE_SANCTIONS;
		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND type = %s", $user_id, 'warning' ) );

		$this->assertSame( 1, $count );
	}

	/**
	 * Exchanging an authorization code should surface provider errors.
	 */
	public function test_social_login_exchange_handles_error_response() {
		$social = self::$plugin->get_social_login();
		$this->assertNotNull( $social );

		update_option( 'lgd_social_google_client_id', 'test-client' );
		update_option( 'lgd_social_google_client_secret', 'test-secret' );

		add_filter( 'pre_http_request', array( $this, 'mock_http_error' ), 10, 3 );

		$method = new ReflectionMethod( $social, 'exchange_code_for_token' );
		$method->setAccessible( true );
		$result = $method->invoke( $social, 'google', 'bad-code', home_url( '/' ) );

		remove_filter( 'pre_http_request', array( $this, 'mock_http_error' ), 10 );

		$this->assertWPError( $result );
	}

	/**
	 * Callback handling should reject invalid states before continuing.
	 */
	public function test_social_login_rejects_invalid_state() {
		$social = self::$plugin->get_social_login();
		$this->assertNotNull( $social );

		update_option( 'lgd_social_google_client_id', 'test-client' );
		update_option( 'lgd_social_google_client_secret', 'test-secret' );

		add_filter( 'wp_die_handler', array( $this, 'filter_wp_die_handler' ) );

		$original_get = $_GET;
		$_GET         = array(
			'lgd_social' => 'google',
			'lgd_action' => 'callback',
			'code'       => 'code',
			'state'      => 'invalid',
		);

		try {
			$social->handle_requests();
			$this->fail( 'Expected wp_die to be triggered for invalid state.' );
		} catch ( Exception $exception ) {
			$this->assertStringContainsString( 'Invalid login state', $exception->getMessage() );
		}

		$_GET = $original_get;
	}

	/**
	 * Business submission forms should honour the feature flag.
	 */
	public function test_business_submission_form_respects_feature_flag() {
		update_option( LGD_Admin::OPTION_FEATURE_FLAGS, $this->feature_flags_with( array( 'business_submissions' => false ) ) );

		$output = do_shortcode( '[lgd_business_submission_form]' );

		$this->assertStringContainsString( 'disabled', strtolower( wp_strip_all_tags( $output ) ) );
	}

	/**
	 * Ad submission should be disabled when the feature is toggled off.
	 */
	public function test_ad_form_respects_feature_flag() {
		update_option( LGD_Admin::OPTION_FEATURE_FLAGS, $this->feature_flags_with( array( 'ads' => false ) ) );

		$output = do_shortcode( '[lgd_ad_form]' );

		$this->assertStringContainsString( 'disabled', strtolower( wp_strip_all_tags( $output ) ) );
	}

	/**
	 * Leaderboard data should not be returned when disabled.
	 */
	public function test_leaderboard_returns_empty_when_feature_disabled() {
		update_option( LGD_Admin::OPTION_FEATURE_FLAGS, $this->feature_flags_with( array( 'leaderboard' => false ) ) );

		$gamification = self::$plugin->get_gamification();
		$this->assertNotNull( $gamification );

		$this->assertSame( array(), $gamification->get_leaderboard() );
	}

	/**
	 * Activity purge should remove entries older than the retention window.
	 */
	public function test_activity_purge_removes_old_records() {
		$activity = self::$plugin->get_activity();
		$this->assertNotNull( $activity );

		global $wpdb;
		$table = $wpdb->prefix . LGD_Activity::TABLE;

		update_option( LGD_Admin::OPTION_ACTIVITY_RETENTION, 30 );

		$wpdb->insert(
			$table,
			array(
				'user_id'     => 1,
				'event'       => 'old',
				'object_type' => 'test',
				'object_id'   => 0,
				'details'     => null,
				'ip_address'  => '',
				'user_agent'  => '',
				'created_at'  => gmdate( 'Y-m-d H:i:s', time() - ( 60 * DAY_IN_SECONDS ) ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		$wpdb->insert(
			$table,
			array(
				'user_id'     => 1,
				'event'       => 'recent',
				'object_type' => 'test',
				'object_id'   => 0,
				'details'     => null,
				'ip_address'  => '',
				'user_agent'  => '',
				'created_at'  => gmdate( 'Y-m-d H:i:s' ),
			),
			array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		$activity->purge_old_logs();

		$events = $wpdb->get_col( "SELECT event FROM {$table} ORDER BY created_at ASC" );

		$this->assertSame( array( 'recent' ), $events );
	}
}
