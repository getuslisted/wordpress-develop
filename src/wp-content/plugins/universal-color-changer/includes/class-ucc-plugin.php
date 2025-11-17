<?php
/**
 * Main plugin bootstrap.
 *
 * @package UniversalColorChanger
 */

namespace UCC;

use WP_Error;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Plugin class.
 */
class Plugin {
/**
 * Initialize hooks.
 */
public static function init() {
self::includes();
Admin::init();
REST_Controller::init();
}

/**
 * Include class files.
 */
private static function includes() {
require_once __DIR__ . '/class-ucc-admin.php';
require_once __DIR__ . '/class-ucc-rest-controller.php';
require_once __DIR__ . '/class-ucc-indexer.php';
require_once __DIR__ . '/class-ucc-converter.php';
require_once __DIR__ . '/class-ucc-replacer.php';
require_once __DIR__ . '/class-ucc-changeset-manager.php';
}

/**
 * Activation callback.
 */
public static function activate() {
global $wpdb;

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

$charset_collate = $wpdb->get_charset_collate();

$occurrences_table = $wpdb->prefix . 'ucc_occurrences';
$changesets_table  = $wpdb->prefix . 'ucc_changesets';
$changes_table     = $wpdb->prefix . 'ucc_changes';

$sql = "CREATE TABLE $occurrences_table (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
object_type varchar(20) NOT NULL,
object_id bigint(20) unsigned NOT NULL,
field varchar(191) NOT NULL,
color_found varchar(50) NOT NULL,
format_found varchar(20) NOT NULL,
pattern_text text NOT NULL,
context_excerpt text NULL,
field_hash char(40) NOT NULL,
last_indexed_at datetime NOT NULL,
PRIMARY KEY (id),
KEY object_lookup (object_type, object_id),
KEY color_lookup (color_found)
) $charset_collate;";

dbDelta( $sql );

$sql = "CREATE TABLE $changesets_table (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
created_at datetime NOT NULL,
created_by bigint(20) unsigned NOT NULL,
source_color varchar(50) NOT NULL,
target_color varchar(50) NOT NULL,
item_count int NOT NULL DEFAULT 0,
status varchar(20) NOT NULL,
nonce varchar(64) NOT NULL,
PRIMARY KEY (id)
) $charset_collate;";

dbDelta( $sql );

$sql = "CREATE TABLE $changes_table (
id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
changeset_id bigint(20) unsigned NOT NULL,
object_type varchar(20) NOT NULL,
object_id bigint(20) unsigned NOT NULL,
field varchar(191) NOT NULL,
before_hash char(40) NOT NULL,
after_hash char(40) NOT NULL,
before_value longtext NOT NULL,
after_value longtext NOT NULL,
PRIMARY KEY (id),
KEY changeset_lookup (changeset_id),
KEY object_lookup (object_type, object_id)
) $charset_collate;";

dbDelta( $sql );
}
}
