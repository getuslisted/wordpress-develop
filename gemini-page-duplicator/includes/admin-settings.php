<?php
/**
 * Admin Settings Page for Gemini Page Duplicator
 *
 * @package GeminiPageDuplicator
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Add the admin menu item for the settings page.
 */
function gpd_add_admin_menu() {
	add_options_page(
		__( 'Gemini Page Duplicator Settings', 'gemini-page-duplicator' ),
		__( 'Gemini Duplicator', 'gemini-page-duplicator' ),
		'manage_options',
		'gemini_page_duplicator_settings',
		'gpd_settings_page_html'
	);
}
add_action( 'admin_menu', 'gpd_add_admin_menu' );

/**
 * Register plugin settings.
 */
function gpd_register_settings() {
	register_setting(
		'gemini_page_duplicator_options_group', // Option group
		'gpd_gemini_api_key',                   // Option name
		array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		)
	);

	add_settings_section(
		'gpd_api_settings_section',             // ID
		__( 'Gemini API Settings', 'gemini-page-duplicator' ), // Title
		'gpd_api_settings_section_callback',    // Callback
		'gemini_page_duplicator_settings'       // Page
	);

	add_settings_field(
		'gpd_api_key_field',                    // ID
		__( 'Gemini API Key', 'gemini-page-duplicator' ), // Title
		'gpd_api_key_field_callback',           // Callback
		'gemini_page_duplicator_settings',       // Page
		'gpd_api_settings_section'              // Section
	);
}
add_action( 'admin_init', 'gpd_register_settings' );

/**
 * Callback for the API settings section.
 */
function gpd_api_settings_section_callback() {
	echo '<p>' . esc_html__( 'Enter your Google Gemini API key below. This key is required to use the content rewriting features.', 'gemini-page-duplicator' ) . '</p>';
	echo '<p>' . sprintf(
		wp_kses(
			/* translators: %s: Link to Google AI Studio. */
			__( 'You can obtain an API key from %s.', 'gemini-page-duplicator' ),
			array( 'a' => array( 'href' => array(), 'target' => array(), 'rel' => array() ) )
		),
		'<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener">Google AI Studio</a>'
	) . '</p>';
}

/**
 * Callback for the API key field.
 */
function gpd_api_key_field_callback() {
	$api_key = get_option( 'gpd_gemini_api_key' );
	echo '<input type="text" id="gpd_gemini_api_key" name="gpd_gemini_api_key" value="' . esc_attr( $api_key ) . '" class="regular-text" />';
}

/**
 * Render the settings page HTML.
 */
function gpd_settings_page_html() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'gemini_page_duplicator_options_group' ); // Nonce, action, option_page fields
			do_settings_sections( 'gemini_page_duplicator_settings' );  // Sections and fields
			submit_button( __( 'Save Settings', 'gemini-page-duplicator' ) );
			?>
		</form>
	</div>
	<?php
}
?>
