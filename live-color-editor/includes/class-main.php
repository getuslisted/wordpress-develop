<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/includes
 * @author     Jules <you@example.com>
 */
class LCE_Main {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      LCE_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
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
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'LCE_VERSION' ) ) {
			$this->version = LCE_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'live-color-editor';

		$this->load_dependencies();
		$this->define_admin_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - LCE_Loader. Orchestrates the hooks of the plugin.
	 * - LCE_Admin. Defines all hooks for the admin area.
	 * - LCE_Frontend. Defines all hooks for the public side of the site.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once LCE_PLUGIN_DIR . 'includes/class-loader.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once LCE_PLUGIN_DIR . 'includes/class-admin.php';

        /**
		 * The class responsible for defining all actions that occur on the frontend.
		 */
		require_once LCE_PLUGIN_DIR . 'includes/class-frontend.php';

        /**
         * The class responsible for the color replacement logic.
         */
        require_once LCE_PLUGIN_DIR . 'includes/class-replacer.php';

        /**
         * The class responsible for logging changes.
         */
        require_once LCE_PLUGIN_DIR . 'includes/class-logger.php';


		$this->loader = new LCE_Loader();

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new LCE_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
        $this->loader->add_action( 'admin_init', $plugin_admin, 'register_settings' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
        $this->loader->add_action( 'wp_ajax_lce_scan_colors', $plugin_admin, 'ajax_scan_colors' );
        $this->loader->add_action( 'wp_ajax_lce_undo_change', $plugin_admin, 'ajax_undo_change' );

	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {
        $plugin_frontend = new LCE_Frontend( $this->get_plugin_name(), $this->get_version() );

        $this->loader->add_action( 'init', $plugin_frontend, 'init_output_buffering' );

        // Hooks for frontend editor
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_frontend, 'enqueue_styles' );
        $this->loader->add_action( 'wp_enqueue_scripts', $plugin_frontend, 'enqueue_scripts' );
        $this->loader->add_action( 'wp_footer', $plugin_frontend, 'add_frontend_overlay' );
        $this->loader->add_action( 'wp_ajax_lce_save_mappings', $plugin_frontend, 'ajax_save_mappings' );
	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    LCE_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
