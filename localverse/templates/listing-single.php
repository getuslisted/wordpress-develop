<?php
/**
 * The template for displaying a single LocalVerse Listing.
 *
 * @link https://example.com/
 * @since 0.1.0
 * @package LocalVerse
 * @subpackage LocalVerse/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

get_header();

// Instantiate the LocalVerse_Listing object
// It's good practice to include the model file if not autoloaded or always included
// require_once LOCALVERSE_PLUGIN_DIR . 'includes/models/Listing.php'; // Ensure this path is correct
// $listing = new LocalVerse_Listing( get_the_ID() ); // Done in the filter usually

global $post; // Or get $listing from a pre-set global or passed variable if using a filter that prepares it
$listing = new LocalVerse_Listing( $post );


?>
<div id="primary" class="content-area localverse-single-listing">
    <main id="main" class="site-main" role="main">

        <?php if ( $listing->is_valid() ) : ?>

            <article id="post-<?php echo esc_attr( $listing->id ); ?>" <?php post_class( '', $listing->id ); ?>>
                <header class="entry-header">
                    <?php echo get_the_post_thumbnail( $listing->id, 'large' ); // Display featured image ?>
                    <h1 class="entry-title"><?php echo esc_html( $listing->get_title() ); ?></h1>
                </header><!-- .entry-header -->

                <div class="entry-content">
                    <?php echo $listing->get_description(); // Main content/description ?>

                    <div class="listing-details">
                        <h2><?php _e( 'Business Details', 'localverse' ); ?></h2>

                        <?php if ( $listing->get_formatted_address('') ) : ?>
                            <div class="listing-address">
                                <h3><?php _e( 'Address', 'localverse' ); ?></h3>
                                <p>
                                    <?php echo esc_html( $listing->get_address_street() ); ?><br>
                                    <?php if($listing->get_address_city()) { echo esc_html( $listing->get_address_city() ) . ', '; } ?>
                                    <?php if($listing->get_address_state()) { echo esc_html( $listing->get_address_state() ) . ' '; } ?>
                                    <?php if($listing->get_address_zip()) { echo esc_html( $listing->get_address_zip() ); } ?><br>
                                    <?php if($listing->get_address_country()) { echo esc_html( $listing->get_address_country() ); } ?>
                                </p>
                                <?php
                                // Basic Google Maps link - replace with actual map embed later
                                $map_query = urlencode($listing->get_formatted_address(' '));
                                if ($map_query) : ?>
                                    <p><a href="https://www.google.com/maps/search/?api=1&query=<?php echo $map_query; ?>" target="_blank"><?php _e('View on Map', 'localverse'); ?></a></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( $listing->get_contact_phone() || $listing->get_contact_email() || $listing->get_contact_website_url() ) : ?>
                            <div class="listing-contact">
                                <h3><?php _e( 'Contact Information', 'localverse' ); ?></h3>
                                <?php if ( $phone = $listing->get_contact_phone() ) : ?>
                                    <p><strong><?php _e( 'Phone:', 'localverse' ); ?></strong> <?php echo esc_html( $phone ); ?></p>
                                <?php endif; ?>
                                <?php if ( $email = $listing->get_contact_email() ) : ?>
                                    <p><strong><?php _e( 'Email:', 'localverse' ); ?></strong> <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a></p>
                                <?php endif; ?>
                                <?php if ( $website = $listing->get_contact_website_url() ) : ?>
                                    <p><strong><?php _e( 'Website:', 'localverse' ); ?></strong> <a href="<?php echo esc_url( $website ); ?>" target="_blank"><?php echo esc_html( $website ); ?></a></p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ( $hours_html = $listing->get_operating_hours_html() ) : ?>
                            <div class="listing-hours">
                                <h3><?php _e( 'Operating Hours', 'localverse' ); ?></h3>
                                <p><?php echo $hours_html; // Already escaped and nl2br'd in model ?></p>
                            </div>
                        <?php endif; ?>

                    </div><!-- .listing-details -->
                </div><!-- .entry-content -->

                <footer class="entry-footer">
                        <?php
                        $categories_list = get_the_term_list( $listing->id, 'listing_category', '<span class="cat-links">' . esc_html__( 'Posted in ', 'localverse' ), esc_html__( ', ', 'localverse' ), '</span>' );
                        if ( $categories_list ) {
                            printf( '%s', $categories_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }

                        $tags_list = get_the_term_list( $listing->id, 'listing_tag', '<span class="tags-links">' . esc_html__( 'Tagged ', 'localverse' ), esc_html__( ', ', 'localverse' ), '</span>' );
                        if ( $tags_list ) {
                            // Add a separator if categories were also displayed
                            if ( $categories_list ) {
                                echo '<span class="sep"> | </span>';
                            }
                            printf( '%s', $tags_list ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                        }
                        ?>
                    <?php // edit_post_link( ... ); ?>
                </footer><!-- .entry-footer -->
            </article><!-- #post-## -->

            <?php
            // If comments are open or we have at least one comment, load up the comment template.
            if ( comments_open( $listing->id ) || get_comments_number( $listing->id ) ) :
                comments_template( '', true ); // Pass true to use separate comments template if desired
            endif;
            ?>

        <?php else : ?>
            <p><?php _e( 'Listing not found.', 'localverse' ); ?></p>
        <?php endif; ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
