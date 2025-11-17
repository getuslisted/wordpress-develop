<?php
/**
 * Plugin Name: Relationship Quizzr
 * Plugin URI: https://example.com/relationship-quizzr
 * Description: Transform your WordPress site into a gamified couples and friends quiz platform with subscriptions, referrals, and leaderboards.
 * Version: 1.0.0
 * Author: OpenAI Codex
 * Author URI: https://openai.com/
 * License: GPL2+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: relationship-quizzr
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'RelationshipQuizzr' ) ) {

    /**
     * Main plugin bootstrapper.
     */
    final class RelationshipQuizzr {

        /**
         * Plugin version.
         *
         * @var string
         */
        public $version = '1.0.0';

        /**
         * Singleton instance.
         *
         * @var RelationshipQuizzr
         */
        protected static $instance;

        /**
         * Absolute plugin path.
         *
         * @var string
         */
        public $path;

        /**
         * Plugin URL.
         *
         * @var string
         */
        public $url;

        /**
         * Registered service classes.
         *
         * @var array
         */
        protected $services = array();

        /**
         * Get singleton instance.
         *
         * @return RelationshipQuizzr
         */
        public static function instance() {
            if ( ! isset( self::$instance ) ) {
                self::$instance = new self();
            }

            return self::$instance;
        }

        /**
         * RelationshipQuizzr constructor.
         */
        private function __construct() {
            $this->path = plugin_dir_path( __FILE__ );
            $this->url  = plugin_dir_url( __FILE__ );

            $this->define_constants();
            $this->includes();
            $this->init_hooks();
        }

        /**
         * Prevent cloning the singleton instance.
         */
        public function __clone() {
            _doing_it_wrong( __FUNCTION__, __( 'Cloning is forbidden.', 'relationship-quizzr' ), '1.0.0' );
        }

        /**
         * Prevent unserializing the singleton instance.
         */
        public function __wakeup() {
            _doing_it_wrong( __FUNCTION__, __( 'Unserializing is forbidden.', 'relationship-quizzr' ), '1.0.0' );
        }

        /**
         * Setup constants.
         */
        protected function define_constants() {
            if ( ! defined( 'RQ_VERSION' ) ) {
                define( 'RQ_VERSION', $this->version );
            }
            if ( ! defined( 'RQ_PLUGIN_PATH' ) ) {
                define( 'RQ_PLUGIN_PATH', $this->path );
            }
            if ( ! defined( 'RQ_PLUGIN_URL' ) ) {
                define( 'RQ_PLUGIN_URL', $this->url );
            }
            if ( ! defined( 'RQ_PLUGIN_BASENAME' ) ) {
                define( 'RQ_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
            }
        }

        /**
         * Include required files.
         */
        protected function includes() {
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-install.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-post-types.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-subscriptions.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-connections.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-quiz-engine.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-referrals.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-rest-controller.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-shortcodes.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-admin.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-notifications.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-leaderboard.php';
            require_once RQ_PLUGIN_PATH . 'includes/class-rq-profile.php';
        }

        /**
         * Hook into WordPress lifecycle.
         */
        protected function init_hooks() {
            register_activation_hook( __FILE__, array( 'RQ_Install', 'activate' ) );
            register_deactivation_hook( __FILE__, array( 'RQ_Install', 'deactivate' ) );
            register_uninstall_hook( __FILE__, array( 'RQ_Install', 'uninstall' ) );

            add_action( 'plugins_loaded', array( $this, 'bootstrap' ) );
        }

        /**
         * Bootstrap plugin services.
         */
        public function bootstrap() {
            load_plugin_textdomain( 'relationship-quizzr', false, dirname( RQ_PLUGIN_BASENAME ) . '/languages' );

            $this->services['post_types']    = new RQ_Post_Types();
            $this->services['subscriptions'] = new RQ_Subscriptions();
            $this->services['connections']   = new RQ_Connections();
            $this->services['quiz_engine']   = new RQ_Quiz_Engine();
            $this->services['referrals']     = new RQ_Referrals();
            $this->services['rest']          = new RQ_REST_Controller( $this->services );
            $this->services['shortcodes']    = new RQ_Shortcodes( $this->services );
            $this->services['admin']         = new RQ_Admin( $this->services );
            $this->services['notifications'] = new RQ_Notifications( $this->services );
            $this->services['leaderboard']   = new RQ_Leaderboard( $this->services );
            $this->services['profile']       = new RQ_Profile( $this->services );

            add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        }

        /**
         * Enqueue frontend assets.
         */
        public function enqueue_assets() {
            wp_enqueue_style( 'relationship-quizzr-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', array(), '5.3.2' );
            wp_enqueue_style( 'relationship-quizzr', RQ_PLUGIN_URL . 'assets/css/frontend.css', array( 'relationship-quizzr-bootstrap' ), RQ_VERSION );
            wp_enqueue_script( 'relationship-quizzr-bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js', array( 'jquery' ), '5.3.2', true );
            wp_enqueue_script( 'relationship-quizzr', RQ_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), RQ_VERSION, true );
            wp_localize_script(
                'relationship-quizzr',
                'RQSettings',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'rest_url' => esc_url_raw( rest_url( 'rq/v1' ) ),
                    'nonce'    => wp_create_nonce( 'wp_rest' ),
                )
            );
        }
    }

    /**
     * Boot plugin on plugins_loaded.
     */
    function relationship_quizzr() {
        return RelationshipQuizzr::instance();
    }

    relationship_quizzr();
}
