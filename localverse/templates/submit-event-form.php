<?php
/**
 * Template for displaying the front-end event submission form.
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
?>
<div id="primary" class="content-area localverse-page submit-event-page">
    <main id="main" class="site-main" role="main">
        <article id="post-<?php the_ID(); // ID of the dashboard page itself ?>" <?php post_class(); ?>>
            <header class="entry-header">
                <h1 class="entry-title"><?php _e( 'Submit New Event', 'localverse' ); ?></h1>
            </header><!-- .entry-header -->

            <div class="entry-content">
                <?php
                // Display any submission status messages
                if ( isset( $_GET['event_submission_status'] ) ) {
                    $status = sanitize_key($_GET['event_submission_status']);
                    $message = '';
                    switch ($status) {
                        case 'success':
                            $message = '<p class="localverse-message success">' . esc_html__( 'Event submitted successfully! It will be reviewed shortly.', 'localverse' ) . '</p>';
                            break;
                        case 'error':
                            $message = '<p class="localverse-message error">' . esc_html__( 'There was an error submitting your event. Please try again.', 'localverse' ) . '</p>';
                            break;
                        case 'nonce_failure':
                            $message = '<p class="localverse-message error">' . esc_html__( 'Security check failed. Please refresh and try again.', 'localverse' ) . '</p>';
                            break;
                        case 'validation_error':
                            $message = '<p class="localverse-message error">' . esc_html__( 'Please fill in all required fields correctly.', 'localverse' ) . '</p>';
                            // Specific errors could be passed via GET/session and displayed here
                            break;
                        case 'cap_failure':
                             $message = '<p class="localverse-message error">' . esc_html__( 'You do not have the necessary permissions to submit an event.', 'localverse' ) . '</p>';
                             break;
                        case 'login_required':
                             $message = '<p class="localverse-message error">' . sprintf( __( 'You must be <a href="%s">logged in</a> to submit an event.', 'localverse' ), esc_url( wp_login_url( get_permalink() ) ) ) . '</p>';
                             break;
                    }
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Message is built from safe, translated strings.
                    echo $message;
                }
                ?>

                <form id="localverse-frontend-event-submission-form" action="" method="POST" enctype="multipart/form-data">
                    <?php wp_nonce_field( 'localverse_submit_event_action', 'localverse_submit_event_nonce' ); ?>
                    <input type="hidden" name="localverse_action" value="submit_event">

                    <fieldset>
                        <legend><?php _e( 'Event Details', 'localverse' ); ?></legend>
                        <p>
                            <label for="lv_event_title"><?php _e( 'Event Title', 'localverse' ); ?> <span class="required">*</span></label>
                            <input type="text" id="lv_event_title" name="lv_event_title" required>
                        </p>
                        <p>
                            <label for="lv_event_description"><?php _e( 'Description', 'localverse' ); ?> <span class="required">*</span></label>
                            <textarea id="lv_event_description" name="lv_event_description" rows="8" required></textarea>
                        </p>
                         <p>
                            <label for="lv_event_category"><?php _e( 'Event Category', 'localverse' ); ?> <span class="required">*</span></label>
                            <?php
                            wp_dropdown_categories( array(
                                'taxonomy'          => 'event_category',
                                'hierarchical'      => 1,
                                'show_option_none'  => __( 'Select an Event Category', 'localverse' ),
                                'name'              => 'lv_event_category', // Single select
                                'id'                => 'lv_event_category',
                                'hide_empty'        => 0,
                                'required'          => true, // HTML5 required
                            ) );
                            ?>
                        </p>
                    </fieldset>

                    <fieldset>
                        <legend><?php _e( 'Date & Time', 'localverse' ); ?></legend>
                        <p>
                            <label for="lv_event_start_datetime"><?php _e( 'Start Date & Time', 'localverse' ); ?> <span class="required">*</span></label>
                            <input type="text" id="lv_event_start_datetime" name="lv_event_start_datetime" placeholder="YYYY-MM-DD HH:MM" required>
                            <small><?php _e( 'Format: YYYY-MM-DD HH:MM (e.g., 2024-12-31 18:30)', 'localverse' ); ?></small>
                        </p>
                        <p>
                            <label for="lv_event_end_datetime"><?php _e( 'End Date & Time (Optional)', 'localverse' ); ?></label>
                            <input type="text" id="lv_event_end_datetime" name="lv_event_end_datetime" placeholder="YYYY-MM-DD HH:MM">
                        </p>
                    </fieldset>

                    <fieldset>
                        <legend><?php _e( 'Location', 'localverse' ); ?></legend>
                        <p>
                            <label for="lv_event_location_name"><?php _e( 'Location Name (e.g., Venue Name, Online)', 'localverse' ); ?> <span class="required">*</span></label>
                            <input type="text" id="lv_event_location_name" name="lv_event_location_name" required>
                        </p>
                        <p>
                            <label for="lv_event_location_address"><?php _e( 'Location Address / Details', 'localverse' ); ?></label>
                            <textarea id="lv_event_location_address" name="lv_event_location_address" rows="3"></textarea>
                        </p>
                    </fieldset>

                     <fieldset>
                        <legend><?php _e( 'Other Details', 'localverse' ); ?></legend>
                         <p>
                            <label for="lv_event_type"><?php _e( 'Event Type (e.g., Workshop, Concert)', 'localverse' ); ?></label>
                            <input type="text" id="lv_event_type" name="lv_event_type">
                        </p>
                        <p>
                            <label for="lv_event_featured_image"><?php _e( 'Event Image (Optional)', 'localverse' ); ?></label>
                            <input type="file" id="lv_event_featured_image" name="lv_event_featured_image" accept="image/*">
                        </p>
                    </fieldset>

                    <p class="lv-hp-field" style="display:none !important;" aria-hidden="true">
                        <label for="lv_event_contact_me_by_fax_only"><?php _e('Fax Number', 'localverse'); ?></label>
                        <input type="text" name="lv_event_contact_me_by_fax_only" id="lv_event_contact_me_by_fax_only" tabindex="-1" autocomplete="off">
                    </p>

                    <p>
                        <input type="submit" name="localverse_submit_event_button" value="<?php esc_attr_e( 'Submit Event for Review', 'localverse' ); ?>">
                    </p>
                </form>
            </div><!-- .entry-content -->
        </article><!-- #post-## -->
    </main><!-- #main -->
</div><!-- #primary -->
<style> /* Basic form styling - move to CSS file */
.submit-event-page .required { color: red; }
.submit-event-page fieldset { margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; }
.submit-event-page legend { font-weight: bold; padding: 0 5px; }
.submit-event-page label { display: block; margin-bottom: 5px; }
.submit-event-page input[type="text"], .submit-event-page textarea { width: 100%; max-width: 400px; padding: 8px; margin-bottom: 3px; }
.submit-event-page textarea { min-height: 100px; }
.submit-event-page small { display: block; font-size: 0.9em; color: #777; }
.localverse-message { padding: 10px; margin-bottom: 15px; border: 1px solid transparent; border-radius: 4px; }
.localverse-message.success { background-color: #d4edda; border-color: #c3e6cb; color: #155724; }
.localverse-message.error { background-color: #f8d7da; border-color: #f5c6cb; color: #721c24; }
</style>
<?php get_footer(); ?>
