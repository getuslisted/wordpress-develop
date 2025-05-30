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
            // Primitive caps for CPT, mapped in CPT registration from edit_post etc.
            'edit_localverse_listing', 'read_localverse_listing', 'delete_localverse_listing',
        );

        // --- Review Capabilities (existing) ---
        $review_caps_list = array(
            'edit_localverse_review', 'read_localverse_review', 'delete_localverse_review',
            'edit_localverse_reviews', 'edit_others_localverse_reviews',
            'publish_localverse_reviews', 'read_private_localverse_reviews',
            'delete_localverse_reviews', 'delete_private_localverse_reviews',
            'delete_published_localverse_reviews', 'delete_others_localverse_reviews',
            'edit_private_localverse_reviews', 'edit_published_localverse_reviews',
            'submit_localverse_review',
        );

        // --- Event Capabilities (New) ---
        $event_caps_list = array(
            'edit_localverse_event',    // Corresponds to edit_post for a single event
            'read_localverse_event',    // Corresponds to read_post for a single event
            'delete_localverse_event',  // Corresponds to delete_post for a single event
            'edit_localverse_events',   // Corresponds to edit_posts (plural)
            'edit_others_localverse_events',
            'publish_localverse_events',
            'read_private_localverse_events',
            'delete_localverse_events', // Primitive cap for deleting events (plural)
            'delete_private_localverse_events',
            'delete_published_localverse_events',
            'delete_others_localverse_events',
            'edit_private_localverse_events',
            'edit_published_localverse_events',
            'submit_localverse_event', // Custom capability to control who can submit an event
        );

        // Business Owner Role (Add submit_localverse_event)
        $business_owner_caps = array(
            'read'                               => true,
            'publish_localverse_listings'        => true,
            'edit_localverse_listings'           => true, // for their own listings
            'delete_localverse_listings'         => true, // for their own listings
            'upload_files'                       => true,
            'read_private_localverse_listings'   => true,
            'edit_published_localverse_listings' => true,
            'delete_published_localverse_listings' => true,
            'submit_localverse_review'           => true,
            'submit_localverse_event'            => true, // ADDED
        );
        $business_owner_role = get_role('business_owner');
        if (!$business_owner_role) {
            add_role('business_owner', __('Business Owner', 'localverse'), $business_owner_caps);
        } else {
            foreach ($business_owner_caps as $cap => $grant) {
                $business_owner_role->add_cap($cap, $grant);
            }
        }

        // Local User Role (Add submit_localverse_event)
        $local_user_caps = array(
            'read' => true,
            'submit_localverse_review' => true,
            'submit_localverse_event'  => true, // ADDED
        );
        $local_user_role = get_role('local_user');
        if (!$local_user_role) {
            add_role('local_user', __('Local User', 'localverse'), $local_user_caps);
        } else {
            foreach ($local_user_caps as $cap => $grant) {
                $local_user_role->add_cap($cap, $grant);
            }
        }

        // Add all listing, review, AND event capabilities to Administrator
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            // Add primitive caps for CPTs that are mapped from 'edit_post', 'read_post', 'delete_post'
            // These are good to add explicitly for completeness, though map_meta_cap handles many scenarios.
            $admin_role->add_cap( 'edit_localverse_listing' );
            $admin_role->add_cap( 'read_localverse_listing' );
            $admin_role->add_cap( 'delete_localverse_listing' );
            $admin_role->add_cap( 'edit_localverse_review' );
            $admin_role->add_cap( 'read_localverse_review' );
            $admin_role->add_cap( 'delete_localverse_review' );
            // Event primitive caps will be in $event_caps_list

            foreach ( $listing_caps_list as $cap ) { $admin_role->add_cap( $cap ); }
            foreach ( $review_caps_list as $cap ) { $admin_role->add_cap( $cap ); }
            foreach ( $event_caps_list as $cap ) { $admin_role->add_cap( $cap ); } // ADDED
        }
    }

    /**
     * Removes custom user roles and capabilities on plugin deactivation.
     *
     * @since 0.1.0
     */
    public static function remove_roles_on_deactivation() {
        remove_role( 'business_owner' );
        remove_role( 'local_user' );

        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
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
            $event_caps_list = array(
                'edit_localverse_event', 'read_localverse_event', 'delete_localverse_event',
                'edit_localverse_events', 'edit_others_localverse_events',
                'publish_localverse_events', 'read_private_localverse_events',
                'delete_localverse_events', 'delete_private_localverse_events',
                'delete_published_localverse_events', 'delete_others_localverse_events',
                'edit_private_localverse_events', 'edit_published_localverse_events',
                'submit_localverse_event',
            );

            foreach ( $listing_caps_list as $cap ) { $admin_role->remove_cap( $cap ); }
            foreach ( $review_caps_list as $cap ) { $admin_role->remove_cap( $cap ); }
            foreach ( $event_caps_list as $cap ) { $admin_role->remove_cap( $cap ); } // ADDED
        }
    }
}
?>
