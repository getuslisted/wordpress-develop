<?php
/**
 * Tests for Universal Color Changer plugin.
 *
 * @package UniversalColorChanger\Tests
 */

use UCC\Changeset_Manager;
use UCC\Converter;
use UCC\Indexer;
use UCC\Plugin;
use UCC\Replacer;

/**
 * @group plugins
 */
class Tests_Plugins_Universal_Color_Changer extends WP_UnitTestCase {
	/**
	 * Load the plugin and ensure tables exist.
	 *
	 * @param WP_UnitTest_Factory $factory Factory instance.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		require_once ABSPATH . 'wp-content/plugins/universal-color-changer/universal-color-changer.php';

		Plugin::activate();
		Plugin::init();

		self::reset_environment();
	}

	/**
	 * Reset plugin state before each test.
	 */
	public function set_up() {
		parent::set_up();

		self::reset_environment();
	}

	/**
	 * Ensure color extraction finds canonical values for hex and rgb(a).
	 */
	public function test_finds_hex_and_rgb_colors() {
		$text    = 'Background #fff #ABCDEF and rgba(255, 0, 0, 0.5) together.';
		$matches = Indexer::find_colors( $text );
		$colors  = wp_list_pluck( $matches, 'canonical' );
		$formats = wp_list_pluck( $matches, 'format' );

                $this->assertContains( '#ffffff', $colors );
                $this->assertContains( '#ff0000@0.5', $colors );
		$this->assertContains( 'hex3', $formats );
		$this->assertContains( 'HEX6', $formats );
		$this->assertContains( 'rgba', $formats );
	}

	/**
	 * Ensure converter respects formatting rules including rgb targets for hex matches.
	 */
	public function test_converter_preserves_formatting() {
		$this->assertSame( '#123', Converter::apply_format( '#ABC', '#123456' ) );
                $this->assertSame( '#000', Converter::apply_format( '#ABC', 'rgba(0, 0, 0, 0.7)' ) );

                $rgb_original = 'rgba( 10 , 20 , 30 , 0.5 )';
                $formatted    = Converter::apply_format( $rgb_original, '#000000' );

                $this->assertSame( 'rgba( 0 , 0 , 0 , 0.5 )', $formatted );
                $this->assertSame( '#ff0000@0.5', Converter::canonicalize( 'rgba(255, 0, 0, 0.5)' ) );
                $this->assertSame( '#ff0000@0.5', Converter::canonicalize( '#ff0000@0.5' ) );
	}

	/**
	 * Validate replacement batches, history tracking, compression, and undo flows.
	 */
	public function test_replacement_and_undo() {
		global $wpdb;

		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<div style="color:#abc">Sample rgba(10, 20, 30, 0.3)</div>',
			)
		);

		$post = get_post( $post_id );
		Indexer::index_field( 'post', $post_id, 'post_content', $post->post_content );

		$dry_run = Replacer::dry_run( '#abc', '#ffffff' );
		$this->assertGreaterThan( 0, $dry_run['total'] );

		$result = Replacer::apply( '#abc', '#ffffff', 0, 5 );
		$this->assertArrayHasKey( 'changeset_id', $result );
		$this->assertSame( 0, $result['remaining'] );

		$updated = get_post( $post_id );
		$this->assertStringContainsString( '#fff', $updated->post_content );

		$occurrence_table   = $wpdb->prefix . 'ucc_occurrences';
		$source_canonical   = Converter::canonicalize( '#abc' );
		$target_canonical   = Converter::canonicalize( '#fff' );
		$source_occurrences = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $occurrence_table WHERE object_type = %s AND object_id = %d AND field = %s AND color_found = %s",
				'post',
				$post_id,
				'post_content',
				$source_canonical
			)
		);
		$this->assertSame( 0, $source_occurrences );

		$target_occurrences = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $occurrence_table WHERE object_type = %s AND object_id = %d AND field = %s AND color_found = %s",
				'post',
				$post_id,
				'post_content',
				$target_canonical
			)
		);
		$this->assertGreaterThan( 0, $target_occurrences );

		$undo = Changeset_Manager::undo_changeset( $result['changeset_id'] );
		$this->assertIsArray( $undo );
		$this->assertSame( $result['changeset_id'], $undo['changeset_id'] );

		$restored = get_post( $post_id );
		$this->assertStringContainsString( '#abc', $restored->post_content );
		$restored_occurrences = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $occurrence_table WHERE object_type = %s AND object_id = %d AND field = %s AND color_found = %s",
				'post',
				$post_id,
				'post_content',
				$source_canonical
			)
		);
		$this->assertGreaterThan( 0, $restored_occurrences );

		$rgb_result = Replacer::apply( 'rgba(10, 20, 30, 0.3)', '#000000', 0, 5 );
		$this->assertSame( 0, $rgb_result['remaining'] );

		$rgb_updated = get_post( $post_id );
		$this->assertStringContainsString( 'rgba(0, 0, 0, 0.3)', $rgb_updated->post_content );

		$rgb_undo = Changeset_Manager::undo_changeset( $rgb_result['changeset_id'] );
		$this->assertSame( $rgb_result['changeset_id'], $rgb_undo['changeset_id'] );

		$large_value = str_repeat( 'abcdef', 20000 ) . '#abc';
		$meta_id     = add_post_meta( $post_id, 'ucc_large_color', $large_value, true );

		Indexer::index_field( 'postmeta', $meta_id, 'ucc_large_color', get_post_meta( $post_id, 'ucc_large_color', true ) );

		$large_changeset = Replacer::apply( '#abc', '#000000', 0, 1 );
		$this->assertArrayHasKey( 'changeset_id', $large_changeset );

		$stored_before = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT before_value FROM {$wpdb->prefix}ucc_changes WHERE changeset_id = %d ORDER BY id DESC LIMIT 1",
				$large_changeset['changeset_id']
			)
		);
		$this->assertStringStartsWith( 'gz:', $stored_before );

		Changeset_Manager::undo_changeset( $large_changeset['changeset_id'] );

		$restored_meta = get_metadata_by_mid( 'post', $meta_id );
		$this->assertSame( $large_value, $restored_meta->meta_value );
	}

	/**
	 * Ensure indexing batches persist field hashes so unchanged content is skipped.
	 */
	public function test_index_batch_persists_hash_state() {
		Indexer::reset();
		$post_id = self::factory()->post->create(
			array(
				'post_content' => '<p style="color:#abc">Color</p>',
			)
		);

		$result = Indexer::index_batch( 5 );
		$this->assertGreaterThan( 0, $result['processed'] );

		$state      = get_option( Indexer::STATE_OPTION );
		$hash_key   = 'post:' . $post_id . ':post_content';
		$hash_value = isset( $state['hashes'][ $hash_key ] ) ? $state['hashes'][ $hash_key ] : '';
		$this->assertNotEmpty( $hash_value );

		$second = Indexer::index_batch( 5 );
		$this->assertSame( 0, $second['processed'] );
		$state_after = get_option( Indexer::STATE_OPTION );
		$this->assertArrayHasKey( $hash_key, $state_after['hashes'] );
		$this->assertSame( $hash_value, $state_after['hashes'][ $hash_key ] );
	}

	/**
	 * Helper to clear plugin state between tests.
	 */
	protected static function reset_environment() {
		global $wpdb;

		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'ucc_changesets' );
		$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'ucc_changes' );

		Indexer::reset();
	}
}
