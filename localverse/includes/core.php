<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      0.1.0
 * @package    LocalVerse
 * @subpackage LocalVerse/includes
 * @author     Your Name <email@example.com>
 */
class LocalVerse_Core {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      LocalVerse_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    0.1.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    0.1.0
     */
    public function __construct() {
        if ( defined( 'LOCALVERSE_VERSION' ) ) {
            $this->version = LOCALVERSE_VERSION;
        } else {
            $this->version = '0.1.0';
        }
        $this->plugin_name = 'localverse';

        // $this->load_dependencies(); // We'll uncomment this when LocalVerse_Loader is created
        // $this->set_locale();
        $this->define_admin_hooks(); // Ensure this is called
        $this->define_public_hooks(); // Ensure this is called
        $this->define_post_types(); // Ensure this is called
        $this->define_taxonomies(); // Ensure this is called and uncommented

    add_action( 'init', array( $this, 'handle_listing_submission' ) ); // Add this line

    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - LocalVerse_Loader. Orchestrates the hooks of the plugin.
     * - LocalVerse_i18n. Defines internationalization functionality.
     * - LocalVerse_Admin. Defines all hooks for the admin area.
     * - LocalVerse_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    0.1.0
     * @access   private
     */
    private function load_dependencies() {
        // Placeholder for loading files like loader, i18n, admin, public classes
        // Example:
        // require_once LOCALVERSE_PLUGIN_DIR . 'includes/class-localverse-loader.php';
        // require_once LOCALVERSE_PLUGIN_DIR . 'includes/admin/class-localverse-admin.php';
        // require_once LOCALVERSE_PLUGIN_DIR . 'includes/public/class-localverse-public.php';

        // $this->loader = new LocalVerse_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the LocalVerse_i18n class in order to set the domain and to load the
     * .mo file.
     *
     * @since    0.1.0
     * @access   private
     */
    private function set_locale() {
        // Placeholder for i18n class
        // $plugin_i18n = new LocalVerse_i18n();
        // $this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    0.1.0
     * @access   private
     */
    private function define_admin_hooks() {
        require_once LOCALVERSE_PLUGIN_DIR . 'includes/admin/settings.php';
        require_once LOCALVERSE_PLUGIN_DIR . 'includes/admin/class-localverse-admin-listing-metaboxes.php'; // Add this line

        if ( is_admin() ) { // Make sure we are in the admin area
            $plugin_admin_settings = new LocalVerse_Admin_Settings();
            add_action( 'admin_menu', array( $plugin_admin_settings, 'add_plugin_page' ) );
            add_action( 'admin_init', array( $plugin_admin_settings, 'page_init' ) );

            // Listing Metaboxes
            $listing_metaboxes = new LocalVerse_Admin_Listing_Metaboxes( $this->get_plugin_name(), $this->get_version() );
            add_action( 'add_meta_boxes_localverse_listing', array( $listing_metaboxes, 'add_meta_boxes' ) ); // Specific to post type
            add_action( 'save_post_localverse_listing', array( $listing_metaboxes, 'save_listing_details' ) ); // Specific to post type

            // Example of enqueueing admin scripts (will be needed later)
            // add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
            // add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
        }
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    0.1.0
     * @access   private
     */
    private function define_public_hooks() {
        // Placeholder for public hooks
        // $plugin_public = new LocalVerse_Public( $this->get_plugin_name(), $this->get_version() );
        // $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
        // $this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

        add_filter( 'single_template', array( $this, 'override_single_listing_template' ) );
        add_filter( 'archive_template', array( $this, 'override_archive_listing_template' ) );
        add_filter( 'template_include', array( $this, 'include_submit_listing_template' ) ); // Add this line
    }

    /**
     * Register custom post types.
     *
     * @since    0.1.0
     * @access   private
     */
    private function define_post_types() {
        require_once LOCALVERSE_PLUGIN_DIR . 'includes/post-types.php';
        // The add_action 'init' is in post-types.php, so it will be registered when the file is included.
    }

    /**
     * Register custom taxonomies.
     *
     * @since    0.1.0
     * @access   private
     */
    private function define_taxonomies() {
        require_once LOCALVERSE_PLUGIN_DIR . 'includes/taxonomies.php';
        // The add_action 'init' calls are in taxonomies.php,
        // so they will be registered when the file is included.
    }


    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    0.1.0
     */
    public function run() {
        // $this->loader->run(); // Uncomment when loader is implemented
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     0.1.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     0.1.0
     * @return    LocalVerse_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     0.1.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Enqueue admin-specific stylesheets.
     * @since 0.1.0
     */
    public function enqueue_admin_styles() {
        // wp_enqueue_style( $this->plugin_name . '-admin', LOCALVERSE_PLUGIN_URL . 'assets/css/admin-style.css', array(), $this->version, 'all' );
    }

    /**
     * Enqueue admin-specific JavaScript.
     * @since 0.1.0
     */
    public function enqueue_admin_scripts() {
        // wp_enqueue_script( $this->plugin_name . '-admin', LOCALVERSE_PLUGIN_URL . 'assets/js/admin-script.js', array( 'jquery' ), $this->version, false );
    }

    /**
     * Overrides the default single post template for 'localverse_listing' CPT.
     *
     * @since 0.1.0
     * @param string $template The path to the template file.
     * @return string The path to the listing single template file.
     */
    public function override_single_listing_template( $template ) {
        if ( is_singular( 'localverse_listing' ) ) {
            $new_template = LOCALVERSE_PLUGIN_DIR . 'templates/listing-single.php';
            if ( file_exists( $new_template ) ) {
                // Before returning the template, ensure the Listing model is available
                // This is one way to make $listing available in the template, though using a global or a filter is common.
                require_once LOCALVERSE_PLUGIN_DIR . 'includes/models/Listing.php';
                // global $post; // $post is already global in WordPress template context
                // $GLOBALS['localverse_listing_object'] = new LocalVerse_Listing( get_the_ID() );
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Overrides the default archive template for 'localverse_listing' CPT.
     *
     * @since 0.1.0
     * @param string $template The path to the template file.
     * @return string The path to the listing archive template file.
     */
    public function override_archive_listing_template( $template ) {
        // is_post_type_archive() checks for the main archive page (e.g., /listings/)
        // is_tax() could be added here later for custom taxonomies associated with 'localverse_listing'
        if ( is_post_type_archive( 'localverse_listing' ) /* || (is_tax() && get_queried_object()->taxonomy === 'your_listing_taxonomy') */ ) {
            $new_template = LOCALVERSE_PLUGIN_DIR . 'templates/archive-localverse_listing.php';
            if ( file_exists( $new_template ) ) {
                // Optional: require Listing model if needed globally for the archive page setup
                // require_once LOCALVERSE_PLUGIN_DIR . 'includes/models/Listing.php';
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Includes the submit listing form template for a page with a specific slug.
     *
     * @since 0.1.0
     * @param string $template The path to the template file being included.
     * @return string The path to the submit listing form template if conditions are met.
     */
    public function include_submit_listing_template( $template ) {
        // The admin should create a page with the slug 'submit-listing'
        if ( is_page( 'submit-listing' ) ) {
            $new_template = LOCALVERSE_PLUGIN_DIR . 'templates/submit-listing-form.php';
            if ( file_exists( $new_template ) ) {
                // Enqueue media scripts if we plan to use wp.media for image uploads later
                // if ( ! did_action( 'wp_enqueue_media' ) ) {
                //     wp_enqueue_media();
                // }
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Handles the front-end listing submission.
     * Hooked to 'init'.
     *
     * @since 0.1.0
     */
    public function handle_listing_submission() {
        // Check if our form has been submitted
        if ( ! isset( $_POST['localverse_action'] ) || $_POST['localverse_action'] !== 'submit_listing' ) {
            return;
        }

        // Verify nonce
        if ( ! isset( $_POST['localverse_submit_listing_nonce'] ) || ! wp_verify_nonce( $_POST['localverse_submit_listing_nonce'], 'localverse_submit_listing_action' ) ) {
            // Nonce is invalid, redirect back to form page with an error
            // Assuming 'submit-listing' is the slug of the page with the form
            $redirect_url = add_query_arg( 'submission_status', 'nonce_failure', get_permalink( get_page_by_path( 'submit-listing' ) ) );
            wp_redirect( $redirect_url );
            exit;
        }

        // --- Basic Validation & Sanitization ---
        // Title is required
        if ( empty( $_POST['lv_title'] ) ) {
            // Handle error - e.g., redirect with error message or store errors in a session/transient
            // For simplicity, redirecting with a generic error for now.
            $redirect_url = add_query_arg( 'submission_status', 'error', get_permalink( get_page_by_path( 'submit-listing' ) ) );
            wp_redirect( $redirect_url );
            exit;
        }
    // ... (all other sanitization as before) ...
    $title            = sanitize_text_field( $_POST['lv_title'] );
    $description      = isset( $_POST['lv_description'] ) ? sanitize_textarea_field( $_POST['lv_description'] ) : '';
    $address_street   = isset( $_POST['lv_address_street'] ) ? sanitize_text_field( $_POST['lv_address_street'] ) : '';
    $address_city     = isset( $_POST['lv_address_city'] ) ? sanitize_text_field( $_POST['lv_address_city'] ) : '';
    $address_state    = isset( $_POST['lv_address_state'] ) ? sanitize_text_field( $_POST['lv_address_state'] ) : '';
    $address_zip      = isset( $_POST['lv_address_zip'] ) ? sanitize_text_field( $_POST['lv_address_zip'] ) : '';
    $address_country  = isset( $_POST['lv_address_country'] ) ? sanitize_text_field( $_POST['lv_address_country'] ) : '';
    $contact_phone    = isset( $_POST['lv_contact_phone'] ) ? sanitize_text_field( $_POST['lv_contact_phone'] ) : '';
    $contact_email    = isset( $_POST['lv_contact_email'] ) ? sanitize_email( $_POST['lv_contact_email'] ) : '';
    $contact_website  = isset( $_POST['lv_contact_website'] ) ? esc_url_raw( $_POST['lv_contact_website'] ) : '';
    $operating_hours  = isset( $_POST['lv_operating_hours'] ) ? sanitize_textarea_field( $_POST['lv_operating_hours'] ) : '';
    $listing_categories = isset( $_POST['lv_listing_category'] ) ? (array) $_POST['lv_listing_category'] : array();
    $listing_categories = array_map( 'intval', $listing_categories );
    $listing_tags     = isset( $_POST['lv_listing_tag'] ) ? sanitize_text_field( $_POST['lv_listing_tag'] ) : '';


    // --- Get Admin Settings ---
    $plugin_options = get_option( 'localverse_options' ); // The option_name from LocalVerse_Admin_Settings
    $default_status = isset( $plugin_options['default_submission_status'] ) ? $plugin_options['default_submission_status'] : 'pending';
    $redirect_page_id = isset( $plugin_options['submission_redirect_page'] ) ? $plugin_options['submission_redirect_page'] : 0;

    // --- Prepare Post Data ---
    $post_data = array(
        'post_title'    => $title,
        'post_content'  => $description,
        'post_status'   => $default_status, // Use admin setting
        'post_type'     => 'localverse_listing',
        'post_author'   => get_current_user_id(),
    );

    $new_listing_id = wp_insert_post( $post_data );

    if ( is_wp_error( $new_listing_id ) ) {
        $form_page_url = get_permalink( get_page_by_path( 'submit-listing' ) );
        $redirect_url = add_query_arg( 'submission_status', 'error', $form_page_url );
        wp_redirect( $redirect_url );
        exit;
    }

    // --- Save Custom Fields (Post Meta) ---
    // ... (as before) ...
    update_post_meta( $new_listing_id, '_lv_address_street', $address_street );
    update_post_meta( $new_listing_id, '_lv_address_city', $address_city );
    update_post_meta( $new_listing_id, '_lv_address_state', $address_state );
    update_post_meta( $new_listing_id, '_lv_address_zip', $address_zip );
    update_post_meta( $new_listing_id, '_lv_address_country', $address_country );
    update_post_meta( $new_listing_id, '_lv_contact_phone', $contact_phone );
    update_post_meta( $new_listing_id, '_lv_contact_email', $contact_email );
    update_post_meta( $new_listing_id, '_lv_contact_website', $contact_website );
    update_post_meta( $new_listing_id, '_lv_operating_hours', $operating_hours );

    // --- Assign Taxonomies ---
    // ... (as before) ...
    if ( ! empty( $listing_categories ) ) {
        wp_set_object_terms( $new_listing_id, $listing_categories, 'listing_category', false );
    }
    if ( ! empty( $listing_tags ) ) {
        $tags_array = array_map( 'trim', explode( ',', $listing_tags ) );
        wp_set_object_terms( $new_listing_id, $tags_array, 'listing_tag', false );
    }


    // --- Handle Featured Image Upload ---
    // ... (as before) ...
    if ( isset( $_FILES['lv_featured_image'] ) && ! empty( $_FILES['lv_featured_image']['name'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        $attachment_id = media_handle_upload( 'lv_featured_image', $new_listing_id );
        if ( !is_wp_error( $attachment_id ) ) {
            set_post_thumbnail( $new_listing_id, $attachment_id );
        }
    }

    // --- Redirect on Success ---
    $form_page_url = get_permalink( get_page_by_path( 'submit-listing' ) ); // Fallback
    $success_redirect_url = $form_page_url; // Default to form page

    if ( $redirect_page_id > 0 && get_permalink( $redirect_page_id ) ) {
        $success_redirect_url = get_permalink( $redirect_page_id );
    }

    // Add status to the redirect URL, whether it's the custom page or form page
    $final_redirect_url = add_query_arg( 'submission_status', 'success', $success_redirect_url );

    // If redirecting to the form page itself, the message is already handled there.
    // If redirecting to a custom thank you page, that page would need to handle the 'submission_status' query arg.
    // For simplicity, if it's a different page, we won't add the query arg, assuming the page itself is the success message.
    // However, to keep the message display consistent (as the form page does), let's add it.

    wp_redirect( $final_redirect_url );
    exit;
}
}

/**
 * Begins execution of the plugin.
 */
function localverse_run_plugin() {
    $plugin = new LocalVerse_Core();
    // $plugin->run(); // We will call run() later when the loader is set up.

    // For now, let's call the methods to register post types and taxonomies directly
    // In a real scenario with a loader, these would be hooked actions.
    // $plugin->define_post_types(); // This will be called after post_types.php is created
    // $plugin->define_taxonomies(); // This will be called after taxonomies.php is created
}

// Modify the run_localverse function in localverse.php to instantiate and run the core class.
// This instantiation should happen *after* the LocalVerse_Core class definition.
// However, for now, we'll just instantiate. The actual 'run' method and hooks will come later.

// Example of how localverse.php's run_localverse function might be updated:
// function run_localverse() {
//     $plugin = new LocalVerse_Core();
//     // $plugin->run(); // This will be uncommented later
// }
// add_action( 'plugins_loaded', 'run_localverse' ); // Hook to run after all plugins are loaded.

// For now, we will call our bootstrap function directly.
// In localverse.php, the `run_localverse()` function at the end of the file should be
// updated to instantiate this LocalVerse_Core class.
// And the `require plugin_dir_path( __FILE__ ) . 'includes/core.php';`
// should be followed by:
// function run_localverse_plugin_boot() {
//     $GLOBALS['localverse_plugin'] = new LocalVerse_Core();
// }
// add_action( 'plugins_loaded', 'run_localverse_plugin_boot' );

?>
