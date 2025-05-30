<?php
/**
 * Template for the Business Owner Dashboard.
 *
 * @link https://example.com/
 * @since 0.1.0
 * @package LocalVerse
 * @subpackage LocalVerse/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// This template should only be accessible to business_owner role.
// The template_include hook will handle redirection if not authorized.

get_header();

$current_user = wp_get_current_user();
?>
<div id="primary" class="content-area localverse-page owner-dashboard-page">
    <main id="main" class="site-main" role="main">
        <article id="post-<?php the_ID(); // ID of the dashboard page itself ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php _e( 'Business Owner Dashboard', 'localverse' ); ?></h1>
            </header><!-- .entry-header -->

            <div class="entry-content">
                <p>
                    <?php
                    printf(
                        /* translators: %s: User display name */
                        esc_html__( 'Welcome, %s!', 'localverse' ),
                        esc_html( $current_user->display_name )
                    );
                    ?>
                </p>

                <div class="dashboard-actions">
                    <?php
                    // Link to the 'Submit Listing' page.
                    // Ensure the 'submit-listing' page slug exists and is correct.
                    $submit_listing_page = get_page_by_path( 'submit-listing' );
                    if ( $submit_listing_page ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $submit_listing_page->ID ) ); ?>" class="button primary-button">
                            <?php _e( 'Add New Listing', 'localverse' ); ?>
                        </a>
                    <?php else : ?>
                        <p><?php _e( 'Submit listing page not found. Please contact admin.', 'localverse' ); ?></p>
                    <?php endif; ?>
                    <?php // More actions like "View Profile", "Manage Subscriptions" can be added later ?>
                </div>

                <div id="my-listings-section">
                    <h2><?php _e( 'My Listings', 'localverse' ); ?></h2>
                    <div class="my-listings-container">
                        <?php
                        $args = array(
                            'post_type'      => 'localverse_listing',
                            'author'         => $current_user->ID,
                            'post_status'    => array('publish', 'pending', 'draft', 'future', 'private'), // Show all statuses
                            'posts_per_page' => -1, // Show all their listings
                            'orderby'        => 'date',
                            'order'          => 'DESC',
                        );
                        $my_listings_query = new WP_Query( $args );

                        if ( $my_listings_query->have_posts() ) :
                        ?>
                            <table class="localverse-dashboard-listings-table">
                                <thead>
                                    <tr>
                                        <th><?php _e( 'Title', 'localverse' ); ?></th>
                                        <th><?php _e( 'Status', 'localverse' ); ?></th>
                                        <th><?php _e( 'Actions', 'localverse' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ( $my_listings_query->have_posts() ) : $my_listings_query->the_post(); ?>
                                        <tr>
                                            <td>
                                                <a href="<?php the_permalink(); ?>" target="_blank" title="<?php esc_attr_e('View Listing', 'localverse'); ?>">
                                                    <?php the_title(); ?>
                                                </a>
                                            </td>
                                            <td><?php echo esc_html( get_post_status_object( get_post_status() )->label ); ?></td>
                                            <td>
                                                <?php
                                                // View Link (already part of title, but can be explicit)
                                                // echo '<a href="' . esc_url( get_permalink() ) . '" target="_blank">' . __( 'View', 'localverse' ) . '</a>';

                                                // Edit Link - points to WP Admin for now
                                                // Check if user can edit this specific post (they should be able to as it's their own)
                                                if ( current_user_can( 'edit_post', get_the_ID() ) ) {
                                                    echo ' <a href="' . esc_url( get_edit_post_link( get_the_ID() ) ) . '">' . __( 'Edit (Admin)', 'localverse' ) . '</a>';
                                                }

                                                // Delete Link - Placeholder for now, or could link to admin action.
                                                // Actual front-end deletion needs AJAX and nonce handling.
                                                // if ( current_user_can( 'delete_post', get_the_ID() ) ) {
                                                //    echo ' | <a href="#delete-' . get_the_ID() . '" class="delete-listing-link-placeholder">' . __( 'Delete', 'localverse' ) . '</a>';
                                                // }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php
                            wp_reset_postdata(); // Important after a custom query
                        else :
                        ?>
                            <p><?php _e( 'You have not submitted any listings yet.', 'localverse' ); ?></p>
                        <?php
                        endif;
                        ?>
                    </div>
                </div>

                <?php
                // Example: Placeholder for other dashboard sections
                // <div id="dashboard-analytics-section">
                //    <h2><?php _e( 'Analytics', 'localverse' ); ?></h2>
                //    <p><?php _e( 'Listing analytics will be shown here.', 'localverse' ); ?></p>
                // </div>
                // <div id="dashboard-reviews-section">
                //    <h2><?php _e( 'My Reviews', 'localverse' ); ?></h2>
                //    <p><?php _e( 'Manage reviews related to your listings.', 'localverse' ); ?></p>
                // </div>
                ?>

            </div><!-- .entry-content -->
        </article><!-- #post-## -->
    </main><!-- #main -->
</div><!-- #primary -->
<style>
    /* Basic styling for the dashboard table */
    .localverse-dashboard-listings-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    .localverse-dashboard-listings-table th, .localverse-dashboard-listings-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    .localverse-dashboard-listings-table th { background-color: #f2f2f2; }
    .localverse-dashboard-listings-table a { text-decoration: none; }
    .localverse-dashboard-listings-table a:hover { text-decoration: underline; }
    .dashboard-actions { margin-bottom: 20px; }
    .dashboard-actions .button { margin-right: 10px; }
</style>
<?php get_footer(); ?>
