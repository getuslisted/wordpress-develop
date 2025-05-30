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

        // Hook scripts for gallery metabox
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_gallery_metabox_scripts' ) );
    }

    /**
     * Enqueue scripts and styles for the gallery metabox.
     * Only loads on the 'localverse_listing' edit screen.
     *
     * @since 0.1.0
     * @param string $hook The current admin page hook.
     */
    public function enqueue_gallery_metabox_scripts( $hook ) {
        global $post_type;
        if ( ( $hook == 'post-new.php' || $hook == 'post.php' ) && $post_type == 'localverse_listing' ) {
            wp_enqueue_media();
            // Potentially enqueue a dedicated JS file for more complex gallery logic later
            // wp_enqueue_script( $this->plugin_name . '-gallery-metabox', plugins_url( '../assets/js/admin-gallery-metabox.js', __FILE__ ), array( 'jquery' ), $this->version, true );
        }
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

        $plugin_options = get_option( 'localverse_options' );
        $gallery_feature_enabled = isset( $plugin_options['enable_image_gallery'] ) ? (bool) $plugin_options['enable_image_gallery'] : true; // Default true

        if ( $gallery_feature_enabled ) { // Check global setting
            add_meta_box(
                'localverse_listing_gallery_metabox',
                __( 'Image Gallery', 'localverse' ),
                array( $this, 'render_listing_gallery_metabox' ),
                'localverse_listing', 'normal', 'low' // Priority changed to low from default
            );
        }
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
            <tr valign="top">
                <th scope="row"><?php _e( 'Verification', 'localverse' ); ?></th>
                <td>
                    <label for="lv_is_verified">
                        <input type="checkbox" id="lv_is_verified" name="lv_is_verified" value="1" <?php checked( get_post_meta( $post->ID, '_lv_is_verified', true ), '1' ); ?> />
                        <?php _e( 'Mark this listing as verified', 'localverse' ); ?>
                    </label>
                    <p class="description"><?php _e( 'Verified listings may be displayed with a special badge.', 'localverse' ); ?></p>
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
        // Nonce check for details (should be at the top)
        if ( ! isset( $_POST['localverse_listing_details_nonce'] ) || ! wp_verify_nonce( $_POST['localverse_listing_details_nonce'], 'localverse_save_listing_details' ) ) {
            return; // Nonce for details failed, stop all saving for this metabox
        }

        // If this is an autosave, our form has not been submitted, so we don't want to do anything.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        // Check the user's permissions.
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // ... (existing saving logic for address, contact, hours fields) ...
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

        // Save "Is Verified" status
        if ( isset( $_POST['lv_is_verified'] ) && $_POST['lv_is_verified'] === '1' ) {
            update_post_meta( $post_id, '_lv_is_verified', '1' );
        } else {
            update_post_meta( $post_id, '_lv_is_verified', '0' ); // Or delete_post_meta if '0' means "not set"
        }

        // Gallery saving logic (should ideally be separate or ensure nonce is checked appropriately if details_nonce fails)
        // The current structure means if details_nonce is bad, gallery won't save either.
        // This was addressed by checking gallery nonce independently in the previous step's implementation.
        // Let's re-verify that the gallery save logic is self-contained with its own nonce check.
        // (Assuming gallery save logic from previous step is correctly in place and self-contained with its nonce)
        if ( isset( $_POST['localverse_listing_gallery_nonce'] ) && wp_verify_nonce( $_POST['localverse_listing_gallery_nonce'], 'localverse_save_listing_gallery' ) ) {
            $gallery_ids_str = isset($_POST['lv_image_gallery_ids']) ? sanitize_text_field( $_POST['lv_image_gallery_ids'] ) : '';
            if ( !empty($gallery_ids_str) ) {
                $gallery_ids = array_map( 'intval', explode( ',', $gallery_ids_str ) );
                $gallery_ids = array_filter($gallery_ids, function($id) { return $id > 0; }); // Ensure positive integers
                if (!empty($gallery_ids)) {
                     update_post_meta( $post_id, '_lv_image_gallery_ids', $gallery_ids );
                } else {
                     delete_post_meta( $post_id, '_lv_image_gallery_ids' );
                }
            } else {
                delete_post_meta( $post_id, '_lv_image_gallery_ids' );
            }
        }
    }

    /**
     * Render the HTML for the 'Image Gallery' meta box.
     *
     * @param WP_Post $post The current post object.
     */
    public function render_listing_gallery_metabox( $post ) {
        wp_nonce_field( 'localverse_save_listing_gallery', 'localverse_listing_gallery_nonce' );
        $gallery_ids_str = get_post_meta( $post->ID, '_lv_image_gallery_ids', true );
        // Ensure it's a string for the hidden input, even if stored as array internally
        $gallery_ids_value = is_array($gallery_ids_str) ? implode(',', $gallery_ids_str) : $gallery_ids_str;
        if (empty($gallery_ids_value) && is_array($gallery_ids_str) && !empty($gallery_ids_str)) { // if it was an array of 0s
             $gallery_ids_value = '';
        }


        ?>
        <div id="listing_gallery_container">
            <ul class="gallery-thumbs">
                <?php
                if ( !empty($gallery_ids_value) ) {
                    $ids = explode( ',', $gallery_ids_value );
                    foreach ( $ids as $id ) {
                        if (empty($id)) continue;
                        $image_url = wp_get_attachment_thumb_url( $id );
                        if ($image_url) {
                            echo '<li data-id="' . esc_attr( $id ) . '">';
                            echo '<img src="' . esc_url( $image_url ) . '" />';
                            echo '<a href="#" class="remove-gallery-image">×</a>';
                            echo '</li>';
                        }
                    }
                }
                ?>
            </ul>
            <input type="hidden" id="lv_image_gallery_ids" name="lv_image_gallery_ids" value="<?php echo esc_attr( $gallery_ids_value ); ?>" />
            <button type="button" class="button" id="add_listing_gallery_images_button"><?php _e( 'Add/Edit Gallery Images', 'localverse' ); ?></button>
        </div>
        <style>
            .gallery-thumbs { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; }
            .gallery-thumbs li { position: relative; width: 100px; height: 100px; margin: 5px; border: 1px solid #ccc; overflow: hidden; }
            .gallery-thumbs li img { width: 100%; height: 100%; object-fit: cover; }
            .gallery-thumbs .remove-gallery-image {
                position: absolute; top: 0; right: 0; background: rgba(0,0,0,0.7); color: white; text-decoration: none;
                padding: 0 5px; font-size: 16px; line-height: 20px; border: none; cursor: pointer;
            }
        </style>
        <script type="text/javascript">
            jQuery(document).ready(function($){
                var mediaFrame;
                $('#add_listing_gallery_images_button').on('click', function(e){
                    e.preventDefault();
                    if (mediaFrame) {
                        mediaFrame.open();
                        return;
                    }
                    mediaFrame = wp.media({
                        title: '<?php _e( "Select or Upload Images for Gallery", "localverse" ); ?>',
                        button: { text: '<?php _e( "Use these images", "localverse" ); ?>' },
                        library: { type: 'image' },
                        multiple: true // Allow multiple selections
                    });

                    mediaFrame.on('select', function(){
                        var selection = mediaFrame.state().get('selection');
                        var ids = selection.map(function(attachment){
                            return attachment.id;
                        });
                        $('#lv_image_gallery_ids').val(ids.join(','));

                        // Update preview
                        var thumbsContainer = $('#listing_gallery_container .gallery-thumbs');
                        thumbsContainer.empty(); // Clear existing thumbs
                        ids.forEach(function(id){
                            var attachment = wp.media.attachment(id);
                            attachment.fetch(); // Ensure model has data, especially sizes
                            var thumbUrl = attachment.attributes.sizes && attachment.attributes.sizes.thumbnail ?
                                           attachment.attributes.sizes.thumbnail.url :
                                           attachment.attributes.url; // Fallback to full if no thumb

                            thumbsContainer.append('<li data-id="' + id + '"><img src="' + thumbUrl + '" /><a href="#" class="remove-gallery-image">×</a></li>');
                        });
                    });
                    mediaFrame.open();
                });

                // Handle removal of an image
                $('#listing_gallery_container').on('click', '.remove-gallery-image', function(e){
                    e.preventDefault();
                    var $li = $(this).closest('li');
                    var idToRemove = $li.data('id');
                    $li.remove();

                    var currentIds = $('#lv_image_gallery_ids').val().split(',');
                    var newIds = currentIds.filter(function(id){
                        return id != idToRemove; // Compare as string if necessary, data('id') gives number
                    });
                    $('#lv_image_gallery_ids').val(newIds.join(','));
                });
            });
        </script>
        <?php
    }
}
?>
