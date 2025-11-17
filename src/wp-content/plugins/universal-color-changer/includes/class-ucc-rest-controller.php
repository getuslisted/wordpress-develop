<?php
/**
 * REST controller.
 *
 * @package UniversalColorChanger
 */

namespace UCC;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * REST Controller class.
 */
class REST_Controller {
/**
 * Namespace.
 */
const REST_NAMESPACE = 'universal-color-changer/v1';

/**
 * Initialize hooks.
 */
public static function init() {
add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
}

/**
 * Register REST routes.
 */
public static function register_routes() {
register_rest_route(
self::REST_NAMESPACE,
'/status',
array(
'permission_callback' => array( __CLASS__, 'permission_check' ),
'methods'             => WP_REST_Server::READABLE,
'callback'            => array( __CLASS__, 'get_status' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/index',
array(
'permission_callback' => array( __CLASS__, 'permission_check' ),
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => array( __CLASS__, 'post_index' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/colors',
array(
'permission_callback' => array( __CLASS__, 'permission_check' ),
'methods'             => WP_REST_Server::READABLE,
'callback'            => array( __CLASS__, 'get_colors' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/by-color',
array(
'permission_callback' => array( __CLASS__, 'permission_check' ),
'methods'             => WP_REST_Server::READABLE,
'callback'            => array( __CLASS__, 'get_by_color' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/by-post',
array(
'permission_callback' => array( __CLASS__, 'permission_check' ),
'methods'             => WP_REST_Server::READABLE,
'callback'            => array( __CLASS__, 'get_by_post' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/dry-run',
array(
'permission_callback' => array( __CLASS__, 'permission_check' ),
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => array( __CLASS__, 'post_dry_run' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/apply',
array(
'permission_callback' => array( __CLASS__, 'permission_check' ),
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => array( __CLASS__, 'post_apply' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/undo',
array(
'permission_callback' => array( __CLASS__, 'permission_check' ),
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => array( __CLASS__, 'post_undo' ),
)
);

register_rest_route(
self::REST_NAMESPACE,
'/undo-all',
array(
'permission_callback' => array( __CLASS__, 'permission_check' ),
'methods'             => WP_REST_Server::CREATABLE,
'callback'            => array( __CLASS__, 'post_undo_all' ),
)
);
}

/**
 * Permission check.
 *
 * @return bool
 */
public static function permission_check() {
return current_user_can( 'manage_options' );
}

/**
 * Get status.
 *
 * @return WP_REST_Response
 */
public static function get_status() {
$status             = Indexer::get_status();
$status['history']  = Changeset_Manager::get_history();
return rest_ensure_response( $status );
}

/**
 * Run index.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
    public static function post_index( WP_REST_Request $request ) {
        $nonce_check = self::validate_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }
$batch_size = (int) $request->get_param( 'batch' );
if ( $batch_size <= 0 ) {
$batch_size = 25;
}

$config = array();
if ( null !== $request->get_param( 'scan_titles' ) ) {
$config['scan_titles'] = (bool) $request->get_param( 'scan_titles' );
}
if ( null !== $request->get_param( 'scan_excerpts' ) ) {
$config['scan_excerpts'] = (bool) $request->get_param( 'scan_excerpts' );
}
if ( null !== $request->get_param( 'postmeta_mode' ) ) {
$config['postmeta_mode'] = sanitize_text_field( $request->get_param( 'postmeta_mode' ) );
}

if ( ! empty( $config ) ) {
Indexer::update_config( $config );
}

if ( $request->get_param( 'reset' ) ) {
Indexer::reset();
}

$result = Indexer::index_batch( $batch_size );

return rest_ensure_response( $result );
}

/**
 * Get colors grouped.
 *
 * @return WP_REST_Response
 */
public static function get_colors() {
return rest_ensure_response( Indexer::get_color_groups() );
}

/**
 * Get items by color.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
public static function get_by_color( WP_REST_Request $request ) {
$color = sanitize_text_field( (string) $request->get_param( 'color' ) );
return rest_ensure_response( Indexer::get_items_by_color( $color ) );
}

/**
 * Get colors by post.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
public static function get_by_post( WP_REST_Request $request ) {
$post_id = (int) $request->get_param( 'post_id' );
return rest_ensure_response( Indexer::get_colors_by_post( $post_id ) );
}

/**
 * Dry run replacements.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
    public static function post_dry_run( WP_REST_Request $request ) {
        $nonce_check = self::validate_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }
$source = sanitize_text_field( (string) $request->get_param( 'source' ) );
$target = sanitize_text_field( (string) $request->get_param( 'target' ) );

$validation = Converter::sanitize_target_color( $target );
if ( is_wp_error( $validation ) ) {
return $validation;
}

return rest_ensure_response( Replacer::dry_run( $source, $validation ) );
}

/**
 * Apply replacements.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
    public static function post_apply( WP_REST_Request $request ) {
        $nonce_check = self::validate_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }
$source      = sanitize_text_field( (string) $request->get_param( 'source' ) );
$target      = sanitize_text_field( (string) $request->get_param( 'target' ) );
$changeset   = (int) $request->get_param( 'changeset_id' );
$batch       = (int) $request->get_param( 'batch' );
$validation  = Converter::sanitize_target_color( $target );
if ( is_wp_error( $validation ) ) {
return $validation;
}

$result = Replacer::apply( $source, $validation, $changeset, $batch );

return rest_ensure_response( $result );
}

/**
 * Undo a changeset.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response|WP_Error
 */
    public static function post_undo( WP_REST_Request $request ) {
        $nonce_check = self::validate_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }
$changeset = (int) $request->get_param( 'changeset_id' );

$result = Changeset_Manager::undo_changeset( $changeset );

return rest_ensure_response( $result );
}

/**
 * Undo all changesets.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
    public static function post_undo_all( WP_REST_Request $request ) {
        $nonce_check = self::validate_nonce( $request );
        if ( is_wp_error( $nonce_check ) ) {
            return $nonce_check;
        }

        return rest_ensure_response( Changeset_Manager::undo_all() );
}

/**
 * Validate nonce.
 *
 * @param WP_REST_Request $request Request.
 * @return void
 */
    private static function validate_nonce( WP_REST_Request $request ) {
        $nonce = $request->get_header( 'X-WP-Nonce' );
        if ( ! $nonce || ! wp_verify_nonce( $nonce, self::REST_NAMESPACE ) ) {
            return new WP_Error(
                'ucc_invalid_nonce',
                __( 'Invalid nonce.', 'universal-color-changer' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }
}
