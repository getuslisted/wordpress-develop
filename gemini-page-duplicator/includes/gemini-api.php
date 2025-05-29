<?php
/**
 * Gemini API Integration
 *
 * This file contains the class and functions for interacting with the Gemini API.
 *
 * @package GeminiPageDuplicator
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class Gemini_API
 *
 * Handles communication with the Google Gemini API.
 */
class Gemini_API {

	/**
	 * The Gemini API key.
	 *
	 * @var string
	 */
	private $api_key = null;

	/**
	 * The Gemini API endpoint URL.
	 *
	 * @var string
	 */
	private $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent';

	/**
	 * Get the API key.
	 *
	 * Retrieves the API key from WordPress options.
	 *
	 * @return string The API key.
	 */
	public function get_api_key() {
		if ( null === $this->api_key ) {
			$this->api_key = get_option( 'gpd_gemini_api_key', '' );
		}
		return $this->api_key;
	}

	/**
	 * Set the API key (primarily for testing or direct setting).
	 *
	 * Note: The primary way to set the API key should be via the settings page.
	 * This method can be used if direct manipulation is needed.
	 *
	 * @param string $api_key The API key to store for the current instance.
	 */
	public function set_api_key( $api_key ) {
		$this->api_key = $api_key;
		// This does not save to options, only sets for the current instance.
		// To save persistently, the Settings API should be used (as in admin-settings.php).
	}

	/**
	 * Send a text prompt to the Gemini API.
	 *
	 * @param string $prompt The text prompt to send to the API.
	 * @return string|WP_Error The generated text from the API or a WP_Error on failure.
	 */
	public function send_prompt( $prompt ) {
		$current_api_key = $this->get_api_key();

		if ( empty( $current_api_key ) ) {
			return new WP_Error( 'api_key_missing', __( 'Gemini API key is missing. Please configure it in the plugin settings.', 'gemini-page-duplicator' ) );
		}

		$url = $this->api_url . '?key=' . $current_api_key;

		$body = array(
			'contents' => array(
				array(
					'parts' => array(
						array(
							'text' => $prompt,
						),
					),
				),
			),
		);

		$args = array(
			'body'        => wp_json_encode( $body ),
			'headers'     => array(
				'Content-Type' => 'application/json',
			),
			'timeout'     => 60, // Seconds
			'method'      => 'POST',
			'data_format' => 'body',
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_request_failed', __( 'Gemini API request failed: ', 'gemini-page-duplicator' ) . $response->get_error_message() );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data = json_decode( $response_body, true );

		if ( $response_code !== 200 ) {
			$api_error_message = '';
			if ( isset( $data['error']['message'] ) ) {
				$api_error_message = $data['error']['message'];
			} elseif ( isset($data['message'] ) ) { // Some APIs might return error in a 'message' field
                $api_error_message = $data['message'];
            }

			$error_detail = sprintf(
				/* translators: 1: HTTP response code, 2: API specific error message if available. */
				__( 'HTTP Error Code: %1$d. API Message: %2$s', 'gemini-page-duplicator' ),
				$response_code,
				esc_html( $api_error_message ? $api_error_message : __( 'No specific error message provided by API.', 'gemini-page-duplicator' ) )
			);
            error_log('Gemini API Error: ' . $response_body); // Log the full error response
			return new WP_Error( 'api_http_error', __( 'Gemini API request failed.', 'gemini-page-duplicator' ) . ' ' . $error_detail );
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log('Gemini API JSON Decode Error: ' . json_last_error_msg() . ' | Response: ' . $response_body );
			return new WP_Error( 'api_json_decode_error', __( 'Failed to decode JSON response from Gemini API.', 'gemini-page-duplicator' ) );
		}
		
		// Check for blockReason which indicates content was blocked (e.g. safety)
		if (isset($data['candidates'][0]['finishReason']) && $data['candidates'][0]['finishReason'] === 'SAFETY') {
			$safety_ratings_info = '';
			if (isset($data['candidates'][0]['safetyRatings'])) {
				foreach ($data['candidates'][0]['safetyRatings'] as $rating) {
					$safety_ratings_info .= sprintf('%s: %s. ', $rating['category'], $rating['probability']);
				}
			}
			$block_error_message = sprintf(
				/* translators: %s: Information about safety ratings if available. */
				__('Content generation was blocked by the API due to safety settings. Details: %s', 'gemini-page-duplicator'),
				trim($safety_ratings_info)
			);
			error_log('Gemini API Safety Block: ' . print_r($data, true));
			return new WP_Error('api_safety_block', $block_error_message);
		}
		
		if (isset($data['promptFeedback']['blockReason'])) {
			$block_reason = $data['promptFeedback']['blockReason'];
			$block_error_message = sprintf(
				/* translators: %s: The reason why the prompt was blocked. */
				__('The prompt was blocked by the API. Reason: %s.', 'gemini-page-duplicator'),
				esc_html($block_reason)
			);
			error_log('Gemini API Prompt Blocked: ' . print_r($data, true));
			return new WP_Error('api_prompt_blocked', $block_error_message);
		}

		if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			error_log('Gemini API Unexpected Response Structure: ' . print_r( $data, true ) );
			return new WP_Error( 'api_response_format_error', __( 'Unexpected response format from Gemini API. Generated text not found.', 'gemini-page-duplicator' ) );
		}

		return $data['candidates'][0]['content']['parts'][0]['text'];
	}
}

/**
 * Helper function to get an instance of the Gemini_API class.
 *
 * @return Gemini_API
 */
function gpd_gemini_api() {
	static $gemini_api_instance;
	if ( null === $gemini_api_instance ) {
		$gemini_api_instance = new Gemini_API();
	}
	return $gemini_api_instance;
}
?>
