<?php
/**
 * Color replacement engine.
 *
 * @package GlobalColorManager
 */

namespace GCM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle storing replacements and generating CSS overrides.
 */
class Replacer {
	/**
	 * Detector.
	 *
	 * @var Detector
	 */
	private $detector;

	/**
	 * Constructor.
	 *
	 * @param Detector $detector Detector.
	 */
	public function __construct( Detector $detector ) {
		$this->detector = $detector;
	}

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_head', array( $this, 'output_override_css' ), 99 );
	}

	/**
	 * Get page replacements.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_page_replacements( $post_id ) {
		$data = get_post_meta( absint( $post_id ), '_gcm_page_replacements', true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Save page replacements.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $replacements Replacements.
	 * @return void
	 */
	public function save_page_replacements( $post_id, $replacements ) {
		update_post_meta( absint( $post_id ), '_gcm_page_replacements', $this->sanitize_replacements( $replacements ) );
	}

	/**
	 * Reset page replacements.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function reset_page_replacements( $post_id ) {
		delete_post_meta( absint( $post_id ), '_gcm_page_replacements' );
	}

	/**
	 * Get global replacements.
	 *
	 * @return array
	 */
	public function get_global_replacements() {
		$data = get_option( 'gcm_global_replacements', array() );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Save global replacements.
	 *
	 * @param array $replacements Replacements.
	 * @return void
	 */
	public function save_global_replacements( $replacements ) {
		update_option( 'gcm_global_replacements', $this->sanitize_replacements( $replacements ), false );
	}

	/**
	 * Reset global replacements.
	 *
	 * @return void
	 */
	public function reset_global_replacements() {
		update_option( 'gcm_global_replacements', array(), false );
	}

	/**
	 * Output CSS overrides.
	 *
	 * @return void
	 */
	public function output_override_css() {
		$replacements = $this->get_global_replacements();

		if ( is_singular() ) {
			$post_id       = get_queried_object_id();
			$page_overrides = $this->get_page_replacements( $post_id );
			$replacements   = array_merge( $replacements, $page_overrides );
		}

		if ( empty( $replacements ) ) {
			return;
		}

		$css = $this->generate_css( $replacements );
		if ( '' === $css ) {
			return;
		}

		echo '<style id="gcm-overrides">' . wp_strip_all_tags( $css ) . '</style>';
	}

	/**
	 * Generate CSS overrides for exact color declarations.
	 *
	 * @param array $replacements Replacements.
	 * @return string
	 */
	public function generate_css( $replacements ) {
		$rules = array();

		foreach ( $replacements as $from_key => $to_hex ) {
			$norm = $this->detector->normalize_color( 'rgb(' . str_replace( ',', ', ', (string) $from_key ) . ')' );
			$to   = $this->detector->normalize_color( $to_hex );
			if ( ! $norm || ! $to ) {
				continue;
			}

			$search_values = array(
				$norm['hex'],
				strtolower( $norm['rgb'] ),
				sprintf( 'rgba(%s,1)', str_replace( ',', ',', (string) $from_key ) ),
				sprintf( 'rgba(%s, 1)', str_replace( ',', ', ', (string) $from_key ) ),
			);

			foreach ( $search_values as $search ) {
				$search = strtolower( $search );
				$escaped = esc_attr( $search );
				$rules[] = sprintf(
					'[style*="color:%1$s" i],[style*="color: %1$s" i],[style*="background-color:%1$s" i],[style*="background-color: %1$s" i],[style*="border-color:%1$s" i],[style*="border-color: %1$s" i]{color:%2$s !important;background-color:%2$s !important;border-color:%2$s !important;}',
					$escaped,
					$to['hex']
				);
			}
		}

		return implode( '', array_unique( $rules ) );
	}

	/**
	 * Sanitize replacements array.
	 *
	 * @param array $replacements Replacements.
	 * @return array
	 */
	private function sanitize_replacements( $replacements ) {
		$clean = array();
		if ( ! is_array( $replacements ) ) {
			return $clean;
		}

		foreach ( $replacements as $from => $to ) {
			$from_norm = preg_replace( '/[^0-9,]/', '', (string) $from );
			$to_norm   = sanitize_hex_color( (string) $to );
			if ( ! $from_norm || ! $to_norm ) {
				continue;
			}
			$clean[ $from_norm ] = strtolower( $to_norm );
		}
		return $clean;
	}
}
