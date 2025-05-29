<?php
/**
 * Plugin Name:       Gemini Page Duplicator
 * Plugin URI:        https://example.com/plugins/gemini-page-duplicator/
 * Description:       Duplicates pages and posts in WordPress.
 * Version:           1.0.0
 * Author:            Gemini AI
 * Author URI:        https://gemini.google.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gemini-page-duplicator
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Placeholder function to ensure the plugin can be activated.
 */
function gpd_placeholder_function() {
	// This is a placeholder function.
	// More functionality will be added later.
}
add_action( 'init', 'gpd_placeholder_function' );

/**
 * Load plugin textdomain.
 */
function gpd_load_textdomain() {
	load_plugin_textdomain( 'gemini-page-duplicator', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'gpd_load_textdomain' );

// Plugin activation/deactivation hooks (optional, but good practice)
register_activation_hook( __FILE__, 'gpd_activate' );
register_deactivation_hook( __FILE__, 'gpd_deactivate' );

/**
 * Plugin activation callback.
 */
function gpd_activate() {
	// Placeholder for activation tasks.
}

/**
 * Plugin deactivation callback.
 */
function gpd_deactivate() {
	// Placeholder for deactivation tasks.
}

// Include other files from the 'includes' folder if needed.
require_once plugin_dir_path( __FILE__ ) . 'includes/gemini-api.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/page-duplicator.php';

// Admin specific includes
if ( is_admin() ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/admin-settings.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/admin-meta-box.php';
	require_once plugin_dir_path( __FILE__ ) . 'includes/admin-ui-enhancements.php'; // Added this line

	// Enqueue scripts for Gutenberg editor enhancements
	add_action( 'enqueue_block_editor_assets', 'gpd_enqueue_editor_enhancements' );
}

/**
 * Enqueue JavaScript and CSS for editor enhancements.
 */
function gpd_enqueue_editor_enhancements() {
	$script_asset_path = plugin_dir_path( __FILE__ ) . 'js/admin-editor-enhancements.asset.php';
	$script_asset = file_exists( $script_asset_path )
		? require( $script_asset_path )
		: array( 'dependencies' => array(
				'wp-plugins', 
				'wp-edit-post', 
				'wp-element', 
				'wp-components', 
				'wp-data', 
				'wp-blocks', 
				'wp-i18n',
				'wp-ajax' // For wp.ajax.post
			), 'version' => filemtime( plugin_dir_path( __FILE__ ) . 'js/admin-editor-enhancements.js' ) 
		);

	wp_enqueue_script(
		'gemini-page-duplicator-editor-enhancements',
		plugin_dir_url( __FILE__ ) . 'js/admin-editor-enhancements.js',
		$script_asset['dependencies'],
		$script_asset['version'],
		true // Load in footer
	);

	// Pass data to script
	wp_localize_script(
		'gemini-page-duplicator-editor-enhancements',
		'gpd_editor_params',
		array(
			'nonce' => wp_create_nonce( 'gpd_rewrite_block_nonce' ),
		)
	);
}

/**
 * AJAX handler for rewriting block content.
 * Proof of concept: just logs the data.
 */
function gpd_ajax_rewrite_block_content_handler() {
	check_ajax_referer( 'gpd_rewrite_block_nonce', 'nonce' );

	$client_id = isset( $_POST['clientId'] ) ? sanitize_text_field( $_POST['clientId'] ) : null;
	$block_name = isset( $_POST['blockName'] ) ? sanitize_text_field( $_POST['blockName'] ) : null;
	// Attributes can be complex, so full sanitization would require knowing the structure.
	// For logging, this is okay. For processing, more care is needed.
	$attributes = isset( $_POST['attributes'] ) ? wp_unslash( $_POST['attributes'] ) : null; 

	error_log( 'Gemini Duplicator AJAX - Rewrite Block:' );
	error_log( 'Client ID: ' . print_r( $client_id, true ) );
	error_log( 'Block Name: ' . print_r( $block_name, true ) );
	error_log( 'Attributes: ' . print_r( $attributes, true ) );

	$text_to_rewrite = '';
	$original_attributes = $attributes; // Keep a copy
	$new_attributes = $attributes;    // Start with original, modify specific ones

	// Extract text based on block type
	switch ( $block_name ) {
		case 'core/paragraph':
		case 'core/heading':
			if ( isset( $attributes['content'] ) ) {
				$text_to_rewrite = $attributes['content'];
			}
			break;
		case 'core/list':
			if ( isset( $attributes['values'] ) ) {
				// values is HTML (e.g., "<li>Item 1</li><li>Item 2</li>")
				// For now, strip tags to get text. More advanced would parse LIs.
				$text_to_rewrite = wp_strip_all_tags( $attributes['values'] ); 
			}
			break;
		case 'core/quote':
			if ( isset( $attributes['value'] ) ) { // value is the main quote HTML
				$text_to_rewrite = wp_strip_all_tags( $attributes['value'] );
			}
			if ( isset( $attributes['citation'] ) && ! empty( $attributes['citation'] ) ) {
				$text_to_rewrite .= "\n\n" . __( 'Citation: ', 'gemini-page-duplicator' ) . wp_strip_all_tags( $attributes['citation'] );
			}
			break;
		case 'core/details':
			if ( isset( $attributes['summary'] ) ) {
				$text_to_rewrite = $attributes['summary'];
				// Rewriting inner blocks content is complex for this AJAX handler, skipping for now.
				// A full solution might involve serializing inner blocks, processing text, and reconstructing.
			}
			break;
		default:
			wp_send_json_error( array( 'message' => __( 'This block type is not supported for rewriting.', 'gemini-page-duplicator' ) ) );
			return;
	}

	if ( empty( trim( $text_to_rewrite ) ) ) {
		wp_send_json_error( array( 'message' => __( 'No text content found in the selected block to rewrite.', 'gemini-page-duplicator' ) ) );
		return;
	}

	// Shortcode preservation
	$shortcode_placeholders = array();
	$placeholder_id_counter = 0;
	$regex = get_shortcode_regex();
	$text_with_placeholders = preg_replace_callback( "/$regex/s", function( $matches ) use ( &$shortcode_placeholders, &$placeholder_id_counter ) {
		$original_shortcode = $matches[0];
		$placeholder = '<!-- GPD_SHORTCODE_PLACEHOLDER_' . $placeholder_id_counter . ' -->';
		$shortcode_placeholders[ $placeholder ] = $original_shortcode;
		$placeholder_id_counter++;
		return $placeholder;
	}, $text_to_rewrite );
	
	$custom_prompt_input = isset( $_POST['customPrompt'] ) ? sanitize_textarea_field( $_POST['customPrompt'] ) : '';
	$final_prompt_text = '';

	$custom_prompt_input = isset( $_POST['customPrompt'] ) ? sanitize_textarea_field( $_POST['customPrompt'] ) : '';
	$base_instruction_for_gemini = '';

	if ( $block_name === 'core/paragraph' || $block_name === 'core/heading' || $block_name === 'core/quote' || $block_name === 'core/list') {
		// For blocks where content is HTML and inline tags should be preserved.
		$base_instruction_for_gemini = __( 'You are an expert content editor. Rewrite the following HTML content. IMPORTANT: Preserve all HTML tags (like <a>, <strong>, <em>, <code>, etc.) and their attributes exactly as they are. Only modify the text nodes within the HTML. Do not add or remove any HTML elements. If custom instructions are provided, they take precedence for the rewrite style but HTML preservation rules must still be followed.', 'gemini-page-duplicator' );
	} else {
		// For blocks where content might be simpler text or specific structures (like details summary)
		$base_instruction_for_gemini = __( 'Rewrite the following text content. If custom instructions are provided, they specify how to rewrite.', 'gemini-page-duplicator' );
	}

	if ( ! empty( $custom_prompt_input ) ) {
		$final_prompt_text = $base_instruction_for_gemini . "\n\n" . __( 'User\'s Custom Instructions:', 'gemini-page-duplicator' ) . "\n" . $custom_prompt_input . "\n\n" . __( 'The HTML/text to process is:', 'gemini-page-duplicator' ) . "\n" . $text_with_placeholders;
	} else {
		// Default action if no custom prompt (e.g. make more concise, or just a general rewrite)
		$default_rewrite_action = __( 'Make the following text more clear and concise, then provide the rewritten version: ', 'gemini-page-duplicator' );
		$final_prompt_text = $base_instruction_for_gemini . "\n\n" . $default_rewrite_action . "\n" . $text_with_placeholders;
	}
	
	// For single blocks, chunking is generally not applied here for simplicity.
	$prompt = $final_prompt_text;
	$gemini_response = gpd_gemini_api()->send_prompt( $prompt );

	if ( is_wp_error( $gemini_response ) ) {
		wp_send_json_error( array( 'message' => __( 'Gemini API Error: ', 'gemini-page-duplicator' ) . $gemini_response->get_error_message() ) );
		return;
	}

	$rewritten_text = $gemini_response;

	// Restore shortcodes
	if ( ! empty( $shortcode_placeholders ) ) {
		foreach ( $shortcode_placeholders as $placeholder => $original_shortcode ) {
			$rewritten_text = str_replace( $placeholder, $original_shortcode, $rewritten_text );
		}
	}

	// Update attributes based on block type
	switch ( $block_name ) {
		case 'core/paragraph':
		case 'core/heading':
			$new_attributes['content'] = $rewritten_text;
			break;
		case 'core/list':
			// This is tricky. Gemini gives plain text. We need to convert to <li> items.
			// Simple approach: each line is an <li>. More complex formatting is lost.
			$list_items_html = '';
			$lines = explode( "\n", $rewritten_text );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( ! empty( $line ) ) {
					$list_items_html .= '<li>' . esc_html( $line ) . '</li>';
				}
			}
			$new_attributes['values'] = $list_items_html;
			break;
		case 'core/quote':
			// Attempt to separate citation if we added a marker.
			// This is very basic and might not work well.
			$citation_marker = "\n\n" . __( 'Citation: ', 'gemini-page-duplicator' );
			$citation_pos = strrpos( $rewritten_text, $citation_marker );
			if ( $citation_pos !== false ) {
				$new_value = substr( $rewritten_text, 0, $citation_pos );
				$new_citation = substr( $rewritten_text, $citation_pos + strlen( $citation_marker ) );
				$new_attributes['value'] = wpautop( $new_value ); // Assuming quote value needs <p>
				$new_attributes['citation'] = $new_citation;
			} else {
				$new_attributes['value'] = wpautop( $rewritten_text );
				if(isset($new_attributes['citation'])) unset($new_attributes['citation']); // Remove citation if not found in rewritten
			}
			break;
		case 'core/details':
			$new_attributes['summary'] = $rewritten_text; 
			// Note: InnerBlocks content not handled here.
			break;
	}

	wp_send_json_success( array( 'newAttributes' => $new_attributes, 'blockName' => $block_name ) );
}
add_action( 'wp_ajax_gpd_rewrite_block_content', 'gpd_ajax_rewrite_block_content_handler' );

?>
