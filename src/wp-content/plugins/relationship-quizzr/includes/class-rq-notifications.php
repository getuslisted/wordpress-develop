<?php
/**
 * Email notifications for Relationship Quizzr.
 *
 * @package RelationshipQuizzr
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Sends transactional emails for invites, quizzes, and rewards.
 */
class RQ_Notifications {

    /**
     * Services container.
     *
     * @var array
     */
    protected $services = array();

    /**
     * Constructor.
     *
     * @param array $services Service map.
     */
    public function __construct( $services ) {
        $this->services = $services;

        add_action( 'rq_connection_invite', array( $this, 'send_connection_invite' ) );
        add_action( 'rq_connection_accepted', array( $this, 'notify_connection_accepted' ) );
        add_action( 'rq_quiz_submitted', array( $this, 'notify_quiz_submission' ) );
        add_action( 'rq_subscription_reward_granted', array( $this, 'notify_referral_reward' ), 10, 2 );
    }

    /**
     * Send invitation email.
     *
     * @param array $invite Invitation payload.
     */
    public function send_connection_invite( $invite ) {
        $inviter = get_userdata( $invite['from'] );
        if ( ! $inviter ) {
            return;
        }

        $subject = sprintf( __( '%s invited you to Relationship Quizzr', 'relationship-quizzr' ), $inviter->display_name );
        $message = sprintf(
            __( 'Hi there! %1$s wants to connect with you on Relationship Quizzr as their %2$s. Use this link to join: %3$s', 'relationship-quizzr' ),
            $inviter->display_name,
            $invite['type'],
            esc_url( add_query_arg( array( 'rq_invite' => $invite['hash'] ), site_url() ) )
        );

        wp_mail( $invite['email'], $subject, $message );
    }

    /**
     * Notify both users when a connection is accepted.
     *
     * @param array $payload Connection data.
     */
    public function notify_connection_accepted( $payload ) {
        $user_one = get_userdata( $payload['user_one'] );
        $user_two = get_userdata( $payload['user_two'] );

        if ( $user_one && $user_two ) {
            $subject = __( 'Connection confirmed!', 'relationship-quizzr' );
            $message = sprintf( __( 'You are now connected with %s. Start taking quizzes together to earn points!', 'relationship-quizzr' ), $user_two->display_name );
            wp_mail( $user_one->user_email, $subject, $message );

            $message_two = sprintf( __( 'You are now connected with %s. Explore the quiz library and invite them!', 'relationship-quizzr' ), $user_one->display_name );
            wp_mail( $user_two->user_email, $subject, $message_two );
        }
    }

    /**
     * Notify connection partner on quiz submission.
     *
     * @param array $payload Submission payload.
     */
    public function notify_quiz_submission( $payload ) {
        if ( empty( $payload['connection_id'] ) ) {
            return;
        }

        global $wpdb;
        $connections_table = $wpdb->prefix . 'rq_connections';
        $connection        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$connections_table} WHERE id = %d", $payload['connection_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

        if ( ! $connection || empty( $connection->user_two ) ) {
            return;
        }

        $partner_id = $connection->user_one === $payload['user_id'] ? (int) $connection->user_two : (int) $connection->user_one;
        $partner    = get_userdata( $partner_id );
        $quiz       = get_post( $payload['quiz_id'] );

        if ( $partner && $quiz ) {
            $subject = sprintf( __( '%s sent you a quiz!', 'relationship-quizzr' ), get_userdata( $payload['user_id'] )->display_name );
            $message = sprintf( __( 'Log in to play %1$s and compare your answers. Compatibility score so far: %2$s%%.', 'relationship-quizzr' ), $quiz->post_title, isset( $payload['compatibility']['score'] ) ? $payload['compatibility']['score'] : 0 );
            wp_mail( $partner->user_email, $subject, $message );
        }
    }

    /**
     * Notify referrer about reward.
     *
     * @param int    $user_id        Referrer ID.
     * @param string $billing_period Billing period string.
     */
    public function notify_referral_reward( $user_id, $billing_period ) {
        $user = get_userdata( $user_id );
        if ( ! $user ) {
            return;
        }

        $subject = __( 'You earned a free month!', 'relationship-quizzr' );
        $message = sprintf( __( 'Congratulations! You have earned a free month for %s because two friends subscribed this period.', 'relationship-quizzr' ), $billing_period );
        wp_mail( $user->user_email, $subject, $message );
    }
}
