<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/admin
 * @author     Jules <you@example.com>
 */
class LCE_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {
        wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . '../assets/css/admin.css', array( 'wp-color-picker' ), $this->version, 'all' );
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts( $hook ) {
        // Only load on our plugin's pages
        if ( strpos( $hook, 'live-color-editor' ) === false ) {
            return;
        }

        if ( 'toplevel_page_live-color-editor' === $hook ) {
            wp_enqueue_script( $this->plugin_name . '-admin', plugin_dir_url( __FILE__ ) . '../assets/js/admin.js', array( 'jquery', 'wp-color-picker' ), $this->version, true );

            wp_localize_script( $this->plugin_name . '-admin', 'lce_admin_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'scan_nonce' => wp_create_nonce( 'lce_scan_colors_nonce' )
            ));
        }

        if ( 'live-color-editor_page_live-color-editor-activity-log' === $hook ) {
             wp_enqueue_script( $this->plugin_name . '-activity-log', plugin_dir_url( __FILE__ ) . '../assets/js/activity-log.js', array( 'jquery' ), $this->version, true );
             wp_localize_script( $this->plugin_name . '-activity-log', 'lce_log_ajax', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'undo_nonce' => wp_create_nonce( 'lce_undo_change_nonce' )
            ));
        }
	}

    /**
     * Adds the admin menu page for the plugin.
     *
     * @since 1.0.0
     */
    public function add_admin_menu() {
        add_menu_page(
            __( 'Live Color Editor', 'live-color-editor' ),
            __( 'Live Color Editor', 'live-color-editor' ),
            'manage_options',
            $this->plugin_name,
            array( $this, 'display_plugin_settings_page' ),
            'dashicons-admin-customizer',
            100
        );

        add_submenu_page(
            $this->plugin_name,
            __( 'Activity Log', 'live-color-editor' ),
            __( 'Activity Log', 'live-color-editor' ),
            'manage_options',
            $this->plugin_name . '-activity-log',
            array( $this, 'display_activity_log_page' )
        );
    }

    /**
     * Register the settings for the plugin.
     *
     * @since 1.0.0
     */
    public function register_settings() {
        register_setting(
            'lce_settings_group', // Option group
            'lce_color_mappings', // Option name
            array( $this, 'sanitize_color_mappings' ) // Sanitize callback
        );

        add_settings_section(
            'lce_settings_section', // ID
            __( 'Color Mappings', 'live-color-editor' ), // Title
            array( $this, 'print_section_info' ), // Callback
            'lce-settings-page' // Page
        );

        add_settings_field(
            'color_mappings', // ID
            __( 'Colors', 'live-color-editor' ), // Title
            array( $this, 'render_color_mappings_field' ), // Callback
            'lce-settings-page', // Page
            'lce_settings_section' // Section
        );
    }

    /**
     * Sanitize the color mappings input.
     *
     * @param array $input The input from the settings form.
     * @return array The sanitized input.
     */
    public function sanitize_color_mappings( $input ) {
        $old_mappings = get_option( 'lce_color_mappings', array() );
        $new_input = array();

        if ( isset( $input['old_color'] ) && isset( $input['new_color'] ) ) {
            $old_colors = $input['old_color'];
            $new_colors = $input['new_color'];

            for ( $i = 0; $i < count( $old_colors ); $i++ ) {
                $old_color = sanitize_text_field( $old_colors[ $i ] );
                $new_color = sanitize_text_field( $new_colors[ $i ] );

                if ( ! empty( $old_color ) && ! empty( $new_color ) ) {
                    // Basic validation for color format
                    if ( ( preg_match( '/^#([a-f0-9]{3}){1,2}$/i', $old_color ) || preg_match('/rgba?\((\s*\d+\s*,){2,3}\s*[\d\.]+\s*\)/i', $old_color) ) &&
                         ( preg_match( '/^#([a-f0-9]{3}){1,2}$/i', $new_color ) || preg_match('/rgba?\((\s*\d+\s*,){2,3}\s*[\d\.]+\s*\)/i', $new_color) ) ) {
                        $new_input[ $old_color ] = $new_color;
                    }
                }
            }
        }

        LCE_Logger::log_changes( $old_mappings, $new_input );

        return $new_input;
    }

    /**
     * Print the Section text
     */
    public function print_section_info() {
        echo '<p>' . esc_html__( 'Enter the colors you want to replace. The "Old Color" will be replaced with the "New Color" across your site.', 'live-color-editor' ) . '</p>';
    }

    /**
     * Render the color mappings field.
     */
    public function render_color_mappings_field() {
        require_once LCE_PLUGIN_DIR . 'templates/admin-settings-template.php';
    }


    /**
     * Renders the admin page.
     *
     * @since 1.0.0
     */
    public function display_plugin_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Live Color Editor Settings', 'live-color-editor' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                    settings_fields( 'lce_settings_group' );
                    do_settings_sections( 'lce-settings-page' );
                    submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * AJAX handler for scanning colors.
     */
    public function ajax_scan_colors() {
        check_ajax_referer( 'lce_scan_colors_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'live-color-editor' ) );
        }

        $scan_url = home_url();
        $response = wp_remote_get( $scan_url );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( __( 'Failed to fetch site content.', 'live-color-editor' ) );
        }

        $body = wp_remote_retrieve_body( $response );

        $replacer = new LCE_Replacer();
        $colors = $replacer->find_colors( $body );

        wp_send_json_success( $colors );
    }

    /**
     * Renders the activity log page.
     *
     * @since 1.0.0
     */
    public function display_activity_log_page() {
        require_once LCE_PLUGIN_DIR . 'templates/admin-activity-log-template.php';
    }

    /**
     * AJAX handler for undoing a change.
     */
    public function ajax_undo_change() {
        check_ajax_referer( 'lce_undo_change_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'Permission denied.', 'live-color-editor' ) );
        }

        $log_id = isset( $_POST['log_id'] ) ? intval( $_POST['log_id'] ) : 0;
        if ( ! $log_id ) {
            wp_send_json_error( __( 'Invalid log ID.', 'live-color-editor' ) );
        }

        global $wpdb;
        $log_table = $wpdb->prefix . 'lce_activity_log';
        $log_entry = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $log_table WHERE id = %d", $log_id ) );

        if ( ! $log_entry ) {
            wp_send_json_error( __( 'Log entry not found.', 'live-color-editor' ) );
        }

        $current_mappings = get_option( 'lce_color_mappings', array() );
        $new_mappings = $current_mappings;

        switch ( $log_entry->action_type ) {
            case 'created':
            case 'updated':
                // To undo a create or update, we remove the mapping.
                unset( $new_mappings[ $log_entry->old_color ] );
                break;
            case 'deleted':
                // To undo a delete, we restore the mapping.
                $new_mappings[ $log_entry->old_color ] = $log_entry->new_color;
                break;
        }

        update_option( 'lce_color_mappings', $new_mappings );

        // Log the undo action itself
        $wpdb->insert( $log_table, array(
            'old_color' => $log_entry->old_color,
            'new_color' => $log_entry->new_color,
            'action_type' => 'undone',
            'user_id' => get_current_user_id(),
            'timestamp' => current_time( 'mysql' )
        ));

        wp_send_json_success( __( 'Change undone.', 'live-color-editor' ) );
    }
}
