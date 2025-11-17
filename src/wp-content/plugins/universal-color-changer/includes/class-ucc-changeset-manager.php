<?php
/**
 * Changeset management.
 *
 * @package UniversalColorChanger
 */

namespace UCC;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Changeset Manager.
 */
class Changeset_Manager {
/**
 * Threshold for compressing stored values.
 *
 * @var int
 */
const COMPRESSION_THRESHOLD = 100000;
/**
 * Create a changeset record.
 *
 * @param string $source Source color.
 * @param string $target Target color.
 * @return int
 */
public static function create_changeset( $source, $target ) {
global $wpdb;
$table = $wpdb->prefix . 'ucc_changesets';
$wpdb->insert(
$table,
array(
'created_at'   => current_time( 'mysql' ),
'created_by'   => get_current_user_id(),
'source_color' => $source,
'target_color' => $target,
'item_count'   => 0,
'status'       => 'running',
'nonce'        => wp_generate_password( 12, false ),
),
array( '%s', '%d', '%s', '%s', '%d', '%s', '%s' )
);

return (int) $wpdb->insert_id;
}

/**
 * Record a change.
 *
 * @param int    $changeset_id Changeset ID.
 * @param string $object_type Object type.
 * @param int    $object_id Object id.
 * @param string $field Field name.
 * @param string $before Before value.
 * @param string $after After value.
 * @return void
 */
public static function record_change( $changeset_id, $object_type, $object_id, $field, $before, $after ) {
global $wpdb;
$table = $wpdb->prefix . 'ucc_changes';

$before_serialized = maybe_serialize( $before );
$after_serialized  = maybe_serialize( $after );

$wpdb->insert(
$table,
array(
'changeset_id' => $changeset_id,
'object_type'  => $object_type,
'object_id'    => $object_id,
'field'        => $field,
'before_hash'  => sha1( $before_serialized ),
'after_hash'   => sha1( $after_serialized ),
'before_value' => self::encode_value( $before_serialized ),
'after_value'  => self::encode_value( $after_serialized ),
),
array( '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
);

$changesets_table = $wpdb->prefix . 'ucc_changesets';
$wpdb->query( $wpdb->prepare( "UPDATE $changesets_table SET item_count = item_count + 1 WHERE id = %d", $changeset_id ) );
}

/**
 * Complete a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return void
 */
public static function complete_changeset( $changeset_id ) {
global $wpdb;
$table = $wpdb->prefix . 'ucc_changesets';
$wpdb->update(
$table,
array( 'status' => 'completed' ),
array( 'id' => $changeset_id ),
array( '%s' ),
array( '%d' )
);
}

/**
 * Fetch history list.
 *
 * @param int $limit Limit.
 * @return array
 */
public static function get_history( $limit = 20 ) {
global $wpdb;
$table = $wpdb->prefix . 'ucc_changesets';
$query = $wpdb->prepare( "SELECT * FROM $table ORDER BY id DESC LIMIT %d", $limit );
$rows  = $wpdb->get_results( $query, ARRAY_A );

return $rows;
}

/**
 * Undo a changeset.
 *
 * @param int $changeset_id Changeset ID.
 * @return array|WP_Error
 */
public static function undo_changeset( $changeset_id ) {
global $wpdb;

$changeset = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ucc_changesets WHERE id = %d", $changeset_id ) );
if ( ! $changeset ) {
return new WP_Error( 'ucc_missing_changeset', __( 'Changeset not found.', 'universal-color-changer' ) );
}

$changes = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ucc_changes WHERE changeset_id = %d ORDER BY id DESC", $changeset_id ) );

$undo_count = 0;
foreach ( $changes as $change ) {
$result = self::restore_change( $change, 'before_value' );
if ( is_wp_error( $result ) ) {
return $result;
}
$undo_count++;
}

$wpdb->update(
$wpdb->prefix . 'ucc_changesets',
array( 'status' => 'undone' ),
array( 'id' => $changeset_id ),
array( '%s' ),
array( '%d' )
);

return array(
'changeset_id' => $changeset_id,
'undone'       => $undo_count,
);
}

/**
 * Undo all changesets.
 *
 * @return array
 */
public static function undo_all() {
$history = self::get_history( 1000 );
$results = array();
foreach ( $history as $changeset ) {
if ( 'undone' === $changeset['status'] ) {
continue;
}
$results[] = self::undo_changeset( (int) $changeset['id'] );
}

return $results;
}

/**
 * Restore a change.
 *
 * @param object $change Change row.
 * @param string $column Column to use (before_value/after_value).
 * @return bool|WP_Error
 */
private static function restore_change( $change, $column ) {
$serialized = self::decode_value( $change->$column );
$value      = maybe_unserialize( $serialized );

switch ( $change->object_type ) {
case 'post':
$args = array(
'ID' => (int) $change->object_id,
);
$args[ $change->field ] = $value;
        $result = wp_update_post( wp_slash( $args ), true );
if ( is_wp_error( $result ) ) {
return $result;
}
Indexer::index_field( 'post', (int) $change->object_id, $change->field, (string) $value );
return true;
case 'postmeta':
$result = update_metadata_by_mid( 'post', (int) $change->object_id, $value );
if ( ! $result ) {
return new WP_Error( 'ucc_meta_undo_error', __( 'Unable to restore post meta.', 'universal-color-changer' ) );
}
Indexer::index_field( 'postmeta', (int) $change->object_id, $change->field, (string) $value );
return true;
case 'option':
global $wpdb;
$option = $wpdb->get_var( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_id = %d", $change->object_id ) );
if ( ! $option ) {
return new WP_Error( 'ucc_option_missing', __( 'Option not found during undo.', 'universal-color-changer' ) );
}
update_option( $option, $value );
Indexer::index_field( 'option', (int) $change->object_id, $option, (string) $value );
return true;
}

return new WP_Error( 'ucc_unknown_type', __( 'Unknown object type during undo.', 'universal-color-changer' ) );
}

/**
 * Encode a serialized value for storage.
 *
 * @param string $serialized Serialized string.
 * @return string
 */
private static function encode_value( $serialized ) {
if ( strlen( $serialized ) >= self::COMPRESSION_THRESHOLD && function_exists( 'gzencode' ) && function_exists( 'gzdecode' ) ) {
$compressed = gzencode( $serialized, 6 );
if ( false !== $compressed ) {
return 'gz:' . base64_encode( $compressed );
}
}

return 'raw:' . $serialized;
}

/**
 * Decode a stored value back to its serialized form.
 *
 * @param string $stored Stored value.
 * @return string
 */
private static function decode_value( $stored ) {
if ( 0 === strpos( $stored, 'gz:' ) ) {
$encoded = substr( $stored, 3 );
$decoded = base64_decode( $encoded, true );
if ( false !== $decoded && function_exists( 'gzdecode' ) ) {
$decompressed = @gzdecode( $decoded );
if ( false !== $decompressed ) {
return $decompressed;
}
}

return $encoded;
}

if ( 0 === strpos( $stored, 'raw:' ) ) {
return substr( $stored, 4 );
}

return $stored;
}
}
