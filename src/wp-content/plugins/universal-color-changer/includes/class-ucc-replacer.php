<?php
/**
 * Replacement routines.
 *
 * @package UniversalColorChanger
 */

namespace UCC;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Replacer class.
 */
class Replacer {
/**
 * Dry run replacements.
 *
 * @param string $source Source color.
 * @param string $target Target color.
 * @return array|WP_Error
 */
public static function dry_run( $source, $target ) {
$canonical = Converter::canonicalize( $source );
if ( ! $canonical ) {
return new WP_Error( 'ucc_invalid_source', __( 'Source color was not found in the index.', 'universal-color-changer' ) );
}

$fields = self::get_fields_for_color( $canonical );

$total = 0;
foreach ( $fields as $field ) {
$total += $field['occurrence_count'];
}

return array(
'changeset' => null,
'total'     => $total,
'fields'    => $fields,
);
}

/**
 * Apply replacements.
 *
 * @param string $source Source color.
 * @param string $target Target color.
 * @param int    $changeset_id Changeset ID.
 * @param int    $batch Batch size.
 * @return array|WP_Error
 */
public static function apply( $source, $target, $changeset_id = 0, $batch = 25 ) {
$canonical = Converter::canonicalize( $source );
if ( ! $canonical ) {
return new WP_Error( 'ucc_invalid_source', __( 'Source color was not found in the index.', 'universal-color-changer' ) );
}

if ( $batch <= 0 ) {
$batch = 25;
}

if ( ! $changeset_id ) {
$changeset_id = Changeset_Manager::create_changeset( $canonical, $target );
}

$fields = self::get_fields_for_color( $canonical, $changeset_id, $batch );

$processed = 0;
foreach ( $fields as $field ) {
$result = self::apply_to_field( $field, $canonical, $target, $changeset_id );
if ( is_wp_error( $result ) ) {
return $result;
}
if ( $result ) {
$processed++;
}
}

$remaining = self::count_remaining_fields( $canonical, $changeset_id );

if ( 0 === $remaining ) {
Changeset_Manager::complete_changeset( $changeset_id );
}

return array(
'changeset_id' => $changeset_id,
'processed'    => $processed,
'remaining'    => $remaining,
);
}

/**
 * Apply replacements to a single field.
 *
 * @param array  $field Field definition.
 * @param string $canonical Canonical color.
 * @param string $target Target color.
 * @param int    $changeset_id Changeset ID.
 * @return bool|WP_Error True if changed.
 */
private static function apply_to_field( array $field, $canonical, $target, $changeset_id ) {
list( $value, $context ) = self::get_field_value( $field['object_type'], $field['object_id'], $field['field'] );
$original_value          = $value;

if ( null === $value ) {
return false;
}

$replacements = self::calculate_replacements( $value, $canonical, $target );
if ( empty( $replacements ) ) {
return false;
}

foreach ( $replacements as $replacement ) {
$before = substr( $value, 0, $replacement['offset'] );
$after  = substr( $value, $replacement['offset'] + $replacement['length'] );
$value  = $before . $replacement['replacement'] . $after;
}

if ( $value === $original_value ) {
return false;
}

$save_result = self::save_field_value( $field['object_type'], $field['object_id'], $field['field'], $value, $context );
if ( is_wp_error( $save_result ) ) {
return $save_result;
}

Changeset_Manager::record_change( $changeset_id, $field['object_type'], $field['object_id'], $field['field'], $original_value, $value );

return true;
}

/**
 * Fetch value for a field.
 *
 * @param string $object_type Object type.
 * @param int    $object_id Object id.
 * @param string $field Field name.
 * @return array Array with value and context data.
 */
private static function get_field_value( $object_type, $object_id, $field ) {
switch ( $object_type ) {
case 'post':
$post = get_post( $object_id );
if ( ! $post ) {
return array( null, array() );
}

$value = $post->$field;
return array( (string) $value, array( 'post' => $post ) );
               case 'postmeta':
                       global $wpdb;
                       $meta = $wpdb->get_row( $wpdb->prepare( "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_id = %d", $object_id ) );
if ( ! $meta ) {
return array( null, array() );
}

return array( (string) $meta->meta_value, array( 'meta' => $meta ) );
               case 'option':
                       global $wpdb;
                       $option = $wpdb->get_row( $wpdb->prepare( "SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_id = %d", $object_id ) );
if ( ! $option ) {
return array( null, array() );
}

return array( (string) $option->option_value, array( 'option' => $option ) );
}

return array( null, array() );
}

/**
 * Save field value.
 *
 * @param string $object_type Object type.
 * @param int    $object_id Object id.
 * @param string $field Field name.
 * @param string $value New value.
 * @param array  $context Context data.
 * @return bool|WP_Error
 */
private static function save_field_value( $object_type, $object_id, $field, $value, $context ) {
switch ( $object_type ) {
case 'post':
/** @var \WP_Post $post */
$post = $context['post'];
$args = array(
'ID' => $post->ID,
);
$args[ $field ] = $value;
            $result = wp_update_post( wp_slash( $args ), true );
if ( is_wp_error( $result ) ) {
return $result;
}

Indexer::index_field( 'post', (int) $post->ID, $field, (string) $value );
return true;
case 'postmeta':
$meta = $context['meta'];
$result = update_metadata_by_mid( 'post', $meta->meta_id, $value );
if ( ! $result ) {
return new WP_Error( 'ucc_meta_error', __( 'Unable to update post meta.', 'universal-color-changer' ) );
}

Indexer::index_field( 'postmeta', $meta->meta_id, $meta->meta_key, (string) $value );
return true;
case 'option':
$option = $context['option'];
$result = update_option( $option->option_name, $value );
if ( ! $result && $value !== get_option( $option->option_name ) ) {
return new WP_Error( 'ucc_option_error', __( 'Unable to update option.', 'universal-color-changer' ) );
}

Indexer::index_field( 'option', $option->option_id, $option->option_name, (string) $value );
return true;
}

return new WP_Error( 'ucc_unknown_type', __( 'Unknown object type.', 'universal-color-changer' ) );
}

/**
 * Calculate replacements for a string.
 *
 * @param string $value Value.
 * @param string $canonical Canonical color.
 * @param string $target Target color.
 * @return array
 */
private static function calculate_replacements( $value, $canonical, $target ) {
$matches = array();

if ( preg_match_all( Converter::HEX_REGEX, $value, $hex_matches, PREG_OFFSET_CAPTURE ) ) {
foreach ( $hex_matches[0] as $match ) {
if ( Converter::canonicalize( $match[0] ) === $canonical ) {
$matches[] = array(
'offset'      => (int) $match[1],
'length'      => strlen( $match[0] ),
'replacement' => Converter::apply_format( $match[0], $target ),
);
}
}
}

if ( preg_match_all( Converter::RGB_REGEX, $value, $rgb_matches, PREG_OFFSET_CAPTURE ) ) {
foreach ( $rgb_matches[0] as $match ) {
if ( Converter::canonicalize( $match[0] ) === $canonical ) {
$matches[] = array(
'offset'      => (int) $match[1],
'length'      => strlen( $match[0] ),
'replacement' => Converter::apply_format( $match[0], $target ),
);
}
}
}

usort(
$matches,
function( $a, $b ) {
return $a['offset'] === $b['offset'] ? 0 : ( $a['offset'] < $b['offset'] ? 1 : -1 );
}
);

return $matches;
}

/**
 * Fetch fields for a color.
 *
 * @param string   $canonical Canonical color.
 * @param int|null $changeset_id Changeset ID to exclude already changed fields.
 * @param int|null $limit Limit.
 * @return array
 */
private static function get_fields_for_color( $canonical, $changeset_id = null, $limit = null ) {
global $wpdb;

$table      = $wpdb->prefix . 'ucc_occurrences';
$changes    = $wpdb->prefix . 'ucc_changes';
$where      = $wpdb->prepare( 'color_found = %s', $canonical );
$exclusions = '';

if ( $changeset_id ) {
$exclusions = $wpdb->prepare( "AND CONCAT(object_type,'-',object_id,'-',field) NOT IN (SELECT CONCAT(object_type,'-',object_id,'-',field) FROM $changes WHERE changeset_id = %d)", $changeset_id );
}

$limit_sql = $limit ? $wpdb->prepare( 'LIMIT %d', $limit ) : '';

$query = "SELECT object_type, object_id, field, COUNT(*) as occurrence_count, MAX(last_indexed_at) as last_seen FROM $table WHERE $where $exclusions GROUP BY object_type, object_id, field ORDER BY last_seen DESC $limit_sql";

$fields = $wpdb->get_results( $query, ARRAY_A );

return $fields;
}

/**
 * Count remaining fields to update.
 *
 * @param string $canonical Canonical color.
 * @param int    $changeset_id Changeset ID.
 * @return int
 */
private static function count_remaining_fields( $canonical, $changeset_id ) {
$fields = self::get_fields_for_color( $canonical, $changeset_id );
return count( $fields );
}
}
