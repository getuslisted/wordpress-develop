<?php
/**
 * Registers the custom post types for the LocalVerse plugin.
 *
 * @link       https://example.com
 * @since      0.1.0
 *
 * @package    LocalVerse
 * @subpackage LocalVerse/includes
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Register Business Listing Post Type.
 *
 * @since 0.1.0
 */
function localverse_register_listing_post_type() {
    $labels = array(
        'name'                  => _x( 'Business Listings', 'Post Type General Name', 'localverse' ),
        'singular_name'         => _x( 'Business Listing', 'Post Type Singular Name', 'localverse' ),
        'menu_name'             => __( 'Listings', 'localverse' ),
        'name_admin_bar'        => __( 'Business Listing', 'localverse' ),
        'archives'              => __( 'Listing Archives', 'localverse' ),
        'attributes'            => __( 'Listing Attributes', 'localverse' ),
        'parent_item_colon'     => __( 'Parent Listing:', 'localverse' ),
        'all_items'             => __( 'All Listings', 'localverse' ),
        'add_new_item'          => __( 'Add New Listing', 'localverse' ),
        'add_new'               => __( 'Add New', 'localverse' ),
        'new_item'              => __( 'New Listing', 'localverse' ),
        'edit_item'             => __( 'Edit Listing', 'localverse' ),
        'update_item'           => __( 'Update Listing', 'localverse' ),
        'view_item'             => __( 'View Listing', 'localverse' ),
        'view_items'            => __( 'View Listings', 'localverse' ),
        'search_items'          => __( 'Search Listing', 'localverse' ),
        'not_found'             => __( 'Not found', 'localverse' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'localverse' ),
        'featured_image'        => __( 'Featured Image', 'localverse' ),
        'set_featured_image'    => __( 'Set featured image', 'localverse' ),
        'remove_featured_image' => __( 'Remove featured image', 'localverse' ),
        'use_featured_image'    => __( 'Use as featured image', 'localverse' ),
        'insert_into_item'      => __( 'Insert into listing', 'localverse' ),
        'uploaded_to_this_item' => __( 'Uploaded to this listing', 'localverse' ),
        'items_list'            => __( 'Listings list', 'localverse' ),
        'items_list_navigation' => __( 'Listings list navigation', 'localverse' ),
        'filter_items_list'     => __( 'Filter listings list', 'localverse' ),
    );
    $args = array(
        'label'                 => __( 'Business Listing', 'localverse' ),
        'description'           => __( 'Directory listings for businesses.', 'localverse' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor', 'excerpt', 'author', 'thumbnail', 'comments', 'revisions', 'custom-fields' ),
        'taxonomies'            => array( /* 'listing_category', 'listing_tag' - will add later */ ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-store',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => true,
        'can_export'            => true,
        'has_archive'           => true,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'capability_type'       => 'post', // Consider 'localverse_listing' for custom capabilities later
        'rewrite'               => array( 'slug' => 'listings' ),
        'show_in_rest'          => true, // For Gutenberg and REST API
    );
    register_post_type( 'localverse_listing', $args );
}
// Hook into 'init' to register the post type
add_action( 'init', 'localverse_register_listing_post_type', 0 );

?>
