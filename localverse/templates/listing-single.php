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

    <?php
    // Check if user is logged in and has capability to display the review form
    if ( is_user_logged_in() && isset($listing) && $listing->is_valid() && current_user_can( 'submit_localverse_review', $listing->id ) ) :
    ?>
        <div id="localverse-review-form-wrapper" class="localverse-review-form-wrapper">
            <hr>
            <h3><?php _e( 'Leave a Review', 'localverse' ); ?></h3>
            <?php
            // Display any submission status messages (e.g., success, error)
            if ( isset( $_GET['review_submission_status'] ) ) {
                if ( $_GET['review_submission_status'] === 'success' ) {
                    echo '<p class="localverse-message success">' . esc_html__( 'Your review has been submitted successfully!', 'localverse' ) . '</p>';
                } elseif ( $_GET['review_submission_status'] === 'error' ) {
                    echo '<p class="localverse-message error">' . esc_html__( 'There was an error submitting your review. Please try again.', 'localverse' ) . '</p>';
                } elseif ( $_GET['review_submission_status'] === 'nonce_failure' ) {
                    echo '<p class="localverse-message error">' . esc_html__( 'Security check failed. Please refresh and try again.', 'localverse' ) . '</p>';
                } elseif ( $_GET['review_submission_status'] === 'validation_error' ) {
                    echo '<p class="localverse-message error">' . esc_html__( 'Please fill in all required fields correctly.', 'localverse' ) . '</p>';
                } elseif ( $_GET['review_submission_status'] === 'cap_failure' ) {
                    echo '<p class="localverse-message error">' . esc_html__( 'You do not have the necessary permissions to submit a review.', 'localverse' ) . '</p>';
                }
            }
            ?>
            <form id="localverse-review-submission-form" action="" method="POST" enctype="multipart/form-data">
                <?php wp_nonce_field( 'localverse_submit_review_action_' . $listing->id, 'localverse_submit_review_nonce' ); ?>
                <input type="hidden" name="listing_id" value="<?php echo esc_attr( $listing->id ); ?>">
                <input type="hidden" name="localverse_action" value="submit_review">

                <p class="comment-form-rating">
                    <label for="lv_review_rating"><?php _e( 'Your Rating', 'localverse' ); ?> <span class="required">*</span></label>
                    <fieldset class="lv-rating">
                        <input type="radio" id="star5" name="lv_review_rating" value="5" required /><label class = "full" for="star5" title="<?php esc_attr_e('Awesome - 5 stars', 'localverse'); ?>"></label>
                        <input type="radio" id="star4" name="lv_review_rating" value="4" /><label class = "full" for="star4" title="<?php esc_attr_e('Pretty good - 4 stars', 'localverse'); ?>"></label>
                        <input type="radio" id="star3" name="lv_review_rating" value="3" /><label class = "full" for="star3" title="<?php esc_attr_e('Meh - 3 stars', 'localverse'); ?>"></label>
                        <input type="radio" id="star2" name="lv_review_rating" value="2" /><label class = "full" for="star2" title="<?php esc_attr_e('Kinda bad - 2 stars', 'localverse'); ?>"></label>
                        <input type="radio" id="star1" name="lv_review_rating" value="1" /><label class = "full" for="star1" title="<?php esc_attr_e('Sucks big time - 1 star', 'localverse'); ?>"></label>
                    </fieldset>
                </p>

                <p class="comment-form-comment">
                    <label for="lv_review_text"><?php _e( 'Your Review', 'localverse' ); ?> <span class="required">*</span></label>
                    <textarea id="lv_review_text" name="lv_review_text" cols="45" rows="8" required></textarea>
                </p>

                <p class="comment-form-images">
                    <label for="lv_review_images"><?php _e( 'Upload Images (optional)', 'localverse' ); ?></label>
                    <input type="file" id="lv_review_images" name="lv_review_images[]" multiple accept="image/*">
                     <small><?php _e('You can upload multiple images.', 'localverse'); ?></small>
                </p>

                <?php
                // Simple anti-spam: honeypot field
                ?>
                <p class="lv-hp-field" style="display:none !important;" aria-hidden="true">
                    <label for="lv_contact_me_by_fax_only_if_you_promise_to_never_contact_me_again"><?php _e('Fax Number', 'localverse'); ?></label>
                    <input type="text" name="lv_contact_me_by_fax_only_if_you_promise_to_never_contact_me_again" id="lv_contact_me_by_fax_only_if_you_promise_to_never_contact_me_again" tabindex="-1" autocomplete="off">
                </p>


                <p class="form-submit">
                    <input name="submit_review_button" type="submit" id="submit_review_button" class="submit" value="<?php esc_attr_e( 'Submit Review', 'localverse' ); ?>">
                </p>
            </form>
        </div>
        <style type="text/css">
            /* Basic Star Rating CSS - can be moved to a stylesheet */
            .lv-rating { border: none; float: left; }
            .lv-rating > input { display: none; }
            .lv-rating > label:before { margin: 5px; font-size: 1.25em; font-family: FontAwesome; /* Requires FontAwesome or similar */ display: inline-block; content: "\f005"; /* Star icon */ }
            .lv-rating > label { color: #ddd; float: right; }
            .lv-rating > input:checked ~ label, /* show gold star when clicked */
            .lv-rating:not(:checked) > label:hover, /* hover current star */
            .lv-rating:not(:checked) > label:hover ~ label { color: #FFD700;  } /* hover previous stars in list */
            .lv-rating > input:checked + label:hover, /* hover current star when changing rating */
            .lv_rating > input:checked ~ label:hover,
            .lv-rating > label:hover ~ input:checked ~ label, /* lighten current selection */
            .lv-rating > input:checked ~ label:hover ~ label { color: #FFED85;  }
            .localverse-review-form-wrapper { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;}
            .localverse-review-form-wrapper .required { color: red; }
            .localverse-review-form-wrapper .lv-message { padding: 10px; margin-bottom: 15px; border: 1px solid transparent; border-radius: 4px; }
            .localverse-review-form-wrapper .lv-message.success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
            .localverse-review-form-wrapper .lv-message.error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
        </style>
    <?php
        // Removed the extra $listing->is_valid() check here as it's part of the main if condition
    // else: // Optional: message for logged-in users who cannot submit reviews
    //    if (is_user_logged_in() && isset($listing) && $listing->is_valid()) { // Check if the only reason is cap failure
    //        echo '<p>' . esc_html__( 'You do not have permission to submit a review for this listing.', 'localverse') . '</p>';
    //    }
    endif; // end if is_user_logged_in() && current_user_can() && $listing->is_valid()

    // Display Reviews Section (this should be outside the review form's conditional logic for capability)
    if ( isset($listing) && $listing->is_valid() ) :
    ?>
    <div id="localverse-listing-reviews" class="localverse-listing-reviews-wrapper">
        <hr>
        <h3><?php _e( 'User Reviews', 'localverse' ); ?></h3>
        <?php
        // Pagination for reviews
        $paged_reviews = ( get_query_var( 'paged_reviews' ) ) ? get_query_var( 'paged_reviews' ) : 1;

        $args_reviews = array(
            'post_type'      => 'localverse_review',
            'post_status'    => 'publish', // Only show published reviews
            'posts_per_page' => 5,         // Number of reviews per page
            'paged'          => $paged_reviews,
            'meta_query'     => array(
                array(
                    'key'     => '_lv_review_listing_id',
                    'value'   => $listing->id,
                    'compare' => '=',
                    'type'    => 'NUMERIC',
                ),
            ),
            'orderby'        => 'date',
            'order'          => 'DESC',
        );
        $reviews_query = new WP_Query( $args_reviews );

        if ( $reviews_query->have_posts() ) :
        ?>
            <ul class="localverse-reviews-list">
                <?php while ( $reviews_query->have_posts() ) : $reviews_query->the_post(); ?>
                    <?php
                    // For each review, get its details
                    $review_author_id = get_the_author_meta('ID');
                    $review_author_name = get_the_author_meta('display_name');
                    // $review_author_avatar = get_avatar( $review_author_id, 60 ); // Get avatar if needed

                    $review_rating = get_post_meta( get_the_ID(), '_lv_review_rating', true );
                    $review_images_ids = get_post_meta( get_the_ID(), '_lv_review_image_ids', true );
                    if(!is_array($review_images_ids)) $review_images_ids = array();
                    ?>
                    <li id="review-<?php the_ID(); ?>" <?php post_class('localverse-review-item'); ?>>
                        <div class="review-author">
                            <?php // echo $review_author_avatar; ?>
                            <strong><?php echo esc_html( $review_author_name ); ?></strong>
                            <span class="review-date"><?php echo esc_html( get_the_date() ); ?></span>
                        </div>

                        <?php if ( $review_rating > 0 ) : ?>
                            <div class="review-rating stars" style="color: #FFD700;">
                                <?php for ( $i = 1; $i <= 5; $i++ ) : ?>
                                    <span class="dashicons dashicons-star-<?php echo ( $i <= $review_rating ) ? 'filled' : 'empty'; ?>"></span>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>

                        <div class="review-content">
                            <?php the_content(); // Review text ?>
                        </div>

                        <?php if ( ! empty( $review_images_ids ) ) : ?>
                            <div class="review-images">
                                <?php foreach ( $review_images_ids as $image_id ) : ?>
                                    <?php $image_thumbnail_url = wp_get_attachment_image_url( $image_id, 'thumbnail' ); ?>
                                    <?php $image_full_url = wp_get_attachment_image_url( $image_id, 'large' ); // Or 'full' ?>
                                    <?php if ( $image_thumbnail_url && $image_full_url ) : ?>
                                        <a href="<?php echo esc_url( $image_full_url ); ?>" target="_blank" rel="noopener noreferrer">
                                            <img src="<?php echo esc_url( $image_thumbnail_url ); ?>" alt="<?php esc_attr_e( 'Review image', 'localverse' ); ?>" style="max-width: 100px; height: auto; margin: 5px; border: 1px solid #eee;">
                                        </a>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </li>
                <?php endwhile; ?>
            </ul>

            <?php
            // Pagination for reviews
            $big = 999999999; // need an unlikely integer
            $pagination_args = array(
                'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ) . '#localverse-listing-reviews',
                'format'    => '?paged_reviews=%#%',
                'current'   => max( 1, $paged_reviews ),
                'total'     => $reviews_query->max_num_pages,
                'prev_text' => __('&laquo; Previous Reviews'),
                'next_text' => __('Next Reviews &raquo;'),
            );

            echo '<div class="localverse-reviews-pagination">';
            echo paginate_links( $pagination_args );
            echo '</div>';

            wp_reset_postdata();

        else :
        ?>
            <p><?php _e( 'No reviews yet for this listing. Be the first to leave a review!', 'localverse' ); ?></p>
        <?php
        endif;
        ?>
    </div>
    <?php
    endif; // End if $listing->is_valid() for review display section
    ?>
    <style type="text/css">
    .localverse-listing-reviews-wrapper { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; }
    .localverse-reviews-list { list-style: none; margin: 0; padding: 0; }
    .localverse-review-item { padding: 15px 0; border-bottom: 1px dotted #ccc; }
    .localverse-review-item:last-child { border-bottom: none; }
    .review-author { margin-bottom: 5px; }
    .review-author strong { font-size: 1.1em; }
    .review-date { font-size: 0.9em; color: #777; margin-left: 10px; }
    .review-rating .dashicons { font-size: 18px; } /* Adjust size of dashicon stars */
    .review-content { margin-top: 10px; margin-bottom: 10px; }
    .review-images img { margin-right: 5px; margin-bottom: 5px; }
    .localverse-reviews-pagination { margin-top: 20px; }
    .localverse-reviews-pagination .page-numbers { padding: 5px 10px; border: 1px solid #ddd; text-decoration: none; margin: 0 2px; }
    .localverse-reviews-pagination .page-numbers.current { background-color: #f0f0f0; font-weight: bold; }
    .localverse-reviews-pagination a.page-numbers:hover { background-color: #e9e9e9; }
    </style>

<?php get_sidebar(); ?>
<?php get_footer(); ?>
