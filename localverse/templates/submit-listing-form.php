<?php
/**
 * Template for displaying the front-end listing submission form.
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

// Required for media uploader (for featured image)
// if ( ! function_exists( 'wp_enqueue_media' ) ) {
//     wp_enqueue_media();
// }
?>
<div id="primary" class="content-area localverse-page submit-listing-page">
    <main id="main" class="site-main" role="main">
        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php _e( 'Submit New Business Listing', 'localverse' ); ?></h1>
            </header><!-- .entry-header -->

            <div class="entry-content">
                <?php
                // Display any success/error messages passed via query args or session
                if ( isset( $_GET['submission_status'] ) ) {
                    if ( $_GET['submission_status'] === 'success' ) {
                        echo '<p class="localverse-message success">' . esc_html__( 'Listing submitted successfully! It will be reviewed shortly.', 'localverse' ) . '</p>';
                    } elseif ( $_GET['submission_status'] === 'error' ) {
                        // More specific errors could be handled via a session or more query args
                        echo '<p class="localverse-message error">' . esc_html__( 'There was an error submitting your listing. Please try again.', 'localverse' ) . '</p>';
                    } elseif ( $_GET['submission_status'] === 'nonce_failure' ) {
                        echo '<p class="localverse-message error">' . esc_html__( 'Security check failed. Please try again.', 'localverse' ) . '</p>';
                    } elseif ( $_GET['submission_status'] === 'login_required' ) { // ADD THIS
                        echo '<p class="localverse-message error">' . sprintf( __( 'You must be <a href="%s">logged in</a> to submit a listing.', 'localverse' ), esc_url( wp_login_url( get_permalink() ) ) ) . '</p>';
                    }
                }
                ?>

                <form id="localverse-frontend-submission-form" action="" method="POST" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'localverse_submit_listing_action', 'localverse_submit_listing_nonce' ); ?>

                    <fieldset>
                        <legend><?php _e( 'Basic Information', 'localverse' ); ?></legend>
                        <p>
                            <label for="lv_title"><?php _e( 'Business Name', 'localverse' ); ?> <span class="required">*</span></label>
                            <input type="text" id="lv_title" name="lv_title" required>
                        </p>
                        <p>
                            <label for="lv_description"><?php _e( 'Description', 'localverse' ); ?></label>
                            <?php
                            // Basic textarea, for a richer editor, wp_editor() could be used but requires more setup here.
                            ?>
                            <textarea id="lv_description" name="lv_description" rows="5"></textarea>
                        </p>
                    </fieldset>

                    <fieldset>
                        <legend><?php _e( 'Address', 'localverse' ); ?></legend>
                        <p>
                            <label for="lv_address_street"><?php _e( 'Street Address', 'localverse' ); ?></label>
                            <input type="text" id="lv_address_street" name="lv_address_street">
                        </p>
                        <p>
                            <label for="lv_address_city"><?php _e( 'City', 'localverse' ); ?></label>
                            <input type="text" id="lv_address_city" name="lv_address_city">
                        </p>
                        <p>
                            <label for="lv_address_state"><?php _e( 'State/Province', 'localverse' ); ?></label>
                            <input type="text" id="lv_address_state" name="lv_address_state">
                        </p>
                        <p>
                            <label for="lv_address_zip"><?php _e( 'Zip/Postal Code', 'localverse' ); ?></label>
                            <input type="text" id="lv_address_zip" name="lv_address_zip">
                        </p>
                        <p>
                            <label for="lv_address_country"><?php _e( 'Country', 'localverse' ); ?></label>
                            <input type="text" id="lv_address_country" name="lv_address_country">
                        </p>
                    </fieldset>

                    <fieldset>
                        <legend><?php _e( 'Contact Information', 'localverse' ); ?></legend>
                        <p>
                            <label for="lv_contact_phone"><?php _e( 'Phone Number', 'localverse' ); ?></label>
                            <input type="text" id="lv_contact_phone" name="lv_contact_phone">
                        </p>
                        <p>
                            <label for="lv_contact_email"><?php _e( 'Email Address', 'localverse' ); ?></label>
                            <input type="email" id="lv_contact_email" name="lv_contact_email">
                        </p>
                        <p>
                            <label for="lv_contact_website"><?php _e( 'Website URL (e.g., https://example.com)', 'localverse' ); ?></label>
                            <input type="url" id="lv_contact_website" name="lv_contact_website" placeholder="https://example.com">
                        </p>
                    </fieldset>

                    <fieldset>
                        <legend><?php _e( 'Operating Hours', 'localverse' ); ?></legend>
                        <p>
                            <label for="lv_operating_hours"><?php _e( 'Operating Hours (e.g., Mon-Fri: 9am-5pm)', 'localverse' ); ?></label>
                            <textarea id="lv_operating_hours" name="lv_operating_hours" rows="3"></textarea>
                        </p>
                    </fieldset>

                    <fieldset>
                        <legend><?php _e( 'Categories & Tags', 'localverse' ); ?></legend>
                        <p>
                            <label for="lv_listing_category"><?php _e( 'Categories', 'localverse' ); ?></label>
                            <?php
                            wp_dropdown_categories( array(
                                'taxonomy'          => 'listing_category',
                                'hierarchical'      => 1,
                                'show_option_none'  => __( 'Select a category', 'localverse' ),
                                'name'              => 'lv_listing_category', // Changed from lv_listing_category[]
                                'id'                => 'lv_listing_category',
                                'selected'          => '', // or some default
                                'hide_empty'        => 0,
                            ) );
                            ?>
                        </p>
                        <p>
                            <label for="lv_listing_tag"><?php _e( 'Tags (comma-separated)', 'localverse' ); ?></label>
                            <input type="text" id="lv_listing_tag" name="lv_listing_tag">
                        </p>
                    </fieldset>

                    <fieldset>
                        <legend><?php _e( 'Featured Image', 'localverse' ); ?></legend>
                        <p>
                            <label for="lv_featured_image"><?php _e( 'Upload an image for your listing:', 'localverse' ); ?></label>
                            <input type="file" id="lv_featured_image" name="lv_featured_image" accept="image/*">
                        </p>
                    </fieldset>

                    <p>
                        <input type="hidden" name="localverse_action" value="submit_listing">
                        <input type="submit" name="localverse_submit_listing_button" value="<?php esc_attr_e( 'Submit Listing', 'localverse' ); ?>">
                    </p>
                </form>
            </div><!-- .entry-content -->
        </article><!-- #post-## -->
    </main><!-- #main -->
</div><!-- #primary -->
<?php get_footer(); ?>
