<?php
/**
 * Frontend tools.
 *
 * @package GlobalColorManager
 */

namespace GCM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend sidebar manager.
 */
class Frontend {
	/**
	 * Detector.
	 *
	 * @var Detector
	 */
	private $detector;

	/**
	 * Replacer.
	 *
	 * @var Replacer
	 */
	private $replacer;

	/**
	 * Constructor.
	 *
	 * @param Detector $detector Detector.
	 * @param Replacer $replacer Replacer.
	 */
	public function __construct( Detector $detector, Replacer $replacer ) {
		$this->detector = $detector;
		$this->replacer = $replacer;
	}

	/**
	 * Setup hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_footer', array( $this, 'render_sidebar' ) );
		add_action( 'wp_ajax_gcm_save_page_replacements', array( $this, 'ajax_save_page_replacements' ) );
		add_action( 'wp_ajax_gcm_reset_page_replacements', array( $this, 'ajax_reset_page_replacements' ) );
	}

	/**
	 * Enqueue assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! is_singular() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$post_id = get_queried_object_id();
		$colors  = $this->detector->get_post_colors( $post_id );

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'gcm-frontend', GCM_PLUGIN_URL . 'assets/css/frontend.css', array(), GCM_VERSION );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'gcm-frontend', GCM_PLUGIN_URL . 'assets/js/frontend.js', array( 'wp-color-picker', 'jquery' ), GCM_VERSION, true );
		wp_localize_script(
			'gcm-frontend',
			'gcmFrontend',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'gcm_page_replace' ),
				'postId'       => $post_id,
				'colors'       => $colors,
				'replacements' => $this->replacer->get_page_replacements( $post_id ),
			)
		);
	}

	/**
	 * Render sidebar shell.
	 *
	 * @return void
	 */
	public function render_sidebar() {
		if ( ! is_singular() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<button id="gcm-toggle" type="button">GCM</button>
		<div id="gcm-sidebar" aria-hidden="true">
			<div class="gcm-header">
				<strong><?php esc_html_e( 'Page Colors', 'global-color-manager' ); ?></strong>
			</div>
			<div id="gcm-color-list"></div>
			<div class="gcm-actions">
				<input type="text" id="gcm-replace-color" class="gcm-color-field" />
				<button type="button" id="gcm-apply" class="button button-primary"><?php esc_html_e( 'Replace Selected', 'global-color-manager' ); ?></button>
				<button type="button" id="gcm-reset" class="button"><?php esc_html_e( 'Reset Page', 'global-color-manager' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Save page replacements.
	 *
	 * @return void
	 */
	public function ajax_save_page_replacements() {
		$this->authorize_ajax();
		$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error();
		}

		$selected = isset( $_POST['selected'] ) && is_array( $_POST['selected'] ) ? wp_unslash( $_POST['selected'] ) : array();
		$to_color = isset( $_POST['color'] ) ? sanitize_hex_color( wp_unslash( $_POST['color'] ) ) : '';
		if ( ! $to_color || empty( $selected ) ) {
			wp_send_json_error();
		}

		$current = $this->replacer->get_page_replacements( $post_id );
		foreach ( $selected as $key ) {
			$clean_key = preg_replace( '/[^0-9,]/', '', sanitize_text_field( $key ) );
			if ( '' !== $clean_key ) {
				$current[ $clean_key ] = $to_color;
			}
		}
		$this->replacer->save_page_replacements( $post_id, $current );
		wp_send_json_success( array( 'replacements' => $current ) );
	}

	/**
	 * Reset page replacements.
	 *
	 * @return void
	 */
	public function ajax_reset_page_replacements() {
		$this->authorize_ajax();
		$post_id = isset( $_POST['postId'] ) ? absint( $_POST['postId'] ) : 0;
		if ( ! $post_id ) {
			wp_send_json_error();
		}
		$this->replacer->reset_page_replacements( $post_id );
		wp_send_json_success();
	}

	/**
	 * Authorize AJAX action.
	 *
	 * @return void
	 */
	private function authorize_ajax() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error();
		}
		check_ajax_referer( 'gcm_page_replace', 'nonce' );
	}
}
