<?php
/**
 * The logger functionality of the plugin.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/includes
 */

class LCE_Logger {

    /**
     * Compare old and new color mappings and log the changes.
     *
     * @param array $old_mappings The color mappings before the change.
     * @param array $new_mappings The color mappings after the change.
     */
    public static function log_changes( $old_mappings, $new_mappings ) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'lce_activity_log';
        $user_id = get_current_user_id();
        $timestamp = current_time( 'mysql' );

        // Check for new or updated mappings
        foreach ( $new_mappings as $old_color => $new_color ) {
            if ( ! isset( $old_mappings[ $old_color ] ) ) {
                $action_type = 'created';
            } elseif ( $old_mappings[ $old_color ] !== $new_color ) {
                $action_type = 'updated';
            } else {
                continue; // No change
            }

            $wpdb->insert(
                $table_name,
                array(
                    'old_color'   => $old_color,
                    'new_color'   => $new_color,
                    'action_type' => $action_type,
                    'user_id'     => $user_id,
                    'timestamp'   => $timestamp,
                )
            );
        }

        // Check for removed mappings
        foreach ( $old_mappings as $old_color => $new_color ) {
            if ( ! isset( $new_mappings[ $old_color ] ) ) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'old_color'   => $old_color,
                        'new_color'   => $new_color, // Log the color that was removed
                        'action_type' => 'deleted',
                        'user_id'     => $user_id,
                        'timestamp'   => $timestamp,
                    )
                );
            }
        }
    }
}
