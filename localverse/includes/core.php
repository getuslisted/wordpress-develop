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

    add_action( 'init', array( $this, 'handle_listing_submission' ) );
    add_action( 'init', array( $this, 'handle_review_submission' ) );

    add_filter( 'query_vars', array( $this, 'add_custom_query_vars' ) ); // ADD THIS LINE
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
        require_once LOCALVERSE_PLUGIN_DIR . 'includes/admin/class-localverse-admin-listing-metaboxes.php';
        require_once LOCALVERSE_PLUGIN_DIR . 'includes/admin/class-localverse-admin-event-metaboxes.php'; // ADD THIS LINE

        if ( is_admin() ) { // Make sure we are in the admin area
            $plugin_admin_settings = new LocalVerse_Admin_Settings();
            add_action( 'admin_menu', array( $plugin_admin_settings, 'add_plugin_page' ) );
            add_action( 'admin_init', array( $plugin_admin_settings, 'page_init' ) );

            // Listing Metaboxes
            $listing_metaboxes = new LocalVerse_Admin_Listing_Metaboxes( $this->get_plugin_name(), $this->get_version() );
            add_action( 'add_meta_boxes_localverse_listing', array( $listing_metaboxes, 'add_meta_boxes' ) );
            add_action( 'save_post_localverse_listing', array( $listing_metaboxes, 'save_listing_details' ) );

            // Event Metaboxes (NEW)
            $event_metaboxes = new LocalVerse_Admin_Event_Metaboxes( $this->get_plugin_name(), $this->get_version() );
            add_action( 'add_meta_boxes_localverse_event', array( $event_metaboxes, 'add_meta_boxes' ) );
            add_action( 'save_post_localverse_event', array( $event_metaboxes, 'save_event_details' ) );
            // Note: save_post_{post_type} is used for specificity.

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
        add_filter( 'template_include', array( $this, 'include_submit_listing_template' ) );
        add_filter( 'template_include', array( $this, 'include_owner_dashboard_template' ) );
        add_filter( 'single_template', array( $this, 'override_single_event_template' ) );

        add_filter( 'archive_template', array( $this, 'override_archive_event_template' ) ); // ADD THIS LINE
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_public_styles_scripts' ) );
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
            if ( ! is_user_logged_in() ) {
                // Redirect to login page, then back to the submission form after login
                $redirect_url = wp_login_url( get_permalink() ); // get_permalink() here gets current page URL
                wp_redirect( $redirect_url );
                exit;
            }
            // Optional: Add role/capability check here if needed in the future
            // For example: if ( !current_user_can('submit_localverse_listing_cap') ) { ... }

            $new_template = LOCALVERSE_PLUGIN_DIR . 'templates/submit-listing-form.php';
            if ( file_exists( $new_template ) ) {
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
        // Check if our form has been submitted via POST
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || ! isset( $_POST['localverse_action'] ) || $_POST['localverse_action'] !== 'submit_listing' ) {
            // If not a POST request or not our action, do nothing further in this handler.
            // The check for 'localverse_action' was already there, adding REQUEST_METHOD check for clarity.
            return;
        }

        // Ensure user is logged in to process submission
        if ( ! is_user_logged_in() ) {
            // Let's redirect to the form page with a specific error message.
            $form_page_url = get_permalink( get_page_by_path( 'submit-listing' ) );
            if ($form_page_url) {
                wp_redirect( add_query_arg( 'submission_status', 'login_required', $form_page_url ) );
                exit;
            } else {
                // Fallback if submit page doesn't exist, redirect to home or login.
                wp_redirect( wp_login_url( home_url() ) ); // Redirect to login, then to home.
                exit;
            }
        }

        // Optional: Add role/capability check here if needed in the future for submission processing
        // For example: if ( !current_user_can('submit_localverse_listing_cap') ) { ... exit or redirect ... }


        // ... (rest of the existing nonce check, validation, sanitization, post creation, etc.)
        // Ensure the nonce check is one of the first things after confirming it's our form and user is logged in.
        if ( ! isset( $_POST['localverse_submit_listing_nonce'] ) || ! wp_verify_nonce( $_POST['localverse_submit_listing_nonce'], 'localverse_submit_listing_action' ) ) {
            $form_page_url = get_permalink( get_page_by_path( 'submit-listing' ) );
            $redirect_url = $form_page_url ? add_query_arg( 'submission_status', 'nonce_failure', $form_page_url ) : home_url();
            wp_redirect( $redirect_url );
            exit;
        }

        // Basic Validation & Sanitization (Title is required)
        if ( empty( $_POST['lv_title'] ) ) {
            $form_page_url = get_permalink( get_page_by_path( 'submit-listing' ) );
            $redirect_url = add_query_arg( 'submission_status', 'error', $form_page_url ? $form_page_url : home_url() );
            wp_redirect( $redirect_url );
            exit;
        }

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
        $listing_category_id = isset( $_POST['lv_listing_category'] ) ? intval( $_POST['lv_listing_category'] ) : 0;
        $listing_tags     = isset( $_POST['lv_listing_tag'] ) ? sanitize_text_field( $_POST['lv_listing_tag'] ) : '';

        // --- Get Admin Settings for fallback status ---
        $plugin_options = get_option( 'localverse_options' );
        $default_admin_status = isset( $plugin_options['default_submission_status'] ) ? $plugin_options['default_submission_status'] : 'pending';
        $redirect_page_id = isset( $plugin_options['submission_redirect_page'] ) ? $plugin_options['submission_redirect_page'] : 0;

        // --- Determine Post Status ---
        $new_listing_status = $default_admin_status; // Default to admin setting

        if ( current_user_can( 'publish_localverse_listings' ) ) {
            $new_listing_status = 'publish';
        }
        if ( !in_array($new_listing_status, array('publish', 'pending', 'draft')) ) {
            $new_listing_status = 'pending';
        }

        // --- Prepare Post Data ---
        $post_data = array(
            'post_title'    => $title,
            'post_content'  => $description,
            'post_status'   => $new_listing_status,
            'post_type'     => 'localverse_listing',
            'post_author'   => get_current_user_id(),
        );

        $new_listing_id = wp_insert_post( $post_data );

        if ( is_wp_error( $new_listing_id ) ) {
            $form_page_url = get_permalink( get_page_by_path( 'submit-listing' ) );
            $error_redirect_url = $form_page_url ? add_query_arg( 'submission_status', 'error', $form_page_url ) : home_url();
            wp_redirect( $error_redirect_url );
            exit;
        }

        // Save Custom Fields (Post Meta)
        update_post_meta( $new_listing_id, '_lv_address_street', $address_street );
        update_post_meta( $new_listing_id, '_lv_address_city', $address_city );
        update_post_meta( $new_listing_id, '_lv_address_state', $address_state );
        update_post_meta( $new_listing_id, '_lv_address_zip', $address_zip );
        update_post_meta( $new_listing_id, '_lv_address_country', $address_country );
        update_post_meta( $new_listing_id, '_lv_contact_phone', $contact_phone );
        update_post_meta( $new_listing_id, '_lv_contact_email', $contact_email );
        update_post_meta( $new_listing_id, '_lv_contact_website', $contact_website );
        update_post_meta( $new_listing_id, '_lv_operating_hours', $operating_hours );

        // Assign Taxonomies
        if ( $listing_category_id > 0 ) {
            wp_set_object_terms( $new_listing_id, $listing_category_id, 'listing_category', false );
        }
        if ( ! empty( $listing_tags ) ) {
            $tags_array = array_map( 'trim', explode( ',', $listing_tags ) ); // Already sanitized $listing_tags
            wp_set_object_terms( $new_listing_id, $tags_array, 'listing_tag', false );
        }

        // Handle Featured Image Upload
        if ( isset( $_FILES['lv_featured_image'] ) && ! empty( $_FILES['lv_featured_image']['name'] ) ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            $attachment_id = media_handle_upload( 'lv_featured_image', $new_listing_id );
            if ( !is_wp_error( $attachment_id ) ) {
                set_post_thumbnail( $new_listing_id, $attachment_id );
            }
        }

        // Redirect on Success
        $form_page_url = get_permalink( get_page_by_path( 'submit-listing' ) );
        $success_redirect_url = ( $redirect_page_id > 0 && get_permalink( $redirect_page_id ) ) ? get_permalink( $redirect_page_id ) : ($form_page_url ? $form_page_url : home_url());
        $final_redirect_url = add_query_arg( 'submission_status', 'success', $success_redirect_url );

        wp_redirect( $final_redirect_url );
        exit;
    }

    // New method to enqueue public-facing styles and scripts
    public function enqueue_public_styles_scripts() {
        $options = get_option( 'localverse_options' );
        $api_key = isset( $options['google_maps_api_key'] ) ? $options['google_maps_api_key'] : '';
        $maps_enabled = isset( $options['enable_google_maps'] ) ? (bool) $options['enable_google_maps'] : true;


        // Conditionally enqueue Google Maps API for single listing pages
        if ( $maps_enabled && !empty($api_key) && is_singular( 'localverse_listing' ) ) {
            global $post;
            // require_once LOCALVERSE_PLUGIN_DIR . 'includes/models/Listing.php'; // Ensure model is available
            // $listing = new LocalVerse_Listing($post->ID); // Handled in template now.
            // Only enqueue if the listing has some address components to geocode
            // This check can be more robust by checking specific address fields from the $listing object

            $listing_obj = new LocalVerse_Listing($post->ID); // Create object to check address
            if ($listing_obj->is_valid() && $listing_obj->get_formatted_address('')) { // Check if address exists
                 wp_enqueue_script(
                    'google-maps-api',
                    'https://maps.googleapis.com/maps/api/js?key=' . esc_attr( $api_key ) . '&callback=lvInitMap', // Added &callback=lvInitMap
                    array(),
                    null, // Version
                    true  // In footer
                );
                // Inline script for map initialization can be added here or directly in the template
                // For template, we'll add a placeholder div and JS there.
            }
        }

        // Enqueue your plugin's public stylesheet (example)
        // wp_enqueue_style( $this->plugin_name . '-public', LOCALVERSE_PLUGIN_URL . 'assets/css/public-style.css', array(), $this->version, 'all' );
    }

    /**
     * Handles the front-end review submission.
     * Hooked to 'init'.
     *
     * @since 0.1.0
     */
    public function handle_review_submission() {
        // Check if our form has been submitted and it's our action
        // ... (existing code for handle_review_submission)
        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' || ! isset( $_POST['localverse_action'] ) || $_POST['localverse_action'] !== 'submit_review' ) {
            return;
        }

        // Ensure user is logged in
        if ( ! is_user_logged_in() ) {
            // This should ideally be caught by page template restriction, but defense in depth.
            // Redirect to login or show error. For now, simply exit.
            return;
        }

        // Get the listing ID from the hidden form field
        if ( ! isset( $_POST['listing_id'] ) || ! is_numeric( $_POST['listing_id'] ) ) {
            // Invalid or missing listing ID
            // Redirect back with error: wp_redirect( add_query_arg( 'review_submission_status', 'error', get_permalink( SOME_FALLBACK_PAGE_ID ) ) );
            return;
        }
        $listing_id = intval( $_POST['listing_id'] );
        $listing_permalink = get_permalink( $listing_id );
        if ( ! $listing_permalink ) $listing_permalink = home_url(); // Fallback redirect

        // Verify nonce (nonce includes listing_id)
        if ( ! isset( $_POST['localverse_submit_review_nonce'] ) || ! wp_verify_nonce( $_POST['localverse_submit_review_nonce'], 'localverse_submit_review_action_' . $listing_id ) ) {
            wp_redirect( add_query_arg( 'review_submission_status', 'nonce_failure', $listing_permalink ) );
            exit;
        }

        // Honeypot field check
        if ( ! empty( $_POST['lv_contact_me_by_fax_only_if_you_promise_to_never_contact_me_again'] ) ) {
            // Detected spam submission through honeypot
            wp_redirect( add_query_arg( 'review_submission_status', 'error', $listing_permalink ) ); // Generic error
            exit;
        }

        // Capability check
        if ( ! current_user_can( 'submit_localverse_review', $listing_id ) ) {
            // $listing_id is passed as context, though our cap is general for now
            wp_redirect( add_query_arg( 'review_submission_status', 'cap_failure', $listing_permalink ) );
            // The form template would need to handle 'cap_failure' status message.
            exit;
        }

        // --- Validation & Sanitization ---
        $errors = array();
        $rating = isset( $_POST['lv_review_rating'] ) ? intval( $_POST['lv_review_rating'] ) : 0;
        $review_text = isset( $_POST['lv_review_text'] ) ? sanitize_textarea_field( $_POST['lv_review_text'] ) : '';

        if ( $rating < 1 || $rating > 5 ) {
            $errors[] = __( 'Please select a valid rating between 1 and 5 stars.', 'localverse' );
        }
        if ( empty( $review_text ) ) {
            $errors[] = __( 'Please enter your review text.', 'localverse' );
        }
        // Max length for review text (optional)
        // if ( strlen( $review_text ) > 2000 ) { $errors[] = 'Review text is too long.'; }


        if ( ! empty( $errors ) ) {
            // Store errors in a transient or session to display them, or use a generic message
            // For now, generic validation error
            wp_redirect( add_query_arg( array('review_submission_status' => 'validation_error', 'errors' => implode(',', $errors) ), $listing_permalink ) );
            exit;
        }

        // --- Prepare Post Data for Review CPT ---
        $current_user = wp_get_current_user();
        $listing_post = get_post( $listing_id );
        $listing_title = $listing_post ? $listing_post->post_title : __( 'a listing', 'localverse' );

        // Auto-generate title for the review post
        $review_title = sprintf(
            /* translators: 1: Listing name, 2: User name, 3: Date */
            __( 'Review for "%1$s" by %2$s on %3$s', 'localverse' ),
            $listing_title,
            $current_user->display_name,
            date_i18n( get_option( 'date_format' ) ) // Localized date
        );

        $review_post_data = array(
            'post_title'    => sanitize_text_field( $review_title ),
            'post_content'  => $review_text, // Already sanitized
            'post_status'   => 'publish', // Default to publish for now. Admin setting later.
            'post_type'     => 'localverse_review',
            'post_author'   => $current_user->ID,
            // 'comment_status' => 'closed', // Or 'open' if using WP comments for replies to reviews
        );

        // Insert the review post into the database
        $new_review_id = wp_insert_post( $review_post_data, true ); // Pass true to return WP_Error on failure

        if ( is_wp_error( $new_review_id ) ) {
            // Log error: error_log("Review submission failed: " . $new_review_id->get_error_message());
            wp_redirect( add_query_arg( 'review_submission_status', 'error', $listing_permalink ) );
            exit;
        }

        // --- Save Custom Fields (Post Meta) for the Review ---
        update_post_meta( $new_review_id, '_lv_review_rating', $rating );
        update_post_meta( $new_review_id, '_lv_review_listing_id', $listing_id );

        // --- Handle Review Image Uploads ---
        $uploaded_image_ids = array();
        if ( isset( $_FILES['lv_review_images'] ) && !empty($_FILES['lv_review_images']['name'][0]) ) {
            // Ensure these files are included for media_handle_upload()
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $files = $_FILES['lv_review_images'];
            foreach ( $files['name'] as $key => $value ) {
                if ( $files['name'][$key] ) {
                    $file_array = array(
                        'name'     => $files['name'][$key],
                        'type'     => $files['type'][$key],
                        'tmp_name' => $files['tmp_name'][$key],
                        'error'    => $files['error'][$key],
                        'size'     => $files['size'][$key]
                    );
                    // Let WordPress handle the upload and security.
                    // Pass $new_review_id to attach the image to the review post.
                    $attachment_id = media_handle_sideload( $file_array, $new_review_id );

                    if ( ! is_wp_error( $attachment_id ) ) {
                        $uploaded_image_ids[] = $attachment_id;
                    } else {
                        // Optional: Log error for individual image upload failure
                        // error_log("Review image upload failed: " . $attachment_id->get_error_message());
                    }
                }
            }
            if ( ! empty( $uploaded_image_ids ) ) {
                update_post_meta( $new_review_id, '_lv_review_image_ids', $uploaded_image_ids );
            }
        }

        // --- Redirect on Success ---
        wp_redirect( add_query_arg( 'review_submission_status', 'success#localverse-review-form-wrapper', $listing_permalink ) ); // Add hash to jump to form/reviews area
        exit;
    }

    /**
     * Add custom query variables.
     * @param array $vars Existing query variables.
     * @return array Modified query variables.
     */
    public function add_custom_query_vars( $vars ) {
        $vars[] = 'paged_reviews'; // For review pagination
        return $vars;
    }

    /**
     * Overrides the default single post template for 'localverse_event' CPT.
     *
     * @since 0.1.0
     * @param string $template The path to the template file.
     * @return string The path to the event single template file.
     */
    public function override_single_event_template( $template ) {
        if ( is_singular( 'localverse_event' ) ) {
            $new_template = LOCALVERSE_PLUGIN_DIR . 'templates/single-localverse_event.php';
            if ( file_exists( $new_template ) ) {
                // Optional: Create and pass a LocalVerse_Event object to the template here
                // global $post;
                // $GLOBALS['localverse_event_object'] = new LocalVerse_Event($post->ID); // If model exists
                return $new_template;
            }
        }
        return $template;
    }

    /**
     * Overrides the default archive template for 'localverse_event' CPT.
     *
     * @since 0.1.0
     * @param string $template The path to the template file.
     * @return string The path to the event archive template file.
     */
    public function override_archive_event_template( $template ) {
        if ( is_post_type_archive( 'localverse_event' ) ) {
            $new_template = LOCALVERSE_PLUGIN_DIR . 'templates/archive-localverse_event.php';
            if ( file_exists( $new_template ) ) {
                return $new_template;
            }
        }
        return $template;
    }

   /**
    * Includes the owner dashboard template for a page with a specific slug,
    * only if the current user has the 'business_owner' role.
    *
    * @since 0.1.0
    * @param string $template The path to the template file being included.
    * @return string The path to the owner dashboard template if conditions are met.
    */
   public function include_owner_dashboard_template( $template ) {
       // The admin should create a page with the slug 'owner-dashboard'
       if ( is_page( 'owner-dashboard' ) ) {
           if ( current_user_can( 'business_owner' ) ) {
               $new_template = LOCALVERSE_PLUGIN_DIR . 'templates/dashboard-owner.php';
               if ( file_exists( $new_template ) ) {
                   return $new_template;
               }
           } else {
               // If not a business owner, redirect to home or login page, or show a 'permission denied' message.
               // For simplicity, redirecting to home.
               wp_redirect( home_url() );
               exit;
           }
       }
       return $template;
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
