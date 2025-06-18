<?php
// wp-content/plugins/google-sheet-csv-updater/includes/class-gscu-cron.php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Class GSCU_Cron
 * Handles scheduled synchronization tasks.
 */
class GSCU_Cron {

    /**
     * Constructor.
     * Hooks into WordPress to schedule cron jobs and define the cron action.
     */
    public function __construct() {
        // The actual scheduling is now triggered by updating the relevant options in GSCU_Admin.
        // This constructor just ensures the hook for the cron job's execution is set up.
        add_action( GSCU_CRON_HOOK, array( $this, 'execute_scheduled_sync' ) );
    }

    /**
     * Schedules or clears the synchronization event based on plugin settings.
     * This method is static and called when relevant settings are updated.
     */
    public static function schedule_or_clear_events() {
        $enable_auto_sync = get_option( 'gscu_enable_auto_sync', 0 );
        $sync_schedule = get_option( 'gscu_sync_schedule', 'daily' );

        // Always clear any existing schedule first to reflect changes immediately.
        self::clear_scheduled_events();

        if ( $enable_auto_sync && $sync_schedule !== 'disabled' ) {
            // Ensure the schedule is valid before trying to schedule
            $schedules = wp_get_schedules();
            if ( isset( $schedules[$sync_schedule] ) ) {
                if ( ! wp_schedule_event( time(), $sync_schedule, GSCU_CRON_HOOK ) ) {
                    // Optionally log an error if scheduling fails
                    error_log('[GSCU Cron] Failed to schedule cron event: ' . GSCU_CRON_HOOK . ' with schedule: ' . $sync_schedule);
                } else {
                    // Optionally log success
                    // error_log('[GSCU Cron] Successfully scheduled cron event: ' . GSCU_CRON_HOOK . ' with schedule: ' . $sync_schedule);
                }
            } else {
                // Log if the schedule name isn't recognized (shouldn't happen with dropdown)
                error_log('[GSCU Cron] Invalid schedule name provided: ' . $sync_schedule);
            }
        }
    }

    /**
     * Executes the scheduled synchronization.
     * This is the callback function for the WP Cron event.
     */
    public function execute_scheduled_sync() {
        // Ensure GSCU_Sync class and its methods are available
        if ( class_exists('GSCU_Sync') && method_exists('GSCU_Sync', 'synchronize_content') ) {
            // Optional: Add a log entry specifically indicating this was a cron-triggered sync
            // For now, synchronize_content itself handles detailed logging.
            GSCU_Sync::synchronize_content();

            // You could add a summary log entry here specific to cron run if desired
            // For example:
            // $logs = get_option(GSCU_LOGS_OPTION_KEY, array());
            // $cron_log_entry = array(
            // 'timestamp' => time(),
            // 'summary'   => __('Cron sync initiated.', 'google-sheet-csv-updater'),
            // 'details'   => []
            // );
            // array_unshift($logs, $cron_log_entry);
            // $logs = array_slice($logs, 0, GSCU_MAX_LOG_ENTRIES);
            // update_option(GSCU_LOGS_OPTION_KEY, $logs);

        } else {
            error_log('[GSCU Cron] GSCU_Sync::synchronize_content() not found during scheduled sync.');
        }
    }

    /**
     * Clears the scheduled synchronization event.
     * Static method called on plugin deactivation or when settings are changed.
     */
    public static function clear_scheduled_events() {
        $timestamp = wp_next_scheduled( GSCU_CRON_HOOK );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, GSCU_CRON_HOOK );
        }
        // As an extra precaution, clear any other potential schedules for this hook.
        wp_clear_scheduled_hook( GSCU_CRON_HOOK );
        // error_log('[GSCU Cron] Cleared scheduled events for hook: ' . GSCU_CRON_HOOK);
    }
}
