<?php
/**
 * Subscription logic for Relationship Quizzr.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Handles subscription tiers and referral rewards.
 */
class RQ_Subscriptions {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'init', array( $this, 'schedule_expiration_event' ) );
        add_action( 'rq_check_subscription_expirations', array( $this, 'maybe_expire_subscriptions' ) );
        add_action( 'rq_referral_paid', array( $this, 'handle_referral_reward' ), 10, 2 );
        add_action( 'rq_subscription_paid', array( $this, 'log_payment' ), 10, 3 );
    }

    /**
     * Ensure the subscription expiration cron event is scheduled.
     */
    public function schedule_expiration_event() {
        if ( ! wp_next_scheduled( 'rq_check_subscription_expirations' ) ) {
            wp_schedule_event( time() + HOUR_IN_SECONDS, 'twicedaily', 'rq_check_subscription_expirations' );
        }
    }

    /**
     * Retrieve subscription settings.
     *
     * @return array
     */
    public function get_settings() {
        $defaults = array(
            'paypal_client_id'     => '',
            'paypal_client_secret' => '',
            'individual_price'     => 9.99,
            'couple_price'         => 14.99,
            'adult_category_lock'  => true,
            'moderation_required'  => true,
        );

        $settings = get_option( 'relationship_quizzr_settings', array() );

        return wp_parse_args( $settings, $defaults );
    }

    /**
     * Start subscription for a user.
     *
     * @param int    $user_id User ID.
     * @param string $tier    Subscription tier.
     * @param array  $payment Payment payload from PayPal.
     *
     * @return bool
     */
    public function create_subscription( $user_id, $tier, $payment = array() ) {
        if ( ! $user_id || ! in_array( $tier, array( 'individual', 'couple' ), true ) ) {
            return false;
        }

        update_user_meta( $user_id, '_rq_subscription_tier', $tier );
        update_user_meta( $user_id, '_rq_subscription_status', 'active' );
        update_user_meta( $user_id, '_rq_subscription_started', current_time( 'mysql', true ) );
        update_user_meta( $user_id, '_rq_subscription_payment', wp_json_encode( $payment ) );

        $expiration = $this->calculate_next_expiration( $user_id, 1 );
        update_user_meta( $user_id, '_rq_subscription_expires', $expiration );

        do_action( 'rq_subscription_paid', $user_id, $tier, $payment );

        return true;
    }

    /**
     * Determine next expiration.
     *
     * @param int $user_id User ID.
     * @param int $months  Number of months to add.
     *
     * @return string
     */
    public function calculate_next_expiration( $user_id, $months = 1 ) {
        $current = get_user_meta( $user_id, '_rq_subscription_expires', true );
        $start   = $current ? strtotime( $current ) : current_time( 'timestamp', true );
        $expires = gmdate( 'Y-m-d H:i:s', strtotime( "+{$months} month", $start ) );

        return $expires;
    }

    /**
     * Handle referral reward.
     *
     * @param int    $referrer_id    User ID that referred.
     * @param string $billing_period Billing period string.
     */
    public function handle_referral_reward( $referrer_id, $billing_period ) {
        $meta_key = '_rq_referrals_' . sanitize_key( $billing_period );
        $count    = (int) get_user_meta( $referrer_id, $meta_key, true );

        if ( $count >= 2 ) {
            $new_expiration = $this->calculate_next_expiration( $referrer_id, 1 );
            update_user_meta( $referrer_id, '_rq_subscription_expires', $new_expiration );
            update_user_meta( $referrer_id, $meta_key, 0 );

            do_action( 'rq_subscription_reward_granted', $referrer_id, $billing_period );
        }
    }

    /**
     * Log payment metadata.
     *
     * @param int    $user_id User ID.
     * @param string $tier    Tier.
     * @param array  $payment Payment data.
     */
    public function log_payment( $user_id, $tier, $payment ) {
        $log = get_user_meta( $user_id, '_rq_subscription_payments', true );
        if ( ! is_array( $log ) ) {
            $log = array();
        }

        $log[] = array(
            'tier'     => sanitize_text_field( $tier ),
            'payload'  => $payment,
            'logged_at'=> current_time( 'mysql', true ),
        );

        update_user_meta( $user_id, '_rq_subscription_payments', $log );
    }

    /**
     * Expire subscriptions when the date passes.
     */
    public function maybe_expire_subscriptions() {
        $args = array(
            'meta_query' => array(
                array(
                    'key'     => '_rq_subscription_expires',
                    'value'   => gmdate( 'Y-m-d H:i:s' ),
                    'compare' => '<=',
                    'type'    => 'DATETIME',
                ),
            ),
            'fields'     => 'ID',
            'number'     => 100,
        );

        $users = get_users( $args );
        foreach ( $users as $user_id ) {
            update_user_meta( $user_id, '_rq_subscription_status', 'expired' );
            do_action( 'rq_subscription_expired', $user_id );
        }
    }

    /**
     * Verify if a user has access to adult content.
     *
     * @param int $user_id User ID.
     *
     * @return bool
     */
    public function can_access_adult_category( $user_id ) {
        $settings = $this->get_settings();
        if ( empty( $settings['adult_category_lock'] ) ) {
            return true;
        }

        $status   = get_user_meta( $user_id, '_rq_subscription_status', true );
        $verified = (bool) get_user_meta( $user_id, '_rq_age_verified', true );

        return ( 'active' === $status && $verified );
    }
}
