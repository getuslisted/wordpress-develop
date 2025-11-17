<?php
/**
 * Plugin Name: Universal Color Changer
 * Description: Bulk find and replace literal color values across WordPress content with preview and undo support.
 * Version: 1.0.0
 * Author: WordPress Contributors
 * License: GPL-2.0-or-later
 * Text Domain: universal-color-changer
 * @package UniversalColorChanger
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

require_once __DIR__ . '/includes/class-ucc-plugin.php';

define( 'UCC_PLUGIN_VERSION', '1.0.0' );
define( 'UCC_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'UCC_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

define( 'UCC_DB_VERSION', '1.0.0' );

register_activation_hook( __FILE__, array( 'UCC\\Plugin', 'activate' ) );

add_action( 'plugins_loaded', array( 'UCC\\Plugin', 'init' ) );
