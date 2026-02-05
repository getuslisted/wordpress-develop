<?php
/**
 * Color detection engine.
 *
 * @package GlobalColorManager
 */

namespace GCM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detect colors from rendered content.
 */
class Detector {
	/**
	 * Transient TTL.
	 */
	private const TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Setup hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'save_post', array( $this, 'clear_post_cache' ) );
		add_action( 'switch_theme', array( $this, 'clear_all_cache' ) );
	}

	/**
	 * Get detected colors for a post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	public function get_post_colors( $post_id ) {
		$post_id = absint( $post_id );
		if ( ! $post_id ) {
			return array();
		}

		$cache_key = 'gcm_detect_post_' . $post_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			return array();
		}

		$rendered = apply_filters( 'the_content', $post->post_content );
		$detected = $this->detect_from_html( (string) $rendered );

		set_transient( $cache_key, $detected, self::TTL );
		return $detected;
	}

	/**
	 * Detect colors from rendered HTML.
	 *
	 * @param string $html HTML.
	 * @return array
	 */
	public function detect_from_html( $html ) {
		$result = array();

		$patterns = array(
			'hex'  => '/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/',
			'rgba' => '/rgba\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*(?:0|1|0?\.\d+)\s*\)/i',
			'rgb'  => '/rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\)/i',
		);

		$context_blocks = array(
			'inline_style' => $this->extract_context( $html, '/style\s*=\s*"([^"]+)"/i' ),
			'style_tag'    => $this->extract_context( $html, '/<style[^>]*>(.*?)<\/style>/is' ),
			'content'      => array( $html ),
		);

		foreach ( $context_blocks as $context => $blocks ) {
			foreach ( $blocks as $block ) {
				foreach ( $patterns as $type => $pattern ) {
					if ( preg_match_all( $pattern, $block, $matches ) ) {
						foreach ( $matches[0] as $found ) {
							$normalized = $this->normalize_color( $found );
							if ( ! $normalized ) {
								continue;
							}

							$key = $normalized['key'];
							if ( ! isset( $result[ $key ] ) ) {
								$result[ $key ] = array(
									'hex'        => $normalized['hex'],
									'rgb'        => $normalized['rgb'],
									'count'      => 0,
									'contexts'   => array(),
									'raw_values' => array(),
								);
							}

							$result[ $key ]['count']++;
							$result[ $key ]['contexts'][ $context ] = isset( $result[ $key ]['contexts'][ $context ] )
								? $result[ $key ]['contexts'][ $context ] + 1
								: 1;
							$result[ $key ]['raw_values'][ strtolower( $found ) ] = true;
						}
					}
				}
			}
		}

		foreach ( $result as $key => $item ) {
			$result[ $key ]['raw_values'] = array_keys( $item['raw_values'] );
		}

		uasort(
			$result,
			static function( $a, $b ) {
				return $b['count'] <=> $a['count'];
			}
		);

		return $result;
	}

	/**
	 * Build a global color index from posts and pages.
	 *
	 * @return array
	 */
	public function get_global_index() {
		$index = array();
		$ids   = get_posts(
			array(
				'post_type'      => array( 'post', 'page' ),
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);

		foreach ( $ids as $post_id ) {
			$colors = $this->get_post_colors( $post_id );
			foreach ( $colors as $key => $color ) {
				if ( ! isset( $index[ $key ] ) ) {
					$index[ $key ] = array(
						'hex'   => $color['hex'],
						'rgb'   => $color['rgb'],
						'count' => 0,
					);
				}
				$index[ $key ]['count'] += absint( $color['count'] );
			}
		}

		update_option( 'gcm_global_index', $index, false );
		return $index;
	}

	/**
	 * Normalize color string to RGB key + HEX/RGB output.
	 *
	 * @param string $value Value.
	 * @return array|null
	 */
	public function normalize_color( $value ) {
		$value = trim( strtolower( (string) $value ) );
		if ( '' === $value ) {
			return null;
		}

		if ( 0 === strpos( $value, '#' ) ) {
			$hex = sanitize_hex_color( $value );
			if ( ! $hex ) {
				return null;
			}
			$rgb = $this->hex_to_rgb( $hex );
			if ( ! $rgb ) {
				return null;
			}
			return $this->build_normalized( $rgb['r'], $rgb['g'], $rgb['b'] );
		}

		if ( preg_match( '/rgba?\(([^)]+)\)/', $value, $match ) ) {
			$parts = array_map( 'trim', explode( ',', $match[1] ) );
			if ( count( $parts ) < 3 ) {
				return null;
			}

			$r = min( 255, max( 0, (int) $parts[0] ) );
			$g = min( 255, max( 0, (int) $parts[1] ) );
			$b = min( 255, max( 0, (int) $parts[2] ) );

			return $this->build_normalized( $r, $g, $b );
		}

		return null;
	}

	/**
	 * Clear a post detection cache.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function clear_post_cache( $post_id ) {
		delete_transient( 'gcm_detect_post_' . absint( $post_id ) );
	}

	/**
	 * Clear all detection cache transients.
	 *
	 * @return void
	 */
	public function clear_all_cache() {
		global $wpdb;

		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gcm_detect_post_%' OR option_name LIKE '_transient_timeout_gcm_detect_post_%'"
		);
	}

	/**
	 * Extract context blocks by regex.
	 *
	 * @param string $html HTML.
	 * @param string $regex Pattern.
	 * @return array
	 */
	private function extract_context( $html, $regex ) {
		$blocks = array();
		if ( preg_match_all( $regex, $html, $matches ) ) {
			$blocks = $matches[1];
		}
		return $blocks;
	}

	/**
	 * Convert HEX to RGB array.
	 *
	 * @param string $hex Hex.
	 * @return array|null
	 */
	private function hex_to_rgb( $hex ) {
		$hex = ltrim( $hex, '#' );
		if ( 3 === strlen( $hex ) ) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if ( 6 !== strlen( $hex ) ) {
			return null;
		}
		return array(
			'r' => hexdec( substr( $hex, 0, 2 ) ),
			'g' => hexdec( substr( $hex, 2, 2 ) ),
			'b' => hexdec( substr( $hex, 4, 2 ) ),
		);
	}

	/**
	 * Build normalized output.
	 *
	 * @param int $r Red.
	 * @param int $g Green.
	 * @param int $b Blue.
	 * @return array
	 */
	private function build_normalized( $r, $g, $b ) {
		$hex = sprintf( '#%02x%02x%02x', $r, $g, $b );
		$rgb = sprintf( 'rgb(%d, %d, %d)', $r, $g, $b );
		$key = sprintf( '%d,%d,%d', $r, $g, $b );
		return array(
			'key' => $key,
			'hex' => $hex,
			'rgb' => $rgb,
		);
	}
}
