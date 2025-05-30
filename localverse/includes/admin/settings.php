<?php
/**
 * Admin Settings for LocalVerse Plugin
 *
 * @link       https://example.com
 * @since      0.1.0
 *
 * @package    LocalVerse
 * @subpackage LocalVerse/includes/admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LocalVerse_Admin_Settings {

    /**
     * Option group and option name.
     *
     * @since    0.1.0
     * @access   private
     * @var      string
     */
    private $option_group = 'localverse_settings_group';
    private $option_name = 'localverse_options'; // This will store all plugin options

    /**
     * Initialize the class and set its properties.
     *
     * @since    0.1.0
     */
    public function __construct() {
        // We will add the admin menu item via the main Admin class or Core class loader
        // For now, this class will just handle the settings page display and registration
    }

    /**
     * Add options page.
     * A real implementation would hook this into 'admin_menu'.
     */
    public function add_plugin_page() {
        add_options_page(
            __( 'LocalVerse Settings', 'localverse' ), // Page title
            __( 'LocalVerse', 'localverse' ),          // Menu title
            'manage_options',                          // Capability
            'localverse-settings',                     // Menu slug
            array( $this, 'create_admin_page' )        // Function to display the page
        );
    }

    /**
     * Options page callback.
     */
    public function create_admin_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p><?php _e( 'Welcome to the LocalVerse settings page. More options will be available here soon!', 'localverse' ); ?></p>

            <form method="post" action="options.php">
                <?php
                // This prints out all hidden setting fields
                settings_fields( $this->option_group );
                do_settings_sections( 'localverse-settings-admin' ); // Page slug for settings sections
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings.
     * A real implementation would hook this into 'admin_init'.
     */
    public function page_init() {
        register_setting(
            $this->option_group,          // Option group
            $this->option_name,           // Option name
            array( $this, 'sanitize' )    // Sanitize callback
        );

        add_settings_section(
            'localverse_general_settings_section', // ID
            __( 'General Settings', 'localverse' ), // Title
        array( $this, 'print_section_info' ),
        'localverse-settings-admin' // Page slug for settings sections
        );

        add_settings_field(
        'example_setting_id', // Keep existing example or remove
        __( 'Example Setting', 'localverse' ),
        array( $this, 'example_setting_callback' ),
        'localverse-settings-admin',
        'localverse_general_settings_section'
    );

    // New Listing Submission Settings Section
    add_settings_section(
        'localverse_submission_settings_section', // ID
        __( 'Listing Submission Settings', 'localverse' ), // Title
        array( $this, 'print_submission_section_info' ), // Callback
        'localverse-settings-admin' // Page
    );

    add_settings_field(
        'default_submission_status', // ID
        __( 'Default Submission Status', 'localverse' ), // Title
        array( $this, 'default_submission_status_callback' ), // Callback
        'localverse-settings-admin', // Page
        'localverse_submission_settings_section' // Section
    );

    add_settings_field(
        'submission_redirect_page', // ID
        __( 'Submission Redirect Page', 'localverse' ), // Title
        array( $this, 'submission_redirect_page_callback' ), // Callback
            'localverse-settings-admin', // Page
        'localverse_submission_settings_section' // Section
        );
    }

    /**
     * Sanitize each setting field as needed.
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input ) {
        $new_input = array();
        if ( isset( $input['example_setting_id'] ) ) { // Keep existing or remove
            $new_input['example_setting_id'] = sanitize_text_field( $input['example_setting_id'] );
        }

        // Sanitize default submission status
        if ( isset( $input['default_submission_status'] ) ) {
            $allowed_statuses = array( 'publish', 'pending', 'draft' ); // Whitelist
            if ( in_array( $input['default_submission_status'], $allowed_statuses, true ) ) {
                $new_input['default_submission_status'] = $input['default_submission_status'];
            } else {
                $new_input['default_submission_status'] = 'pending'; // Fallback
            }
        } else {
            $new_input['default_submission_status'] = 'pending'; // Default if not set
        }

        // Sanitize submission redirect page ID
        if ( isset( $input['submission_redirect_page'] ) ) {
            $new_input['submission_redirect_page'] = absint( $input['submission_redirect_page'] ); // Ensure it's a positive integer (0 for none)
        } else {
            $new_input['submission_redirect_page'] = 0; // Default if not set
        }

        return $new_input;
    }

    /**
     * Print the Section Text.
     */
    public function print_section_info() {
        _e( 'Enter your settings below:', 'localverse' );
    }

    /**
     * Get the settings option array and print one of its values.
     */
    public function example_setting_callback() {
        $options = get_option( $this->option_name );
        printf(
            '<input type="text" id="example_setting_id" name="%s[example_setting_id]" value="%s" />',
            esc_attr( $this->option_name ), // Ensure name is like localverse_options[example_setting_id]
            isset( $options['example_setting_id'] ) ? esc_attr( $options['example_setting_id'] ) : ''
        );
        echo '<p class="description">' . esc_html__( 'This is an example setting field.', 'localverse' ) . '</p>';
    }

    // New callback for submission section info:
    public function print_submission_section_info() {
        _e( 'Configure how front-end listing submissions are handled:', 'localverse' );
    }

    // New callback for default submission status dropdown:
    public function default_submission_status_callback() {
        $options = get_option( $this->option_name );
        $current_status = isset( $options['default_submission_status'] ) ? $options['default_submission_status'] : 'pending';
        $statuses = get_post_statuses(); // Get all registered post statuses
        ?>
        <select id="default_submission_status" name="<?php echo esc_attr( $this->option_name ); ?>[default_submission_status]">
            <?php foreach ( $statuses as $status_val => $status_label ) : ?>
                <?php // Only allow a subset of statuses relevant for submission for simplicity
                if (in_array($status_val, ['publish', 'pending', 'draft'])) : ?>
                <option value="<?php echo esc_attr( $status_val ); ?>" <?php selected( $current_status, $status_val ); ?>>
                    <?php echo esc_html( $status_label ); ?>
                </option>
                <?php endif; ?>
            <?php endforeach; ?>
        </select>
        <p class="description"><?php _e( 'Select the default status for listings submitted via the front-end form.', 'localverse' ); ?></p>
        <?php
    }

    // New callback for submission redirect page dropdown:
    public function submission_redirect_page_callback() {
        $options = get_option( $this->option_name );
        $current_page_id = isset( $options['submission_redirect_page'] ) ? $options['submission_redirect_page'] : 0;

        wp_dropdown_pages(array(
            'name'              => esc_attr( $this->option_name ) . '[submission_redirect_page]',
            'id'                => 'submission_redirect_page',
            'selected'          => $current_page_id,
            'show_option_none'  => __( '&mdash; Select a Page (defaults to form page) &mdash;', 'localverse' ),
            'option_none_value' => 0, // Value for "no page selected"
        ));
        echo '<p class="description">' . esc_html__( 'Select a page to redirect users to after successful listing submission. If none, redirects to the submission form page.', 'localverse' ) . '</p>';
    }
}

// The instantiation and hook registration will be managed by a dedicated admin class or the core loader.
// For now, to make it testable if you were to activate the plugin:
// if ( is_admin() ) {
//     $localverse_settings_page = new LocalVerse_Admin_Settings();
//     add_action( 'admin_menu', array( $localverse_settings_page, 'add_plugin_page' ) );
//     add_action( 'admin_init', array( $localverse_settings_page, 'page_init' ) );
// }
?>
