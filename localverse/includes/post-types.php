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
            'capability_type'       => 'localverse_listing', // Changed
            'capabilities'          => array(
                'edit_post'              => 'edit_localverse_listing',
                'read_post'              => 'read_localverse_listing',
                'delete_post'            => 'delete_localverse_listing',
                'edit_posts'             => 'edit_localverse_listings',
                'edit_others_posts'      => 'edit_others_localverse_listings',
                'publish_posts'          => 'publish_localverse_listings',
                'read_private_posts'     => 'read_private_localverse_listings',
                'delete_posts'           => 'delete_localverse_listings',
                'delete_private_posts'   => 'delete_private_localverse_listings',
                'delete_published_posts' => 'delete_published_localverse_listings',
                'delete_others_posts'    => 'delete_others_localverse_listings',
                'edit_private_posts'     => 'edit_private_localverse_listings',
                'edit_published_posts'   => 'edit_published_localverse_listings',
                // 'create_posts'           => 'create_localverse_listings', // Usually handled by publish_localverse_listings or edit_localverse_listings
            ),
            'map_meta_cap'          => true, // Important for custom capabilities
        'rewrite'               => array( 'slug' => 'listings' ),
        'show_in_rest'          => true, // For Gutenberg and REST API
    );
    register_post_type( 'localverse_listing', $args );
}
// Hook into 'init' to register the post type
add_action( 'init', 'localverse_register_listing_post_type', 0 );


/**
 * Register Review Custom Post Type.
 *
 * @since 0.1.0
 */
function localverse_register_review_post_type() {
    $labels = array(
        'name'                  => _x( 'Reviews', 'Post Type General Name', 'localverse' ),
        'singular_name'         => _x( 'Review', 'Post Type Singular Name', 'localverse' ),
        'menu_name'             => __( 'Reviews', 'localverse' ),
        'name_admin_bar'        => __( 'Review', 'localverse' ),
        'archives'              => __( 'Review Archives', 'localverse' ),
        'attributes'            => __( 'Review Attributes', 'localverse' ),
        'parent_item_colon'     => __( 'Parent Review:', 'localverse' ),
        'all_items'             => __( 'All Reviews', 'localverse' ),
        'add_new_item'          => __( 'Add New Review', 'localverse' ),
        'add_new'               => __( 'Add New', 'localverse' ), // For admin UI "Add New" button
        'new_item'              => __( 'New Review', 'localverse' ),
        'edit_item'             => __( 'Edit Review', 'localverse' ),
        'update_item'           => __( 'Update Review', 'localverse' ),
        'view_item'             => __( 'View Review', 'localverse' ),
        'view_items'            => __( 'View Reviews', 'localverse' ),
        'search_items'          => __( 'Search Review', 'localverse' ),
        'not_found'             => __( 'No reviews found', 'localverse' ),
        'not_found_in_trash'    => __( 'No reviews found in Trash', 'localverse' ),
        'featured_image'        => __( 'Review Image', 'localverse' ), // Though we'll use custom meta for multiple images
        'set_featured_image'    => __( 'Set review image', 'localverse' ),
        'remove_featured_image' => __( 'Remove review image', 'localverse' ),
        'use_featured_image'    => __( 'Use as review image', 'localverse' ),
        'insert_into_item'      => __( 'Insert into review', 'localverse' ),
        'uploaded_to_this_item' => __( 'Uploaded to this review', 'localverse' ),
        'items_list'            => __( 'Reviews list', 'localverse' ),
        'items_list_navigation' => __( 'Reviews list navigation', 'localverse' ),
        'filter_items_list'     => __( 'Filter reviews list', 'localverse' ),
    );
    $args = array(
        'label'                 => __( 'Review', 'localverse' ),
        'description'           => __( 'User reviews for business listings.', 'localverse' ),
        'labels'                => $labels,
        'supports'              => array( 'author', 'editor', /* 'title' - title can be auto-generated */ 'comments' /* for replies */ ),
        'hierarchical'          => false,
        'public'                => false, // Not publicly queryable as a standalone archive
        'show_ui'               => true,  // Show in admin UI
        'show_in_menu'          => true,  // Show as a top-level menu item. Can be 'edit.php?post_type=localverse_listing' to be a sub-menu
        'menu_position'         => 26,    // Below Listings (assuming Listings is at 25 or higher if default is 5)
        'menu_icon'             => 'dashicons-star-half',
        'show_in_admin_bar'     => true,
        'show_in_nav_menus'     => false, // Usually not needed for reviews
        'can_export'            => true,
        'has_archive'           => false, // No public archive page for reviews themselves
        'exclude_from_search'   => true,  // Exclude from front-end site search (usually)
        'publicly_queryable'    => false, // As public is false

        'capability_type'       => 'localverse_review', // Singular name for capability type
        'capabilities'          => array(
            'edit_post'           => 'edit_localverse_review',
            'read_post'           => 'read_localverse_review',
            'delete_post'         => 'delete_localverse_review',
            'edit_posts'          => 'edit_localverse_reviews',
            'edit_others_posts'   => 'edit_others_localverse_reviews',
            'publish_posts'       => 'publish_localverse_reviews',
            'read_private_posts'  => 'read_private_localverse_reviews',
            'delete_posts'        => 'delete_localverse_reviews',
            'delete_private_posts' => 'delete_private_localverse_reviews',
            'delete_published_posts' => 'delete_published_localverse_reviews',
            'delete_others_posts' => 'delete_others_localverse_reviews',
            'edit_private_posts'  => 'edit_private_localverse_reviews',
            'edit_published_posts' => 'edit_published_localverse_reviews',
        ),
        'map_meta_cap'          => true, // Important for mapping meta capabilities

        'rewrite'               => false, // No rewrite rules needed if not public
        'show_in_rest'          => true,  // Allow access via REST API, e.g., for JS interactions or app
    );
    register_post_type( 'localverse_review', $args );
}
// Hook into 'init' to register all post types
// Ensure this doesn't conflict if localverse_register_listing_post_type also hooks to init.
// It's fine to have multiple functions hooked to the same action.
// Or, create a single function that calls both registration functions.

// Let's assume localverse_register_listing_post_type is already hooked to init.
// We can add this one too.
add_action( 'init', 'localverse_register_review_post_type', 0 );


// It's good practice to have a single function that calls all CPT/taxonomy registrations,
// hooked once to 'init'. For example:
/*
function localverse_register_all_post_types() {
    localverse_register_listing_post_type();
    localverse_register_review_post_type();
}
add_action( 'init', 'localverse_register_all_post_types', 0 );
*/
// For this subtask, just adding the new hook is fine. If localverse_register_listing_post_type
// is already hooked, this will just add another function to the 'init' action queue.
?>
