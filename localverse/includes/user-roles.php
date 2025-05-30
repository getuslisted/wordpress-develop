<?php
/**
 * Manages user roles and capabilities for the LocalVerse plugin.
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

class LocalVerse_User_Roles {

    /**
     * Adds custom user roles and assigns capabilities on plugin activation.
     *
     * @since 0.1.0
     */
    public static function add_roles_on_activation() {
        // --- Listing Capabilities (existing) ---
        $listing_caps_list = array(
            'edit_localverse_listings', 'edit_others_localverse_listings',
            'publish_localverse_listings', 'read_private_localverse_listings',
            'delete_localverse_listings', 'delete_private_localverse_listings',
            'delete_published_localverse_listings', 'delete_others_localverse_listings',
            'edit_private_localverse_listings', 'edit_published_localverse_listings',
            // These are meta caps, usually not directly assigned to roles unless specific needs.
            // They are derived from primitive caps like edit_posts, edit_others_posts etc.
            // 'edit_localverse_listing', 'read_localverse_listing', 'delete_localverse_listing',
        );

        // --- Review Capabilities (New) ---
        $review_caps_list = array(
            'edit_localverse_review',    // Corresponds to edit_post for a single review
            'read_localverse_review',    // Corresponds to read_post for a single review
            'delete_localverse_review',  // Corresponds to delete_post for a single review
            'edit_localverse_reviews',   // Corresponds to edit_posts (plural, for managing all reviews of this type)
            'edit_others_localverse_reviews',
            'publish_localverse_reviews',
            'read_private_localverse_reviews',
            'delete_localverse_reviews', // Primitive cap for deleting reviews (plural)
            'delete_private_localverse_reviews',
            'delete_published_localverse_reviews',
            'delete_others_localverse_reviews',
            'edit_private_localverse_reviews',
            'edit_published_localverse_reviews',
            'submit_localverse_review', // Custom capability to control who can submit a review
        );

        // Business Owner Role (Add submit_localverse_review)
        $business_owner_caps = array(
            'read'                               => true,
            'publish_localverse_listings'        => true,
            'edit_localverse_listings'           => true, // for their own listings
            'delete_localverse_listings'         => true, // for their own listings
            'upload_files'                       => true,
            'read_private_localverse_listings'   => true,
            'edit_published_localverse_listings' => true,
            'delete_published_localverse_listings' => true,
            'submit_localverse_review'           => true, // Can submit reviews
        );
        $business_owner_role = get_role('business_owner');
        if (!$business_owner_role) {
            add_role('business_owner', __('Business Owner', 'localverse'), $business_owner_caps);
        } else {
            foreach ($business_owner_caps as $cap => $grant) {
                $business_owner_role->add_cap($cap, $grant);
            }
        }

        // Local User Role (Add submit_localverse_review)
        $local_user_caps = array(
            'read' => true,
            'submit_localverse_review' => true, // Can submit reviews
        );
        $local_user_role = get_role('local_user');
        if (!$local_user_role) {
            add_role('local_user', __('Local User', 'localverse'), $local_user_caps);
        } else {
            foreach ($local_user_caps as $cap => $grant) {
                $local_user_role->add_cap($cap, $grant);
            }
        }

        // Add all listing AND review capabilities to Administrator
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            // Add caps that might be part of CPT registration's 'capabilities' array (singular forms)
            // if not covered by map_meta_cap handling for 'edit_posts' etc.
            $admin_role->add_cap( 'edit_localverse_listing' ); // For CPT 'edit_post'
            $admin_role->add_cap( 'delete_localverse_listing' ); // For CPT 'delete_post'
            $admin_role->add_cap( 'read_localverse_listing' );   // For CPT 'read_post' (usually covered by read)

            foreach ( $listing_caps_list as $cap ) {
                $admin_role->add_cap( $cap );
            }
            foreach ( $review_caps_list as $cap ) {
                $admin_role->add_cap( $cap );
            }
        }
    }

    /**
     * Removes custom user roles and capabilities on plugin deactivation.
     *
     * @since 0.1.0
     */
    public static function remove_roles_on_deactivation() {
        // Business Owner and Local User roles and their specific caps are removed by remove_role()
        remove_role( 'business_owner' );
        remove_role( 'local_user' );

        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            // Define lists again or make them class properties if preferred
            $listing_caps_list = array(
                'edit_localverse_listings', 'edit_others_localverse_listings',
                'publish_localverse_listings', 'read_private_localverse_listings',
                'delete_localverse_listings', 'delete_private_localverse_listings',
                'delete_published_localverse_listings', 'delete_others_localverse_listings',
                'edit_private_localverse_listings', 'edit_published_localverse_listings',
                'edit_localverse_listing', 'read_localverse_listing', 'delete_localverse_listing',
            );
            $review_caps_list = array(
                'edit_localverse_review', 'read_localverse_review', 'delete_localverse_review',
                'edit_localverse_reviews', 'edit_others_localverse_reviews',
                'publish_localverse_reviews', 'read_private_localverse_reviews',
                'delete_localverse_reviews', 'delete_private_localverse_reviews',
                'delete_published_localverse_reviews', 'delete_others_localverse_reviews',
                'edit_private_localverse_reviews', 'edit_published_localverse_reviews',
                'submit_localverse_review',
            );

            foreach ( $listing_caps_list as $cap ) {
                $admin_role->remove_cap( $cap );
            }
            foreach ( $review_caps_list as $cap ) {
                $admin_role->remove_cap( $cap );
            }
        }
    }
}
?>
