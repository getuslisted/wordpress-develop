<?php
/**
 * Registers custom taxonomies for the LocalVerse plugin.
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
 * Register Listing Category Taxonomy.
 *
 * @since 0.1.0
 */
function localverse_register_listing_category_taxonomy() {
    $labels = array(
        'name'                       => _x( 'Listing Categories', 'Taxonomy General Name', 'localverse' ),
        'singular_name'              => _x( 'Listing Category', 'Taxonomy Singular Name', 'localverse' ),
        'menu_name'                  => __( 'Categories', 'localverse' ),
        'all_items'                  => __( 'All Categories', 'localverse' ),
        'parent_item'                => __( 'Parent Category', 'localverse' ),
        'parent_item_colon'          => __( 'Parent Category:', 'localverse' ),
        'new_item_name'              => __( 'New Category Name', 'localverse' ),
        'add_new_item'               => __( 'Add New Category', 'localverse' ),
        'edit_item'                  => __( 'Edit Category', 'localverse' ),
        'update_item'                => __( 'Update Category', 'localverse' ),
        'view_item'                  => __( 'View Category', 'localverse' ),
        'separate_items_with_commas' => __( 'Separate categories with commas', 'localverse' ),
        'add_or_remove_items'        => __( 'Add or remove categories', 'localverse' ),
        'choose_from_most_used'      => __( 'Choose from the most used', 'localverse' ),
        'popular_items'              => __( 'Popular Categories', 'localverse' ),
        'search_items'               => __( 'Search Categories', 'localverse' ),
        'not_found'                  => __( 'Not Found', 'localverse' ),
        'no_terms'                   => __( 'No categories', 'localverse' ),
        'items_list'                 => __( 'Categories list', 'localverse' ),
        'items_list_navigation'      => __( 'Categories list navigation', 'localverse' ),
    );
    $args = array(
        'labels'                     => $labels,
        'hierarchical'               => true,
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => false, // Usually false for categories
        'rewrite'                    => array( 'slug' => 'listing-category' ),
        'show_in_rest'               => true, // Enable for Gutenberg and REST API
    );
    register_taxonomy( 'listing_category', array( 'localverse_listing' ), $args );
}
add_action( 'init', 'localverse_register_listing_category_taxonomy', 0 );

/**
 * Register Listing Tag Taxonomy.
 *
 * @since 0.1.0
 */
function localverse_register_listing_tag_taxonomy() {
    $labels = array(
        'name'                       => _x( 'Listing Tags', 'Taxonomy General Name', 'localverse' ),
        'singular_name'              => _x( 'Listing Tag', 'Taxonomy Singular Name', 'localverse' ),
        'menu_name'                  => __( 'Tags', 'localverse' ),
        'all_items'                  => __( 'All Tags', 'localverse' ),
        'parent_item'                => null, // Tags are not hierarchical
        'parent_item_colon'          => null, // Tags are not hierarchical
        'new_item_name'              => __( 'New Tag Name', 'localverse' ),
        'add_new_item'               => __( 'Add New Tag', 'localverse' ),
        'edit_item'                  => __( 'Edit Tag', 'localverse' ),
        'update_item'                => __( 'Update Tag', 'localverse' ),
        'view_item'                  => __( 'View Tag', 'localverse' ),
        'separate_items_with_commas' => __( 'Separate tags with commas', 'localverse' ),
        'add_or_remove_items'        => __( 'Add or remove tags', 'localverse' ),
        'choose_from_most_used'      => __( 'Choose from the most used tags', 'localverse' ),
        'popular_items'              => __( 'Popular Tags', 'localverse' ),
        'search_items'               => __( 'Search Tags', 'localverse' ),
        'not_found'                  => __( 'Not Found', 'localverse' ),
        'no_terms'                   => __( 'No tags', 'localverse' ),
        'items_list'                 => __( 'Tags list', 'localverse' ),
        'items_list_navigation'      => __( 'Tags list navigation', 'localverse' ),
    );
    $args = array(
        'labels'                     => $labels,
        'hierarchical'               => false,
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => true,
        'show_tagcloud'              => true,
        'rewrite'                    => array( 'slug' => 'listing-tag' ),
        'show_in_rest'               => true, // Enable for Gutenberg and REST API
    );
    register_taxonomy( 'listing_tag', array( 'localverse_listing' ), $args );
}
add_action( 'init', 'localverse_register_listing_tag_taxonomy', 0 );

?>
