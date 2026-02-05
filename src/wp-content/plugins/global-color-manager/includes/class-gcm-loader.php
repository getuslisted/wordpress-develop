<?php
/**
 * Loader class.
 *
 * @package GlobalColorManager
 */

namespace GCM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once GCM_PLUGIN_PATH . 'includes/class-gcm-detector.php';
require_once GCM_PLUGIN_PATH . 'includes/class-gcm-replacer.php';
require_once GCM_PLUGIN_PATH . 'includes/class-gcm-admin.php';
require_once GCM_PLUGIN_PATH . 'includes/class-gcm-frontend.php';

/**
 * Bootstrap plugin components.
 */
class Loader {
	/**
	 * Detector instance.
	 *
	 * @var Detector
	 */
	private $detector;

	/**
	 * Replacer instance.
	 *
	 * @var Replacer
	 */
	private $replacer;

	/**
	 * Admin instance.
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * Frontend instance.
	 *
	 * @var Frontend
	 */
	private $frontend;

	/**
	 * Initialize plugin.
	 *
	 * @return void
	 */
	public function init() {
		$this->detector = new Detector();
		$this->replacer = new Replacer( $this->detector );
		$this->admin    = new Admin( $this->detector, $this->replacer );
		$this->frontend = new Frontend( $this->detector, $this->replacer );

		$this->detector->init();
		$this->replacer->init();
		$this->admin->init();
		$this->frontend->init();
	}

	/**
	 * Activation callback.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( false === get_option( 'gcm_global_replacements', false ) ) {
			add_option( 'gcm_global_replacements', array(), '', false );
		}
		if ( false === get_option( 'gcm_global_index', false ) ) {
			add_option( 'gcm_global_index', array(), '', false );
		}
	}

	/**
	 * Uninstall callback.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		delete_option( 'gcm_global_replacements' );
		delete_option( 'gcm_global_index' );

		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_gcm_page_replacements'"
		);

		$transients = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				'_transient_gcm_detect_post_%',
				'_transient_timeout_gcm_detect_post_%'
			)
		);

		if ( ! empty( $transients ) ) {
			foreach ( $transients as $transient ) {
				$option_name = str_replace( '_transient_', '', $transient );
				delete_transient( $option_name );
			}
		}
	}
}
