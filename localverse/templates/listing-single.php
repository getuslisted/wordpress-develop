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
                    <?php echo get_the_post_thumbnail( $listing->id, 'large' ); ?>
                    <h1 class="entry-title"><?php echo esc_html( $listing->get_title() ); ?></h1>
                    <?php if ( $listing->is_verified() ) : ?>
                        <div class="localverse-verified-badge-wrapper">
                            <span class="localverse-verified-badge" style="background-color: #d4edda; color: #155724; padding: 5px 10px; border-radius: 4px; font-size: 0.9em; border: 1px solid #c3e6cb; display: inline-block; margin-top: 5px;">
                                <?php _e( '✔ Verified Listing', 'localverse' ); ?>
                            </span>
                        </div>
                    <?php endif; ?>
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

                        <?php
                        // In localverse/templates/listing-single.php, inside the <div class="listing-details"> section:

                        // ... (after address display, for example) ...
                        $options = get_option( 'localverse_options' );
                        $api_key_present = !empty( $options['google_maps_api_key'] );
                        $maps_globally_enabled = isset( $options['enable_google_maps'] ) ? (bool) $options['enable_google_maps'] : true;
                        $full_address = $listing->get_formatted_address(' '); // Use space for geocoding

                        if ( $api_key_present && $maps_globally_enabled && !empty($full_address) ) : ?>
                            <div class="listing-map-wrapper">
                                <h3><?php _e( 'Location Map', 'localverse' ); ?></h3>
                                <div id="localverse-listing-map" style="height: 400px; width: 100%;"></div>
                                <script type="text/javascript">
                                    function lvInitMap() {
                                        const fullAddress = <?php echo json_encode($full_address); ?>;
                                        const geocoder = new google.maps.Geocoder();
                                        const mapElement = document.getElementById('localverse-listing-map');

                                        if (!mapElement) {
                                            console.error('Map element not found.');
                                            return;
                                        }
                                        if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
                                            console.error('Google Maps API not loaded.');
                                            return;
                                        }


                                        geocoder.geocode( { 'address': fullAddress }, function(results, status) {
                                            if (status === 'OK' && results[0]) {
                                                const map = new google.maps.Map(mapElement, {
                                                    zoom: 15,
                                                    center: results[0].geometry.location
                                                });
                                                new google.maps.Marker({
                                                    map: map,
                                                    position: results[0].geometry.location
                                                });
                                            } else {
                                                // console.error('Geocode was not successful for the following reason: ' + status);
                                                mapElement.innerHTML = '<p><?php echo esc_js( __( 'Map could not be loaded for this address (Geocoding failed). Reason: ', 'localverse' ) ); ?>' + status + '</p>';

                                            }
                                        });
                                    }
                                    // If the Google Maps API script is loaded with a callback (like &callback=lvInitMap),
                                    // lvInitMap will be called automatically.
                                    // If not using callback in URL, you might need to call it manually or on window.load.
                                    // However, the &callback=lvInitMap in wp_enqueue_script handles this.
                                </script>
                            </div>
                        <?php endif; ?>

                        <?php // ... (rest of listing-single.php) ... ?>

                        <?php
                        // Display Image Gallery - Controlled by Admin Setting
                        $plugin_options_gallery = get_option( 'localverse_options' );
                        $gallery_enabled = isset( $plugin_options_gallery['enable_image_gallery'] ) ? (bool) $plugin_options_gallery['enable_image_gallery'] : true; // Default true

                        if ( $gallery_enabled ) { // Check the global setting
                            $gallery_images = $listing->get_image_gallery_data('medium', 'large'); // Or 'thumbnail', 'medium', etc.
                            if ( ! empty( $gallery_images ) ) : ?>
                                <div class="listing-image-gallery">
                                    <h3><?php _e( 'Image Gallery', 'localverse' ); ?></h3>
                                    <div class="gallery-items-wrapper" style="display: flex; flex-wrap: wrap; gap: 10px;">
                                        <?php foreach ( $gallery_images as $image ) : ?>
                                            <div class="gallery-item" style="flex: 1 0 150px; max-width: 200px;"> {/* Adjusted flex basis and max-width */}
                                                <a href="<?php echo esc_url( $image['full_url'] ); ?>" target="_blank" title="<?php echo esc_attr( $image['caption'] ? $image['caption'] : $image['alt'] ); ?>">
                                                    <img src="<?php echo esc_url( $image['thumb_url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" style="max-width: 100%; height: auto; border: 1px solid #ddd; padding: 2px;" />
                                                </a>
                                                <?php if ( !empty( $image['caption'] ) ) : ?>
                                                    <p class="gallery-item-caption" style="font-size: 0.9em; text-align: center;"><?php echo esc_html( $image['caption'] ); ?></p>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif;
                        } // End $gallery_enabled check
                        ?>
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
