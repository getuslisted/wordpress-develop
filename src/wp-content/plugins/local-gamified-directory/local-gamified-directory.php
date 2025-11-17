<?php
/**
 * Plugin Name:       Local Gamified Directory
 * Plugin URI:        https://example.com/local-gamified-directory
 * Description:       Business directory, classifieds, forums integration, and gamified engagement system for local communities.
 * Version:           0.1.0
 * Author:            Local Gamified Directory Contributors
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       local-gamified-directory
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

if ( ! defined( 'LGD_PLUGIN_FILE' ) ) {
        define( 'LGD_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'LGD_PLUGIN_DIR' ) ) {
        define( 'LGD_PLUGIN_DIR', plugin_dir_path( LGD_PLUGIN_FILE ) );
}

if ( ! defined( 'LGD_PLUGIN_URL' ) ) {
        define( 'LGD_PLUGIN_URL', plugin_dir_url( LGD_PLUGIN_FILE ) );
}

require_once LGD_PLUGIN_DIR . 'includes/class-lgd-admin.php';
require_once LGD_PLUGIN_DIR . 'includes/class-lgd-activity.php';
require_once LGD_PLUGIN_DIR . 'includes/class-lgd-social-login.php';
require_once LGD_PLUGIN_DIR . 'includes/class-lgd-frontend.php';
require_once LGD_PLUGIN_DIR . 'includes/class-lgd-gamification.php';
require_once LGD_PLUGIN_DIR . 'includes/class-lgd-ads.php';
require_once LGD_PLUGIN_DIR . 'includes/class-lgd-subscriptions.php';

if ( ! class_exists( 'Local_Gamified_Directory' ) ) {
	/**
	* Main plugin class.
	*/
	class Local_Gamified_Directory {

		/**
		* Plugin version.
		*
		* @var string
		*/
		const VERSION = '0.1.0';

		/**
		* Plugin slug.
		*
		* @var string
		*/
		const SLUG = 'local-gamified-directory';

		/**
		 * Meta key prefix.
		 *
		 * @var string
		 */
		const META_PREFIX = '_lgd_';

		/**
		 * Meta key for the business listing owner.
		 *
		 * @var string
		 */
		const META_OWNER_USER = '_lgd_owner_user';

                /**
                * Singleton instance.
                *
                * @var Local_Gamified_Directory|null
                */
                private static $instance = null;

                /**
                 * Front-end handler instance.
                 *
                 * @var LGD_Frontend|null
                 */
                private $frontend = null;

                /**
                 * Admin tools instance.
                 *
                 * @var LGD_Admin|null
                 */
                private $admin = null;

                /**
                 * Activity tracker instance.
                 *
                 * @var LGD_Activity|null
                 */
                private $activity = null;

                /**
                 * Social login handler instance.
                 *
                 * @var LGD_Social_Login|null
                 */
                private $social_login = null;

                /**
                 * Gamification handler instance.
                 *
                 * @var LGD_Gamification|null
                 */
                private $gamification = null;

                /**
                 * Advertising system instance.
                 *
                 * @var LGD_Ads|null
                 */
                private $ads = null;

                /**
                 * Subscription handler instance.
                 *
                 * @var LGD_Subscriptions|null
                 */
                private $subscriptions = null;

                /**
                 * Counter used to generate unique help identifiers.
                 *
                 * @var int
                 */
                private $help_counter = 0;

		/**
		* Retrieve the singleton instance.
		*
		* @return Local_Gamified_Directory
		*/
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		* Constructor.
		*/
		private function __construct() {
			add_action( 'init', array( $this, 'register_post_types' ) );
			add_action( 'init', array( $this, 'register_taxonomies' ) );
			add_action( 'init', array( $this, 'register_meta' ) );
			add_action( 'init', array( __CLASS__, 'add_roles_and_capabilities' ), 5 );
                        add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
                        add_action( 'plugins_loaded', array( $this, 'bootstrap_modules' ), 20 );
                        add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
                        add_action( 'save_post_business_listing', array( $this, 'save_business_listing_meta' ), 10, 2 );
                        add_filter( 'wp_insert_post_data', array( $this, 'set_business_listing_pending' ), 10, 2 );
                        add_action( 'init', array( $this, 'maybe_schedule_events' ) );
                        add_action( 'lgd_expire_classifieds', array( $this, 'expire_classifieds' ) );
                        add_action( 'transition_post_status', array( $this, 'maybe_notify_business_owner_status_change' ), 10, 3 );
		}

		/**
		* Load plugin text domain for translations.
		*/
                public function load_textdomain() {
                        load_plugin_textdomain( 'local-gamified-directory', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
                }

                /**
                 * Instantiate supporting modules.
                 */
                public function bootstrap_modules() {
                        if ( null === $this->admin ) {
                                $this->admin = new LGD_Admin( $this );
                        }

                        if ( null === $this->activity ) {
                                $this->activity = new LGD_Activity( $this );
                        }

                        if ( null === $this->social_login ) {
                                $this->social_login = new LGD_Social_Login( $this );
                        }

                        if ( null === $this->gamification ) {
                                $this->gamification = new LGD_Gamification( $this );
                        }

                        if ( null === $this->frontend ) {
                                $this->frontend = new LGD_Frontend( $this );
                        }

                        if ( null === $this->ads ) {
                                $this->ads = new LGD_Ads( $this );
                        }

                        if ( null === $this->subscriptions ) {
                                $this->subscriptions = new LGD_Subscriptions( $this );
                        }
                }

                /**
                 * Retrieve the gamification handler.
                 *
                 * @return LGD_Gamification|null
                 */
                public function get_gamification() {
                        return $this->gamification;
                }

                /**
                 * Retrieve the admin handler.
                 *
                 * @return LGD_Admin|null
                 */
                public function get_admin() {
                        return $this->admin;
                }

                /**
                 * Retrieve the activity tracker.
                 *
                 * @return LGD_Activity|null
                 */
                public function get_activity() {
                        return $this->activity;
                }

                /**
                 * Retrieve the social login handler.
                 *
                 * @return LGD_Social_Login|null
                 */
                public function get_social_login() {
                        return $this->social_login;
                }

                /**
                 * Retrieve the advertising handler.
                 *
                 * @return LGD_Ads|null
                 */
                public function get_ads() {
                        return $this->ads;
                }

                /**
                 * Retrieve the subscriptions handler.
                 *
                 * @return LGD_Subscriptions|null
                 */
                public function get_subscriptions() {
                        return $this->subscriptions;
                }

                /**
                 * Retrieve a plugin setting.
                 *
                 * @param string $key     Setting key without prefix.
                 * @param mixed  $default Default value if unset.
                 *
                 * @return mixed
                 */
                /**
                 * Retrieve the list of toggleable features.
                 *
                 * @return array
                 */
                public function get_features() {
                        $features = array(
                                'business_submissions' => array(
                                        'label'       => __( 'Business submissions', 'local-gamified-directory' ),
                                        'description' => __( 'Allow business owners to create and manage business listings.', 'local-gamified-directory' ),
                                ),
                                'classifieds' => array(
                                        'label'       => __( 'Classifieds', 'local-gamified-directory' ),
                                        'description' => __( 'Enable community members to post classified listings.', 'local-gamified-directory' ),
                                ),
                                'ads' => array(
                                        'label'       => __( 'Advertising', 'local-gamified-directory' ),
                                        'description' => __( 'Allow businesses to create and run sponsored ads.', 'local-gamified-directory' ),
                                ),
                                'gamification' => array(
                                        'label'       => __( 'Gamification', 'local-gamified-directory' ),
                                        'description' => __( 'Award points, badges, and leaderboards for engagement.', 'local-gamified-directory' ),
                                ),
                                'leaderboard' => array(
                                        'label'       => __( 'Leaderboards', 'local-gamified-directory' ),
                                        'description' => __( 'Display leaderboards showcasing top community members.', 'local-gamified-directory' ),
                                ),
                                'claiming' => array(
                                        'label'       => __( 'Business claiming', 'local-gamified-directory' ),
                                        'description' => __( 'Allow users to request ownership of existing business listings.', 'local-gamified-directory' ),
                                ),
                                'frontend_dashboard' => array(
                                        'label'       => __( 'User dashboard', 'local-gamified-directory' ),
                                        'description' => __( 'Provide the front-end dashboard for managing submissions.', 'local-gamified-directory' ),
                                ),
                                'social_login' => array(
                                        'label'       => __( 'Social login', 'local-gamified-directory' ),
                                        'description' => __( 'Let users sign in with Google or Facebook.', 'local-gamified-directory' ),
                                ),
                                'activity_tracking' => array(
                                        'label'       => __( 'Activity tracking', 'local-gamified-directory' ),
                                        'description' => __( 'Record user activity for analytics and abuse detection.', 'local-gamified-directory' ),
                                ),
                        );

                        /**
                         * Filter the list of directory features.
                         *
                         * @since 0.1.0
                         *
                         * @param array $features Feature definitions.
                         */
                        return apply_filters( 'lgd_features', $features );
                }

                /**
                 * Determine if a feature is enabled globally.
                 *
                 * @param string $feature Feature key.
                 *
                 * @return bool
                 */
                public function is_feature_enabled( $feature ) {
                        $features = $this->get_features();

                        if ( ! isset( $features[ $feature ] ) ) {
                                return true;
                        }

                        $flags = get_option( LGD_Admin::OPTION_FEATURE_FLAGS, array() );

                        if ( isset( $flags[ $feature ] ) ) {
                                return (bool) $flags[ $feature ];
                        }

                        return true;
                }

                /**
                 * Check if a user currently has access to a feature.
                 *
                 * @param int    $user_id User ID.
                 * @param string $feature Feature key.
                 *
                 * @return bool
                 */
                public function user_has_feature_access( $user_id, $feature ) {
                        if ( ! $this->is_feature_enabled( $feature ) ) {
                                return false;
                        }

                        $user_id = absint( $user_id );
                        if ( $user_id <= 0 ) {
                                return false;
                        }

                        if ( $this->is_user_suspended( $user_id ) ) {
                                return false;
                        }

                        $disabled = (array) get_user_meta( $user_id, LGD_Admin::META_DISABLED_FEATURES, true );
                        if ( in_array( $feature, $disabled, true ) ) {
                                return false;
                        }

                        $enabled = (array) get_user_meta( $user_id, LGD_Admin::META_ENABLED_FEATURES, true );
                        if ( in_array( $feature, $enabled, true ) ) {
                                return true;
                        }

                        $role_map = get_option( LGD_Admin::OPTION_FEATURE_ROLE_MAP, array() );
                        if ( ! empty( $role_map[ $feature ] ) ) {
                                $user = get_userdata( $user_id );

                                if ( ! $user ) {
                                        return false;
                                }

                                if ( empty( array_intersect( (array) $user->roles, (array) $role_map[ $feature ] ) ) ) {
                                        return false;
                                }
                        }

                        return true;
                }

                /**
                 * Determine whether a user is currently suspended.
                 *
                 * @param int $user_id User ID.
                 *
                 * @return bool
                 */
                public function is_user_suspended( $user_id ) {
                        $status = get_user_meta( $user_id, LGD_Admin::META_ACCOUNT_STATUS, true );

                        if ( 'suspended' !== $status ) {
                                return false;
                        }

                        $until = get_user_meta( $user_id, LGD_Admin::META_SUSPENDED_UNTIL, true );

                        if ( ! empty( $until ) ) {
                                $timestamp = strtotime( $until . ' 23:59:59' );
                                if ( $timestamp && $timestamp <= current_time( 'timestamp', true ) ) {
                                        delete_user_meta( $user_id, LGD_Admin::META_ACCOUNT_STATUS );
                                        delete_user_meta( $user_id, LGD_Admin::META_SUSPENDED_UNTIL );
                                        delete_user_meta( $user_id, LGD_Admin::META_SUSPENSION_REASON );
                                        return false;
                                }
                        }

                        return true;
                }

                /**
                 * Retrieve a plugin setting.
                 *
                 * @param string $key     Setting key without prefix.
                 * @param mixed  $default Default value if unset.
                 *
                 * @return mixed
                 */
                public function get_setting( $key, $default = false ) {
                        $value = get_option( 'lgd_' . $key, null );

                        if ( null === $value || false === $value ) {
                                return $default;
                        }

                        return $value;
                }

                /**
                 * Update a plugin setting.
                 *
                 * @param string $key   Setting key without prefix.
                 * @param mixed  $value Value to store.
                 */
                public function update_setting( $key, $value ) {
                        update_option( 'lgd_' . $key, $value );
                }

                /**
                 * Retrieve point-awarding actions for configuration screens.
                 *
                 * @return array
                 */
                public function get_point_actions() {
                        $actions = array(
                                'registration'       => array(
                                        'label'       => __( 'Registration bonus', 'local-gamified-directory' ),
                                        'description' => __( 'Awarded when a new user creates an account.', 'local-gamified-directory' ),
                                        'default'     => 50,
                                ),
                                'daily_login'        => array(
                                        'label'       => __( 'Daily login', 'local-gamified-directory' ),
                                        'description' => __( 'Granted the first time a user logs in each day.', 'local-gamified-directory' ),
                                        'default'     => 5,
                                ),
                                'forum_topic'        => array(
                                        'label'       => __( 'New forum topic', 'local-gamified-directory' ),
                                        'description' => __( 'Points for creating a new discussion topic in the forums.', 'local-gamified-directory' ),
                                        'default'     => 5,
                                ),
                                'forum_reply'        => array(
                                        'label'       => __( 'Forum reply', 'local-gamified-directory' ),
                                        'description' => __( 'Granted when a user replies to an existing topic.', 'local-gamified-directory' ),
                                        'default'     => 2,
                                ),
                                'classified_publish' => array(
                                        'label'       => __( 'Publish classified listing', 'local-gamified-directory' ),
                                        'description' => __( 'Awarded when a new classified listing is approved.', 'local-gamified-directory' ),
                                        'default'     => 5,
                                ),
                                'business_publish'   => array(
                                        'label'       => __( 'Publish business listing', 'local-gamified-directory' ),
                                        'description' => __( 'Granted when a business profile goes live.', 'local-gamified-directory' ),
                                        'default'     => 10,
                                ),
                        );

                        /**
                         * Filter the list of configurable point actions.
                         *
                         * @since 0.1.0
                         *
                         * @param array $actions Action configuration data.
                         */
                        return apply_filters( 'lgd_point_actions', $actions );
                }

                /**
                 * Retrieve the default point values for each action.
                 *
                 * @return array
                 */
                public function get_default_points_rules() {
                        $defaults = array();

                        foreach ( $this->get_point_actions() as $key => $action ) {
                                $defaults[ $key ] = isset( $action['default'] ) ? (int) $action['default'] : 0;
                        }

                        return $defaults;
                }

                /**
                 * Retrieve the default rank thresholds used for badges.
                 *
                 * @return array
                 */
                public function get_default_rank_thresholds() {
                        $thresholds = array(
                                1000 => 'Expert',
                                500  => 'Intermediate',
                                0    => 'Beginner',
                        );

                        /**
                         * Filter the default rank thresholds.
                         *
                         * @since 0.1.0
                         *
                         * @param array $thresholds Rank thresholds keyed by minimum point requirement.
                         */
                        return apply_filters( 'lgd_default_rank_thresholds', $thresholds );
                }

		/**
		 * Retrieve contextual help definitions keyed by form element.
		 *
		 * @return array
		 */
        public function get_help_contexts() {
                $contexts = array(
                        'business_claim_listing' => array(
                                'label'       => __( 'Business claim selector', 'local-gamified-directory' ),
                                'description' => __( 'Displayed next to the claim dropdown on the business submission form.', 'local-gamified-directory' ),
                                'default'     => __( 'Choose an existing listing if you are claiming it. Leave this set to “Create a new listing” for brand-new businesses.', 'local-gamified-directory' ),
                        ),
                        'business_claim_notes' => array(
                                'label'       => __( 'Claim verification notes', 'local-gamified-directory' ),
                                'description' => __( 'Shown alongside the verification textarea on the business submission form.', 'local-gamified-directory' ),
                                'default'     => __( 'Provide ownership details such as a business email or phone number so our team can verify your request quickly.', 'local-gamified-directory' ),
                        ),
                        'business_name' => array(
                                'label'       => __( 'Business name', 'local-gamified-directory' ),
                                'description' => __( 'Appears beside the Business Name field.', 'local-gamified-directory' ),
                                'default'     => __( 'Enter the exact business name customers recognise. This is displayed on your public profile.', 'local-gamified-directory' ),
                        ),
                        'business_description' => array(
                                'label'       => __( 'Business description', 'local-gamified-directory' ),
                                'description' => __( 'Appears beside the description textarea on the business form.', 'local-gamified-directory' ),
                                'default'     => __( 'Share a concise overview of your services, specialties, or mission to help visitors understand what you offer.', 'local-gamified-directory' ),
                        ),
                        'business_address' => array(
                                'label'       => __( 'Business address', 'local-gamified-directory' ),
                                'description' => __( 'Shown next to the address field on the business form.', 'local-gamified-directory' ),
                                'default'     => __( 'Provide the street address visitors should use to find your location. Include suite numbers if applicable.', 'local-gamified-directory' ),
                        ),
                        'business_phone' => array(
                                'label'       => __( 'Business phone', 'local-gamified-directory' ),
                                'description' => __( 'Displayed by the phone input on the business form.', 'local-gamified-directory' ),
                                'default'     => __( 'List the primary phone number your team monitors so customers can contact you quickly.', 'local-gamified-directory' ),
                        ),
                        'business_email' => array(
                                'label'       => __( 'Business contact email', 'local-gamified-directory' ),
                                'description' => __( 'Displayed by the contact email field.', 'local-gamified-directory' ),
                                'default'     => __( 'Use an inbox that is actively monitored for customer enquiries and listing notifications.', 'local-gamified-directory' ),
                        ),
                        'business_hours' => array(
                                'label'       => __( 'Operating hours', 'local-gamified-directory' ),
                                'description' => __( 'Displayed beside the hours textarea.', 'local-gamified-directory' ),
                                'default'     => __( 'List your opening hours or appointment times. Mention holiday or seasonal changes if they apply.', 'local-gamified-directory' ),
                        ),
                        'business_place_id' => array(
                                'label'       => __( 'Google Place ID', 'local-gamified-directory' ),
                                'description' => __( 'Appears beside the Google Place ID field.', 'local-gamified-directory' ),
                                'default'     => __( 'Paste the Google Place ID so we can connect maps, reviews, and search results to this profile.', 'local-gamified-directory' ),
                        ),
                        'business_category' => array(
                                'label'       => __( 'Business categories', 'local-gamified-directory' ),
                                'description' => __( 'Shown near the category multi-select.', 'local-gamified-directory' ),
                                'default'     => __( 'Choose every category that accurately describes your business so it appears in the right searches.', 'local-gamified-directory' ),
                        ),
                        'business_region' => array(
                                'label'       => __( 'Business regions', 'local-gamified-directory' ),
                                'description' => __( 'Displayed next to the region selector.', 'local-gamified-directory' ),
                                'default'     => __( 'Select the neighbourhoods or regions you serve to help locals find you faster.', 'local-gamified-directory' ),
                        ),
                        'business_logo' => array(
                                'label'       => __( 'Business logo upload', 'local-gamified-directory' ),
                                'description' => __( 'Appears by the logo upload field on the business form.', 'local-gamified-directory' ),
                                'default'     => __( 'Upload a clear PNG or JPG logo. Square images display best across the directory.', 'local-gamified-directory' ),
                        ),
                        'classified_title' => array(
                                'label'       => __( 'Classified title', 'local-gamified-directory' ),
                                'description' => __( 'Displayed beside the classified title field.', 'local-gamified-directory' ),
                                'default'     => __( 'Write a short, searchable title that summarises what you are offering or requesting.', 'local-gamified-directory' ),
                        ),
                        'classified_description' => array(
                                'label'       => __( 'Classified description', 'local-gamified-directory' ),
                                'description' => __( 'Appears next to the classified description textarea.', 'local-gamified-directory' ),
                                'default'     => __( 'Describe the item or opportunity, noting condition, inclusions, and any important terms.', 'local-gamified-directory' ),
                        ),
                        'classified_category' => array(
                                'label'       => __( 'Classified categories', 'local-gamified-directory' ),
                                'description' => __( 'Displayed next to the classifieds category selector.', 'local-gamified-directory' ),
                                'default'     => __( 'Pick the categories that best match your listing so it appears to the right audience.', 'local-gamified-directory' ),
                        ),
                        'classified_price' => array(
                                'label'       => __( 'Classified price', 'local-gamified-directory' ),
                                'description' => __( 'Appears by the price input.', 'local-gamified-directory' ),
                                'default'     => __( 'Enter your price or mention if it is free or negotiable to set expectations.', 'local-gamified-directory' ),
                        ),
                        'classified_location' => array(
                                'label'       => __( 'Classified location', 'local-gamified-directory' ),
                                'description' => __( 'Displayed next to the location field.', 'local-gamified-directory' ),
                                'default'     => __( 'Share where the item is located or the area you can service or deliver to.', 'local-gamified-directory' ),
                        ),
                        'classified_contact' => array(
                                'label'       => __( 'Classified contact method', 'local-gamified-directory' ),
                                'description' => __( 'Appears by the contact field.', 'local-gamified-directory' ),
                                'default'     => __( 'Tell interested people how to reach you—email, phone, or a preferred messaging app.', 'local-gamified-directory' ),
                        ),
                        'classified_image' => array(
                                'label'       => __( 'Classified image upload', 'local-gamified-directory' ),
                                'description' => __( 'Displayed beside the classified image field.', 'local-gamified-directory' ),
                                'default'     => __( 'Upload a bright, well-lit image. Listings with photos receive more views and enquiries.', 'local-gamified-directory' ),
                        ),
                        'dashboard_summary' => array(
                                'label'       => __( 'Dashboard summary panel', 'local-gamified-directory' ),
                                'description' => __( 'Shown next to the Account Summary heading on the user dashboard.', 'local-gamified-directory' ),
                                'default'     => __( 'Keep an eye on your points, badges, and premium status to see how participation is rewarded.', 'local-gamified-directory' ),
                        ),
                        'dashboard_businesses' => array(
                                'label'       => __( 'Dashboard business listings section', 'local-gamified-directory' ),
                                'description' => __( 'Displayed near the business listings dashboard section heading.', 'local-gamified-directory' ),
                                'default'     => __( 'Review the status of each listing, edit details, or launch new promotions from here.', 'local-gamified-directory' ),
                        ),
                        'dashboard_classifieds' => array(
                                'label'       => __( 'Dashboard classifieds section', 'local-gamified-directory' ),
                                'description' => __( 'Shown near the classifieds dashboard heading.', 'local-gamified-directory' ),
                                'default'     => __( 'Manage active and expired classifieds, renew successful posts, or mark items as sold.', 'local-gamified-directory' ),
                        ),
                        'dashboard_ads' => array(
                                'label'       => __( 'Dashboard ads section', 'local-gamified-directory' ),
                                'description' => __( 'Displayed next to the sponsored ads heading in the dashboard.', 'local-gamified-directory' ),
                                'default'     => __( 'Track when each sponsored ad runs and how many tokens you invested in the campaign.', 'local-gamified-directory' ),
                        ),
                        'ad_title' => array(
                                'label'       => __( 'Ad title', 'local-gamified-directory' ),
                                'description' => __( 'Appears by the ad title field.', 'local-gamified-directory' ),
                                'default'     => __( 'Give the ad a headline that attracts attention and helps you identify the campaign later.', 'local-gamified-directory' ),
                        ),
                        'ad_content' => array(
                                'label'       => __( 'Ad content', 'local-gamified-directory' ),
                                'description' => __( 'Displayed by the ad content textarea.', 'local-gamified-directory' ),
                                'default'     => __( 'Write a short message that highlights your offer and includes a clear call to action.', 'local-gamified-directory' ),
                        ),
                        'ad_url' => array(
                                'label'       => __( 'Ad destination URL', 'local-gamified-directory' ),
                                'description' => __( 'Appears beside the ad URL field.', 'local-gamified-directory' ),
                                'default'     => __( 'Paste the link people should visit when clicking your ad. It can be your listing or any landing page.', 'local-gamified-directory' ),
                        ),
                        'ad_category' => array(
                                'label'       => __( 'Ad target category', 'local-gamified-directory' ),
                                'description' => __( 'Displayed by the ad category selector.', 'local-gamified-directory' ),
                                'default'     => __( 'Target a specific business category for focused exposure or choose all categories for a wider reach.', 'local-gamified-directory' ),
                        ),
                        'ad_region' => array(
                                'label'       => __( 'Ad target region', 'local-gamified-directory' ),
                                'description' => __( 'Appears next to the ad region selector.', 'local-gamified-directory' ),
                                'default'     => __( 'Limit your campaign to certain regions if you only serve specific neighbourhoods.', 'local-gamified-directory' ),
                        ),
                        'ad_duration' => array(
                                'label'       => __( 'Ad duration', 'local-gamified-directory' ),
                                'description' => __( 'Displayed next to the duration input.', 'local-gamified-directory' ),
                                'default'     => __( 'Choose how many days the ad should run. Tokens are deducted based on the duration you select.', 'local-gamified-directory' ),
                        ),
                        'ad_image' => array(
                                'label'       => __( 'Ad image upload', 'local-gamified-directory' ),
                                'description' => __( 'Appears by the ad image field.', 'local-gamified-directory' ),
                                'default'     => __( 'Upload a high-quality promotional image to boost engagement. Landscape images work best.', 'local-gamified-directory' ),
                        ),
                );

                /**
                 * Filter the contextual help definitions.
                 *
                 * @since 0.1.0
                 *
                 * @param array $contexts Registered help contexts.
                 */
                return apply_filters( 'lgd_help_contexts', $contexts );
        }

        /**
         * Retrieve default help text keyed by context.
         *
         * @return array
         */
        public function get_default_help_texts() {
                $defaults = array();

                foreach ( $this->get_help_contexts() as $key => $context ) {
                        $defaults[ $key ] = isset( $context['default'] ) ? $context['default'] : '';
                }

                return $defaults;
        }

        /**
         * Retrieve effective help text values including overrides.
         *
         * @return array
         */
        public function get_help_texts() {
                $texts   = $this->get_default_help_texts();
                $stored  = get_option( LGD_Admin::OPTION_HELP_TEXTS, array() );

                if ( is_array( $stored ) ) {
                        foreach ( $stored as $key => $value ) {
                                if ( ! array_key_exists( $key, $texts ) ) {
                                        continue;
                                }

                                $value = is_string( $value ) ? trim( $value ) : '';

                                if ( '' === $value ) {
                                        continue;
                                }

                                $texts[ $key ] = wp_kses_post( $value );
                        }
                }

                /**
                 * Filter the resolved help text values.
                 *
                 * @since 0.1.0
                 *
                 * @param array $texts Help text keyed by context.
                 */
                return apply_filters( 'lgd_help_texts', $texts );
        }

        /**
         * Retrieve a single help text value by key.
         *
         * @param string $key Help context key.
         * @return string
         */
        public function get_help_text( $key ) {
                $texts = $this->get_help_texts();

                return isset( $texts[ $key ] ) ? $texts[ $key ] : '';
        }

        /**
         * Determine whether contextual help is enabled.
         *
         * @return bool
         */
        public function is_help_enabled() {
                $enabled = (bool) get_option( LGD_Admin::OPTION_HELP_ENABLED, 1 );

                /**
                 * Filter whether contextual help is active.
                 *
                 * @since 0.1.0
                 *
                 * @param bool $enabled True when contextual help should display.
                 */
                return (bool) apply_filters( 'lgd_help_enabled', $enabled );
        }

        /**
         * Build tooltip markup for a contextual help key.
         *
         * @param string $key Help context key.
         * @return array{id:string,html:string}
         */
        public function get_help_tooltip( $key ) {
                $empty = array(
                        'id'   => '',
                        'html' => '',
                );

                if ( ! $this->is_help_enabled() ) {
                        return $empty;
                }

                $text = $this->get_help_text( $key );

                if ( '' === $text || '' === trim( wp_strip_all_tags( $text ) ) ) {
                        return $empty;
                }

                $this->help_counter++;
                $id    = 'lgd-help-' . sanitize_html_class( $key ) . '-' . $this->help_counter;
                $html  = sprintf(
                        '<span class="lgd-help" data-lgd-open="false"><button type="button" class="lgd-help__icon" aria-expanded="false" aria-controls="%1$s"><span aria-hidden="true">?</span><span class="lgd-screen-reader-text">%2$s</span></button><span class="lgd-help__tooltip" role="tooltip" id="%1$s">%3$s</span></span>',
                        esc_attr( $id ),
                        esc_html__( 'Toggle help information', 'local-gamified-directory' ),
                        wp_kses_post( $text )
                );

                /**
                 * Filter the rendered tooltip HTML for a help context.
                 *
                 * @since 0.1.0
                 *
                 * @param string $html Tooltip markup.
                 * @param string $key  Help context key.
                 * @param string $text Help text used inside the tooltip.
                 * @param string $id   Tooltip DOM id attribute.
                 */
                $html = apply_filters( 'lgd_help_tooltip_html', $html, $key, $text, $id );

                /**
                 * Filter the tooltip payload returned for a help context.
                 *
                 * @since 0.1.0
                 *
                 * @param array  $tooltip Tooltip data array with id/html keys.
                 * @param string $key     Help context key.
                 * @param string $text    Help text used inside the tooltip.
                 */
                return apply_filters(
                        'lgd_help_tooltip',
                        array(
                                'id'   => $id,
                                'html' => $html,
                        ),
                        $key,
                        $text
                );
        }

        /**
         * Retrieve the configured support message.
         *
         * @return string
         */
        public function get_support_message() {
                $message = get_option( LGD_Admin::OPTION_SUPPORT_MESSAGE, '' );
                $message = is_string( $message ) ? trim( $message ) : '';

                if ( '' === $message ) {
                        return '';
                }

                return $message;
        }

        /**
         * Retrieve the configured support link.
         *
         * @return string
         */
        public function get_support_link() {
                $link = get_option( LGD_Admin::OPTION_SUPPORT_LINK, '' );
                $link = is_string( $link ) ? trim( $link ) : '';

                if ( '' === $link ) {
                        return '';
                }

                return esc_url_raw( $link );
        }

        /**
         * Generate the HTML support callout shown beneath forms.
         *
         * @return string
         */
        public function get_support_callout_html() {
                $message = $this->get_support_message();
                $link    = $this->get_support_link();

                if ( '' === $message && '' === $link ) {
                        return '';
                }

                $parts = array();

                if ( '' !== $message ) {
                        $parts[] = '<p class="lgd-support-callout__text">' . esc_html( $message ) . '</p>';
                }

                if ( '' !== $link ) {
                        $parts[] = '<p class="lgd-support-callout__action"><a class="lgd-support-callout__link" href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Open help center', 'local-gamified-directory' ) . '</a></p>';
                }

                if ( empty( $parts ) ) {
                        return '';
                }

                $html = '<div class="lgd-support-callout">' . implode( '', $parts ) . '</div>';

                /**
                 * Filter the support callout markup.
                 *
                 * @since 0.1.0
                 *
                 * @param string $html    Rendered support callout HTML.
                 * @param string $message Support message text.
                 * @param string $link    Support link URL.
                 */
                return apply_filters( 'lgd_support_callout_html', $html, $message, $link );
        }

                /**
                 * Send a notification to the site administrator.
                 *
                 * @param string $subject Email subject.
                 * @param string $message Email message body.
                 */
                public function notify_admin( $subject, $message ) {
                        $admin_email = get_option( 'admin_email' );

                        if ( empty( $admin_email ) ) {
                                return;
                        }

                        $this->send_email( $admin_email, $subject, $message );
                }

                /**
                 * Send an email with plugin defaults applied.
                 *
                 * @param string       $to      Recipient email address.
                 * @param string       $subject Email subject.
                 * @param string       $message Email body.
                 * @param array|string $headers Optional email headers.
                 */
                public function send_email( $to, $subject, $message, $headers = array() ) {
                        if ( empty( $to ) || empty( $subject ) || empty( $message ) ) {
                                return;
                        }

                        $default_headers = array( 'Content-Type: text/plain; charset=UTF-8' );

                        if ( empty( $headers ) ) {
                                $headers = $default_headers;
                        }

                        /**
                         * Filter the email headers before the message is sent.
                         *
                         * @since 0.1.0
                         *
                         * @param array|string $headers Email headers.
                         * @param string       $to      Recipient email address.
                         * @param string       $subject Email subject.
                         */
                        $headers = apply_filters( 'lgd_email_headers', $headers, $to, $subject );

                        /**
                         * Allow the email subject to be filtered prior to sending.
                         *
                         * @since 0.1.0
                         *
                         * @param string $subject Email subject.
                         * @param string $to      Recipient email address.
                         */
                        $subject = apply_filters( 'lgd_email_subject', $subject, $to );

                        /**
                         * Allow the email message body to be filtered prior to sending.
                         *
                         * @since 0.1.0
                         *
                         * @param string $message Email message body.
                         * @param string $to      Recipient email address.
                         * @param string $subject Email subject.
                         */
                        $message = apply_filters( 'lgd_email_message', $message, $to, $subject );

                        wp_mail( $to, $subject, $message, $headers );
                }

                /**
                 * Schedule recurring events for maintenance tasks.
                 *
                 * @param bool $force Whether to clear existing events first.
                 */
                public function maybe_schedule_events( $force = false ) {
                        if ( $force ) {
                                wp_clear_scheduled_hook( 'lgd_expire_classifieds' );
                                wp_clear_scheduled_hook( 'lgd_purge_activity_logs' );
                        }

                        if ( ! wp_next_scheduled( 'lgd_expire_classifieds' ) ) {
                                wp_schedule_event( time(), 'daily', 'lgd_expire_classifieds' );
                        }

                        if ( ! wp_next_scheduled( 'lgd_purge_activity_logs' ) ) {
                                wp_schedule_event( time(), 'daily', 'lgd_purge_activity_logs' );
                        }
                }

                /**
                 * Expire classifieds whose end date has passed.
                 */
                public function expire_classifieds() {
                        $this->send_classified_expiration_reminders();

                        $query = new WP_Query(
                                array(
                                        'post_type'      => 'classified_listing',
                                        'post_status'    => array( 'publish', 'pending' ),
                                        'posts_per_page' => 100,
                                        'meta_query'     => array(
                                                array(
                                                        'key'     => self::META_PREFIX . 'expires_at',
                                                        'value'   => gmdate( 'Y-m-d H:i:s' ),
                                                        'compare' => '<=',
                                                        'type'    => 'DATETIME',
                                                ),
                                        ),
                                        'fields'         => 'ids',
                                )
                        );

                        if ( empty( $query->posts ) ) {
                                return;
                        }

                        foreach ( $query->posts as $post_id ) {
                                wp_update_post(
                                        array(
                                                'ID'          => $post_id,
                                                'post_status' => 'draft',
                                        )
                                );

                                $author_id = (int) get_post_field( 'post_author', $post_id );

                                if ( ! $author_id ) {
                                        continue;
                                }

                                $user = get_userdata( $author_id );

                                if ( ! $user || empty( $user->user_email ) ) {
                                        continue;
                                }

                                $blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
                                $title    = get_the_title( $post_id );

                                $subject = sprintf(
                                        /* translators: 1: Site name, 2: Classified listing title. */
                                        __( '[%1$s] Your classified "%2$s" has expired', 'local-gamified-directory' ),
                                        $blogname,
                                        $title
                                );

                                $message = sprintf(
                                        /* translators: 1: User display name, 2: Classified listing title, 3: Site name. */
                                        __(
'Hi %1$s,

Your classified listing "%2$s" has expired and is no longer visible on %3$s. Visit your dashboard to renew or repost the listing when you are ready.

Thank you,
%3$s',
                                                'local-gamified-directory'
                                        ),
                                        $user->display_name,
                                        $title,
                                        $blogname
                                );

                                $this->send_email( $user->user_email, $subject, $message );

                                delete_post_meta( $post_id, self::META_PREFIX . 'expiration_reminder_sent' );
                        }
                }

                /**
                 * Send reminders for classifieds that are approaching expiration.
                 */
                private function send_classified_expiration_reminders() {
                        $reminder_days = (int) apply_filters( 'lgd_classified_expiration_reminder_days', 5 );

                        if ( $reminder_days <= 0 ) {
                                return;
                        }

                        $now            = time();
                        $reminder_limit = $now + ( $reminder_days * DAY_IN_SECONDS );

                        $query = new WP_Query(
                                array(
                                        'post_type'      => 'classified_listing',
                                        'post_status'    => 'publish',
                                        'posts_per_page' => 100,
                                        'fields'         => 'ids',
                                        'meta_query'     => array(
                                                'relation' => 'AND',
                                                array(
                                                        'key'     => self::META_PREFIX . 'expires_at',
                                                        'value'   => gmdate( 'Y-m-d H:i:s', $now ),
                                                        'compare' => '>=',
                                                        'type'    => 'DATETIME',
                                                ),
                                                array(
                                                        'key'     => self::META_PREFIX . 'expires_at',
                                                        'value'   => gmdate( 'Y-m-d H:i:s', $reminder_limit ),
                                                        'compare' => '<=',
                                                        'type'    => 'DATETIME',
                                                ),
                                                array(
                                                        'key'     => self::META_PREFIX . 'expiration_reminder_sent',
                                                        'compare' => 'NOT EXISTS',
                                                ),
                                        ),
                                )
                        );

                        if ( empty( $query->posts ) ) {
                                return;
                        }

                        foreach ( $query->posts as $post_id ) {
                                $author_id = (int) get_post_field( 'post_author', $post_id );

                                if ( ! $author_id ) {
                                        continue;
                                }

                                $user = get_userdata( $author_id );

                                if ( ! $user || empty( $user->user_email ) ) {
                                        continue;
                                }

                                $blogname   = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
                                $title      = get_the_title( $post_id );
                                $expires_at = get_post_meta( $post_id, self::META_PREFIX . 'expires_at', true );
                                $timestamp  = $expires_at ? mysql2date( 'U', $expires_at, false ) : false;
                                $expiry     = $timestamp ? wp_date( get_option( 'date_format' ), $timestamp ) : '';

                                $subject = sprintf(
                                        /* translators: 1: Site name, 2: Classified listing title. */
                                        __( '[%1$s] Reminder: Your classified "%2$s" expires soon', 'local-gamified-directory' ),
                                        $blogname,
                                        $title
                                );

                                $message_template = __(
'Hi %1$s,

This is a friendly reminder that your classified listing "%2$s" will expire on %3$s. Visit your dashboard to update or renew the listing if you would like it to remain visible.

Thank you,
%4$s',
                                        'local-gamified-directory'
                                );

                                $message = sprintf(
                                        $message_template,
                                        $user->display_name,
                                        $title,
                                        $expiry,
                                        $blogname
                                );

                                $this->send_email( $user->user_email, $subject, $message );

                                update_post_meta( $post_id, self::META_PREFIX . 'expiration_reminder_sent', 1 );
                        }
                }

                /**
                 * Notify business owners when their listing status changes.
                 *
                 * @param string  $new_status The new post status.
                 * @param string  $old_status The previous post status.
                 * @param WP_Post $post       Post object.
                 */
                public function maybe_notify_business_owner_status_change( $new_status, $old_status, $post ) {
                        if ( $new_status === $old_status || ! $post instanceof WP_Post || 'business_listing' !== $post->post_type ) {
                                return;
                        }

                        if ( in_array( $new_status, array( 'inherit', 'auto-draft', 'revision' ), true ) ) {
                                return;
                        }

                        $owner_id = (int) get_post_meta( $post->ID, self::META_OWNER_USER, true );

                        if ( ! $owner_id ) {
                                $owner_id = (int) $post->post_author;
                        }

                        if ( ! $owner_id ) {
                                return;
                        }

                        $user = get_userdata( $owner_id );

                        if ( ! $user || empty( $user->user_email ) ) {
                                return;
                        }

                        $blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
                        $title    = get_the_title( $post );
                        $link     = get_permalink( $post );

                        if ( 'publish' === $new_status && 'publish' !== $old_status ) {
                                $subject = sprintf(
                                        /* translators: 1: Site name, 2: Business listing title. */
                                        __( '[%1$s] Your business "%2$s" has been approved', 'local-gamified-directory' ),
                                        $blogname,
                                        $title
                                );

                                $message_template = __(
'Hi %1$s,

Great news! Your business listing "%2$s" has been approved and is now live on %3$s.

You can view your listing here: %4$s

Thank you,
%3$s',
                                        'local-gamified-directory'
                                );

                                $message = sprintf(
                                        $message_template,
                                        $user->display_name,
                                        $title,
                                        $blogname,
                                        $link
                                );

                                $this->send_email( $user->user_email, $subject, $message );
                                return;
                        }

                        $rejected_statuses = array( 'draft', 'trash', 'pending' );

                        if ( in_array( $old_status, array( 'pending', 'publish' ), true ) && in_array( $new_status, $rejected_statuses, true ) ) {
                                $status_object = get_post_status_object( $new_status );
                                $status_label  = $status_object ? $status_object->label : $new_status;

                                $subject = sprintf(
                                        /* translators: 1: Site name, 2: Business listing title. */
                                        __( '[%1$s] Your business "%2$s" needs updates', 'local-gamified-directory' ),
                                        $blogname,
                                        $title
                                );

                                $message_template = __(
'Hi %1$s,

Your business listing "%2$s" has been moved to the "%3$s" state. Please review your information and make any updates needed before resubmitting.

You can access the listing here: %4$s

Thank you,
%5$s',
                                        'local-gamified-directory'
                                );

                                $message = sprintf(
                                        $message_template,
                                        $user->display_name,
                                        $title,
                                        $status_label,
                                        $link,
                                        $blogname
                                );

                                $this->send_email( $user->user_email, $subject, $message );
                        }
                }

                /**
                * Plugin activation callback.
                */
                public static function activate() {
                        self::add_roles_and_capabilities();
                        self::instance()->register_post_types();
                        self::instance()->register_taxonomies();
                        self::instance()->maybe_schedule_events( true );
                        LGD_Admin::activate();
                        LGD_Activity::activate();
                        LGD_Gamification::activate();
                        LGD_Ads::activate();
                        LGD_Subscriptions::activate();
                        LGD_Social_Login::activate();
                        flush_rewrite_rules();
                }

                /**
                * Plugin deactivation callback.
                */
                public static function deactivate() {
                        wp_clear_scheduled_hook( 'lgd_expire_classifieds' );
                        wp_clear_scheduled_hook( 'lgd_purge_activity_logs' );
                        flush_rewrite_rules();
                }

		/**
		* Register custom post types.
		*/
		public function register_post_types() {
			$this->register_business_listing_post_type();
			$this->register_classified_listing_post_type();
		}

		/**
		* Register custom taxonomies.
		*/
		public function register_taxonomies() {
			$this->register_business_taxonomies();
			$this->register_classified_taxonomies();
		}

		/**
		 * Register post meta used by the plugin.
		 */
		public function register_meta() {
			$business_meta = array(
				self::META_PREFIX . 'address' => array(
					'type'              => 'string',
					'description'       => __( 'Street address for the business.', 'local-gamified-directory' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
				self::META_PREFIX . 'phone' => array(
					'type'              => 'string',
					'description'       => __( 'Primary phone number for the business.', 'local-gamified-directory' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
				self::META_PREFIX . 'contact_email' => array(
					'type'              => 'string',
					'description'       => __( 'Contact email address for the business.', 'local-gamified-directory' ),
					'sanitize_callback' => 'sanitize_email',
				),
				self::META_PREFIX . 'hours' => array(
					'type'              => 'string',
					'description'       => __( 'Operating hours for the business.', 'local-gamified-directory' ),
					'sanitize_callback' => 'sanitize_textarea_field',
				),
                                self::META_PREFIX . 'google_place_id' => array(
                                        'type'              => 'string',
                                        'description'       => __( 'Google Place ID tied to the business.', 'local-gamified-directory' ),
                                        'sanitize_callback' => 'sanitize_text_field',
                                ),
                                self::META_PREFIX . 'ad_free' => array(
                                        'type'              => 'boolean',
                                        'description'       => __( 'Whether ads should be hidden for this listing.', 'local-gamified-directory' ),
                                        'sanitize_callback' => array( $this, 'sanitize_boolean_meta' ),
                                ),
				self::META_OWNER_USER => array(
					'type'              => 'integer',
					'description'       => __( 'User ID for the business owner.', 'local-gamified-directory' ),
					'sanitize_callback' => 'absint',
				),
			);

			foreach ( $business_meta as $key => $args ) {
				$defaults = array(
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => array( $this, 'business_meta_auth_callback' ),
				);

				register_post_meta( 'business_listing', $key, array_merge( $defaults, $args ) );
			}

			$classified_meta = array(
				self::META_PREFIX . 'price' => array(
					'type'              => 'string',
					'description'       => __( 'Price associated with a classified listing.', 'local-gamified-directory' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
				self::META_PREFIX . 'contact_method' => array(
					'type'              => 'string',
					'description'       => __( 'Preferred contact method for a classified listing.', 'local-gamified-directory' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
				self::META_PREFIX . 'location' => array(
					'type'              => 'string',
					'description'       => __( 'Location information for a classified listing.', 'local-gamified-directory' ),
					'sanitize_callback' => 'sanitize_text_field',
				),
                                self::META_PREFIX . 'expires_at' => array(
                                        'type'              => 'string',
                                        'description'       => __( 'Expiration timestamp for a classified listing.', 'local-gamified-directory' ),
                                        'sanitize_callback' => array( $this, 'sanitize_datetime_meta' ),
                                ),
                                self::META_PREFIX . 'expiration_reminder_sent' => array(
                                        'type'              => 'boolean',
                                        'description'       => __( 'Whether an expiration reminder has been sent.', 'local-gamified-directory' ),
                                        'sanitize_callback' => array( $this, 'sanitize_boolean_meta' ),
                                ),
                        );

			foreach ( $classified_meta as $key => $args ) {
				$defaults = array(
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => array( $this, 'classified_meta_auth_callback' ),
				);

				register_post_meta( 'classified_listing', $key, array_merge( $defaults, $args ) );
			}
		}

		/**
		* Register the business listing post type.
		*/
		private function register_business_listing_post_type() {
			$labels = array(
				'name'                  => _x( 'Business Listings', 'Post type general name', 'local-gamified-directory' ),
				'singular_name'         => _x( 'Business Listing', 'Post type singular name', 'local-gamified-directory' ),
				'menu_name'             => _x( 'Business Listings', 'Admin Menu text', 'local-gamified-directory' ),
				'name_admin_bar'        => _x( 'Business Listing', 'Add New on Toolbar', 'local-gamified-directory' ),
				'add_new'               => __( 'Add New', 'local-gamified-directory' ),
				'add_new_item'          => __( 'Add New Business Listing', 'local-gamified-directory' ),
				'new_item'              => __( 'New Business Listing', 'local-gamified-directory' ),
				'edit_item'             => __( 'Edit Business Listing', 'local-gamified-directory' ),
				'view_item'             => __( 'View Business Listing', 'local-gamified-directory' ),
				'all_items'             => __( 'All Business Listings', 'local-gamified-directory' ),
				'search_items'          => __( 'Search Business Listings', 'local-gamified-directory' ),
				'parent_item_colon'     => __( 'Parent Business Listings:', 'local-gamified-directory' ),
				'not_found'             => __( 'No business listings found.', 'local-gamified-directory' ),
				'not_found_in_trash'    => __( 'No business listings found in Trash.', 'local-gamified-directory' ),
				'featured_image'        => _x( 'Business Logo', 'Overrides the “Featured Image” phrase', 'local-gamified-directory' ),
				'set_featured_image'    => _x( 'Set business logo', 'Overrides the “Set featured image” phrase', 'local-gamified-directory' ),
				'remove_featured_image' => _x( 'Remove business logo', 'Overrides the “Remove featured image” phrase', 'local-gamified-directory' ),
				'use_featured_image'    => _x( 'Use as business logo', 'Overrides the “Use as featured image” phrase', 'local-gamified-directory' ),
				'archives'              => _x( 'Business Listing archives', 'The post type archive label', 'local-gamified-directory' ),
				'insert_into_item'      => _x( 'Insert into business listing', 'Overrides the “Insert into post” phrase', 'local-gamified-directory' ),
				'uploaded_to_this_item' => _x( 'Uploaded to this business listing', 'Overrides the “Uploaded to this post” phrase', 'local-gamified-directory' ),
				'filter_items_list'     => _x( 'Filter business listings list', 'Screen reader text', 'local-gamified-directory' ),
				'items_list_navigation' => _x( 'Business listings list navigation', 'Screen reader text', 'local-gamified-directory' ),
				'items_list'            => _x( 'Business listings list', 'Screen reader text', 'local-gamified-directory' ),
			);

			$args = array(
				'labels'             => $labels,
				'public'             => true,
				'has_archive'        => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-store',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt', 'author' ),
				'rewrite'            => array(
					'slug'       => 'businesses',
					'with_front' => false,
				),
				'capability_type'    => array( 'business_listing', 'business_listings' ),
				'capabilities'       => self::get_post_type_capabilities( 'business_listing', 'business_listings' ),
				'map_meta_cap'       => true,
				'show_in_nav_menus'  => true,
			);

			register_post_type( 'business_listing', $args );
		}

		/**
		* Register the classified listing post type.
		*/
		private function register_classified_listing_post_type() {
			$labels = array(
				'name'                  => _x( 'Classified Listings', 'Post type general name', 'local-gamified-directory' ),
				'singular_name'         => _x( 'Classified Listing', 'Post type singular name', 'local-gamified-directory' ),
				'menu_name'             => _x( 'Classifieds', 'Admin Menu text', 'local-gamified-directory' ),
				'name_admin_bar'        => _x( 'Classified Listing', 'Add New on Toolbar', 'local-gamified-directory' ),
				'add_new'               => __( 'Add New', 'local-gamified-directory' ),
				'add_new_item'          => __( 'Add New Classified', 'local-gamified-directory' ),
				'new_item'              => __( 'New Classified', 'local-gamified-directory' ),
				'edit_item'             => __( 'Edit Classified', 'local-gamified-directory' ),
				'view_item'             => __( 'View Classified', 'local-gamified-directory' ),
				'all_items'             => __( 'All Classifieds', 'local-gamified-directory' ),
				'search_items'          => __( 'Search Classifieds', 'local-gamified-directory' ),
				'parent_item_colon'     => __( 'Parent Classifieds:', 'local-gamified-directory' ),
				'not_found'             => __( 'No classifieds found.', 'local-gamified-directory' ),
				'not_found_in_trash'    => __( 'No classifieds found in Trash.', 'local-gamified-directory' ),
				'featured_image'        => _x( 'Classified Image', 'Overrides featured image text', 'local-gamified-directory' ),
				'set_featured_image'    => _x( 'Set classified image', 'Overrides set featured image text', 'local-gamified-directory' ),
				'remove_featured_image' => _x( 'Remove classified image', 'Overrides remove featured image text', 'local-gamified-directory' ),
				'use_featured_image'    => _x( 'Use as classified image', 'Overrides use featured image text', 'local-gamified-directory' ),
				'archives'              => _x( 'Classified archives', 'The post type archive label', 'local-gamified-directory' ),
			);

			$args = array(
				'labels'             => $labels,
				'public'             => true,
				'has_archive'        => true,
				'publicly_queryable' => true,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_rest'       => true,
				'menu_icon'          => 'dashicons-megaphone',
				'supports'           => array( 'title', 'editor', 'thumbnail', 'author' ),
				'rewrite'            => array(
					'slug'       => 'classifieds',
					'with_front' => false,
				),
				'capability_type'    => array( 'classified_listing', 'classified_listings' ),
				'capabilities'       => self::get_post_type_capabilities( 'classified_listing', 'classified_listings' ),
				'map_meta_cap'       => true,
				'show_in_nav_menus'  => true,
			);

			register_post_type( 'classified_listing', $args );
		}

		/**
		* Register business related taxonomies.
		*/
		private function register_business_taxonomies() {
			$category_labels = array(
				'name'              => _x( 'Business Categories', 'taxonomy general name', 'local-gamified-directory' ),
				'singular_name'     => _x( 'Business Category', 'taxonomy singular name', 'local-gamified-directory' ),
				'search_items'      => __( 'Search Business Categories', 'local-gamified-directory' ),
				'all_items'         => __( 'All Business Categories', 'local-gamified-directory' ),
				'parent_item'       => __( 'Parent Business Category', 'local-gamified-directory' ),
				'parent_item_colon' => __( 'Parent Business Category:', 'local-gamified-directory' ),
				'edit_item'         => __( 'Edit Business Category', 'local-gamified-directory' ),
				'update_item'       => __( 'Update Business Category', 'local-gamified-directory' ),
				'add_new_item'      => __( 'Add New Business Category', 'local-gamified-directory' ),
				'new_item_name'     => __( 'New Business Category Name', 'local-gamified-directory' ),
				'menu_name'         => __( 'Business Categories', 'local-gamified-directory' ),
			);

                        register_taxonomy(
                                'business_category',
                                array( 'business_listing', LGD_Ads::POST_TYPE ),
				array(
					'labels'            => $category_labels,
					'show_ui'           => true,
					'show_admin_column' => true,
					'hierarchical'      => true,
					'show_in_rest'      => true,
					'rewrite'           => array(
						'slug'       => 'business-category',
						'with_front' => false,
					),
				)
			);

			$region_labels = array(
				'name'              => _x( 'Business Regions', 'taxonomy general name', 'local-gamified-directory' ),
				'singular_name'     => _x( 'Business Region', 'taxonomy singular name', 'local-gamified-directory' ),
				'search_items'      => __( 'Search Business Regions', 'local-gamified-directory' ),
				'all_items'         => __( 'All Business Regions', 'local-gamified-directory' ),
				'parent_item'       => __( 'Parent Business Region', 'local-gamified-directory' ),
				'parent_item_colon' => __( 'Parent Business Region:', 'local-gamified-directory' ),
				'edit_item'         => __( 'Edit Business Region', 'local-gamified-directory' ),
				'update_item'       => __( 'Update Business Region', 'local-gamified-directory' ),
				'add_new_item'      => __( 'Add New Business Region', 'local-gamified-directory' ),
				'new_item_name'     => __( 'New Business Region Name', 'local-gamified-directory' ),
				'menu_name'         => __( 'Business Regions', 'local-gamified-directory' ),
			);

                        register_taxonomy(
                                'business_region',
                                array( 'business_listing', LGD_Ads::POST_TYPE ),
				array(
					'labels'            => $region_labels,
					'show_ui'           => true,
					'show_admin_column' => true,
					'hierarchical'      => true,
					'show_in_rest'      => true,
					'rewrite'           => array(
						'slug'       => 'business-region',
						'with_front' => false,
					),
				)
			);
		}

		/**
		* Register classified related taxonomies.
		*/
		private function register_classified_taxonomies() {
			$labels = array(
				'name'              => _x( 'Classified Categories', 'taxonomy general name', 'local-gamified-directory' ),
				'singular_name'     => _x( 'Classified Category', 'taxonomy singular name', 'local-gamified-directory' ),
				'search_items'      => __( 'Search Classified Categories', 'local-gamified-directory' ),
				'all_items'         => __( 'All Classified Categories', 'local-gamified-directory' ),
				'parent_item'       => __( 'Parent Classified Category', 'local-gamified-directory' ),
				'parent_item_colon' => __( 'Parent Classified Category:', 'local-gamified-directory' ),
				'edit_item'         => __( 'Edit Classified Category', 'local-gamified-directory' ),
				'update_item'       => __( 'Update Classified Category', 'local-gamified-directory' ),
				'add_new_item'      => __( 'Add New Classified Category', 'local-gamified-directory' ),
				'new_item_name'     => __( 'New Classified Category Name', 'local-gamified-directory' ),
				'menu_name'         => __( 'Classified Categories', 'local-gamified-directory' ),
			);

			register_taxonomy(
				'classified_category',
				'classified_listing',
				array(
					'labels'            => $labels,
					'show_ui'           => true,
					'show_admin_column' => true,
					'hierarchical'      => true,
					'show_in_rest'      => true,
					'rewrite'           => array(
						'slug'       => 'classified-category',
						'with_front' => false,
					),
				)
			);
		}

		/**
		 * Register admin meta boxes for business listings.
		 */
		public function register_meta_boxes() {
			add_meta_box(
				'lgd_business_details',
				__( 'Business Details', 'local-gamified-directory' ),
				array( $this, 'render_business_details_meta_box' ),
				'business_listing',
				'normal',
				'high'
			);

			add_meta_box(
				'lgd_business_owner',
				__( 'Business Owner', 'local-gamified-directory' ),
				array( $this, 'render_business_owner_meta_box' ),
				'business_listing',
				'side',
				'default'
			);
		}

		/**
		 * Render the business details meta box content.
		 *
		 * @param WP_Post $post Current post object.
		 */
		public function render_business_details_meta_box( $post ) {
			wp_nonce_field( 'lgd_save_business_details', 'lgd_business_details_nonce' );

			$address       = get_post_meta( $post->ID, self::META_PREFIX . 'address', true );
			$phone         = get_post_meta( $post->ID, self::META_PREFIX . 'phone', true );
			$contact_email = get_post_meta( $post->ID, self::META_PREFIX . 'contact_email', true );
			$hours         = get_post_meta( $post->ID, self::META_PREFIX . 'hours', true );
			$place_id      = get_post_meta( $post->ID, self::META_PREFIX . 'google_place_id', true );
			?>
			<p>
				<label for="lgd_address"><strong><?php esc_html_e( 'Street Address', 'local-gamified-directory' ); ?></strong></label>
				<input type="text" class="widefat" id="lgd_address" name="lgd_address" value="<?php echo esc_attr( $address ); ?>" />
			</p>
			<p>
				<label for="lgd_phone"><strong><?php esc_html_e( 'Phone Number', 'local-gamified-directory' ); ?></strong></label>
				<input type="text" class="widefat" id="lgd_phone" name="lgd_phone" value="<?php echo esc_attr( $phone ); ?>" />
			</p>
			<p>
				<label for="lgd_contact_email"><strong><?php esc_html_e( 'Contact Email', 'local-gamified-directory' ); ?></strong></label>
				<input type="email" class="widefat" id="lgd_contact_email" name="lgd_contact_email" value="<?php echo esc_attr( $contact_email ); ?>" />
			</p>
			<p>
				<label for="lgd_hours"><strong><?php esc_html_e( 'Operating Hours', 'local-gamified-directory' ); ?></strong></label>
				<textarea class="widefat" rows="3" id="lgd_hours" name="lgd_hours"><?php echo esc_textarea( $hours ); ?></textarea>
			</p>
			<p>
				<label for="lgd_google_place_id"><strong><?php esc_html_e( 'Google Place ID', 'local-gamified-directory' ); ?></strong></label>
				<input type="text" class="widefat" id="lgd_google_place_id" name="lgd_google_place_id" value="<?php echo esc_attr( $place_id ); ?>" />
			</p>
			<?php
		}

		/**
		 * Render the business owner meta box content.
		 *
		 * @param WP_Post $post Current post object.
		 */
		public function render_business_owner_meta_box( $post ) {
			wp_nonce_field( 'lgd_save_business_owner', 'lgd_business_owner_nonce' );

			$current_owner = get_post_meta( $post->ID, self::META_OWNER_USER, true );

			if ( ! $current_owner ) {
				$current_owner = (int) $post->post_author;
			}

			$users = get_users(
				array(
					'role__in' => array( 'business_owner', 'administrator', 'editor' ),
					'fields'   => array( 'ID', 'display_name' ),
					'orderby'  => 'display_name',
				)
			);
			?>
			<p>
				<label for="lgd_owner_user"><strong><?php esc_html_e( 'Assign Owner', 'local-gamified-directory' ); ?></strong></label>
				<select id="lgd_owner_user" name="lgd_owner_user" class="widefat">
					<option value="0"><?php esc_html_e( '— No owner —', 'local-gamified-directory' ); ?></option>
					<?php foreach ( $users as $user ) : ?>
						<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $current_owner, $user->ID ); ?>><?php echo esc_html( $user->display_name ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="description">
				<?php esc_html_e( 'Owners can manage this listing from the front end once front-end management tools are enabled.', 'local-gamified-directory' ); ?>
			</p>
			<?php
		}

		/**
		 * Save business listing meta box data.
		 *
		 * @param int     $post_id Post identifier.
		 * @param WP_Post $post    Post object.
		 */
		public function save_business_listing_meta( $post_id, $post ) {
			if ( ! $post instanceof WP_Post ) {
				return;
			}

			if ( 'business_listing' !== $post->post_type ) {
				return;
			}

			if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
				return;
			}

			if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
				return;
			}

			if ( ! isset( $_POST['lgd_business_details_nonce'], $_POST['lgd_business_owner_nonce'] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( wp_unslash( $_POST['lgd_business_details_nonce'] ), 'lgd_save_business_details' ) ) {
				return;
			}

			if ( ! wp_verify_nonce( wp_unslash( $_POST['lgd_business_owner_nonce'] ), 'lgd_save_business_owner' ) ) {
				return;
			}

			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				return;
			}

			$meta_map = array(
				self::META_PREFIX . 'address'       => isset( $_POST['lgd_address'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_address'] ) ) : '',
				self::META_PREFIX . 'phone'         => isset( $_POST['lgd_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_phone'] ) ) : '',
				self::META_PREFIX . 'contact_email' => isset( $_POST['lgd_contact_email'] ) ? sanitize_email( wp_unslash( $_POST['lgd_contact_email'] ) ) : '',
				self::META_PREFIX . 'hours'         => isset( $_POST['lgd_hours'] ) ? sanitize_textarea_field( wp_unslash( $_POST['lgd_hours'] ) ) : '',
				self::META_PREFIX . 'google_place_id' => isset( $_POST['lgd_google_place_id'] ) ? sanitize_text_field( wp_unslash( $_POST['lgd_google_place_id'] ) ) : '',
			);

			foreach ( $meta_map as $key => $value ) {
				if ( '' === $value ) {
					delete_post_meta( $post_id, $key );
				} else {
					update_post_meta( $post_id, $key, $value );
				}
			}

			$owner_id = 0;

			if ( isset( $_POST['lgd_owner_user'] ) ) {
				$owner_id = absint( wp_unslash( $_POST['lgd_owner_user'] ) );
			}

			if ( $owner_id > 0 && get_user_by( 'id', $owner_id ) ) {
				update_post_meta( $post_id, self::META_OWNER_USER, $owner_id );

				if ( (int) $post->post_author !== $owner_id ) {
					wp_update_post(
						array(
							'ID'          => $post_id,
							'post_author' => $owner_id,
						)
					);
				}
			} else {
				delete_post_meta( $post_id, self::META_OWNER_USER );
			}
		}

		/**
		 * Ensure non-publishing users create pending business listings.
		 *
		 * @param array $data    An array of slashed post data.
		 * @param array $postarr An array of sanitized (and slashed) but otherwise unmodified post data.
		 *
		 * @return array
		 */
		public function set_business_listing_pending( $data, $postarr ) {
			if ( empty( $data['post_type'] ) || 'business_listing' !== $data['post_type'] ) {
				return $data;
			}

			$current_user = get_current_user_id();

			if ( empty( $postarr['ID'] ) && $current_user && ! user_can( $current_user, 'publish_business_listings' ) ) {
				$data['post_status'] = 'pending';
			}

			return $data;
		}

		/**
		 * Authorization callback for business meta access.
		 *
		 * @param bool   $allowed  Whether the user is allowed to edit the meta.
		 * @param string $meta_key Meta key.
		 * @param int    $post_id  Post identifier.
		 *
		 * @return bool
		 */
		public function business_meta_auth_callback( $allowed, $meta_key, $post_id, $user_id, $cap, $caps ) {
			unset( $allowed );
			unset( $meta_key );
			unset( $cap );
			unset( $caps );

			if ( empty( $user_id ) ) {
				$user_id = get_current_user_id();
			}

			return user_can( $user_id, 'edit_post', $post_id );
		}

		/**
		 * Authorization callback for classified meta access.
		 *
		 * @param bool   $allowed  Whether the user is allowed to edit the meta.
		 * @param string $meta_key Meta key.
		 * @param int    $post_id  Post identifier.
		 *
		 * @return bool
		 */
		public function classified_meta_auth_callback( $allowed, $meta_key, $post_id, $user_id, $cap, $caps ) {
			unset( $allowed );
			unset( $meta_key );
			unset( $cap );
			unset( $caps );

			if ( empty( $user_id ) ) {
				$user_id = get_current_user_id();
			}

			return user_can( $user_id, 'edit_post', $post_id );
		}

		/**
		 * Sanitize a date/time string to a standardized format.
		 *
		 * @param string $value Raw input value.
		 *
		 * @return string
		 */
                public function sanitize_datetime_meta( $value, $meta_key = '', $object_type = '' ) {
                        if ( empty( $value ) ) {
                                return '';
                        }

			$timestamp = strtotime( $value );

			if ( false === $timestamp ) {
				return '';
			}

                        return gmdate( 'Y-m-d H:i:s', $timestamp );
                }

                /**
                 * Sanitize boolean meta values to integers.
                 *
                 * @param mixed $value Raw input value.
                 *
                 * @return int
                 */
                public function sanitize_boolean_meta( $value ) {
                        return ! empty( $value ) ? 1 : 0;
                }



		/**
		* Ensure roles and capabilities exist.
		*/
		public static function add_roles_and_capabilities() {
			self::add_roles();
			self::add_capabilities();
		}

		/**
		* Add custom roles for the plugin.
		*/
		private static function add_roles() {
			$business_owner_capabilities = array(
				'read'                             => true,
				'upload_files'                     => true,
                               'edit_business_listings'           => true,
                               'edit_business_listing'            => true,
                               'edit_published_business_listings' => true,
                               'create_business_listings'         => true,
                               'delete_business_listing'          => true,
                               'delete_business_listings'         => true,
                               'edit_classified_listings'         => true,
                               'edit_classified_listing'          => true,
                               'publish_classified_listings'      => true,
                               'create_classified_listings'       => true,
                               'delete_classified_listing'        => true,
                               'delete_classified_listings'       => true,
                               'edit_lgd_ads'                     => true,
                               'edit_lgd_ad'                      => true,
                               'create_lgd_ads'                   => true,
                               'delete_lgd_ad'                    => true,
                               'delete_lgd_ads'                   => true,
			);

			$community_member_capabilities = array(
				'read'                        => true,
				'upload_files'                => true,
				'edit_classified_listings'    => true,
				'edit_classified_listing'     => true,
				'publish_classified_listings' => true,
				'create_classified_listings'  => true,
				'delete_classified_listing'   => true,
				'delete_classified_listings'  => true,
			);

			if ( null === get_role( 'business_owner' ) ) {
				add_role( 'business_owner', __( 'Business Owner', 'local-gamified-directory' ), $business_owner_capabilities );
			}

			if ( null === get_role( 'community_member' ) ) {
				add_role( 'community_member', __( 'Community Member', 'local-gamified-directory' ), $community_member_capabilities );
			}
		}

		/**
		* Assign capabilities to appropriate roles.
		*/
		private static function add_capabilities() {
			$roles = array( 'administrator', 'editor', 'business_owner', 'community_member' );

                       $business_caps = self::get_post_type_capabilities( 'business_listing', 'business_listings' );
                       $class_caps    = self::get_post_type_capabilities( 'classified_listing', 'classified_listings' );
                       $ad_caps       = self::get_post_type_capabilities( 'lgd_ad', 'lgd_ads' );

			foreach ( $roles as $role_key ) {
				$role = get_role( $role_key );

				if ( ! $role instanceof WP_Role ) {
					continue;
				}

				foreach ( $business_caps as $cap ) {
					if ( in_array( $role_key, array( 'administrator', 'editor' ), true ) ) {
						$role->add_cap( $cap );
					} elseif ( 'business_owner' === $role_key && in_array( $cap, self::get_business_owner_caps(), true ) ) {
						$role->add_cap( $cap );
					}
				}

                               foreach ( $class_caps as $cap ) {
                                       if ( in_array( $role_key, array( 'administrator', 'editor' ), true ) ) {
                                               $role->add_cap( $cap );
                                       } elseif ( in_array( $role_key, array( 'business_owner', 'community_member' ), true ) && in_array( $cap, self::get_classified_caps_for_members(), true ) ) {
                                               $role->add_cap( $cap );
                                       }
                               }

                               foreach ( $ad_caps as $cap ) {
                                       if ( in_array( $role_key, array( 'administrator', 'editor' ), true ) ) {
                                               $role->add_cap( $cap );
                                       } elseif ( 'business_owner' === $role_key && in_array( $cap, self::get_ad_caps_for_business_owners(), true ) ) {
                                               $role->add_cap( $cap );
                                       }
                               }
                       }
               }

		/**
		* Caps business owners should receive for business listings.
		*
		* @return array
		*/
		private static function get_business_owner_caps() {
			return array(
				'edit_business_listing',
				'edit_business_listings',
				'edit_published_business_listings',
				'create_business_listings',
				'read_business_listing',
				'delete_business_listing',
				'delete_business_listings',
			);
		}

		/**
		* Caps that community members and business owners should receive for classifieds.
		*
		* @return array
		*/
               private static function get_classified_caps_for_members() {
                       return array(
                               'read_classified_listing',
                               'edit_classified_listing',
                               'edit_classified_listings',
                               'publish_classified_listings',
                               'create_classified_listings',
                               'delete_classified_listing',
                               'delete_classified_listings',
                       );
               }

               /**
                * Caps business owners should receive for ads.
                *
                * @return array
                */
               private static function get_ad_caps_for_business_owners() {
                       return array(
                               'read_lgd_ad',
                               'read_private_lgd_ads',
                               'edit_lgd_ad',
                               'edit_lgd_ads',
                               'edit_private_lgd_ads',
                               'edit_published_lgd_ads',
                               'create_lgd_ads',
                               'delete_lgd_ad',
                               'delete_lgd_ads',
                               'delete_private_lgd_ads',
                               'delete_published_lgd_ads',
                       );
               }

		/**
		* Helper to generate capabilities for custom post types.
		*
		* @param string $singular Singular capability base.
		* @param string $plural   Plural capability base.
		*
		* @return array
		*/
               public static function get_post_type_capabilities( $singular, $plural ) {
			return array(
				'edit_post'              => "edit_{$singular}",
				'read_post'              => "read_{$singular}",
				'delete_post'            => "delete_{$singular}",
				'edit_posts'             => "edit_{$plural}",
				'edit_others_posts'      => "edit_others_{$plural}",
				'publish_posts'          => "publish_{$plural}",
				'read_private_posts'     => "read_private_{$plural}",
				'delete_posts'           => "delete_{$plural}",
				'delete_private_posts'   => "delete_private_{$plural}",
				'delete_published_posts' => "delete_published_{$plural}",
				'delete_others_posts'    => "delete_others_{$plural}",
				'edit_private_posts'     => "edit_private_{$plural}",
				'edit_published_posts'   => "edit_published_{$plural}",
				'create_posts'           => "create_{$plural}",
			);
		}
	}

	Local_Gamified_Directory::instance();

	register_activation_hook( __FILE__, array( 'Local_Gamified_Directory', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'Local_Gamified_Directory', 'deactivate' ) );
}
