<?php
/**
 * Admin Meta Box for Gemini Page Duplicator
 *
 * @package GeminiPageDuplicator
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Add meta box to page and post edit screens.
 */
function gpd_add_meta_box() {
	$post_types = array( 'page', 'post' ); // Apply to pages and posts
	foreach ( $post_types as $post_type ) {
		add_meta_box(
			'gpd_duplicate_rewrite_meta_box',             // ID
			__( 'Gemini Page Duplicator', 'gemini-page-duplicator' ), // Title
			'gpd_meta_box_html',                          // Callback
			$post_type,                                   // Screen (post type)
			'side',                                       // Context (normal, side, advanced)
			'high'                                        // Priority (high, core, default, low)
		);
	}
}
add_action( 'add_meta_boxes', 'gpd_add_meta_box' );

/**
 * Render the meta box HTML.
 *
 * @param WP_Post $post The current post object.
 */
function gpd_meta_box_html( $post ) {
	// Add a nonce field for security
	wp_nonce_field( 'gpd_duplicate_action', 'gpd_duplicate_nonce' );
	?>
	<p>
		<label for="gpd_custom_prompt"><?php esc_html_e( 'Custom Gemini Instructions (Optional):', 'gemini-page-duplicator' ); ?></label>
		<textarea id="gpd_custom_prompt" name="gpd_custom_prompt" rows="4" style="width:100%;"></textarea>
		<span class="description"><?php esc_html_e( 'e.g., "Make the tone more formal." or "Summarize this text."', 'gemini-page-duplicator' ); ?></span>
	</p>
	<p>
		<button type="submit" name="gpd_duplicate_button" class="button button-primary button-large" value="true">
			<?php esc_html_e( 'Duplicate & Rewrite with Gemini', 'gemini-page-duplicator' ); ?>
		</button>
	</p>
	<p class="description">
		<?php esc_html_e( 'Note: This will save any current changes to this post and then create a new duplicated post with content rewritten by Gemini. The new post will be a draft.', 'gemini-page-duplicator' ); ?>
	</p>
	<?php
	// Display any feedback messages
	// Notice for successful duplication and rewrite (shown on the new post's edit screen)
	$success_transient_key = 'gpd_admin_notice_' . $post->ID . '_' . get_current_user_id();
	if ( $message_details = get_transient( $success_transient_key ) ) {
		$message = isset($message_details['message']) ? $message_details['message'] : $message_details; // backward compatibility
		$new_post_id = isset($message_details['new_post_id']) ? $message_details['new_post_id'] : 0;

		echo '<div class="notice notice-success is-dismissible" style="margin-top:10px;"><p>';
		echo esc_html( $message );
		if ( $new_post_id && get_edit_post_link( $new_post_id ) ) {
			echo ' <a href="' . esc_url( get_edit_post_link( $new_post_id ) ) . '">' . esc_html__( 'Edit the new draft.', 'gemini-page-duplicator' ) . '</a>';
		}
		echo '</p></div>';
		delete_transient( $success_transient_key );
	}

	// Notice for errors during duplication (shown on the original post's edit screen if redirect fails or not applicable)
	$error_transient_key = 'gpd_admin_error_' . $post->ID . '_' . get_current_user_id();
	if ( $error_message = get_transient( $error_transient_key ) ) {
		echo '<div class="notice notice-error is-dismissible" style="margin-top:10px;"><p>' . esc_html( $error_message ) . '</p></div>';
		delete_transient( $error_transient_key );
	}
    
    // Notice for API/DOM issues during rewrite (shown on the new post's edit screen, if duplication itself succeeded)
	$rewrite_notice_key = 'gpd_rewrite_notice_' . $post->ID . '_' . get_current_user_id();
    if ( $rewrite_message = get_transient( $rewrite_notice_key ) ) {
        echo '<div class="notice notice-warning is-dismissible" style="margin-top:10px;"><p>';
        echo '<strong>' . esc_html__( 'Rewrite Information:', 'gemini-page-duplicator' ) . '</strong> ' . esc_html( $rewrite_message );
        echo '</p></div>';
        delete_transient( $rewrite_notice_key );
    }
}

/**
 * Handle the duplication process when the meta box button is clicked.
 *
 * This function hooks into 'save_post' which runs when a post is saved.
 * We check if our button was clicked.
 */
function gpd_handle_duplication_on_save( $post_id, $post, $update ) {
	// Check if our button was pressed.
	if ( ! isset( $_POST['gpd_duplicate_button'] ) || $_POST['gpd_duplicate_button'] !== 'true' ) {
		return;
	}

	// Verify nonce
	if ( ! isset( $_POST['gpd_duplicate_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $_POST['gpd_duplicate_nonce'] ), 'gpd_duplicate_action' ) ) {
		set_transient( 'gpd_admin_error_' . $post_id . '_' . get_current_user_id(), __( 'Security check failed.', 'gemini-page-duplicator' ), 45 );
		return;
	}

	// Prevent infinite loops and ensure it's not an auto-save
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}

	// Check user permissions for the original post
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		set_transient( 'gpd_admin_error_' . $post_id . '_' . get_current_user_id(), __( 'You do not have permission to edit this post.', 'gemini-page-duplicator' ), 45 );
		return;
	}
    
    // Get post type object to check 'create_posts' capability
    $post_type_object = get_post_type_object( $post->post_type );
    if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->create_posts ) ) {
        set_transient( 'gpd_admin_error_' . $post_id . '_' . get_current_user_id(), __( 'You do not have permission to create new posts of this type.', 'gemini-page-duplicator' ), 45 );
        return;
    }

	// Get custom prompt if provided
	$custom_prompt_instructions = '';
	if ( isset( $_POST['gpd_custom_prompt'] ) ) {
		$custom_prompt_instructions = sanitize_textarea_field( $_POST['gpd_custom_prompt'] );
	}

	// Temporarily unhook this function to prevent it from firing again during wp_update_post or wp_insert_post
    remove_action( 'save_post_' . $post->post_type, 'gpd_handle_duplication_on_save', 10 );

	// Call the duplication function
	// Note: gpd_duplicate_post might need to be updated to accept the custom prompt.
	// For now, we're just passing the post_id. The prompt logic is inside gpd_duplicate_post.
	// If gpd_duplicate_post is modified to take custom prompt, pass it here.
	// Let's assume for now the prompt logic in page-duplicator.php needs to be updated to receive this.
	// We will need to modify `gpd_duplicate_post` to accept this custom prompt.
	// For now, this POC will call it without the custom prompt fully integrated into the duplication logic itself.
	
	// Store the custom prompt in a temporary option or transient to be picked up by gpd_duplicate_post
    // This is a workaround as we can't directly pass more args to save_post hooks easily.
    // A better way would be an AJAX handler.
    if ( ! empty( $custom_prompt_instructions ) ) {
        update_option( 'gpd_temp_custom_prompt_' . $post_id, $custom_prompt_instructions );
    }

	$new_post_id = gpd_duplicate_post( $post_id );
    
    // Clean up the temporary option
    delete_option( 'gpd_temp_custom_prompt_' . $post_id );


	// Re-hook the function
    add_action( 'save_post_' . $post->post_type, 'gpd_handle_duplication_on_save', 10, 3 );


	if ( is_wp_error( $new_post_id ) ) {
		// Error during wp_insert_post or from pre-duplication checks in gpd_duplicate_post
		$error_message = __( 'Failed to duplicate post: ', 'gemini-page-duplicator' ) . $new_post_id->get_error_message();
		set_transient( 'gpd_admin_error_' . $post_id . '_' . get_current_user_id(), $error_message, 45 );
		// No redirect here, let the user see the error on the current page.
	} else {
		// Duplication itself (wp_insert_post) succeeded.
		// The gpd_rewrite_notice_ transient would have been set inside gpd_duplicate_post if there were API/DOM issues.
		// Set a general success message for the new post.
		$success_message_details = array(
			'message'     => __( 'Post duplicated successfully! You are now editing the new draft.', 'gemini-page-duplicator' ),
			'new_post_id' => $new_post_id 
		);
        // If there was a rewrite notice (e.g. API failed but duplication continued), it will be shown alongside this.
		set_transient( 'gpd_admin_notice_' . $new_post_id . '_' . get_current_user_id(), $success_message_details, 45 );
		
		wp_safe_redirect( get_edit_post_link( $new_post_id, 'raw' ) );
		exit;
	}
}
// Using a priority of 10 and 3 arguments for save_post_{post_type}
add_action( 'save_post_page', 'gpd_handle_duplication_on_save', 10, 3 );
add_action( 'save_post_post', 'gpd_handle_duplication_on_save', 10, 3 );

// We also need to modify gpd_duplicate_post to check for and use 'gpd_temp_custom_prompt_'.$post_id
?>
