<?php
/**
 * Manages custom meta boxes for the 'localverse_event' post type.
 *
 * @package    LocalVerse
 * @subpackage LocalVerse/includes/admin
 * @since      0.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LocalVerse_Admin_Event_Metaboxes {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        // No admin_enqueue_scripts needed here yet unless we add datepickers immediately
    }

    /**
     * Add meta boxes for 'localverse_event' post type.
     * Hooked to 'add_meta_boxes_{post_type}'.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'localverse_event_details_metabox', // ID
            __( 'Event Details', 'localverse' ),    // Title
            array( $this, 'render_event_details_metabox' ), // Callback
            'localverse_event', // Post type
            'normal',           // Context
            'high'              // Priority
        );
    }

    /**
     * Render the HTML for the 'Event Details' meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_event_details_metabox( $post ) {
        wp_nonce_field( 'localverse_save_event_details', 'localverse_event_details_nonce' );

        $start_datetime = get_post_meta( $post->ID, '_lv_event_start_datetime', true );
        $end_datetime   = get_post_meta( $post->ID, '_lv_event_end_datetime', true );
        $location_name  = get_post_meta( $post->ID, '_lv_event_location_name', true );
        $location_address = get_post_meta( $post->ID, '_lv_event_location_address', true );
        $event_type     = get_post_meta( $post->ID, '_lv_event_type', true );
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="lv_event_start_datetime"><?php _e( 'Start Date & Time', 'localverse' ); ?></label></th>
                <td>
                    <input type="text" id="lv_event_start_datetime" name="lv_event_start_datetime" value="<?php echo esc_attr( $start_datetime ); ?>" class="regular-text" placeholder="YYYY-MM-DD HH:MM" />
                    <p class="description"><?php _e( 'E.g., 2024-12-31 18:30. Use 24-hour format for time.', 'localverse' ); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="lv_event_end_datetime"><?php _e( 'End Date & Time (Optional)', 'localverse' ); ?></label></th>
                <td>
                    <input type="text" id="lv_event_end_datetime" name="lv_event_end_datetime" value="<?php echo esc_attr( $end_datetime ); ?>" class="regular-text" placeholder="YYYY-MM-DD HH:MM" />
                     <p class="description"><?php _e( 'Leave blank if not applicable or a single-day event with no specific end time.', 'localverse' ); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="lv_event_location_name"><?php _e( 'Location Name', 'localverse' ); ?></label></th>
                <td><input type="text" id="lv_event_location_name" name="lv_event_location_name" value="<?php echo esc_attr( $location_name ); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g., Community Hall, Online', 'localverse'); ?>" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="lv_event_location_address"><?php _e( 'Location Address / Details', 'localverse' ); ?></label></th>
                <td><textarea id="lv_event_location_address" name="lv_event_location_address" rows="3" class="large-text"><?php echo esc_textarea( $location_address ); ?></textarea>
                    <p class="description"><?php _e( 'Full address if physical, or details like "Online event - link will be provided".', 'localverse' ); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="lv_event_type"><?php _e( 'Event Type / Category', 'localverse' ); ?></label></th>
                <td><input type="text" id="lv_event_type" name="lv_event_type" value="<?php echo esc_attr( $event_type ); ?>" class="regular-text" placeholder="<?php esc_attr_e('e.g., Workshop, Concert, Webinar', 'localverse'); ?>" />
                    <p class="description"><?php _e( 'A general type or category for the event.', 'localverse' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the meta box data when the 'localverse_event' post is saved.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_event_details( $post_id ) {
        if ( ! isset( $_POST['localverse_event_details_nonce'] ) || ! wp_verify_nonce( $_POST['localverse_event_details_nonce'], 'localverse_save_event_details' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { // Check if user can edit this specific post
             // More specific check: current_user_can( get_post_type_object('localverse_event')->cap->edit_post, $post_id )
            return;
        }
        if (get_post_type($post_id) !== 'localverse_event') { // Ensure we are saving the correct post type
            return;
        }

        $meta_fields = array(
            '_lv_event_start_datetime'   => 'sanitize_text_field', // Basic sanitization, could be stricter for date format
            '_lv_event_end_datetime'     => 'sanitize_text_field',
            '_lv_event_location_name'    => 'sanitize_text_field',
            '_lv_event_location_address' => 'sanitize_textarea_field',
            '_lv_event_type'             => 'sanitize_text_field',
        );

        foreach ( $meta_fields as $key => $sanitize_callback ) {
            if ( isset( $_POST[ substr( $key, 1 ) ] ) ) { // Remove leading underscore for POST key
                $value = call_user_func( $sanitize_callback, $_POST[ substr( $key, 1 ) ] );
                if ( ! empty( $value ) ) {
                    update_post_meta( $post_id, $key, $value );
                } else {
                    delete_post_meta( $post_id, $key );
                }
            } else {
                 // If field is not set in POST (e.g. checkbox unchecked), you might want to delete meta.
                 // For text fields, if empty, they are deleted above. This else is for fields not submitted at all.
                 delete_post_meta( $post_id, $key );
            }
        }
    }
}
?>
