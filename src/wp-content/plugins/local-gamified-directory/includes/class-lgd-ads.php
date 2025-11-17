<?php
/**
 * Advertising utilities for the Local Gamified Directory plugin.
 *
 * @package LocalGamifiedDirectory
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manage ad creation and placement.
 */
class LGD_Ads {

	const POST_TYPE = 'lgd_ad';

	const META_TARGET_CATEGORY = '_lgd_ad_target_category';
	const META_TARGET_REGION   = '_lgd_ad_target_region';
	const META_START           = '_lgd_ad_start';
	const META_END             = '_lgd_ad_end';
	const META_TOKENS_SPENT    = '_lgd_ad_tokens';
	const META_OWNER           = '_lgd_ad_owner';
	const META_TARGET_URL      = '_lgd_ad_target_url';

	/**
	 * Plugin instance.
	 *
	 * @var Local_Gamified_Directory
	 */
	private $plugin;

	/**
	 * Front-end notices queued for display.
	 *
	 * @var array
	 */
	private $notices = array();

	/**
	 * Constructor.
	 *
	 * @param Local_Gamified_Directory $plugin Main plugin instance.
	 */
	public function __construct( Local_Gamified_Directory $plugin ) {
	        $this->plugin = $plugin;

	        add_action( 'init', array( $this, 'register_post_type' ) );
	        add_action( 'init', array( $this, 'register_meta' ) );
	        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
	        add_action( 'save_post_' . self::POST_TYPE, array( $this, 'save_ad_meta' ), 10, 2 );
	        add_action( 'init', array( $this, 'maybe_handle_frontend_submission' ), 20 );
	        add_shortcode( 'lgd_ad_form', array( $this, 'render_ad_form_shortcode' ) );
	        add_filter( 'the_content', array( $this, 'inject_business_ads' ) );
	        add_action( 'wp_footer', array( $this, 'render_frontend_notices' ) );
	}

	/**
	 * Set up the ad custom post type.
	 */
	public function register_post_type() {
	        $labels = array(
	                'name'               => _x( 'Directory Ads', 'post type general name', 'local-gamified-directory' ),
	                'singular_name'      => _x( 'Directory Ad', 'post type singular name', 'local-gamified-directory' ),
	                'menu_name'          => _x( 'Directory Ads', 'admin menu', 'local-gamified-directory' ),
	                'name_admin_bar'     => _x( 'Directory Ad', 'add new on admin bar', 'local-gamified-directory' ),
	                'add_new'            => _x( 'Add New', 'ad', 'local-gamified-directory' ),
	                'add_new_item'       => __( 'Add New Ad', 'local-gamified-directory' ),
	                'new_item'           => __( 'New Ad', 'local-gamified-directory' ),
	                'edit_item'          => __( 'Edit Ad', 'local-gamified-directory' ),
	                'view_item'          => __( 'View Ad', 'local-gamified-directory' ),
	                'all_items'          => __( 'All Ads', 'local-gamified-directory' ),
	                'search_items'       => __( 'Search Ads', 'local-gamified-directory' ),
	                'not_found'          => __( 'No ads found.', 'local-gamified-directory' ),
	                'not_found_in_trash' => __( 'No ads found in Trash.', 'local-gamified-directory' ),
	        );

	        register_post_type(
	                self::POST_TYPE,
	                array(
	                        'labels'        => $labels,
	                        'public'        => false,
	                        'show_ui'       => true,
	                        'show_in_menu'  => true,
	                        'show_in_rest'  => true,
	                        'supports'      => array( 'title', 'editor', 'thumbnail' ),
	                        'capability_type' => array( 'lgd_ad', 'lgd_ads' ),
	                        'capabilities'    => Local_Gamified_Directory::get_post_type_capabilities( 'lgd_ad', 'lgd_ads' ),
	                        'map_meta_cap'    => true,
	                )
	        );
	}

	/**
	 * Register ad meta fields.
	 */
	public function register_meta() {
	        $meta_keys = array(
	                self::META_TARGET_CATEGORY => array(
	                        'type'              => 'integer',
	                        'sanitize_callback' => 'absint',
	                        'description'       => __( 'Primary business category targeted by the ad.', 'local-gamified-directory' ),
	                ),
	                self::META_TARGET_REGION => array(
	                        'type'              => 'integer',
	                        'sanitize_callback' => 'absint',
	                        'description'       => __( 'Business region targeted by the ad.', 'local-gamified-directory' ),
	                ),
	                self::META_START => array(
	                        'type'              => 'string',
	                        'sanitize_callback' => array( $this->plugin, 'sanitize_datetime_meta' ),
	                        'description'       => __( 'Ad start date.', 'local-gamified-directory' ),
	                ),
	                self::META_END => array(
	                        'type'              => 'string',
	                        'sanitize_callback' => array( $this->plugin, 'sanitize_datetime_meta' ),
	                        'description'       => __( 'Ad end date.', 'local-gamified-directory' ),
	                ),
	                self::META_TOKENS_SPENT => array(
	                        'type'              => 'integer',
	                        'sanitize_callback' => 'absint',
	                        'description'       => __( 'Tokens spent to create the ad.', 'local-gamified-directory' ),
	                ),
	                self::META_OWNER => array(
	                        'type'              => 'integer',
	                        'sanitize_callback' => 'absint',
	                        'description'       => __( 'Business owner user ID.', 'local-gamified-directory' ),
	                ),
	                self::META_TARGET_URL => array(
	                        'type'              => 'string',
	                        'sanitize_callback' => 'esc_url_raw',
	                        'description'       => __( 'Destination URL for the ad.', 'local-gamified-directory' ),
	                ),
	        );

	        foreach ( $meta_keys as $key => $args ) {
	                register_post_meta(
	                        self::POST_TYPE,
	                        $key,
	                        array_merge(
	                                array(
	                                        'single'       => true,
	                                        'show_in_rest' => true,
	                                ),
	                                $args
	                        )
	                );
	        }
	}

	/**
	 * Register admin meta boxes.
	 */
	public function register_meta_boxes() {
	        add_meta_box(
	                'lgd_ad_details',
	                __( 'Ad Details', 'local-gamified-directory' ),
	                array( $this, 'render_ad_details_meta_box' ),
	                self::POST_TYPE,
	                'normal',
	                'default'
	        );
	}

	/**
	 * Render the ad details meta box.
	 *
	 * @param WP_Post $post Current post object.
	 */
	public function render_ad_details_meta_box( $post ) {
	        wp_nonce_field( 'lgd_save_ad_details', 'lgd_ad_details_nonce' );

	        $target_category = (int) get_post_meta( $post->ID, self::META_TARGET_CATEGORY, true );
	        $target_region   = (int) get_post_meta( $post->ID, self::META_TARGET_REGION, true );
	        $start           = get_post_meta( $post->ID, self::META_START, true );
	        $end             = get_post_meta( $post->ID, self::META_END, true );
	        $tokens          = (int) get_post_meta( $post->ID, self::META_TOKENS_SPENT, true );
	        $target_url      = get_post_meta( $post->ID, self::META_TARGET_URL, true );

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
	        ?>
	        <p>
	                <label for="lgd_ad_target_category"><?php esc_html_e( 'Target Business Category', 'local-gamified-directory' ); ?></label>
	                <select id="lgd_ad_target_category" name="lgd_ad_target_category">
	                        <option value="0"><?php esc_html_e( 'All Categories', 'local-gamified-directory' ); ?></option>
	                        <?php foreach ( $categories as $category ) : ?>
	                                <option value="<?php echo esc_attr( $category->term_id ); ?>" <?php selected( $target_category, $category->term_id ); ?>><?php echo esc_html( $category->name ); ?></option>
	                        <?php endforeach; ?>
	                </select>
	        </p>
	        <p>
	                <label for="lgd_ad_target_region"><?php esc_html_e( 'Target Business Region', 'local-gamified-directory' ); ?></label>
	                <select id="lgd_ad_target_region" name="lgd_ad_target_region">
	                        <option value="0"><?php esc_html_e( 'All Regions', 'local-gamified-directory' ); ?></option>
	                        <?php foreach ( $regions as $region ) : ?>
	                                <option value="<?php echo esc_attr( $region->term_id ); ?>" <?php selected( $target_region, $region->term_id ); ?>><?php echo esc_html( $region->name ); ?></option>
	                        <?php endforeach; ?>
	                </select>
	        </p>
	        <p>
	                <label for="lgd_ad_target_url"><?php esc_html_e( 'Target URL', 'local-gamified-directory' ); ?></label>
	                <input type="url" id="lgd_ad_target_url" name="lgd_ad_target_url" class="widefat" value="<?php echo esc_attr( $target_url ); ?>" />
	        </p>
	        <p>
	                <label for="lgd_ad_start"><?php esc_html_e( 'Start Date', 'local-gamified-directory' ); ?></label>
	                <input type="datetime-local" id="lgd_ad_start" name="lgd_ad_start" value="<?php echo esc_attr( $start ? gmdate( 'Y-m-d\TH:i', strtotime( $start ) ) : '' ); ?>" />
	        </p>
	        <p>
	                <label for="lgd_ad_end"><?php esc_html_e( 'End Date', 'local-gamified-directory' ); ?></label>
	                <input type="datetime-local" id="lgd_ad_end" name="lgd_ad_end" value="<?php echo esc_attr( $end ? gmdate( 'Y-m-d\TH:i', strtotime( $end ) ) : '' ); ?>" />
	        </p>
	        <p>
	                <label for="lgd_ad_tokens"><?php esc_html_e( 'Tokens Spent', 'local-gamified-directory' ); ?></label>
	                <input type="number" id="lgd_ad_tokens" name="lgd_ad_tokens" value="<?php echo esc_attr( $tokens ); ?>" />
	        </p>
	        <?php
	}

	/**
	 * Save ad meta values.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function save_ad_meta( $post_id, $post ) {
	        if ( ! isset( $_POST['lgd_ad_details_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lgd_ad_details_nonce'] ) ), 'lgd_save_ad_details' ) ) {
	                return;
	        }

	        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
	                return;
	        }

	        if ( ! current_user_can( 'edit_post', $post_id ) ) {
	                return;
	        }

	        $target_category = isset( $_POST['lgd_ad_target_category'] ) ? absint( $_POST['lgd_ad_target_category'] ) : 0;
	        $target_region   = isset( $_POST['lgd_ad_target_region'] ) ? absint( $_POST['lgd_ad_target_region'] ) : 0;
	        $target_url      = isset( $_POST['lgd_ad_target_url'] ) ? esc_url_raw( wp_unslash( $_POST['lgd_ad_target_url'] ) ) : '';
	        $start           = isset( $_POST['lgd_ad_start'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_ad_start'] ) ) : '';
	        $end             = isset( $_POST['lgd_ad_end'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_ad_end'] ) ) : '';
	        $tokens          = isset( $_POST['lgd_ad_tokens'] ) ? absint( $_POST['lgd_ad_tokens'] ) : 0;

	        update_post_meta( $post_id, self::META_TARGET_CATEGORY, $target_category );
	        update_post_meta( $post_id, self::META_TARGET_REGION, $target_region );
	        update_post_meta( $post_id, self::META_TARGET_URL, $target_url );
	        update_post_meta( $post_id, self::META_START, $start );
	        update_post_meta( $post_id, self::META_END, $end );
	        update_post_meta( $post_id, self::META_TOKENS_SPENT, $tokens );
	}

	/**
	 * Process front-end ad submissions from business owners.
	 */
	public function maybe_handle_frontend_submission() {
	        if ( ! $this->plugin->is_feature_enabled( 'ads' ) ) {
	                return;
	        }

	        if ( empty( $_POST['lgd_ad_submission_nonce'] ) ) {
	                return;
	        }

	        if ( ! is_user_logged_in() ) {
	                return;
	        }

	        if ( ! $this->plugin->user_has_feature_access( get_current_user_id(), 'ads' ) ) {
	                $this->add_notice( __( 'You are not permitted to create ads at this time.', 'local-gamified-directory' ), 'error' );
	                return;
	        }

	        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['lgd_ad_submission_nonce'] ) ), 'lgd_submit_ad' ) ) {
	                return;
	        }

	        if ( ! current_user_can( 'create_business_listings' ) ) {
	                return;
	        }

	        $user_id = get_current_user_id();

	        $title       = isset( $_POST['lgd_ad_title'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_ad_title'] ) ) : '';
	        $content     = isset( $_POST['lgd_ad_content'] ) ? wp_kses_post( wp_unslash( $_POST['lgd_ad_content'] ) ) : '';
	        $target_url  = isset( $_POST['lgd_ad_url'] ) ? esc_url_raw( wp_unslash( $_POST['lgd_ad_url'] ) ) : '';
	        $category_id = isset( $_POST['lgd_ad_category'] ) ? absint( $_POST['lgd_ad_category'] ) : 0;
	        $region_id   = isset( $_POST['lgd_ad_region'] ) ? absint( $_POST['lgd_ad_region'] ) : 0;
	        $duration    = isset( $_POST['lgd_ad_duration'] ) ? absint( $_POST['lgd_ad_duration'] ) : 1;

	        if ( empty( $title ) || empty( $content ) ) {
	                $this->add_notice( __( 'Please provide the required ad information.', 'local-gamified-directory' ), 'error' );
	                return;
	        }

	        $cost_per_day = (int) $this->plugin->get_setting( 'ad_cost_per_day', 10 );
	        $cost         = max( 1, $duration ) * max( 1, $cost_per_day );

	        $gamification = $this->plugin->get_gamification();
	        if ( ! $gamification ) {
	                return;
	        }

	        $balance = $gamification->get_user_points( $user_id );
	        if ( $balance < $cost ) {
	                $this->add_notice( __( 'You do not have enough tokens to create this ad.', 'local-gamified-directory' ), 'error' );
	                return;
	        }

	        $post_id = wp_insert_post(
	                array(
	                        'post_type'    => self::POST_TYPE,
	                        'post_status'  => 'pending',
	                        'post_title'   => $title,
	                        'post_content' => $content,
	                        'post_author'  => $user_id,
	                ),
	                true
	        );

	        if ( is_wp_error( $post_id ) ) {
	                $this->add_notice( $post_id->get_error_message(), 'error' );
	                return;
	        }

	        $start = gmdate( 'Y-m-d H:i:s' );
	        $end   = gmdate( 'Y-m-d H:i:s', time() + ( $duration * DAY_IN_SECONDS ) );

	        update_post_meta( $post_id, self::META_TARGET_CATEGORY, $category_id );
	        update_post_meta( $post_id, self::META_TARGET_REGION, $region_id );
	        update_post_meta( $post_id, self::META_TARGET_URL, $target_url );
	        update_post_meta( $post_id, self::META_START, $start );
	        update_post_meta( $post_id, self::META_END, $end );
	        update_post_meta( $post_id, self::META_TOKENS_SPENT, $cost );
	        update_post_meta( $post_id, self::META_OWNER, $user_id );

	        $gamification->remove_points( $user_id, $cost, 'ad_purchase', array( 'post_id' => $post_id ) );

	        if ( ! empty( $_FILES['lgd_ad_image']['name'] ) ) {
	                require_once ABSPATH . 'wp-admin/includes/file.php';
	                require_once ABSPATH . 'wp-admin/includes/media.php';
	                require_once ABSPATH . 'wp-admin/includes/image.php';

	                $attachment_id = media_handle_upload( 'lgd_ad_image', $post_id );
	                if ( ! is_wp_error( $attachment_id ) ) {
	                        set_post_thumbnail( $post_id, $attachment_id );
	                }
	        }

	        $activity = $this->plugin->get_activity();
	        if ( $activity ) {
	                $activity->log_event( $user_id, 'ad_submission', 'lgd_ad', $post_id );
	        }

	        $this->add_notice( __( 'Your ad has been submitted for review.', 'local-gamified-directory' ), 'success' );
	}

	/**
	 * Render the ad submission form shortcode.
	 *
	 * @return string
	 */
	public function render_ad_form_shortcode() {
	        if ( ! $this->plugin->is_feature_enabled( 'ads' ) ) {
	                return '<div class="lgd-notice lgd-notice--info">' . esc_html__( 'Ad creation is currently disabled.', 'local-gamified-directory' ) . '</div>';
	        }

	        if ( ! is_user_logged_in() ) {
	                return sprintf( '<div class="lgd-notice lgd-notice--warning">%s</div>', esc_html__( 'Please log in to create an ad.', 'local-gamified-directory' ) );
	        }

	        if ( ! $this->plugin->user_has_feature_access( get_current_user_id(), 'ads' ) ) {
	                return '<div class="lgd-notice lgd-notice--warning">' . esc_html__( 'Your account cannot create ads right now.', 'local-gamified-directory' ) . '</div>';
	        }

	        if ( ! current_user_can( 'create_business_listings' ) ) {
	                return sprintf( '<div class="lgd-notice lgd-notice--warning">%s</div>', esc_html__( 'Only business owners can create ads.', 'local-gamified-directory' ) );
	        }

	        wp_enqueue_style( 'lgd-frontend' );

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

	        $cost_per_day = (int) $this->plugin->get_setting( 'ad_cost_per_day', 10 );

	        ob_start();
	        ?>
	        <form class="lgd-form" method="post" enctype="multipart/form-data">
	                <?php wp_nonce_field( 'lgd_submit_ad', 'lgd_ad_submission_nonce' ); ?>
                        <div class="lgd-form__field">
                                <?php $help_id = $this->render_field_header( 'lgd_ad_title', __( 'Ad Title', 'local-gamified-directory' ), 'ad_title' ); ?>
                                <input type="text" id="lgd_ad_title" name="lgd_ad_title" required<?php $this->print_describedby_attribute( $help_id ); ?> />
                        </div>
                        <div class="lgd-form__field">
                                <?php $help_id = $this->render_field_header( 'lgd_ad_content', __( 'Ad Content', 'local-gamified-directory' ), 'ad_content' ); ?>
                                <textarea id="lgd_ad_content" name="lgd_ad_content" rows="4" required<?php $this->print_describedby_attribute( $help_id ); ?>></textarea>
                        </div>
                        <div class="lgd-form__field">
                                <?php $help_id = $this->render_field_header( 'lgd_ad_url', __( 'Destination URL', 'local-gamified-directory' ), 'ad_url' ); ?>
                                <input type="url" id="lgd_ad_url" name="lgd_ad_url" placeholder="https://"<?php $this->print_describedby_attribute( $help_id ); ?> />
                        </div>
                        <div class="lgd-form__field">
                                <?php $help_id = $this->render_field_header( 'lgd_ad_category', __( 'Target Category', 'local-gamified-directory' ), 'ad_category' ); ?>
                                <select id="lgd_ad_category" name="lgd_ad_category"<?php $this->print_describedby_attribute( $help_id ); ?>>
	                                <option value="0"><?php esc_html_e( 'All Categories', 'local-gamified-directory' ); ?></option>
	                                <?php foreach ( $categories as $category ) : ?>
	                                        <option value="<?php echo esc_attr( $category->term_id ); ?>"><?php echo esc_html( $category->name ); ?></option>
	                                <?php endforeach; ?>
	                        </select>
	                </div>
                        <div class="lgd-form__field">
                                <?php $help_id = $this->render_field_header( 'lgd_ad_region', __( 'Target Region', 'local-gamified-directory' ), 'ad_region' ); ?>
                                <select id="lgd_ad_region" name="lgd_ad_region"<?php $this->print_describedby_attribute( $help_id ); ?>>
	                                <option value="0"><?php esc_html_e( 'All Regions', 'local-gamified-directory' ); ?></option>
	                                <?php foreach ( $regions as $region ) : ?>
	                                        <option value="<?php echo esc_attr( $region->term_id ); ?>"><?php echo esc_html( $region->name ); ?></option>
	                                <?php endforeach; ?>
	                        </select>
	                </div>
                        <div class="lgd-form__field">
                                <?php $help_id = $this->render_field_header( 'lgd_ad_duration', __( 'Duration (days)', 'local-gamified-directory' ), 'ad_duration' ); ?>
                                <input type="number" id="lgd_ad_duration" name="lgd_ad_duration" min="1" value="7"<?php $this->print_describedby_attribute( $help_id ); ?> />
	                        <p class="description"><?php echo esc_html( sprintf( __( 'Each day costs %d tokens.', 'local-gamified-directory' ), $cost_per_day ) ); ?></p>
	                </div>
                        <div class="lgd-form__field">
                                <?php $help_id = $this->render_field_header( 'lgd_ad_image', __( 'Ad Image', 'local-gamified-directory' ), 'ad_image' ); ?>
                                <input type="file" id="lgd_ad_image" name="lgd_ad_image" accept="image/*"<?php $this->print_describedby_attribute( $help_id ); ?> />
	                </div>
	                <div class="lgd-form__actions">
	                        <button type="submit" class="lgd-button lgd-button--primary"<?php echo $this->get_tracking_attributes( 'ad_submit', 'lgd_ad' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Create Ad', 'local-gamified-directory' ); ?></button>
	                </div>
	        </form>
	        <?php
	        return $this->wrap_output( ob_get_clean() );
	}

	/**
	 * Display ads on business listing pages when applicable.
	 *
	 * @param string $content Original content.
	 *
	 * @return string
	 */
	public function inject_business_ads( $content ) {
	        if ( ! $this->plugin->is_feature_enabled( 'ads' ) ) {
	                return $content;
	        }

	        if ( ! is_singular( 'business_listing' ) || ! in_the_loop() || ! is_main_query() ) {
	                return $content;
	        }

	        $post_id = get_the_ID();

	        if ( get_post_meta( $post_id, Local_Gamified_Directory::META_PREFIX . 'ad_free', true ) ) {
	                return $content;
	        }

	        $ads = $this->get_ads_for_listing( $post_id );

	        if ( empty( $ads ) ) {
	                return $content;
	        }

	        $ad_markup = '<aside class="lgd-ad-slot"><h3>' . esc_html__( 'Sponsored', 'local-gamified-directory' ) . '</h3>';

	        $activity = $this->plugin->get_activity();

	        foreach ( $ads as $ad ) {
	                $target_url = get_post_meta( $ad->ID, self::META_TARGET_URL, true );
	                if ( $activity ) {
	                        $activity->log_event( get_current_user_id(), 'ad_impression', 'lgd_ad', $ad->ID );
	                        if ( ! empty( $target_url ) ) {
	                                $target_url = $activity->get_tracked_url( 'ad_click', 'lgd_ad', $ad->ID, $target_url );
	                        }
	                }
	                $ad_markup .= '<div class="lgd-ad">';

	                if ( has_post_thumbnail( $ad->ID ) ) {
	                        $ad_markup .= '<div class="lgd-ad__image">';
	                        $ad_markup .= get_the_post_thumbnail( $ad->ID, 'medium' );
	                        $ad_markup .= '</div>';
	                }

	                $ad_markup .= '<div class="lgd-ad__content">';
	                $ad_markup .= '<strong>' . esc_html( get_the_title( $ad->ID ) ) . '</strong>';
	                $ad_markup .= wp_kses_post( wpautop( $ad->post_content ) );

	                if ( $target_url ) {
	                        $ad_markup .= '<p><a class="lgd-button lgd-button--secondary" href="' . esc_url( $target_url ) . '" target="_blank" rel="nofollow sponsored noopener">' . esc_html__( 'Learn More', 'local-gamified-directory' ) . '</a></p>';
	                }

	                $ad_markup .= '</div></div>';
	        }

	        $ad_markup .= '</aside>';

	        return $content . $ad_markup;
	}

	/**
	 * Retrieve active ads for a business listing.
	 *
	 * @param int $post_id Business listing ID.
	 *
	 * @return array
	 */
	private function get_ads_for_listing( $post_id ) {
	        $now        = gmdate( 'Y-m-d H:i:s' );
	        $categories = wp_get_post_terms( $post_id, 'business_category', array( 'fields' => 'ids' ) );
	        $regions    = wp_get_post_terms( $post_id, 'business_region', array( 'fields' => 'ids' ) );

	        $query = new WP_Query(
	                array(
	                        'post_type'      => self::POST_TYPE,
	                        'post_status'    => 'publish',
	                        'meta_query'     => array(
	                                'relation' => 'AND',
	                                array(
	                                        'key'     => self::META_START,
	                                        'value'   => $now,
	                                        'compare' => '<=',
	                                        'type'    => 'DATETIME',
	                                ),
	                                array(
	                                        'key'     => self::META_END,
	                                        'value'   => $now,
	                                        'compare' => '>=',
	                                        'type'    => 'DATETIME',
	                                ),
	                        ),
	                        'fields'         => 'ids',
	                        'posts_per_page' => 3,
	                )
	        );

	        if ( empty( $query->posts ) ) {
	                return array();
	        }

	        $ads = array();

	        foreach ( $query->posts as $ad_id ) {
	                $target_category = (int) get_post_meta( $ad_id, self::META_TARGET_CATEGORY, true );
	                $target_region   = (int) get_post_meta( $ad_id, self::META_TARGET_REGION, true );

	                $category_match = ! $target_category || in_array( $target_category, $categories, true );
	                $region_match   = ! $target_region || in_array( $target_region, $regions, true );

	                if ( $category_match && $region_match ) {
	                        $ads[] = get_post( $ad_id );
	                }
	        }

		return $ads;
	}

	/**
	 * Retrieve the ads created by a specific user.
	 *
	 * @param int $user_id User ID.
	 *
	 * @return array Array of WP_Post objects.
	 */
	public function get_ads_for_user( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return array();
		}

		$query = new WP_Query(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'pending', 'publish', 'draft' ),
				'posts_per_page' => -1,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'no_found_rows'  => true,
				'author'         => $user_id,
				'meta_query'     => array(
					array(
						'key'   => self::META_OWNER,
						'value' => $user_id,
					),
				),
			)
		);

		return $query->posts;
	}

	/**
	 * Queue a front-end notice for display.
	 *
	 * @param string $message Message text.
	 * @param string $type    Notice type (success, error, warning, info).
	 */
	private function add_notice( $message, $type = 'info' ) {
	        $this->notices[] = array(
	                'message' => $message,
	                'type'    => $type,
	        );
	}

	/**
	 * Render queued notices in the site footer.
	 */
	public function render_frontend_notices() {
	        if ( empty( $this->notices ) ) {
	                return;
	        }

	        foreach ( $this->notices as $notice ) {
	                printf(
	                        '<div class="lgd-notice lgd-notice--%1$s">%2$s</div>',
	                        esc_attr( $notice['type'] ),
	                        esc_html( $notice['message'] )
	                );
	        }

	        $this->notices = array();
	}

	/**
	 * Generate tracking attributes for ad interactions when enabled.
	 *
	 * @param string $event     Event identifier.
	 * @param string $type      Optional object type.
	 * @param int    $object_id Optional object ID.
	 *
	 * @return string Attribute string starting with a space when populated.
	 */
	private function get_tracking_attributes( $event, $type = '', $object_id = 0 ) {
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

	        $compiled = array();
	        foreach ( $attributes as $key => $value ) {
	                $compiled[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( (string) $value ) );
	        }

	        return empty( $compiled ) ? '' : ' ' . implode( ' ', $compiled );
	}

        /**
         * Render a form label with optional contextual help.
         *
         * @param string $for      Field ID.
         * @param string $label    Label text.
         * @param string $help_key Help context key.
         *
         * @return string Tooltip ID when available.
         */
        private function render_field_header( $for, $label, $help_key = '' ) {
                $tooltip = array(
                        'id'   => '',
                        'html' => '',
                );

                if ( ! empty( $help_key ) ) {
                        $tooltip = $this->plugin->get_help_tooltip( $help_key );
                }

                echo '<div class="lgd-form__label">';
                printf( '<label for="%1$s">%2$s</label>', esc_attr( $for ), esc_html( $label ) );

                if ( ! empty( $tooltip['html'] ) ) {
                        echo $tooltip['html']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }

                echo '</div>';

                return ! empty( $tooltip['id'] ) ? $tooltip['id'] : '';
        }

        /**
         * Print an aria-describedby attribute when needed.
         *
         * @param string $id Tooltip ID.
         */
        private function print_describedby_attribute( $id ) {
                if ( empty( $id ) ) {
                        return;
                }

                echo ' aria-describedby="' . esc_attr( $id ) . '"';
        }

        /**
         * Wrap ad output in a container.
         *
         * @param string $html HTML markup.
         *
         * @return string
         */
        private function wrap_output( $html ) {
                $support = $this->plugin->get_support_callout_html();

                if ( ! empty( $support ) ) {
                        $html .= $support;
                }

                return sprintf( '<div class="lgd-form-wrapper">%s</div>', $html );
        }

	/**
	 * Activation tasks for the ads module.
	 */
	public static function activate() {
	        // Ensure cost option has a sensible default.
	        $instance = Local_Gamified_Directory::instance();
	        if ( $instance ) {
	                $instance->update_setting( 'ad_cost_per_day', $instance->get_setting( 'ad_cost_per_day', 10 ) );
	        }
	}
}
