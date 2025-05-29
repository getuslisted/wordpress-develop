<?php
/**
 * Admin UI Enhancements for Gemini Page Duplicator
 * - 'Add New with AI' button
 * - Temporary admin page for initial prompt
 *
 * @package GeminiPageDuplicator
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Add a hidden submenu page for the "Add New with AI" functionality.
 * This page will serve as the target for our custom button.
 */
function gpd_add_new_with_ai_page() {
	// Add for Pages
	add_submenu_page(
		null, // Hidden from menu
		__( 'Add New Page with AI', 'gemini-page-duplicator' ),
		__( 'Add New with AI', 'gemini-page-duplicator' ),
		'edit_pages', // Capability
		'gpd_add_new_page_with_ai',
		'gpd_render_new_with_ai_page'
	);
	// Add for Posts
	add_submenu_page(
		null, // Hidden from menu
		__( 'Add New Post with AI', 'gemini-page-duplicator' ),
		__( 'Add New with AI', 'gemini-page-duplicator' ),
		'edit_posts', // Capability
		'gpd_add_new_post_with_ai',
		'gpd_render_new_with_ai_page'
	);
}
add_action( 'admin_menu', 'gpd_add_new_with_ai_page' );

/**
 * Render the HTML for the "Add New with AI" page.
 */
function gpd_render_new_with_ai_page() {
	// Determine post type from page query arg
	$current_screen = get_current_screen();
	$post_type = 'post'; // Default
	if ( $current_screen && ($current_screen->id === 'admin_page_gpd_add_new_page_with_ai' || $current_screen->base === 'admin_page_gpd_add_new_page_with_ai') ) {
		$post_type = 'page';
	} elseif ( $current_screen && ($current_screen->id === 'admin_page_gpd_add_new_post_with_ai' || $current_screen->base === 'admin_page_gpd_add_new_post_with_ai') ) {
		$post_type = 'post';
	} else {
        // Fallback or error if screen is not identifiable, though direct access should be rare.
        wp_die(esc_html__('Invalid page context.', 'gemini-page-duplicator'));
    }
    
    $page_title = ($post_type === 'page') ? __('Add New Page with AI', 'gemini-page-duplicator') : __('Add New Post with AI', 'gemini-page-duplicator');
    $submit_button_text = ($post_type === 'page') ? __('Create Page with AI', 'gemini-page-duplicator') : __('Create Post with AI', 'gemini-page-duplicator');

	?>
	<div class="wrap">
		<h1><?php echo esc_html( $page_title ); ?></h1>
		<?php
        // Display any admin notices from redirection
        settings_errors('gpd_new_with_ai_notices');
        ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gpd_handle_create_new_with_ai">
			<input type="hidden" name="gpd_post_type" value="<?php echo esc_attr( $post_type ); ?>">
			<?php wp_nonce_field( 'gpd_create_new_with_ai_action', 'gpd_create_new_with_ai_nonce' ); ?>

			<table class="form-table">
				<tr valign="top">
					<th scope="row">
						<label for="gpd_new_post_title"><?php esc_html_e( 'New Title', 'gemini-page-duplicator' ); ?></label>
					</th>
					<td>
						<input type="text" id="gpd_new_post_title" name="gpd_new_post_title" class="regular-text" required />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="gpd_initial_prompt"><?php esc_html_e( 'Initial Content Prompt for Gemini', 'gemini-page-duplicator' ); ?></label>
					</th>
					<td>
						<textarea id="gpd_initial_prompt" name="gpd_initial_prompt" rows="5" class="large-text" required></textarea>
						<p class="description"><?php esc_html_e( 'Describe the content you want Gemini to generate for this new ' . esc_html($post_type) . '.', 'gemini-page-duplicator' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( $submit_button_text ); ?>
		</form>
	</div>
	<?php
}

/**
 * Handle the form submission from the "Add New with AI" page.
 */
function gpd_handle_create_new_with_ai_action_callback() {
	if ( ! isset( $_POST['gpd_create_new_with_ai_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gpd_create_new_with_ai_nonce'] ) ), 'gpd_create_new_with_ai_action' ) ) {
		wp_die( esc_html__( 'Security check failed.', 'gemini-page-duplicator' ) );
	}

	$post_type = isset( $_POST['gpd_post_type'] ) ? sanitize_key( $_POST['gpd_post_type'] ) : 'post';
	$post_title = isset( $_POST['gpd_new_post_title'] ) ? sanitize_text_field( wp_unslash( $_POST['gpd_new_post_title'] ) ) : '';
	$initial_prompt = isset( $_POST['gpd_initial_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['gpd_initial_prompt'] ) ) : '';

	if ( empty( $post_title ) ) {
        add_settings_error('gpd_new_with_ai_notices', 'title_empty', __('Title cannot be empty.', 'gemini-page-duplicator'), 'error');
        $redirect_url = ($post_type === 'page') ? admin_url('admin.php?page=gpd_add_new_page_with_ai') : admin_url('admin.php?page=gpd_add_new_post_with_ai');
		wp_safe_redirect( $redirect_url );
		exit;
	}
    if ( empty( $initial_prompt ) ) {
        add_settings_error('gpd_new_with_ai_notices', 'prompt_empty', __('Initial prompt cannot be empty.', 'gemini-page-duplicator'), 'error');
        $redirect_url = ($post_type === 'page') ? admin_url('admin.php?page=gpd_add_new_page_with_ai') : admin_url('admin.php?page=gpd_add_new_post_with_ai');
        wp_safe_redirect( $redirect_url );
		exit;
	}

	// Check capabilities
	$post_type_object = get_post_type_object( $post_type );
	if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->create_posts ) ) {
		wp_die( esc_html__( 'You do not have permission to create this post type.', 'gemini-page-duplicator' ) );
	}

	$new_post_args = array(
		'post_title'   => $post_title,
		'post_content' => '', // Content will be generated later or via block editor
		'post_status'  => 'draft',
		'post_type'    => $post_type,
		'post_author'  => get_current_user_id(),
	);

	$new_post_id = wp_insert_post( $new_post_args, true );

	if ( is_wp_error( $new_post_id ) ) {
		wp_die( esc_html__( 'Failed to create new post: ', 'gemini-page-duplicator' ) . $new_post_id->get_error_message() );
	}

	// Store the initial prompt in post meta
	update_post_meta( $new_post_id, '_gpd_initial_prompt', $initial_prompt );
    // Store a transient to indicate this post was just created with AI for further action (e.g., auto-triggering generation)
    set_transient('gpd_just_created_with_ai_' . $new_post_id, $initial_prompt, HOUR_IN_SECONDS);


	// Redirect to the edit screen of the new post
	$redirect_url = get_edit_post_link( $new_post_id, 'raw' );
	if ( $redirect_url ) {
		wp_safe_redirect( $redirect_url );
		exit;
	} else {
		// Fallback if somehow edit link isn't available
		wp_die( esc_html__( 'New post created, but failed to redirect to the editor. Post ID: ', 'gemini-page-duplicator' ) . esc_html( $new_post_id ) );
	}
}
add_action( 'admin_post_gpd_handle_create_new_with_ai', 'gpd_handle_create_new_with_ai_action_callback' );

/**
 * Enqueue script for adding "Add New with AI" button.
 */
function gpd_enqueue_admin_ui_enhancements_scripts( $hook_suffix ) {
    global $pagenow;

    if ( $pagenow === 'edit.php' && isset($_GET['post_type']) && in_array($_GET['post_type'], ['page', 'post']) ) {
        wp_enqueue_script(
            'gemini-page-duplicator-admin-ui',
            plugin_dir_url( __FILE__ ) . '../js/admin-ui-enhancements.js', // Path relative to this file
            array( 'jquery', 'wp-dom-ready' ),
            filemtime( plugin_dir_path( __FILE__ ) . '../js/admin-ui-enhancements.js' ),
            true
        );
        // Pass data like the correct URL for the Add New with AI page
        $post_type = isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : 'post';
        $add_new_ai_url = ($post_type === 'page') ? 
            admin_url('admin.php?page=gpd_add_new_page_with_ai') : 
            admin_url('admin.php?page=gpd_add_new_post_with_ai');

        wp_localize_script('gemini-page-duplicator-admin-ui', 'gpdAdminUiParams', array(
            'addNewAiUrl' => esc_url($add_new_ai_url),
            'buttonText'  => ($post_type === 'page') ? __('Add New Page with AI', 'gemini-page-duplicator') : __('Add New Post with AI', 'gemini-page-duplicator'),
            'postType'    => $post_type,
            'strings' => array(
                'modalTitle' => __('Generate AI Content', 'gemini-page-duplicator'),
                'modalPromptLabel' => __('Initial prompt for Gemini:', 'gemini-page-duplicator'),
                'modalGenerateButton' => __('Generate Content with Gemini', 'gemini-page-duplicator'),
                'modalCancelButton' => __('Manually Edit Instead', 'gemini-page-duplicator'),
                'generatingContent' => __('Generating content, please wait...', 'gemini-page-duplicator'),
                'contentGeneratedSuccess' => __('AI content has been generated and inserted!', 'gemini-page-duplicator'),
                'contentGeneratedError' => __('Could not generate AI content.', 'gemini-page-duplicator'),
            )
        ));
    }

    // Check if we are on a new AI page editor and pass data to editor script
    if ( $pagenow === 'post.php' && isset($_GET['post']) && isset($_GET['action']) && $_GET['action'] === 'edit' ) {
        $post_id = intval($_GET['post']);
        $post = get_post($post_id);
        $initial_prompt = get_transient('gpd_just_created_with_ai_' . $post_id);

        // Check if content is empty or just a placeholder block
        $is_content_empty = empty($post->post_content) || 
                            trim($post->post_content) === '<!-- wp:paragraph --><p></p><!-- /wp:paragraph -->' ||
                            trim($post->post_content) === '<p></p>';


        if ( $initial_prompt && $is_content_empty ) {
            // Ensure block editor scripts are enqueued for gpd_editor_params
            $script_asset_path = plugin_dir_path( __FILE__ ) . '../js/admin-editor-enhancements.asset.php';
            $script_asset = file_exists( $script_asset_path )
                ? require( $script_asset_path )
                : array( 'dependencies' => array('wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-blocks', 'wp-i18n', 'wp-ajax'), 'version' => filemtime( plugin_dir_path( __FILE__ ) . '../js/admin-editor-enhancements.js' ) );

            wp_enqueue_script(
                'gemini-page-duplicator-editor-enhancements', // Same handle as used in the block editor specific part
                plugin_dir_url( __FILE__ ) . '../js/admin-editor-enhancements.js',
                $script_asset['dependencies'],
                $script_asset['version'],
                true
            );
            
            $localized_data = !wp_script_is('gemini-page-duplicator-editor-enhancements', 'data') ? [] : wp_scripts()->get_data('gemini-page-duplicator-editor-enhancements', 'data');
            if (is_string($localized_data)) $localized_data = json_decode(str_replace('var gpd_editor_params = ', '', rtrim($localized_data,';')), true);


            wp_localize_script(
                'gemini-page-duplicator-editor-enhancements',
                'gpd_editor_params', // Use the same object name to add to it or override
                array_merge( // Merge to preserve existing params like nonce if already set
                    is_array($localized_data) ? $localized_data : [],
                    array(
                        'showAiModalOnLoad' => true,
                        'initialPrompt'     => $initial_prompt,
                        'currentPostId'     => $post_id,
                        'generateNonce'     => wp_create_nonce('gpd_generate_initial_content_nonce')
                    )
                )
            );
        }
    }
}
add_action( 'admin_enqueue_scripts', 'gpd_enqueue_admin_ui_enhancements_scripts' );


/**
 * AJAX handler for generating initial post content.
 */
function gpd_ajax_generate_initial_content_handler() {
    check_ajax_referer( 'gpd_generate_initial_content_nonce', 'nonce' );

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $prompt = isset( $_POST['initial_prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['initial_prompt'] ) ) : '';

    if ( ! $post_id || empty( $prompt ) ) {
        wp_send_json_error( array( 'message' => __( 'Missing Post ID or prompt.', 'gemini-page-duplicator' ) ) );
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        wp_send_json_error( array( 'message' => __( 'Permission denied.', 'gemini-page-duplicator' ) ) );
        return;
    }

    // Shortcode and chunking considerations for the prompt itself are minor here,
    // as it's a prompt, not existing content to be preserved.
    // The main concern is the length of Gemini's *output*.
    // The current gpd_gemini_api()->send_prompt() doesn't handle chunking of output.
    // This is a known limitation for very large generated content.

    $gemini_response = gpd_gemini_api()->send_prompt( $prompt );

    if ( is_wp_error( $gemini_response ) ) {
        wp_send_json_error( array( 'message' => __( 'Gemini API Error: ', 'gemini-page-duplicator' ) . $gemini_response->get_error_message() ) );
        return;
    }
    
    // Gemini might return plain text. We might want to wpautop it or ensure it's valid HTML for blocks.
    // For now, assume it's reasonably formatted for insertion or will be parsed into blocks client-side.
    $generated_content = $gemini_response; 
    // $generated_content = wpautop($gemini_response); // Optional: if Gemini returns plain text and we want paragraphs

    $post_arr = array(
        'ID'           => $post_id,
        'post_content' => $generated_content,
    );

    $update_result = wp_update_post( $post_arr, true );

    if ( is_wp_error( $update_result ) ) {
        wp_send_json_error( array( 'message' => __( 'Failed to update post content: ', 'gemini-page-duplicator' ) . $update_result->get_error_message() ) );
        return;
    }

    // Clear the transient and meta so the modal doesn't reappear
    delete_transient( 'gpd_just_created_with_ai_' . $post_id );
    delete_post_meta( $post_id, '_gpd_initial_prompt' );

    wp_send_json_success( array(
        'message'           => __( 'Content generated successfully!', 'gemini-page-duplicator' ),
        'generated_content_html' => $generated_content, // Send raw HTML back
    ) );
}
add_action( 'wp_ajax_gpd_generate_initial_content', 'gpd_ajax_generate_initial_content_handler' );

?>
