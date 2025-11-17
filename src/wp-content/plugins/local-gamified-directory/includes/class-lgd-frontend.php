<?php
/**
 * Front-end helpers for the Local Gamified Directory plugin.
 *
 * @package LocalGamifiedDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handle front-end rendering and form submissions.
 */
class LGD_Frontend {

	/**
	 * Plugin instance.
	 *
	 * @var Local_Gamified_Directory
	 */
	private $plugin;

	/**
	 * Collection of form messages keyed by context.
	 *
	 * @var array
	 */
	private $messages = array();

	/**
	 * Constructor.
	 *
	 * @param Local_Gamified_Directory $plugin Main plugin instance.
	 */
	public function __construct( Local_Gamified_Directory $plugin ) {
	        $this->plugin = $plugin;

	        add_action( 'init', array( $this, 'register_shortcodes' ) );
	        add_action( 'init', array( $this, 'handle_forms' ), 20 );
	        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register shortcodes used by the plugin.
	 */
	public function register_shortcodes() {
	        add_shortcode( 'lgd_business_submission_form', array( $this, 'render_business_submission_form' ) );
	        add_shortcode( 'lgd_classified_submission_form', array( $this, 'render_classified_submission_form' ) );
	        add_shortcode( 'lgd_user_dashboard', array( $this, 'render_user_dashboard' ) );
	}

	/**
	 * Register and enqueue front-end assets.
	 */
	public function enqueue_assets() {
	        wp_register_style(
	                'lgd-frontend',
	                LGD_PLUGIN_URL . 'assets/css/frontend.css',
	                array(),
	                Local_Gamified_Directory::VERSION
	        );

	        wp_register_script(
	                'lgd-activity',
	                LGD_PLUGIN_URL . 'assets/js/frontend-tracking.js',
	                array(),
	                Local_Gamified_Directory::VERSION,
	                true
	        );

	        wp_localize_script(
	                'lgd-activity',
	                'LGDActivity',
	                array(
	                        'root'  => esc_url_raw( rest_url( 'lgd/v1/' ) ),
	                        'nonce' => wp_create_nonce( 'wp_rest' ),
	                )
	        );

	        $post_id = get_queried_object_id();
	        $content = $post_id ? get_post_field( 'post_content', $post_id ) : '';

	        if ( has_shortcode( $content, 'lgd_business_submission_form' )
	                || has_shortcode( $content, 'lgd_classified_submission_form' )
	                || has_shortcode( $content, 'lgd_user_dashboard' )
	                || has_shortcode( $content, 'lgd_leaderboard' )
	                || has_shortcode( $content, 'lgd_ad_form' ) ) {
	                wp_enqueue_style( 'lgd-frontend' );
	                wp_enqueue_script( 'lgd-activity' );
	        }
	}

	/**
	 * Retrieve a list of business listings that can be claimed by a user.
	 *
	 * @param int $user_id Current user ID.
	 *
	 * @return WP_Post[]
	 */
	private function get_claimable_businesses( $user_id ) {
	        if ( ! $user_id ) {
	                return array();
	        }

	        $max_posts = (int) apply_filters( 'lgd_max_claimable_businesses', 200 );

	        $posts = get_posts(
	                array(
	                        'post_type'      => 'business_listing',
	                        'post_status'    => array( 'publish', 'pending' ),
	                        'posts_per_page' => $max_posts > 0 ? $max_posts : 200,
	                        'orderby'        => 'title',
	                        'order'          => 'ASC',
	                )
	        );

	        if ( empty( $posts ) ) {
	                return array();
	        }

	        $claimable = array();

	        foreach ( $posts as $post ) {
	                $owner_id = (int) get_post_meta( $post->ID, Local_Gamified_Directory::META_OWNER_USER, true );

	                if ( $owner_id && $owner_id === $user_id ) {
	                        continue;
	                }

	                $existing_requests = get_post_meta( $post->ID, Local_Gamified_Directory::META_PREFIX . 'claim_request' );
	                $already_requested = false;

	                foreach ( $existing_requests as $request ) {
	                        $request = maybe_unserialize( $request );

	                        if ( is_array( $request ) && isset( $request['user_id'] ) && (int) $request['user_id'] === $user_id ) {
	                                $already_requested = true;
	                                break;
	                        }
	                }

	                if ( $already_requested ) {
	                        continue;
	                }

	                $claimable[] = $post;
	        }

	        return $claimable;
	}

	/**
	 * Process form submissions for business listings, classifieds, and dashboard actions.
	 */
	public function handle_forms() {
	        if ( isset( $_POST['lgd_business_submission_nonce'] ) ) {
	                $this->process_business_submission();
	        }

	        if ( isset( $_POST['lgd_classified_submission_nonce'] ) ) {
	                $this->process_classified_submission();
	        }
	}

	/**
	 * Process a business submission form request.
	 */
	private function process_business_submission() {
	        if ( ! $this->plugin->is_feature_enabled( 'business_submissions' ) ) {
	                return;
	        }

	        if ( ! is_user_logged_in() ) {
	                $this->add_error( 'business', __( 'You must be logged in to submit a business.', 'local-gamified-directory' ) );
	                return;
	        }

	        if ( ! $this->plugin->user_has_feature_access( get_current_user_id(), 'business_submissions' ) ) {
	                $this->add_error( 'business', __( 'You are not permitted to submit business listings at this time.', 'local-gamified-directory' ) );
	                return;
	        }

	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lgd_business_submission_nonce'] ) ), 'lgd_submit_business' ) ) {
	                $this->add_error( 'business', __( 'Security check failed. Please try again.', 'local-gamified-directory' ) );
	                return;
	        }

	        if ( ! current_user_can( 'create_business_listings' ) ) {
	                $this->add_error( 'business', __( 'You do not have permission to submit a business listing.', 'local-gamified-directory' ) );
	                return;
	        }

	        $claim_listing_id = isset( $_POST['lgd_business_claim_listing'] ) ? absint( $_POST['lgd_business_claim_listing'] ) : 0;

	        if ( $claim_listing_id ) {
	                $claim_notes = isset( $_POST['lgd_business_claim_notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lgd_business_claim_notes'] ) ) : '';
	                $this->handle_business_claim( $claim_listing_id, $claim_notes );
	                return;
	        }

	        $title       = isset( $_POST['lgd_business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_business_name'] ) ) : '';
	        $description = isset( $_POST['lgd_business_description'] ) ? wp_kses_post( wp_unslash( $_POST['lgd_business_description'] ) ) : '';

	        if ( empty( $title ) ) {
	                $this->add_error( 'business', __( 'Please provide a business name.', 'local-gamified-directory' ) );
	        }

	        if ( empty( $description ) ) {
	                $this->add_error( 'business', __( 'Please provide a business description.', 'local-gamified-directory' ) );
	        }

	        if ( $this->has_errors( 'business' ) ) {
	                return;
	        }

	        $meta = array(
	                Local_Gamified_Directory::META_PREFIX . 'address'        => isset( $_POST['lgd_business_address'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_business_address'] ) ) : '',
	                Local_Gamified_Directory::META_PREFIX . 'phone'          => isset( $_POST['lgd_business_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_business_phone'] ) ) : '',
	                Local_Gamified_Directory::META_PREFIX . 'contact_email'  => isset( $_POST['lgd_business_email'] ) ? sanitize_email( wp_unslash( $_POST['lgd_business_email'] ) ) : '',
	                Local_Gamified_Directory::META_PREFIX . 'hours'          => isset( $_POST['lgd_business_hours'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lgd_business_hours'] ) ) : '',
	                Local_Gamified_Directory::META_PREFIX . 'google_place_id' => isset( $_POST['lgd_business_place_id'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_business_place_id'] ) ) : '',
	        );

	        $current_user_id = get_current_user_id();

	        $post_data = array(
	                'post_type'    => 'business_listing',
	                'post_status'  => 'pending',
	                'post_title'   => $title,
	                'post_content' => $description,
	                'post_author'  => $current_user_id,
	        );

	        $post_id = wp_insert_post( $post_data, true );

	        if ( is_wp_error( $post_id ) ) {
	                $this->add_error( 'business', $post_id->get_error_message() );
	                return;
	        }

	        foreach ( $meta as $key => $value ) {
	                update_post_meta( $post_id, $key, $value );
	        }

	        update_post_meta( $post_id, Local_Gamified_Directory::META_OWNER_USER, $current_user_id );

	        $category_ids = array();
	        if ( ! empty( $_POST['lgd_business_category'] ) ) {
	                $category_ids = array_map( 'intval', (array) wp_unslash( $_POST['lgd_business_category'] ) );
	        }

	        if ( ! empty( $category_ids ) ) {
	                wp_set_post_terms( $post_id, $category_ids, 'business_category', false );
	        }

	        $region_ids = array();
	        if ( ! empty( $_POST['lgd_business_region'] ) ) {
	                $region_ids = array_map( 'intval', (array) wp_unslash( $_POST['lgd_business_region'] ) );
	        }

	        if ( ! empty( $region_ids ) ) {
	                wp_set_post_terms( $post_id, $region_ids, 'business_region', false );
	        }

	        $this->handle_media_upload( 'lgd_business_logo', $post_id, 'business' );

	        $gamification = $this->plugin->get_gamification();
	        if ( $gamification ) {
	                $gamification->add_points( $current_user_id, 10, 'business_submission' );
	        }

	        $activity = $this->plugin->get_activity();
	        if ( $activity ) {
	                $activity->log_event( $current_user_id, 'business_submission', 'business_listing', $post_id );
	        }

	        $current_user = wp_get_current_user();
	        $blogname     = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );

	        $subject = sprintf(
	                /* translators: 1: Site name, 2: Business listing title. */
	                __( '[%1$s] New business submission: "%2$s"', 'local-gamified-directory' ),
	                $blogname,
	                $title
	        );

	        $message_template = __(
'A new business listing has been submitted on %1$s.

Title: %2$s
Submitted by: %3$s <%4$s>

Review the submission: %5$s',
	                'local-gamified-directory'
	        );

	        $this->plugin->notify_admin(
	                $subject,
	                sprintf(
	                        $message_template,
	                        $blogname,
	                        $title,
	                        $current_user->display_name,
	                        $current_user->user_email,
	                        admin_url( 'post.php?post=' . $post_id . '&action=edit' )
	                )
	        );

	        $this->add_success( 'business', __( 'Thank you! Your business listing has been submitted and is awaiting approval.', 'local-gamified-directory' ) );
	}

	/**
	 * Handle a request to claim an existing business listing.
	 *
	 * @param int    $listing_id Listing ID being claimed.
	 * @param string $notes      Verification notes supplied by the requester.
	 */
	private function handle_business_claim( $listing_id, $notes ) {
	        $listing = get_post( $listing_id );

	        if ( ! $listing || 'business_listing' !== $listing->post_type ) {
	                $this->add_error( 'business', __( 'The selected business could not be found. Please try again.', 'local-gamified-directory' ) );
	                return;
	        }

	        $current_user_id = get_current_user_id();
	        $owner_id        = (int) get_post_meta( $listing_id, Local_Gamified_Directory::META_OWNER_USER, true );

	        if ( $owner_id && $owner_id === $current_user_id ) {
	                $this->add_error( 'business', __( 'You already manage this business listing.', 'local-gamified-directory' ) );
	                return;
	        }

	        if ( empty( $notes ) ) {
	                $this->add_error( 'business', __( 'Please provide verification details so our team can review your claim.', 'local-gamified-directory' ) );
	                return;
	        }

	        $existing_requests = get_post_meta( $listing_id, Local_Gamified_Directory::META_PREFIX . 'claim_request' );

	        foreach ( $existing_requests as $request ) {
	                $request = maybe_unserialize( $request );

	                if ( is_array( $request ) && isset( $request['user_id'] ) && (int) $request['user_id'] === $current_user_id ) {
	                        $this->add_error( 'business', __( 'You already have a pending claim for this business.', 'local-gamified-directory' ) );
	                        return;
	                }
	        }

	        $request = array(
	                'user_id'  => $current_user_id,
	                'message'  => $notes,
	                'date_gmt' => current_time( 'mysql', true ),
	        );

	        add_post_meta( $listing_id, Local_Gamified_Directory::META_PREFIX . 'claim_request', $request );

	        $activity = $this->plugin->get_activity();
	        if ( $activity ) {
	                $activity->log_event( $current_user_id, 'business_claim_request', 'business_listing', $listing_id, array( 'notes' => wp_strip_all_tags( $notes ) ) );
	        }

	        $blogname     = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	        $listing_name = get_the_title( $listing );
	        $current_user = wp_get_current_user();

	        $admin_subject = sprintf(
	                /* translators: 1: Site name, 2: Business listing title. */
	                __( '[%1$s] Business claim request: "%2$s"', 'local-gamified-directory' ),
	                $blogname,
	                $listing_name
	        );

	        $admin_message_template = __(
'A business claim request has been submitted on %1$s.

Listing: %2$s
Requested by: %3$s <%4$s>

Verification details:
%5$s

Review listing: %6$s',
	                'local-gamified-directory'
	        );

	        $this->plugin->notify_admin(
	                $admin_subject,
	                sprintf(
	                        $admin_message_template,
	                        $blogname,
	                        $listing_name,
	                        $current_user->display_name,
	                        $current_user->user_email,
	                        $notes,
	                        admin_url( 'post.php?post=' . $listing_id . '&action=edit' )
	                )
	        );

	        $user_subject = sprintf(
	                /* translators: 1: Site name, 2: Business listing title. */
	                __( '[%1$s] We received your claim for "%2$s"', 'local-gamified-directory' ),
	                $blogname,
	                $listing_name
	        );

	        $user_message_template = __(
'Hi %1$s,

Thank you for claiming "%2$s". Our team will review your request and get in touch once the ownership change is approved.

Thank you,
%3$s',
	                'local-gamified-directory'
	        );

	        $this->plugin->send_email(
	                $current_user->user_email,
	                $user_subject,
	                sprintf(
	                        $user_message_template,
	                        $current_user->display_name,
	                        $listing_name,
	                        $blogname
	                )
	        );

	        $gamification = $this->plugin->get_gamification();

	        if ( $gamification ) {
	                $gamification->add_points( $current_user_id, 10, 'business_claim' );
	        }

	        $this->add_success( 'business', __( 'Thank you! Your claim request has been sent to the directory team.', 'local-gamified-directory' ) );
	}

	/**
	 * Process a classified submission.
	 */
	private function process_classified_submission() {
	        if ( ! $this->plugin->is_feature_enabled( 'classifieds' ) ) {
	                return;
	        }

	        if ( ! is_user_logged_in() ) {
	                $this->add_error( 'classified', __( 'You must be logged in to submit a classified listing.', 'local-gamified-directory' ) );
	                return;
	        }

	        if ( ! $this->plugin->user_has_feature_access( get_current_user_id(), 'classifieds' ) ) {
	                $this->add_error( 'classified', __( 'You are not permitted to submit classified listings at this time.', 'local-gamified-directory' ) );
	                return;
	        }

	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lgd_classified_submission_nonce'] ) ), 'lgd_submit_classified' ) ) {
	                $this->add_error( 'classified', __( 'Security check failed. Please try again.', 'local-gamified-directory' ) );
	                return;
	        }

	        if ( ! current_user_can( 'create_classified_listings' ) ) {
	                $this->add_error( 'classified', __( 'You do not have permission to submit classifieds.', 'local-gamified-directory' ) );
	                return;
	        }

	        $title       = isset( $_POST['lgd_classified_title'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_classified_title'] ) ) : '';
	        $description = isset( $_POST['lgd_classified_description'] ) ? wp_kses_post( wp_unslash( $_POST['lgd_classified_description'] ) ) : '';

	        if ( empty( $title ) ) {
	                $this->add_error( 'classified', __( 'Please provide a classified title.', 'local-gamified-directory' ) );
	        }

	        if ( empty( $description ) ) {
	                $this->add_error( 'classified', __( 'Please provide a classified description.', 'local-gamified-directory' ) );
	        }

	        if ( $this->has_errors( 'classified' ) ) {
	                return;
	        }

	        $status = $this->plugin->get_setting( 'classified_requires_approval', false ) ? 'pending' : 'publish';

	        $post_id = wp_insert_post(
	                array(
	                        'post_type'    => 'classified_listing',
	                        'post_status'  => $status,
	                        'post_title'   => $title,
	                        'post_content' => $description,
	                        'post_author'  => get_current_user_id(),
	                ),
	                true
	        );

	        if ( is_wp_error( $post_id ) ) {
	                $this->add_error( 'classified', $post_id->get_error_message() );
	                return;
	        }

	        $meta = array(
	                Local_Gamified_Directory::META_PREFIX . 'price'          => isset( $_POST['lgd_classified_price'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_classified_price'] ) ) : '',
	                Local_Gamified_Directory::META_PREFIX . 'contact_method' => isset( $_POST['lgd_classified_contact'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_classified_contact'] ) ) : '',
	                Local_Gamified_Directory::META_PREFIX . 'location'       => isset( $_POST['lgd_classified_location'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_classified_location'] ) ) : '',
	                Local_Gamified_Directory::META_PREFIX . 'expires_at'     => gmdate( 'Y-m-d H:i:s', time() + ( 30 * DAY_IN_SECONDS ) ),
	        );

	        foreach ( $meta as $key => $value ) {
	                update_post_meta( $post_id, $key, $value );
	        }

	        $category_ids = array();
	        if ( ! empty( $_POST['lgd_classified_category'] ) ) {
	                $category_ids = array_map( 'intval', (array) wp_unslash( $_POST['lgd_classified_category'] ) );
	        }

	        if ( ! empty( $category_ids ) ) {
	                wp_set_post_terms( $post_id, $category_ids, 'classified_category', false );
	        }

	        $this->handle_media_upload( 'lgd_classified_image', $post_id, 'classified' );

	        $gamification = $this->plugin->get_gamification();
	        if ( $gamification ) {
	                $gamification->add_points( get_current_user_id(), 5, 'classified_submission' );
	        }

	        $activity = $this->plugin->get_activity();
	        if ( $activity ) {
	                $activity->log_event( get_current_user_id(), 'classified_submission', 'classified_listing', $post_id );
	        }

	        if ( 'pending' === $status ) {
	                $this->add_success( 'classified', __( 'Your classified listing has been submitted and awaits moderation.', 'local-gamified-directory' ) );
	        } else {
	                $this->add_success( 'classified', __( 'Your classified listing is now live!', 'local-gamified-directory' ) );
	        }
	}

	/**
	 * Handle uploads for front-end forms.
	 *
	 * @param string $field   File field name.
	 * @param int    $post_id Post ID to associate with the upload.
	 */
	private function handle_media_upload( $field, $post_id, $context ) {
	        if ( empty( $_FILES[ $field ]['name'] ) ) {
	                return;
	        }

	        require_once ABSPATH . 'wp-admin/includes/file.php';
	        require_once ABSPATH . 'wp-admin/includes/media.php';
	        require_once ABSPATH . 'wp-admin/includes/image.php';

	        $attachment_id = media_handle_upload( $field, $post_id );

	        if ( is_wp_error( $attachment_id ) ) {
	                $this->add_error( $context, $attachment_id->get_error_message() );
	                return;
	        }

	        set_post_thumbnail( $post_id, $attachment_id );
	}

	/**
	 * Render the business submission form shortcode.
	 *
	 * @return string
	 */
	public function render_business_submission_form() {
	        if ( ! $this->plugin->is_feature_enabled( 'business_submissions' ) ) {
	                return '<div class="lgd-notice lgd-notice--info">' . esc_html__( 'Business submissions are currently disabled.', 'local-gamified-directory' ) . '</div>';
	        }

	        if ( ! is_user_logged_in() ) {
	                return sprintf(
	                        '<div class="lgd-notice lgd-notice--warning">%s</div>',
	                        esc_html__( 'Please log in to submit a business listing.', 'local-gamified-directory' )
	                );
	        }

	        if ( ! $this->plugin->user_has_feature_access( get_current_user_id(), 'business_submissions' ) ) {
	                return '<div class="lgd-notice lgd-notice--warning">' . esc_html__( 'Your account is not permitted to submit business listings at this time.', 'local-gamified-directory' ) . '</div>';
	        }

	        if ( ! current_user_can( 'create_business_listings' ) ) {
	                return sprintf(
	                        '<div class="lgd-notice lgd-notice--warning">%s</div>',
	                        esc_html__( 'You do not have permission to submit a business listing.', 'local-gamified-directory' )
	                );
	        }

	        wp_enqueue_style( 'lgd-frontend' );

	        $messages = $this->get_messages( 'business' );

	        $categories = get_terms(
	                array(
	                        'taxonomy'   => 'business_category',
	                        'hide_empty' => false,
	                )
	        );

	        $regions = get_terms(
	                array(
	                        'taxonomy'   => 'business_region',
	                        'hide_empty' => false,
	                )
	        );

	        $claimable = $this->get_claimable_businesses( get_current_user_id() );

	        ob_start();
	        ?>
	        <form class="lgd-form" method="post" enctype="multipart/form-data">
	                <?php wp_nonce_field( 'lgd_submit_business', 'lgd_business_submission_nonce' ); ?>
	                <?php $this->render_messages( $messages ); ?>
	                <?php if ( ! empty( $claimable ) ) : ?>
	                        <div class="lgd-form__field">
	                                <label for="lgd_business_claim_listing"><?php esc_html_e( 'Claim an Existing Listing', 'local-gamified-directory' ); ?></label>
	                                <select id="lgd_business_claim_listing" name="lgd_business_claim_listing">
	                                        <option value=""><?php esc_html_e( '— Create a new listing —', 'local-gamified-directory' ); ?></option>
	                                        <?php foreach ( $claimable as $claim_post ) : ?>
	                                                <option value="<?php echo esc_attr( $claim_post->ID ); ?>"><?php echo esc_html( $claim_post->post_title ); ?></option>
	                                        <?php endforeach; ?>
	                                </select>
	                                <p class="lgd-form__help"><?php esc_html_e( 'Select a published listing to request ownership. Leave this blank to add a new business.', 'local-gamified-directory' ); ?></p>
	                        </div>
	                        <div class="lgd-form__field">
	                                <label for="lgd_business_claim_notes"><?php esc_html_e( 'Verification Details', 'local-gamified-directory' ); ?></label>
	                                <textarea id="lgd_business_claim_notes" name="lgd_business_claim_notes" rows="4" placeholder="<?php esc_attr_e( 'Share information that helps verify your ownership, such as business email or phone number.', 'local-gamified-directory' ); ?>"></textarea>
	                        </div>
	                <?php endif; ?>
	                <div class="lgd-form__field">
	                        <label for="lgd_business_name"><?php esc_html_e( 'Business Name', 'local-gamified-directory' ); ?></label>
	                        <input type="text" id="lgd_business_name" name="lgd_business_name" />
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_business_description"><?php esc_html_e( 'Description', 'local-gamified-directory' ); ?></label>
	                        <textarea id="lgd_business_description" name="lgd_business_description" rows="5"></textarea>
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_business_address"><?php esc_html_e( 'Address', 'local-gamified-directory' ); ?></label>
	                        <input type="text" id="lgd_business_address" name="lgd_business_address" />
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_business_phone"><?php esc_html_e( 'Phone', 'local-gamified-directory' ); ?></label>
	                        <input type="text" id="lgd_business_phone" name="lgd_business_phone" />
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_business_email"><?php esc_html_e( 'Contact Email', 'local-gamified-directory' ); ?></label>
	                        <input type="email" id="lgd_business_email" name="lgd_business_email" />
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_business_hours"><?php esc_html_e( 'Operating Hours', 'local-gamified-directory' ); ?></label>
	                        <textarea id="lgd_business_hours" name="lgd_business_hours" rows="3"></textarea>
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_business_place_id"><?php esc_html_e( 'Google Place ID', 'local-gamified-directory' ); ?></label>
	                        <input type="text" id="lgd_business_place_id" name="lgd_business_place_id" />
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_business_category"><?php esc_html_e( 'Business Category', 'local-gamified-directory' ); ?></label>
	                        <select id="lgd_business_category" name="lgd_business_category[]" multiple>
	                                <?php foreach ( $categories as $category ) : ?>
	                                        <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
	                                <?php endforeach; ?>
	                        </select>
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_business_region"><?php esc_html_e( 'Business Region', 'local-gamified-directory' ); ?></label>
	                        <select id="lgd_business_region" name="lgd_business_region[]" multiple>
	                                <?php foreach ( $regions as $region ) : ?>
	                                        <option value="<?php echo esc_attr( $region->term_id ); ?>"><?php echo esc_html( $region->name ); ?></option>
	                                <?php endforeach; ?>
	                        </select>
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_business_logo"><?php esc_html_e( 'Business Logo', 'local-gamified-directory' ); ?></label>
	                        <input type="file" id="lgd_business_logo" name="lgd_business_logo" accept="image/*" />
	                </div>
	                <div class="lgd-form__actions">
	                        <button type="submit" class="lgd-button lgd-button--primary"<?php echo $this->get_tracking_attributes( 'business_submit', 'business_listing' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Submit Business', 'local-gamified-directory' ); ?></button>
	                </div>
	        </form>
	        <?php
	        return $this->wrap_form_output( ob_get_clean() );
	}

	/**
	 * Render the classified submission form shortcode.
	 *
	 * @return string
	 */
	public function render_classified_submission_form() {
	        if ( ! $this->plugin->is_feature_enabled( 'classifieds' ) ) {
	                return '<div class="lgd-notice lgd-notice--info">' . esc_html__( 'Classified submissions are currently disabled.', 'local-gamified-directory' ) . '</div>';
	        }

	        if ( ! is_user_logged_in() ) {
	                return sprintf(
	                        '<div class="lgd-notice lgd-notice--warning">%s</div>',
	                        esc_html__( 'Please log in to submit a classified listing.', 'local-gamified-directory' )
	                );
	        }

	        if ( ! $this->plugin->user_has_feature_access( get_current_user_id(), 'classifieds' ) ) {
	                return '<div class="lgd-notice lgd-notice--warning">' . esc_html__( 'Your account is not permitted to submit classified listings at this time.', 'local-gamified-directory' ) . '</div>';
	        }

	        if ( ! current_user_can( 'create_classified_listings' ) ) {
	                return sprintf(
	                        '<div class="lgd-notice lgd-notice--warning">%s</div>',
	                        esc_html__( 'You do not have permission to submit classifieds.', 'local-gamified-directory' )
	                );
	        }

	        wp_enqueue_style( 'lgd-frontend' );

	        $messages = $this->get_messages( 'classified' );

	        $categories = get_terms(
	                array(
	                        'taxonomy'   => 'classified_category',
	                        'hide_empty' => false,
	                )
	        );

	        ob_start();
	        ?>
	        <form class="lgd-form" method="post" enctype="multipart/form-data">
	                <?php wp_nonce_field( 'lgd_submit_classified', 'lgd_classified_submission_nonce' ); ?>
	                <?php $this->render_messages( $messages ); ?>
	                <div class="lgd-form__field">
	                        <label for="lgd_classified_title"><?php esc_html_e( 'Title', 'local-gamified-directory' ); ?></label>
	                        <input type="text" id="lgd_classified_title" name="lgd_classified_title" required />
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_classified_description"><?php esc_html_e( 'Description', 'local-gamified-directory' ); ?></label>
	                        <textarea id="lgd_classified_description" name="lgd_classified_description" rows="5" required></textarea>
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_classified_category"><?php esc_html_e( 'Category', 'local-gamified-directory' ); ?></label>
	                        <select id="lgd_classified_category" name="lgd_classified_category[]" multiple>
	                                <?php foreach ( $categories as $category ) : ?>
	                                        <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
	                                <?php endforeach; ?>
	                        </select>
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_classified_price"><?php esc_html_e( 'Price', 'local-gamified-directory' ); ?></label>
	                        <input type="text" id="lgd_classified_price" name="lgd_classified_price" />
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_classified_location"><?php esc_html_e( 'Location', 'local-gamified-directory' ); ?></label>
	                        <input type="text" id="lgd_classified_location" name="lgd_classified_location" />
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_classified_contact"><?php esc_html_e( 'Contact Method', 'local-gamified-directory' ); ?></label>
	                        <input type="text" id="lgd_classified_contact" name="lgd_classified_contact" />
	                </div>
	                <div class="lgd-form__field">
	                        <label for="lgd_classified_image"><?php esc_html_e( 'Featured Image', 'local-gamified-directory' ); ?></label>
	                        <input type="file" id="lgd_classified_image" name="lgd_classified_image" accept="image/*" />
	                </div>
	                <div class="lgd-form__actions">
	                        <button type="submit" class="lgd-button lgd-button--primary"<?php echo $this->get_tracking_attributes( 'classified_submit', 'classified_listing' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Submit Classified', 'local-gamified-directory' ); ?></button>
	                </div>
	        </form>
	        <?php
	        return $this->wrap_form_output( ob_get_clean() );
	}

	/**
	 * Render the user dashboard shortcode.
	 *
	 * @return string
	 */
	public function render_user_dashboard() {
	        if ( ! $this->plugin->is_feature_enabled( 'frontend_dashboard' ) ) {
	                return '<div class="lgd-notice lgd-notice--info">' . esc_html__( 'The user dashboard is currently disabled.', 'local-gamified-directory' ) . '</div>';
	        }

	        if ( ! is_user_logged_in() ) {
	                return sprintf(
	                        '<div class="lgd-notice lgd-notice--warning">%s</div>',
	                        esc_html__( 'Please log in to view your dashboard.', 'local-gamified-directory' )
	                );
	        }

	        if ( ! $this->plugin->user_has_feature_access( get_current_user_id(), 'frontend_dashboard' ) ) {
	                return '<div class="lgd-notice lgd-notice--warning">' . esc_html__( 'Your account cannot access the dashboard right now.', 'local-gamified-directory' ) . '</div>';
	        }

	        wp_enqueue_style( 'lgd-frontend' );

	        $user_id        = get_current_user_id();
	        $businesses     = $this->get_user_businesses( $user_id );
	        $classifieds    = $this->get_user_classifieds( $user_id );
	        $gamification   = $this->plugin->get_gamification();
	        $subscriptions  = $this->plugin->get_subscriptions();
	        $points         = $gamification ? $gamification->get_user_points( $user_id ) : 0;
	        $rank           = $gamification ? $gamification->get_user_rank( $user_id ) : '';
	        $badges         = $gamification ? $gamification->get_user_badges( $user_id ) : array();
	        $is_premium     = $subscriptions ? $subscriptions->user_has_premium( $user_id ) : false;
	        $ads            = $this->plugin->is_feature_enabled( 'ads' ) ? $this->get_user_ads( $user_id ) : array();

	        $activity = $this->plugin->get_activity();
	        if ( $activity ) {
	                $activity->log_event( $user_id, 'dashboard_view', 'user', $user_id );
	        }

	        ob_start();
	        ?>
	        <div class="lgd-dashboard">
	                <section class="lgd-dashboard__summary">
	                        <h2><?php esc_html_e( 'Account Summary', 'local-gamified-directory' ); ?></h2>
	                        <p class="lgd-dashboard__stat">
	                                <strong><?php esc_html_e( 'Points Balance:', 'local-gamified-directory' ); ?></strong>
	                                <?php echo esc_html( number_format_i18n( $points ) ); ?>
	                        </p>
	                        <?php if ( ! empty( $rank ) ) : ?>
	                                <p class="lgd-dashboard__stat">
	                                        <strong><?php esc_html_e( 'Rank:', 'local-gamified-directory' ); ?></strong>
	                                        <?php echo esc_html( $rank ); ?>
	                                </p>
	                        <?php endif; ?>
	                       <?php if ( ! empty( $badges ) ) : ?>
	                               <p class="lgd-dashboard__stat">
	                                       <strong><?php esc_html_e( 'Badges:', 'local-gamified-directory' ); ?></strong>
	                                       <?php echo esc_html( implode( ', ', $badges ) ); ?>
	                               </p>
	                       <?php endif; ?>
	                        <p class="lgd-dashboard__stat">
	                                <strong><?php esc_html_e( 'Premium Status:', 'local-gamified-directory' ); ?></strong>
	                                <?php echo esc_html( $is_premium ? __( 'Active', 'local-gamified-directory' ) : __( 'Free Member', 'local-gamified-directory' ) ); ?>
	                        </p>
	                </section>
	                <section class="lgd-dashboard__section">
	                        <h2><?php esc_html_e( 'Your Business Listings', 'local-gamified-directory' ); ?></h2>
	                        <?php if ( empty( $businesses ) ) : ?>
	                                <p><?php esc_html_e( 'You have not submitted any business listings yet.', 'local-gamified-directory' ); ?></p>
	                        <?php else : ?>
	                                <table class="lgd-table">
	                                        <thead>
	                                                <tr>
	                                                        <th><?php esc_html_e( 'Business', 'local-gamified-directory' ); ?></th>
	                                                        <th><?php esc_html_e( 'Status', 'local-gamified-directory' ); ?></th>
	                                                        <th><?php esc_html_e( 'Actions', 'local-gamified-directory' ); ?></th>
	                                                </tr>
	                                        </thead>
	                                        <tbody>
	                                                <?php foreach ( $businesses as $business ) : ?>
	                                                        <tr>
	                                                                <td>
	                                                                        <a href="<?php echo esc_url( get_permalink( $business ) ); ?>"<?php echo $this->get_tracking_attributes( 'dashboard_view_business', 'business_listing', $business ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	                                                                                <?php echo esc_html( get_the_title( $business ) ); ?>
	                                                                        </a>
	                                                                </td>
	                                                                <td><?php echo esc_html( get_post_status_object( get_post_status( $business ) )->label ); ?></td>
	                                                                <td class="lgd-table__actions">
	                                                                        <a class="lgd-button lgd-button--small" href="<?php echo esc_url( get_edit_post_link( $business ) ); ?>"<?php echo $this->get_tracking_attributes( 'dashboard_edit_business', 'business_listing', $business ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	                                                                                <?php esc_html_e( 'Edit', 'local-gamified-directory' ); ?>
	                                                                        </a>
	                                                                </td>
	                                                        </tr>
	                                                <?php endforeach; ?>
	                                        </tbody>
	                                </table>
	                        <?php endif; ?>
	                </section>
	                <section class="lgd-dashboard__section">
	                        <h2><?php esc_html_e( 'Your Classifieds', 'local-gamified-directory' ); ?></h2>
	                        <?php if ( empty( $classifieds ) ) : ?>
	                                <p><?php esc_html_e( 'You have not posted any classifieds yet.', 'local-gamified-directory' ); ?></p>
	                        <?php else : ?>
	                                <table class="lgd-table">
	                                        <thead>
	                                                <tr>
	                                                        <th><?php esc_html_e( 'Classified', 'local-gamified-directory' ); ?></th>
	                                                        <th><?php esc_html_e( 'Status', 'local-gamified-directory' ); ?></th>
	                                                        <th><?php esc_html_e( 'Expires', 'local-gamified-directory' ); ?></th>
	                                                        <th><?php esc_html_e( 'Actions', 'local-gamified-directory' ); ?></th>
	                                                </tr>
	                                        </thead>
	                                        <tbody>
	                                                <?php foreach ( $classifieds as $classified ) :
	                                                        $expires = get_post_meta( $classified, Local_Gamified_Directory::META_PREFIX . 'expires_at', true );
	                                                        ?>
	                                                        <tr>
	                                                                <td>
	                                                                        <a href="<?php echo esc_url( get_permalink( $classified ) ); ?>"<?php echo $this->get_tracking_attributes( 'dashboard_view_classified', 'classified_listing', $classified ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	                                                                                <?php echo esc_html( get_the_title( $classified ) ); ?>
	                                                                        </a>
	                                                                </td>
	                                                                <td><?php echo esc_html( get_post_status_object( get_post_status( $classified ) )->label ); ?></td>
	                                                                <td><?php echo esc_html( $expires ? date_i18n( get_option( 'date_format' ), strtotime( $expires ) ) : __( 'N/A', 'local-gamified-directory' ) ); ?></td>
	                                                                <td class="lgd-table__actions">
	                                                                        <a class="lgd-button lgd-button--small" href="<?php echo esc_url( get_edit_post_link( $classified ) ); ?>"<?php echo $this->get_tracking_attributes( 'dashboard_edit_classified', 'classified_listing', $classified ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	                                                                                <?php esc_html_e( 'Edit', 'local-gamified-directory' ); ?>
	                                                                        </a>
	                                                                </td>
	                                                        </tr>
	                                                <?php endforeach; ?>
	                                        </tbody>
	                                </table>
	                        <?php endif; ?>
	                </section>
	                <section class="lgd-dashboard__section">
	                        <h2><?php esc_html_e( 'Your Sponsored Ads', 'local-gamified-directory' ); ?></h2>
	                        <?php if ( empty( $ads ) ) : ?>
	                                <p><?php esc_html_e( 'You have not created any sponsored ads yet.', 'local-gamified-directory' ); ?></p>
	                        <?php else : ?>
	                                <table class="lgd-table">
	                                        <thead>
	                                                <tr>
	                                                        <th><?php esc_html_e( 'Ad', 'local-gamified-directory' ); ?></th>
	                                                        <th><?php esc_html_e( 'Status', 'local-gamified-directory' ); ?></th>
	                                                        <th><?php esc_html_e( 'Schedule', 'local-gamified-directory' ); ?></th>
	                                                        <th><?php esc_html_e( 'Tokens Spent', 'local-gamified-directory' ); ?></th>
	                                                </tr>
	                                        </thead>
	                                        <tbody>
	                                                <?php foreach ( $ads as $ad ) :
	                                                        $start        = get_post_meta( $ad->ID, LGD_Ads::META_START, true );
	                                                        $end          = get_post_meta( $ad->ID, LGD_Ads::META_END, true );
	                                                        $tokens       = (int) get_post_meta( $ad->ID, LGD_Ads::META_TOKENS_SPENT, true );
	                                                        $status       = get_post_status( $ad );
	                                                        $status_obj   = $status ? get_post_status_object( $status ) : null;
	                                                        $status_label = $status_obj ? $status_obj->label : ucfirst( (string) $status );
	                                                        $start_str    = $start ? date_i18n( get_option( 'date_format' ), strtotime( $start ) ) : __( 'N/A', 'local-gamified-directory' );
	                                                        $end_str      = $end ? date_i18n( get_option( 'date_format' ), strtotime( $end ) ) : __( 'N/A', 'local-gamified-directory' );
	                                                        $schedule     = sprintf( '%1$s – %2$s', $start_str, $end_str );
	                                                        ?>
	                                                        <tr>
	                                                                <td><?php echo esc_html( get_the_title( $ad ) ); ?></td>
	                                                                <td><?php echo esc_html( $status_label ); ?></td>
	                                                                <td><?php echo esc_html( $schedule ); ?></td>
	                                                                <td><?php echo esc_html( number_format_i18n( $tokens ) ); ?></td>
	                                                        </tr>
	                                                <?php endforeach; ?>
	                                        </tbody>
	                                </table>
	                        <?php endif; ?>
	                </section>
	                <section class="lgd-dashboard__section">
	                        <h2><?php esc_html_e( 'Promote Your Business', 'local-gamified-directory' ); ?></h2>
	                        <?php echo do_shortcode( '[lgd_ad_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	                </section>
	        </div>
	        <?php
	        return ob_get_clean();
	}

	/**
	 * Fetch the current user's business listings.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	private function get_user_businesses( $user_id ) {
	        $query = new WP_Query(
	                array(
	                        'post_type'      => 'business_listing',
	                        'posts_per_page' => -1,
	                        'post_status'    => array( 'publish', 'pending', 'draft' ),
	                        'meta_query'     => array(
	                                array(
	                                        'key'   => Local_Gamified_Directory::META_OWNER_USER,
	                                        'value' => $user_id,
	                                ),
	                        ),
	                        'fields'         => 'ids',
	                )
	        );

	        return $query->posts;
	}

	/**
	 * Fetch the current user's classified listings.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	private function get_user_classifieds( $user_id ) {
	        $query = new WP_Query(
	                array(
	                        'post_type'      => 'classified_listing',
	                        'posts_per_page' => -1,
	                        'post_status'    => array( 'publish', 'pending', 'draft' ),
	                        'author'         => $user_id,
	                        'fields'         => 'ids',
	                )
	        );

	        return $query->posts;
	}

	/**
	 * Fetch the current user's sponsored ads.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array
	 */
	private function get_user_ads( $user_id ) {
	        $ads = $this->plugin->get_ads();

	        if ( ! $ads ) {
	                return array();
	        }

	        return $ads->get_ads_for_user( $user_id );
	}

	/**
	 * Collect a message for a given context.
	 *
	 * @param string $context Context key.
	 * @param string $message Message text.
	 */
	private function add_success( $context, $message ) {
	        $this->messages[ $context ]['success'][] = $message;
	}

	/**
	 * Collect an error for a given context.
	 *
	 * @param string $context Context key.
	 * @param string $message Message text.
	 */
	private function add_error( $context, $message ) {
	        $this->messages[ $context ]['errors'][] = $message;
	}

	/**
	 * Determine whether the given context has errors.
	 *
	 * @param string $context Context key.
	 *
	 * @return bool
	 */
	private function has_errors( $context ) {
	        return ! empty( $this->messages[ $context ]['errors'] );
	}

	/**
	 * Retrieve collected messages.
	 *
	 * @param string $context Context key.
	 *
	 * @return array
	 */
	private function get_messages( $context ) {
	        return isset( $this->messages[ $context ] ) ? $this->messages[ $context ] : array();
	}

	/**
	 * Render notice markup for a message array.
	 *
	 * @param array $messages Message collection.
	 */
	private function render_messages( $messages ) {
	        if ( empty( $messages ) ) {
	                return;
	        }

	        if ( ! empty( $messages['errors'] ) ) {
	                foreach ( $messages['errors'] as $error ) {
	                        printf( '<div class="lgd-notice lgd-notice--error">%s</div>', esc_html( $error ) );
	                }
	        }

	        if ( ! empty( $messages['success'] ) ) {
	                foreach ( $messages['success'] as $message ) {
	                        printf( '<div class="lgd-notice lgd-notice--success">%s</div>', esc_html( $message ) );
	                }
	        }
	}

	/**
	 * Wrap form markup with a container.
	 *
	 * @param string $html Raw HTML output.
	 *
	 * @return string
	 */
	private function wrap_form_output( $html ) {
	        return sprintf( '<div class="lgd-form-wrapper">%s</div>', $html );
	}

	/**
	 * Build activity tracking attributes when the feature is enabled.
	 *
	 * @param string $event      Event identifier.
	 * @param string $type       Optional object type.
	 * @param int    $object_id  Optional object ID.
	 * @param array  $details    Optional associative array of details.
	 *
	 * @return string Attribute string prefixed with a space when applicable.
	 */
	private function get_tracking_attributes( $event, $type = '', $object_id = 0, $details = array() ) {
	        if ( ! $this->plugin->is_feature_enabled( 'activity_tracking' ) || empty( $event ) ) {
	                return '';
	        }

	        $attributes = array( 'data-lgd-track-event' => sanitize_key( $event ) );

	        if ( ! empty( $type ) ) {
	                $attributes['data-lgd-track-type'] = sanitize_key( $type );
	        }

	        $object_id = absint( $object_id );
	        if ( $object_id > 0 ) {
	                $attributes['data-lgd-track-object'] = $object_id;
	        }

	        if ( ! empty( $details ) ) {
	                $json = wp_json_encode( $details );
	                if ( $json ) {
	                        $attributes['data-lgd-track-details'] = $json;
	                }
	        }

	        $compiled = array();
	        foreach ( $attributes as $key => $value ) {
	                $compiled[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
	        }

	        return empty( $compiled ) ? '' : ' ' . implode( ' ', $compiled );
	}
}
