<?php
/**
 * Admin UI.
 *
 * @package GlobalColorManager
 */

namespace GCM;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global admin page.
 */
class Admin {
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
	 * Init hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_gcm_scan_global', array( $this, 'handle_scan' ) );
		add_action( 'admin_post_gcm_save_global', array( $this, 'handle_save' ) );
		add_action( 'admin_post_gcm_reset_global', array( $this, 'handle_reset' ) );
	}

	/**
	 * Register menu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_theme_page(
			__( 'Global Color Manager', 'global-color-manager' ),
			__( 'Global Color Manager', 'global-color-manager' ),
			'manage_options',
			'global-color-manager',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @param string $hook Hook.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'appearance_page_global-color-manager' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'gcm-admin', GCM_PLUGIN_URL . 'assets/css/admin.css', array(), GCM_VERSION );
		wp_enqueue_script( 'wp-color-picker' );
		wp_enqueue_script( 'gcm-admin', GCM_PLUGIN_URL . 'assets/js/admin.js', array( 'wp-color-picker', 'jquery' ), GCM_VERSION, true );
	}

	/**
	 * Handle global scan.
	 *
	 * @return void
	 */
	public function handle_scan() {
		$this->authorize_action( 'gcm_scan_global' );
		$this->detector->get_global_index();
		wp_safe_redirect( admin_url( 'themes.php?page=global-color-manager&scanned=1' ) );
		exit;
	}

	/**
	 * Handle save replacements.
	 *
	 * @return void
	 */
	public function handle_save() {
		$this->authorize_action( 'gcm_save_global' );

		$replacements = array();
		if ( isset( $_POST['replacements'] ) && is_array( $_POST['replacements'] ) ) {
			foreach ( $_POST['replacements'] as $key => $value ) {
				$replacements[ sanitize_text_field( wp_unslash( $key ) ) ] = sanitize_hex_color( wp_unslash( $value ) );
			}
		}

		$this->replacer->save_global_replacements( $replacements );
		wp_safe_redirect( admin_url( 'themes.php?page=global-color-manager&updated=1' ) );
		exit;
	}

	/**
	 * Handle reset.
	 *
	 * @return void
	 */
	public function handle_reset() {
		$this->authorize_action( 'gcm_reset_global' );
		$this->replacer->reset_global_replacements();
		wp_safe_redirect( admin_url( 'themes.php?page=global-color-manager&reset=1' ) );
		exit;
	}

	/**
	 * Render admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$index        = get_option( 'gcm_global_index', array() );
		$replacements = $this->replacer->get_global_replacements();
		?>
		<div class="wrap gcm-admin-wrap">
			<h1><?php echo esc_html__( 'Global Color Manager', 'global-color-manager' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gcm-admin-card">
				<input type="hidden" name="action" value="gcm_scan_global" />
				<?php wp_nonce_field( 'gcm_scan_global' ); ?>
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Scan Entire Site', 'global-color-manager' ); ?></button>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gcm-admin-card">
				<input type="hidden" name="action" value="gcm_save_global" />
				<?php wp_nonce_field( 'gcm_save_global' ); ?>
				<table class="widefat striped gcm-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Swatch', 'global-color-manager' ); ?></th>
							<th><?php esc_html_e( 'HEX', 'global-color-manager' ); ?></th>
							<th><?php esc_html_e( 'RGB', 'global-color-manager' ); ?></th>
							<th><?php esc_html_e( 'Total', 'global-color-manager' ); ?></th>
							<th><?php esc_html_e( 'Replacement', 'global-color-manager' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $index ) ) : ?>
							<tr><td colspan="5"><?php esc_html_e( 'No scan data yet. Run a site scan.', 'global-color-manager' ); ?></td></tr>
						<?php else : ?>
							<?php foreach ( $index as $key => $item ) : ?>
								<tr>
									<td><span class="gcm-swatch" style="background: <?php echo esc_attr( $item['hex'] ); ?>"></span></td>
									<td><?php echo esc_html( $item['hex'] ); ?></td>
									<td><?php echo esc_html( $item['rgb'] ); ?></td>
									<td><?php echo esc_html( (string) $item['count'] ); ?></td>
									<td>
										<input
											type="text"
											name="replacements[<?php echo esc_attr( $key ); ?>]"
											value="<?php echo esc_attr( $replacements[ $key ] ?? '' ); ?>"
											class="gcm-color-field"
										/>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>
				<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Apply Sitewide Override', 'global-color-manager' ); ?></button></p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gcm-admin-card">
				<input type="hidden" name="action" value="gcm_reset_global" />
				<?php wp_nonce_field( 'gcm_reset_global' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Reset Global Overrides', 'global-color-manager' ); ?></button>
			</form>
		</div>
		<?php
	}

	/**
	 * Authorize actions.
	 *
	 * @param string $nonce_action Nonce action.
	 * @return void
	 */
	private function authorize_action( $nonce_action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions.', 'global-color-manager' ) );
		}
		check_admin_referer( $nonce_action );
	}
}
