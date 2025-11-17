<?php
/**
 * Content indexing.
 *
 * @package UniversalColorChanger
 */

namespace UCC;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Indexer class.
 */
class Indexer {
/**
 * Option key for index state.
 *
 * @var string
 */
const STATE_OPTION = 'ucc_index_state';

/**
 * Get status information.
 *
 * @return array
 */
public static function get_status() {
global $wpdb;

$occurrences_table = $wpdb->prefix . 'ucc_occurrences';
$count             = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $occurrences_table" );
$colors            = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT color_found) FROM $occurrences_table" );

$state = self::get_state();

return array(
'occurrence_count' => $count,
'color_count'      => $colors,
'last_indexed_at'  => isset( $state['last_indexed_at'] ) ? $state['last_indexed_at'] : null,
'scan_titles'      => ! empty( $state['scan_titles'] ),
'scan_excerpts'    => ! empty( $state['scan_excerpts'] ),
'postmeta_mode'    => isset( $state['postmeta_mode'] ) ? $state['postmeta_mode'] : 'allowlist',
);
}

/**
 * Run an indexing batch.
 *
 * @param int $batch_size Batch size.
 * @return array
 */
public static function index_batch( $batch_size = 25 ) {
$state = self::get_state();

$processed = 0;
while ( $processed < $batch_size ) {
$item = self::next_item( $state );
if ( ! $item ) {
break;
}

self::index_item( $item, $state );
$processed++;
}

$state['last_indexed_at'] = current_time( 'mysql' );
$state['hashes']         = self::get_state_hashes();
self::save_state( $state );

$remaining = self::count_remaining( $state );

return array(
'processed' => $processed,
'remaining' => $remaining,
'state'     => $state,
);
}

/**
 * Count remaining items to index (rough estimate).
 *
 * @param array $state State.
 * @return int
 */
private static function count_remaining( $state ) {
global $wpdb;

$post_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status != %s AND post_type IN ('post','page','wp_block','custom_css') AND ID > %d", 'trash', (int) $state['last_post_id'] ) );
$meta_where = self::postmeta_where_clause( $state );
$meta_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_id > %d $meta_where", (int) $state['last_meta_id'] ) );
$option_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_id > %d AND (option_name LIKE %s OR option_name LIKE %s)", (int) $state['last_option_id'], 'widget_%', 'custom_css_%' ) );

return $post_count + $meta_count + $option_count;
}

/**
 * Determine next item to index.
 *
 * @param array $state State.
 * @return array|null
 */
private static function next_item( array &$state ) {
global $wpdb;

$post = $wpdb->get_row(
$wpdb->prepare(
"SELECT ID, post_content, post_title, post_excerpt, post_type FROM {$wpdb->posts} WHERE post_status != %s AND post_type IN ('post','page','wp_block','custom_css') AND ID > %d ORDER BY ID ASC LIMIT 1",
'trash',
(int) $state['last_post_id']
)
);

if ( $post ) {
$state['last_post_id'] = (int) $post->ID;

return array(
'type' => 'post',
'id'   => (int) $post->ID,
'post' => $post,
);
}

$meta_where = self::postmeta_where_clause( $state );
$meta       = $wpdb->get_row(
$wpdb->prepare(
"SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_id > %d $meta_where ORDER BY meta_id ASC LIMIT 1",
(int) $state['last_meta_id']
)
);

if ( $meta ) {
$state['last_meta_id'] = (int) $meta->meta_id;

return array(
'type' => 'postmeta',
'id'   => (int) $meta->meta_id,
'data' => $meta,
);
}

$option = $wpdb->get_row(
$wpdb->prepare(
"SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_id > %d AND (option_name LIKE %s OR option_name LIKE %s) ORDER BY option_id ASC LIMIT 1",
(int) $state['last_option_id'],
'widget_%',
'custom_css_%'
)
);

if ( $option ) {
$state['last_option_id'] = (int) $option->option_id;

return array(
'type' => 'option',
'id'   => (int) $option->option_id,
'data' => $option,
);
}

return null;
}

/**
 * Build where clause for postmeta scanning.
 *
 * @param array $state State.
 * @return string
 */
private static function postmeta_where_clause( $state ) {
if ( 'all' === $state['postmeta_mode'] ) {
return '';
}

$allowlist = apply_filters(
'ucc_postmeta_allowlist',
array(
'_wp_attachment_metadata',
'_wp_page_template',
'_wp_block_colors',
'_customize_changeset_data',
)
);

$allowlist = array_map( 'sanitize_key', $allowlist );
if ( empty( $allowlist ) ) {
return '';
}

global $wpdb;
$placeholders = implode( ',', array_fill( 0, count( $allowlist ), '%s' ) );
return $wpdb->prepare( "AND meta_key IN ($placeholders)", ...$allowlist );
}

/**
 * Index an item.
 *
 * @param array $item Item.
 * @param array $state State.
 */
private static function index_item( array $item, array &$state ) {
switch ( $item['type'] ) {
case 'post':
self::index_post( $item['post'], $state );
break;
case 'postmeta':
self::index_postmeta( $item['data'] );
break;
case 'option':
self::index_option( $item['data'] );
break;
}
}

/**
 * Index a post.
 *
 * @param WP_Post|object $post Post object.
 * @param array          $state State.
 */
private static function index_post( $post, array $state ) {
$fields = array(
'post_content' => $post->post_content,
);

if ( ! empty( $state['scan_titles'] ) ) {
$fields['post_title'] = $post->post_title;
}

if ( ! empty( $state['scan_excerpts'] ) ) {
$fields['post_excerpt'] = $post->post_excerpt;
}

foreach ( $fields as $field => $value ) {
self::index_field( 'post', (int) $post->ID, $field, $value );
}
}

/**
 * Index post meta.
 *
 * @param object $meta Meta row.
 */
private static function index_postmeta( $meta ) {
self::index_field( 'postmeta', (int) $meta->meta_id, $meta->meta_key, (string) $meta->meta_value );
}

/**
 * Index option.
 *
 * @param object $option Option row.
 */
private static function index_option( $option ) {
self::index_field( 'option', (int) $option->option_id, $option->option_name, (string) $option->option_value );
}

/**
 * Index a single field.
 *
 * @param string $object_type Object type.
 * @param int    $object_id Object id.
 * @param string $field Field name.
 * @param string $value Field value.
 */
public static function index_field( $object_type, $object_id, $field, $value ) {
global $wpdb;

$hash = sha1( (string) $value );

if ( self::field_hash_matches( $object_type, $object_id, $field, $hash ) ) {
return;
}

self::delete_field_occurrences( $object_type, $object_id, $field );

$matches = self::find_colors( (string) $value );
if ( empty( $matches ) ) {
self::store_field_hash( $object_type, $object_id, $field, $hash );
return;
}

$table = $wpdb->prefix . 'ucc_occurrences';
foreach ( $matches as $match ) {
$wpdb->insert(
$table,
array(
'object_type'     => $object_type,
'object_id'       => $object_id,
'field'           => $field,
'color_found'     => $match['canonical'],
'format_found'    => $match['format'],
'pattern_text'    => $match['match'],
'context_excerpt' => $match['context'],
'field_hash'      => $hash,
'last_indexed_at' => current_time( 'mysql' ),
),
array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
);
}

self::store_field_hash( $object_type, $object_id, $field, $hash );
}

/**
 * Find color matches in text.
 *
 * @param string $text Text.
 * @return array
 */
public static function find_colors( $text ) {
$matches = array();

if ( preg_match_all( Converter::HEX_REGEX, $text, $hex_matches, PREG_OFFSET_CAPTURE ) ) {
foreach ( $hex_matches[0] as $hex_match ) {
$matches[] = self::prepare_match( $text, $hex_match[0], (int) $hex_match[1] );
}
}

if ( preg_match_all( Converter::RGB_REGEX, $text, $rgb_matches, PREG_OFFSET_CAPTURE ) ) {
foreach ( $rgb_matches[0] as $rgb_match ) {
$matches[] = self::prepare_match( $text, $rgb_match[0], (int) $rgb_match[1] );
}
}

return $matches;
}

/**
 * Prepare a match entry.
 *
 * @param string $text Text.
 * @param string $match Match string.
 * @param int    $offset Offset.
 * @return array
 */
private static function prepare_match( $text, $match, $offset ) {
$canonical = Converter::canonicalize( $match );
$format    = self::determine_format( $match );
$context   = self::build_excerpt( $text, $offset, strlen( $match ) );

return array(
'match'     => $match,
'canonical' => $canonical,
'format'    => $format,
'context'   => $context,
);
}

/**
 * Determine format.
 *
 * @param string $match Match string.
 * @return string
 */
private static function determine_format( $match ) {
if ( 0 === strpos( $match, '#' ) ) {
$body = substr( $match, 1 );
if ( 3 === strlen( $body ) ) {
return 'hex3';
}
if ( preg_match( '/[A-F]/', $body ) && strtoupper( $body ) === $body ) {
return 'HEX6';
}
return 'hex6';
}

return stripos( $match, 'rgba' ) !== false ? 'rgba' : 'rgb';
}

/**
 * Build excerpt around match.
 *
 * @param string $text Text.
 * @param int    $offset Offset.
 * @param int    $length Length.
 * @return string
 */
private static function build_excerpt( $text, $offset, $length ) {
$start   = max( 0, $offset - 30 );
$end     = min( strlen( $text ), $offset + $length + 30 );
$excerpt = substr( $text, $start, $end - $start );

return $excerpt;
}

/**
 * Delete occurrences for a field.
 *
 * @param string $object_type Object type.
 * @param int    $object_id Object id.
 * @param string $field Field name.
 */
private static function delete_field_occurrences( $object_type, $object_id, $field ) {
global $wpdb;
$table = $wpdb->prefix . 'ucc_occurrences';
$wpdb->delete(
$table,
array(
'object_type' => $object_type,
'object_id'   => $object_id,
'field'       => $field,
),
array( '%s', '%d', '%s' )
);
}

/**
 * Check if field hash matches stored hash.
 *
 * @param string $object_type Object type.
 * @param int    $object_id Object id.
 * @param string $field Field name.
 * @param string $hash Hash.
 * @return bool
 */
private static function field_hash_matches( $object_type, $object_id, $field, $hash ) {
$hashes = self::get_state_hashes();
$key    = self::hash_key( $object_type, $object_id, $field );
return isset( $hashes[ $key ] ) && $hashes[ $key ] === $hash;
}

/**
 * Store field hash.
 *
 * @param string $object_type Object type.
 * @param int    $object_id Object id.
 * @param string $field Field name.
 * @param string $hash Hash.
 */
private static function store_field_hash( $object_type, $object_id, $field, $hash ) {
$hashes             = self::get_state_hashes();
$key                = self::hash_key( $object_type, $object_id, $field );
$hashes[ $key ]     = $hash;
self::save_state_hashes( $hashes );
}

/**
 * Generate hash key.
 *
 * @param string $object_type Object type.
 * @param int    $object_id Object id.
 * @param string $field Field name.
 * @return string
 */
private static function hash_key( $object_type, $object_id, $field ) {
return $object_type . ':' . $object_id . ':' . $field;
}

/**
 * Get hashes map.
 *
 * @return array
 */
private static function get_state_hashes() {
$state = self::get_state();
return isset( $state['hashes'] ) ? $state['hashes'] : array();
}

/**
 * Save hashes map.
 *
 * @param array $hashes Hashes.
 */
private static function save_state_hashes( $hashes ) {
$state           = self::get_state();
$state['hashes'] = $hashes;
if ( count( $state['hashes'] ) > 5000 ) {
$state['hashes'] = array_slice( $state['hashes'], -5000, 5000, true );
}
self::save_state( $state );
}

/**
 * Get state.
 *
 * @return array
 */
private static function get_state() {
$defaults = array(
'last_post_id'   => 0,
'last_meta_id'   => 0,
'last_option_id' => 0,
'scan_titles'    => (bool) get_option( 'ucc_scan_titles', false ),
'scan_excerpts'  => (bool) get_option( 'ucc_scan_excerpts', false ),
'postmeta_mode'  => get_option( 'ucc_postmeta_mode', 'allowlist' ),
'hashes'         => array(),
);

$state = get_option( self::STATE_OPTION, array() );
$state = wp_parse_args( $state, $defaults );

return $state;
}

/**
 * Save state.
 *
 * @param array $state State.
 */
private static function save_state( array $state ) {
update_option( self::STATE_OPTION, $state, false );
update_option( 'ucc_scan_titles', ! empty( $state['scan_titles'] ) );
update_option( 'ucc_scan_excerpts', ! empty( $state['scan_excerpts'] ) );
update_option( 'ucc_postmeta_mode', $state['postmeta_mode'] );
}

/**
 * List colors grouped.
 *
 * @return array
 */
public static function get_color_groups() {
global $wpdb;
$table = $wpdb->prefix . 'ucc_occurrences';
$rows  = $wpdb->get_results( "SELECT color_found, COUNT(*) as total FROM $table GROUP BY color_found ORDER BY total DESC" );

return array_map(
function( $row ) {
return array(
'color' => $row->color_found,
'count' => (int) $row->total,
);
},
$rows
);
}

/**
 * Items by color.
 *
 * @param string $color Color.
 * @return array
 */
public static function get_items_by_color( $color ) {
global $wpdb;
$table = $wpdb->prefix . 'ucc_occurrences';
$color = Converter::canonicalize( $color );
if ( ! $color ) {
return array();
}

$query = $wpdb->prepare( "SELECT * FROM $table WHERE color_found = %s ORDER BY last_indexed_at DESC", $color );
$rows  = $wpdb->get_results( $query, ARRAY_A );

return $rows;
}

/**
 * Colors by post.
 *
 * @param int $post_id Post ID.
 * @return array
 */
public static function get_colors_by_post( $post_id ) {
global $wpdb;
$table = $wpdb->prefix . 'ucc_occurrences';
$query = $wpdb->prepare( "SELECT color_found, COUNT(*) as total FROM $table WHERE object_type = %s AND object_id = %d GROUP BY color_found", 'post', $post_id );
$rows  = $wpdb->get_results( $query );

return array_map(
function( $row ) {
return array(
'color' => $row->color_found,
'count' => (int) $row->total,
);
},
$rows
);
}

/**
 * Reset state for reindex.
 */
public static function reset() {
global $wpdb;
$wpdb->query( 'TRUNCATE TABLE ' . $wpdb->prefix . 'ucc_occurrences' );
update_option(
self::STATE_OPTION,
array(
'last_post_id'   => 0,
'last_meta_id'   => 0,
'last_option_id' => 0,
'hashes'         => array(),
),
false
);
}

/**
 * Update configuration flags.
 *
 * @param array $args Arguments.
 */
public static function update_config( array $args ) {
$state = self::get_state();
if ( array_key_exists( 'scan_titles', $args ) ) {
$state['scan_titles'] = (bool) $args['scan_titles'];
}
if ( array_key_exists( 'scan_excerpts', $args ) ) {
$state['scan_excerpts'] = (bool) $args['scan_excerpts'];
}
if ( array_key_exists( 'postmeta_mode', $args ) && in_array( $args['postmeta_mode'], array( 'allowlist', 'all' ), true ) ) {
$state['postmeta_mode'] = $args['postmeta_mode'];
}
self::save_state( $state );
}
}
