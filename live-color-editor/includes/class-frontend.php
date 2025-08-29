<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and the core output buffering hooks.
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/public
 * @author     Jules <you@example.com>
 */
class LCE_Frontend {

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
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

    /**
     * Starts the output buffer.
     *
     * @since 1.0.0
     */
    public function init_output_buffering() {
        // Don't run in admin, during AJAX requests or for non-html requests.
        if ( is_admin() || wp_doing_ajax() || ( defined('DOING_CRON') && DOING_CRON ) ) {
            return;
        }

        // Get saved color mappings
        $color_mappings = get_option( 'lce_color_mappings', array() );

        // Only start the buffer if there are colors to replace.
        if ( ! empty( $color_mappings ) || current_user_can( 'manage_options' ) ) {
            ob_start( array( $this, 'replace_colors_in_buffer' ) );
        }
    }

    /**
     * The callback function for the output buffer.
     * Replaces the colors in the buffered content.
     *
     * @since 1.0.0
     * @param string $buffer The output buffer.
     * @return string The modified buffer.
     */
    public function replace_colors_in_buffer( $buffer ) {
        $replacer = new LCE_Replacer();
        return $replacer->replace( $buffer );
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        if ( current_user_can( 'manage_options' ) ) {
            wp_enqueue_style( $this->plugin_name . '-frontend', plugin_dir_url( __FILE__ ) . '../assets/css/frontend.css', array( 'wp-color-picker' ), $this->version, 'all' );
        }
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        if ( current_user_can( 'manage_options' ) ) {
            wp_enqueue_script( $this->plugin_name . '-frontend', plugin_dir_url( __FILE__ ) . '../assets/js/frontend.js', array( 'jquery', 'wp-color-picker' ), $this->version, true );

            // Pass data to script
            wp_localize_script( $this->plugin_name . '-frontend', 'lce_ajax_object', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'save_nonce'    => wp_create_nonce( 'lce_save_mappings_nonce' ),
                'scan_nonce'    => wp_create_nonce( 'lce_scan_colors_nonce' ),
            ) );
        }
    }

    /**
     * Add the frontend editor overlay to the footer.
     *
     * @since 1.0.0
     */
    public function add_frontend_overlay() {
        if ( current_user_can( 'manage_options' ) ) {
            require_once LCE_PLUGIN_DIR . 'templates/frontend-overlay-template.php';
        }
    }

    /**
     * Handle AJAX request to save color mappings.
     *
     * @since 1.0.0
     */
    public function ajax_save_mappings() {
        check_ajax_referer( 'lce_save_mappings_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( __( 'You do not have permission to do this.', 'live-color-editor' ) );
        }

        $old_mappings = get_option( 'lce_color_mappings', array() );

        $mappings_data = isset( $_POST['lce_color_mappings'] ) ? (array) $_POST['lce_color_mappings'] : array();

        $sanitized_mappings = array();
        if ( isset( $mappings_data['old_color'] ) && isset( $mappings_data['new_color'] ) ) {
            $old_colors = $mappings_data['old_color'];
            $new_colors = $mappings_data['new_color'];

            for ( $i = 0; $i < count( $old_colors ); $i++ ) {
                $old_color = sanitize_text_field( $old_colors[ $i ] );
                $new_color = sanitize_text_field( $new_colors[ $i ] );

                if ( ! empty( $old_color ) ) {
                    if ( ( preg_match( '/^#([a-f0-9]{3}){1,2}$/i', $old_color ) || preg_match('/rgba?\((\s*\d+\s*,){2,3}\s*[\d\.]+\s*\)/i', $old_color) ) &&
                         ( empty($new_color) || preg_match( '/^#([a-f0-9]{3}){1,2}$/i', $new_color ) || preg_match('/rgba?\((\s*\d+\s*,){2,3}\s*[\d\.]+\s*\)/i', $new_color) ) ) {
                        $sanitized_mappings[ $old_color ] = $new_color;
                    }
                }
            }
        }

        LCE_Logger::log_changes( $old_mappings, $sanitized_mappings );

        update_option( 'lce_color_mappings', $sanitized_mappings );

        wp_send_json_success( __( 'Mappings saved!', 'live-color-editor' ) );
    }
}
