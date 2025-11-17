<?php
/**
 * Referral management for Relationship Quizzr.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Generates referral codes and tracks rewards.
 */
class RQ_Referrals {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'user_register', array( $this, 'assign_referral_code' ) );
    }

    /**
     * Assign referral code to user on registration.
     *
     * @param int $user_id User ID.
     */
    public function assign_referral_code( $user_id ) {
        $this->get_referral_code( $user_id );
    }

    /**
     * Generate or retrieve referral code.
     *
     * @param int $user_id User ID.
     *
     * @return string
     */
    public function get_referral_code( $user_id ) {
        $code = get_user_meta( $user_id, '_rq_referral_code', true );
        if ( $code ) {
            return $code;
        }

        $code = strtoupper( wp_generate_password( 8, false, false ) );
        update_user_meta( $user_id, '_rq_referral_code', $code );

        return $code;
    }

    /**
     * Record a referral relationship.
     *
     * @param int    $referrer_id Referrer user ID.
     * @param int    $referee_id  Referee user ID.
     * @param string $status      Status.
     */
    public function record_referral( $referrer_id, $referee_id, $status = 'pending' ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rq_referral_logs';

        $wpdb->insert(
            $table,
            array(
                'referrer_id'   => $referrer_id,
                'referee_id'    => $referee_id,
                'referral_code' => get_user_meta( $referrer_id, '_rq_referral_code', true ),
                'status'        => $status,
                'billing_period'=> $this->get_billing_period(),
                'created_at'    => current_time( 'mysql', true ),
            ),
            array( '%d', '%d', '%s', '%s', '%s', '%s' )
        );
    }

    /**
     * Process paid referral and trigger reward.
     *
     * @param string $referral_code Referral code.
     * @param int    $referee_id    Referee user ID.
     */
    public function mark_referral_paid( $referral_code, $referee_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rq_referral_logs';

        $referral = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE referral_code = %s AND referee_id = %d ORDER BY id DESC LIMIT 1",
                $referral_code,
                $referee_id
            )
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        if ( ! $referral ) {
            return;
        }

        $wpdb->update(
            $table,
            array( 'status' => 'paid' ),
            array( 'id' => $referral->id ),
            array( '%s' ),
            array( '%d' )
        );

        $meta_key = '_rq_referrals_' . sanitize_key( $referral->billing_period );
        $count    = (int) get_user_meta( $referral->referrer_id, $meta_key, true );
        $count++;
        update_user_meta( $referral->referrer_id, $meta_key, $count );

        do_action( 'rq_referral_paid', (int) $referral->referrer_id, $referral->billing_period );
    }

    /**
     * Retrieve referral stats for a user.
     *
     * @param int $user_id User ID.
     *
     * @return array
     */
    public function get_referral_stats( $user_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'rq_referral_logs';

        $stats = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT status, COUNT(*) as total FROM {$table} WHERE referrer_id = %d GROUP BY status",
                $user_id
            ),
            ARRAY_A
        ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        $summary = array(
            'pending' => 0,
            'paid'    => 0,
        );

        foreach ( $stats as $row ) {
            $summary[ $row['status'] ] = (int) $row['total'];
        }

        return $summary;
    }

    /**
     * Determine current billing period string.
     *
     * @return string
     */
    protected function get_billing_period() {
        return gmdate( 'Y-m' );
    }
}
