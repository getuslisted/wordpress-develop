<?php
/**
 * Manages custom meta boxes for the 'localverse_listing' post type.
 *
 * @package    LocalVerse
 * @subpackage LocalVerse/includes/admin
 * @author     Your Name <email@example.com>
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LocalVerse_Admin_Listing_Metaboxes {

    /**
     * The ID of this plugin.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    0.1.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version    The version of this plugin.
     */
    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Add meta boxes for 'localverse_listing' post type.
     * Hooked to 'add_meta_boxes'.
     */
    public function add_meta_boxes() {
        add_meta_box(
            'localverse_listing_details_metabox', // ID
            __( 'Business Details', 'localverse' ), // Title
            array( $this, 'render_listing_details_metabox' ), // Callback
            'localverse_listing', // Post type
            'normal', // Context (normal, side, advanced)
            'high' // Priority (high, core, default, low)
        );
    }

    /**
     * Render the HTML for the 'Business Details' meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_listing_details_metabox( $post ) {
        // Add a nonce field for security
        wp_nonce_field( 'localverse_save_listing_details', 'localverse_listing_details_nonce' );

        // Retrieve existing values from the database
        $address_street   = get_post_meta( $post->ID, '_lv_address_street', true );
        $address_city     = get_post_meta( $post->ID, '_lv_address_city', true );
        $address_state    = get_post_meta( $post->ID, '_lv_address_state', true );
        $address_zip      = get_post_meta( $post->ID, '_lv_address_zip', true );
        $address_country  = get_post_meta( $post->ID, '_lv_address_country', true );

        $contact_phone    = get_post_meta( $post->ID, '_lv_contact_phone', true );
        $contact_email    = get_post_meta( $post->ID, '_lv_contact_email', true );
        $contact_website  = get_post_meta( $post->ID, '_lv_contact_website', true );

        $operating_hours  = get_post_meta( $post->ID, '_lv_operating_hours', true );
        // For operating hours, a simple textarea for now. Could be more complex later.
        ?>
        <table class="form-table">
            <tr valign="top">
                <th scope="row"><label for="lv_address_street"><?php _e( 'Street Address', 'localverse' ); ?></label></th>
                <td><input type="text" id="lv_address_street" name="lv_address_street" value="<?php echo esc_attr( $address_street ); ?>" class="regular-text" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="lv_address_city"><?php _e( 'City', 'localverse' ); ?></label></th>
                <td><input type="text" id="lv_address_city" name="lv_address_city" value="<?php echo esc_attr( $address_city ); ?>" class="regular-text" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="lv_address_state"><?php _e( 'State/Province', 'localverse' ); ?></label></th>
                <td><input type="text" id="lv_address_state" name="lv_address_state" value="<?php echo esc_attr( $address_state ); ?>" class="regular-text" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="lv_address_zip"><?php _e( 'Zip/Postal Code', 'localverse' ); ?></label></th>
                <td><input type="text" id="lv_address_zip" name="lv_address_zip" value="<?php echo esc_attr( $address_zip ); ?>" class="regular-text" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="lv_address_country"><?php _e( 'Country', 'localverse' ); ?></label></th>
                <td><input type="text" id="lv_address_country" name="lv_address_country" value="<?php echo esc_attr( $address_country ); ?>" class="regular-text" /></td>
            </tr>

            <tr><td colspan="2"><hr></td></tr>

            <tr valign="top">
                <th scope="row"><label for="lv_contact_phone"><?php _e( 'Phone Number', 'localverse' ); ?></label></th>
                <td><input type="text" id="lv_contact_phone" name="lv_contact_phone" value="<?php echo esc_attr( $contact_phone ); ?>" class="regular-text" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="lv_contact_email"><?php _e( 'Email Address', 'localverse' ); ?></label></th>
                <td><input type="email" id="lv_contact_email" name="lv_contact_email" value="<?php echo esc_attr( $contact_email ); ?>" class="regular-text" /></td>
            </tr>
            <tr valign="top">
                <th scope="row"><label for="lv_contact_website"><?php _e( 'Website URL', 'localverse' ); ?></label></th>
                <td><input type="url" id="lv_contact_website" name="lv_contact_website" value="<?php echo esc_attr( $contact_website ); ?>" class="regular-text" placeholder="https://example.com" /></td>
            </tr>

            <tr><td colspan="2"><hr></td></tr>

            <tr valign="top">
                <th scope="row"><label for="lv_operating_hours"><?php _e( 'Operating Hours', 'localverse' ); ?></label></th>
                <td>
                    <textarea id="lv_operating_hours" name="lv_operating_hours" rows="5" cols="50" class="large-text"><?php echo esc_textarea( $operating_hours ); ?></textarea>
                    <p class="description"><?php _e( 'E.g., Monday - Friday: 9 AM - 5 PM, Saturday: 10 AM - 2 PM, Sunday: Closed', 'localverse' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save the meta box data when the post is saved.
     * Hooked to 'save_post_localverse_listing'.
     *
     * @param int $post_id The ID of the post being saved.
     */
    public function save_listing_details( $post_id ) {
        // Check if our nonce is set.
        if ( ! isset( $_POST['localverse_listing_details_nonce'] ) ) {
            return;
        }
        // Verify that the nonce is valid.
        if ( ! wp_verify_nonce( $_POST['localverse_listing_details_nonce'], 'localverse_save_listing_details' ) ) {
            return;
        }
        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Check the user's permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Sanitize and save address fields
        $fields_to_save = array(
            'lv_address_street', 'lv_address_city', 'lv_address_state', 'lv_address_zip', 'lv_address_country',
            'lv_contact_phone',
        );
        foreach ( $fields_to_save as $field_key ) {
            if ( isset( $_POST[$field_key] ) ) {
                update_post_meta( $post_id, '_' . $field_key, sanitize_text_field( $_POST[$field_key] ) );
            }
        }

        // Sanitize and save email
        if ( isset( $_POST['lv_contact_email'] ) ) {
            update_post_meta( $post_id, '_lv_contact_email', sanitize_email( $_POST['lv_contact_email'] ) );
        }

        // Sanitize and save website URL
        if ( isset( $_POST['lv_contact_website'] ) ) {
            update_post_meta( $post_id, '_lv_contact_website', esc_url_raw( $_POST['lv_contact_website'] ) );
        }

        // Sanitize and save operating hours (textarea)
        if ( isset( $_POST['lv_operating_hours'] ) ) {
            update_post_meta( $post_id, '_lv_operating_hours', sanitize_textarea_field( $_POST['lv_operating_hours'] ) );
        }
    }
}
?>
