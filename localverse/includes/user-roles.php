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
        // Business Owner Role
        add_role(
            'business_owner',
            __( 'Business Owner', 'localverse' ),
            array(
                'read'                               => true,
                'publish_localverse_listings'        => true, // Can publish their own listings
                'edit_localverse_listings'           => true, // Can edit their own listings (even after publishing)
                'delete_localverse_listings'         => true, // Can delete their own listings
                'upload_files'                       => true, // To upload featured images, gallery images
                'read_private_localverse_listings'   => true, // If they save as draft/private
                'edit_published_localverse_listings' => true, // Can edit their published listings
                'delete_published_localverse_listings' => true, // Can delete their published listings
                // Note: edit_others_localverse_listings is NOT given here.
            )
        );

        // Local User Role (basic subscriber-like role, might be expanded later)
        add_role(
            'local_user',
            __( 'Local User', 'localverse' ),
            array(
                'read' => true,
            )
        );

        // Grant all listing-specific capabilities to Administrator
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            // Core CPT capabilities
            $admin_role->add_cap( 'edit_localverse_listing' );
            $admin_role->add_cap( 'read_localverse_listing' ); // Though admin can read all posts
            $admin_role->add_cap( 'delete_localverse_listing' );
            // Meta capabilities (usually dynamically mapped, but good to be explicit for some)
            $admin_role->add_cap( 'edit_localverse_listings' );
            $admin_role->add_cap( 'edit_others_localverse_listings' );
            $admin_role->add_cap( 'publish_localverse_listings' );
            $admin_role->add_cap( 'read_private_localverse_listings' );
            $admin_role->add_cap( 'delete_localverse_listings' );
            $admin_role->add_cap( 'delete_private_localverse_listings' );
            $admin_role->add_cap( 'delete_published_localverse_listings' );
            $admin_role->add_cap( 'delete_others_localverse_listings' );
            $admin_role->add_cap( 'edit_private_localverse_listings' );
            $admin_role->add_cap( 'edit_published_localverse_listings' );
            // Custom/primitive capabilities mapped in CPT args
            $admin_role->add_cap( 'manage_localverse_listing_terms'); // For categories/tags if we add specific cap later
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

        // Remove listing-specific capabilities from Administrator
        $admin_role = get_role( 'administrator' );
        if ( $admin_role ) {
            $admin_role->remove_cap( 'edit_localverse_listing' );
            $admin_role->remove_cap( 'read_localverse_listing' );
            $admin_role->remove_cap( 'delete_localverse_listing' );
            $admin_role->remove_cap( 'edit_localverse_listings' );
            $admin_role->remove_cap( 'edit_others_localverse_listings' );
            $admin_role->remove_cap( 'publish_localverse_listings' );
            $admin_role->remove_cap( 'read_private_localverse_listings' );
            $admin_role->remove_cap( 'delete_localverse_listings' );
            $admin_role->remove_cap( 'delete_private_localverse_listings' );
            $admin_role->remove_cap( 'delete_published_localverse_listings' );
            $admin_role->remove_cap( 'delete_others_localverse_listings' );
            $admin_role->remove_cap( 'edit_private_localverse_listings' );
            $admin_role->remove_cap( 'edit_published_localverse_listings' );
            $admin_role->remove_cap( 'manage_localverse_listing_terms');
        }
    }
}
?>
