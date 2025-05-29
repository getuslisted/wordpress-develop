<?php
/**
 * Page Duplication Functionality
 *
 * This file contains the core functions for duplicating posts/pages.
 *
 * @package GeminiPageDuplicator
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Duplicates a post or page.
 *
 * @param int $post_id The ID of the post to duplicate.
 * @return int|WP_Error The ID of the new post on success, or WP_Error on failure.
 */
function gpd_duplicate_post( $post_id ) {
	// Get the original post
	$original_post = get_post( $post_id );

	if ( ! $original_post ) {
		return new WP_Error( 'original_post_not_found', __( 'Original post not found.', 'gemini-page-duplicator' ) );
	}

	// Check if the current user can create posts of this type
	$post_type_object = get_post_type_object( $original_post->post_type );
	if ( ! current_user_can( $post_type_object->cap->create_posts ) ) {
		return new WP_Error( 'permission_denied', __( 'You do not have permission to create posts of this type.', 'gemini-page-duplicator' ) );
	}
    // And if they can edit the original post
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return new WP_Error( 'permission_denied_edit_original', __( 'You do not have permission to edit the original post.', 'gemini-page-duplicator' ) );
    }


	// New post arguments
	$original_content_for_processing = $original_post->post_content;
	$new_post_content = $original_content_for_processing; // Default to original content
	$gemini_api_error_obj = null; // To store any WP_Error from Gemini API
	$dom_error_obj = null; // To store any DOM related error
	$shortcode_placeholders = array();

	// 1. Identify and replace shortcodes with placeholders
	if ( ! empty( $original_content_for_processing ) ) {
		$regex = get_shortcode_regex();
		$placeholder_id_counter = 0;
		
		$original_content_for_processing = preg_replace_callback( "/$regex/s", function( $matches ) use ( &$shortcode_placeholders, &$placeholder_id_counter ) {
			$original_shortcode = $matches[0];
			// Using HTML comments as placeholders for better compatibility with DOMDocument and Gemini
			$placeholder = '<!-- GPD_SHORTCODE_PLACEHOLDER_' . $placeholder_id_counter . ' -->';
			$shortcode_placeholders[ $placeholder ] = $original_shortcode;
			$placeholder_id_counter++;
			return $placeholder;
		}, $original_content_for_processing );
	}

	// Attempt to use DOMDocument for more precise text replacement using $original_content_for_processing
	if ( ! empty( $original_content_for_processing ) && class_exists( 'DOMDocument' ) ) {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		// Try to load the HTML content (which now contains shortcode placeholders)
		if ( ! $doc->loadHTML( mb_convert_encoding( $original_content_for_processing, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD ) ) {
			$dom_error_message = __( 'Failed to parse the HTML content (with shortcode placeholders) of the original post. Content rewriting with Gemini was skipped.', 'gemini-page-duplicator' );
			error_log( 'Gemini Page Duplicator: DOM Load Error - ' . $dom_error_message );
			$dom_error_obj = new WP_Error( 'dom_load_failed', $dom_error_message );
			libxml_clear_errors();
			// If DOM loading fails, $new_post_content remains $original_content_for_processing (with placeholders)
			// Shortcodes will be restored later.
			$new_post_content = $original_content_for_processing;

		} else {
			libxml_clear_errors();
			$xpath = new DOMXPath( $doc );
			$text_nodes = array();
			$original_text_parts = array();

			// Iterate over text nodes, excluding those inside <script> or <style> tags
			foreach ( $xpath->query( '//body//text()[not(ancestor::script) and not(ancestor::style)]' ) as $node ) {
				if ( trim( $node->nodeValue ) !== '' ) {
					$text_nodes[] = $node;
					$original_text_parts[] = $node->nodeValue;
				}
			}

			if ( ! empty( $original_text_parts ) ) {
				$text_to_rewrite = implode( "\n\n", $original_text_parts ); // Consolidate text from DOM
				$processed_gemini_text_output = ''; // This will hold the reassembled text from chunks

				if ( ! empty( $text_to_rewrite ) ) {
					$max_chunk_size = 4500; // Target character count for each chunk
					$text_chunks = gpd_split_text_into_chunks( $text_to_rewrite, $max_chunk_size );
					$all_rewritten_chunks = array();
					$first_api_error_chunks = null; // Store the first error encountered during chunk processing

					$custom_instructions = get_option( 'gpd_temp_custom_prompt_' . $original_post->ID );
					$base_prompt_template = __( 'Please rewrite the following text content, maintaining its original meaning and tone. The text has been extracted from an HTML document. Preserve paragraph-like breaks if you detect them (indicated by double newlines in the input). Your response should be the rewritten text, formatted similarly with paragraph breaks if applicable. Do not add any extra conversational fluff or introductory/concluding remarks. Focus on rewriting the provided text only.', 'gemini-page-duplicator' );
					
					// Delete temp option once read if it's going to be used for multiple chunks
					if ( ! empty( $custom_instructions ) ) {
						delete_option( 'gpd_temp_custom_prompt_' . $original_post->ID );
					}

					foreach ( $text_chunks as $chunk_index => $text_chunk ) {
						$current_prompt = $base_prompt_template;
						if ( ! empty( $custom_instructions ) ) {
							$prompt = sprintf(
								__( '%1$s Additional instructions: "%2$s". Text to rewrite: %3$s', 'gemini-page-duplicator' ),
								$current_prompt, $custom_instructions, $text_chunk
							);
						} else {
							$prompt = sprintf( __( '%1$s Text: %2$s', 'gemini-page-duplicator' ), $current_prompt, $text_chunk );
						}

						$chunk_response = gpd_gemini_api()->send_prompt( $prompt );

						if ( is_wp_error( $chunk_response ) ) {
							error_log( 'Gemini Page Duplicator: API Error for DOM chunk ' . $chunk_index . ' - ' . $chunk_response->get_error_message() );
							if ( !$first_api_error_chunks ) { $first_api_error_chunks = $chunk_response; }
							$all_rewritten_chunks[] = $text_chunk; // Use original chunk on error
						} else {
							$all_rewritten_chunks[] = $chunk_response;
						}
					}
					
					if ( $first_api_error_chunks && !$gemini_api_error_obj ) { // Prioritize existing $gemini_api_error_obj
						$gemini_api_error_obj = new WP_Error(
							$first_api_error_chunks->get_error_code(),
							sprintf( __('One or more content chunks (DOM path) failed API processing. Original content was used for these chunks. First error: %s', 'gemini-page-duplicator'), $first_api_error_chunks->get_error_message() )
						);
					}
					$processed_gemini_text_output = implode( "\n\n", $all_rewritten_chunks );
				} else { // $text_to_rewrite was empty (e.g. content was only shortcodes/comments)
					$processed_gemini_text_output = ''; 
				}

				// If there was an API error during chunking, $gemini_api_error_obj is set.
				// $processed_gemini_text_output will contain original text for failed chunks.
				// If all chunks succeeded, $gemini_api_error_obj is null.
				// We proceed to try and re-insert this $processed_gemini_text_output into the DOM.
				
				if ( ! empty( $processed_gemini_text_output ) ) {
					// This is where the challenge of mapping a single block of (potentially modified) text back to multiple DOM nodes lies.
					// The current preg_split approach on $processed_gemini_text_output might not perfectly align with original $text_nodes count.
					$rewritten_parts = preg_split( '/\n\s*\n/', $processed_gemini_text_output, -1, PREG_SPLIT_NO_EMPTY );

					if ( count( $text_nodes ) === count( $rewritten_parts ) ) {
						foreach ( $text_nodes as $index => $node ) {
							if ( isset( $rewritten_parts[ $index ] ) ) {
								$new_text_node = $doc->createTextNode( $rewritten_parts[ $index ] );
								if ($node->parentNode) {
									$node->parentNode->replaceChild( $new_text_node, $node );
								} else {
									error_log('Gemini Page Duplicator: Text node has no parent during DOM replacement.');
								}
							}
						}
						$new_html_content = $doc->saveHTML();
						$body_node = $doc->getElementsByTagName('body')->item(0);
						if ($body_node) {
							$new_post_content = '';
							foreach ($body_node->childNodes as $child) { $new_post_content .= $doc->saveHTML($child); }
						} else {
							$new_post_content = $new_html_content; 
						}
					} else { // Mismatch after chunking and reassembly
						$mismatch_message = sprintf(
							__( 'Could not precisely match all rewritten text segments (post-chunking) to the original content structure (original nodes: %1$d, reassembled rewritten parts: %2$d). The rewritten content will be used with basic paragraph formatting.', 'gemini-page-duplicator' ),
							count( $text_nodes ), count( $rewritten_parts )
						);
						error_log( 'Gemini Page Duplicator: Segment Mismatch (post-chunking) - ' . $mismatch_message );
						if (!$gemini_api_error_obj) { // Only set if no API error already captured
							$gemini_api_error_obj = new WP_Error( 'rewrite_segment_mismatch_chunked', $mismatch_message );
						}
						$new_post_content = wpautop( $processed_gemini_text_output );
					}
				} elseif ( $gemini_api_error_obj ) {
					// API error occurred, $processed_gemini_text_output contains original chunks for failed parts.
					// We might still try to use it with wpautop if DOM re-insertion is too risky or if $text_nodes is empty.
					// Or, more safely, stick to $original_content_for_processing if API errors happened.
					// $new_post_content is already $original_content_for_processing (with placeholders) by default if API errors occur.
					// The $gemini_api_error_obj is already set.
				}
				// If $processed_gemini_text_output is empty (e.g. original content was just shortcodes),
				// $new_post_content remains $original_content_for_processing (placeholders for shortcodes).
			}
		}
	} elseif ( empty( $original_post->post_content ) ) {
		// Content is empty, do nothing with Gemini. $new_post_content is already empty.
	} else { 
		// Fallback logic (DOMDocument class not available, or $original_content_for_processing was empty after placeholder replacement)
		if ( !class_exists('DOMDocument') && !$dom_error_obj ) {
			$dom_error_message = __( 'DOMDocument class is not available. Using simplified text processing for Gemini, which may not preserve HTML structure.', 'gemini-page-duplicator' );
			error_log( 'Gemini Page Duplicator: DOMDocument class not available (Fallback).' );
			$dom_error_obj = new WP_Error( 'dom_unavailable_fallback', $dom_error_message );
		}
		
		// $original_content_for_processing has placeholders here.
		// wp_strip_all_tags will remove HTML comment placeholders.
		$text_to_rewrite_fallback = wp_strip_all_tags( $original_content_for_processing );

		if ( ! empty( $text_to_rewrite_fallback ) ) {
			$processed_gemini_text_fallback = '';
			$max_chunk_size_fallback = 4500;
			$text_chunks_fallback = gpd_split_text_into_chunks( $text_to_rewrite_fallback, $max_chunk_size_fallback );
			$all_rewritten_chunks_fallback = array();
			$first_api_error_fallback = null;

			$custom_instructions_fallback = get_option( 'gpd_temp_custom_prompt_' . $original_post->ID );
			$base_prompt_fallback = __( 'Rewrite the following text, maintaining its original meaning and tone:', 'gemini-page-duplicator' );
			if ( ! empty( $custom_instructions_fallback ) ) {
				delete_option( 'gpd_temp_custom_prompt_' . $original_post->ID ); 
			}

			foreach ( $text_chunks_fallback as $chunk_index_fb => $text_chunk_fb ) {
				$current_prompt_fb = $base_prompt_fallback;
				if ( ! empty( $custom_instructions_fallback ) ) {
					$prompt_fb = sprintf(__( '%1$s Additional instructions: "%2$s". Text: %3$s', 'gemini-page-duplicator' ), $current_prompt_fb, $custom_instructions_fallback, $text_chunk_fb);
				} else {
					$prompt_fb = sprintf(__( '%1$s Text: %2$s', 'gemini-page-duplicator' ), $current_prompt_fb, $text_chunk_fb);
				}
				$chunk_response_fb = gpd_gemini_api()->send_prompt( $prompt_fb );
				if ( is_wp_error( $chunk_response_fb ) ) {
					error_log( 'Gemini Page Duplicator: API Error for fallback chunk ' . $chunk_index_fb . ' - ' . $chunk_response_fb->get_error_message() );
					if ( !$first_api_error_fallback ) { $first_api_error_fallback = $chunk_response_fb; }
					$all_rewritten_chunks_fallback[] = $text_chunk_fb; // Use original
				} else {
					$all_rewritten_chunks_fallback[] = $chunk_response_fb;
				}
			}

			if ( $first_api_error_fallback && !$gemini_api_error_obj ) {
				$gemini_api_error_obj = new WP_Error(
					$first_api_error_fallback->get_error_code(),
					sprintf( __('One or more content chunks (fallback mode) failed API processing. Original content was used for these. First error: %s', 'gemini-page-duplicator'), $first_api_error_fallback->get_error_message() )
				);
			}
			$processed_gemini_text_fallback = implode( "\n\n", $all_rewritten_chunks_fallback );
			
			if ( !empty( $processed_gemini_text_fallback ) ) {
                 // If API calls were partially or fully successful, use the (partially) rewritten text.
                 // $new_post_content was initialized with $original_content_for_processing (placeholders).
                 // Here, we are replacing the non-shortcode content with Gemini's output.
                 // This is a simplification for the fallback path.
                $new_post_content = wpautop( $processed_gemini_text_fallback );
			} 
			// If $processed_gemini_text_fallback is empty (e.g. all chunks failed AND original text was non-empty),
            // $new_post_content remains $original_content_for_processing (with placeholders).
            // The $gemini_api_error_obj will be set if there were errors.

		} elseif (empty($text_to_rewrite_fallback) && !empty($original_content_for_processing) && ($dom_error_obj || !class_exists('DOMDocument'))) {
            // This case means content was likely only shortcodes (as placeholders), and DOM processing was skipped/failed.
            // $new_post_content is already $original_content_for_processing (placeholders).
            // No text was sent to Gemini. Shortcodes will be restored.
        }
	}

	// 3. Restore shortcodes
	if ( ! empty( $shortcode_placeholders ) ) {
		foreach ( $shortcode_placeholders as $placeholder => $original_shortcode ) {
			$new_post_content = str_replace( $placeholder, $original_shortcode, $new_post_content );
		}
	}

	// New post arguments
	$new_post_args = array(
		'post_title'   => $original_post->post_title . ' (Copy)',
		'post_content' => $new_post_content, 
		'post_status'  => 'draft',       
		'post_type'    => $original_post->post_type,
		'post_author'  => get_current_user_id(), // Set current user as author
		'post_parent'  => $original_post->post_parent,
		'menu_order'   => $original_post->menu_order,
		'comment_status' => $original_post->comment_status,
		'ping_status'  => $original_post->ping_status,
		'post_password' => $original_post->post_password,
		'post_excerpt' => $original_post->post_excerpt,
		// 'post_date'    => $original_post->post_date, // Uncomment if you want to keep original date
		// 'post_date_gmt'=> $original_post->post_date_gmt, // Uncomment if you want to keep original date GMT
	);

	// Insert the new post
	$new_post_id = wp_insert_post( $new_post_args, true ); // Pass true to return WP_Error on failure

	if ( is_wp_error( $new_post_id ) ) {
		// This is an error from wp_insert_post itself.
		return $new_post_id; 
	}

	// Copy all post metadata
	$meta_keys = get_post_custom_keys( $post_id );
	if ( $meta_keys ) {
		foreach ( $meta_keys as $meta_key ) {
			// Filter out protected meta keys (starting with '_') that we might not want to copy directly,
            // e.g., _edit_lock, _edit_last, or specific plugin meta.
            // However, some important ones like _wp_page_template or _thumbnail_id should be copied.
            // For now, we'll copy most, but this might need refinement.
			if ( $meta_key === '_edit_lock' || $meta_key === '_edit_last' ) {
				continue;
			}
			$meta_values = get_post_custom_values( $meta_key, $post_id );
			foreach ( $meta_values as $meta_value ) {
				// If the meta value is serialized, it will be unserialized by get_post_custom_values
                // and add_post_meta will handle serialization if needed.
				add_post_meta( $new_post_id, $meta_key, $meta_value );
			}
		}
	}

    // Copy taxonomies (categories, tags, custom taxonomies)
    $taxonomies = get_object_taxonomies( $original_post->post_type );
    if ( ! empty( $taxonomies ) ) {
        foreach ( $taxonomies as $taxonomy ) {
            $terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                wp_set_object_terms( $new_post_id, $terms, $taxonomy, false );
            }
        }
    }

	// After successfully creating the post and copying meta/taxonomies:
	// Store any DOM or API error to be shown as a notice on the new post's edit screen.
	// Prioritize DOM errors as they happen earlier and might prevent API calls.
	$error_notice_transient_key = 'gpd_rewrite_notice_' . $new_post_id . '_' . get_current_user_id();
	if ( $dom_error_obj ) {
        set_transient( $error_notice_transient_key, $dom_error_obj->get_error_message(), 45 );
    } elseif ( $gemini_api_error_obj ) { // Only set Gemini error if no preceding DOM error
		set_transient( $error_notice_transient_key, $gemini_api_error_obj->get_error_message(), 45 );
	}

	return $new_post_id;
}

/**
 * Helper function to split text into manageable chunks.
 * Tries to split at paragraph breaks near the max_size.
 *
 * @param string $text The text to split.
 * @param int $max_size Approximate maximum size of each chunk.
 * @return array Array of text chunks.
 */
function gpd_split_text_into_chunks( $text, $max_size = 4500 ) {
	$chunks = array();
	if ( empty(trim($text)) ) return $chunks; // Return empty if text is empty or whitespace

	$current_chunk = '';
	// Split by double newlines (common paragraph separators)
	$paragraphs = preg_split( '/(\n\s*\n)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE );

	foreach ( $paragraphs as $para_or_delim ) {
		// Skip empty captures from preg_split if they are not actual delimiters
		if ( empty( $para_or_delim ) && !in_array($para_or_delim, ["\n\n", "\n \n"], true) ) {
			continue;
		}

		if ( strlen( $current_chunk ) + strlen( $para_or_delim ) > $max_size && !empty(trim($current_chunk)) ) {
			$chunks[] = $current_chunk;
			$current_chunk = $para_or_delim;
		} else {
			$current_chunk .= $para_or_delim;
		}
	}

	if ( ! empty( trim($current_chunk) ) ) {
		$chunks[] = $current_chunk;
	}
	
	// If any chunk is still too large (e.g., a single paragraph or element is massive), hard split it.
	$final_chunks = array();
	foreach ($chunks as $chunk) {
		if (strlen($chunk) > $max_size) {
			// Hard split the oversized chunk
			$sub_chunks = str_split($chunk, $max_size);
			foreach ($sub_chunks as $sub_chunk) {
				if (!empty(trim($sub_chunk))) $final_chunks[] = $sub_chunk;
			}
		} else {
			if (!empty(trim($chunk))) $final_chunks[] = $chunk;
		}
	}
	// Ensure at least one chunk if original text was not empty, to avoid issues in loops.
    if (empty($final_chunks) && !empty(trim($text))) {
        $final_chunks[] = substr($text, 0, $max_size); // Add the first part as a chunk
    }

	return $final_chunks;
}
?>
