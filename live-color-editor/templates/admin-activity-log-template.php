<?php
/**
 * Template for the admin activity log page.
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/templates
 */

global $wpdb;
$table_name = $wpdb->prefix . 'lce_activity_log';
$logs = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY timestamp DESC" );
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Activity Log', 'live-color-editor' ); ?></h1>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Date', 'live-color-editor' ); ?></th>
                <th scope="col"><?php esc_html_e( 'User', 'live-color-editor' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Action', 'live-color-editor' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Description', 'live-color-editor' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Undo', 'live-color-editor' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( ! empty( $logs ) ) : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( $log->timestamp ); ?></td>
                        <td>
                            <?php
                                $user = get_user_by( 'id', $log->user_id );
                                echo esc_html( $user ? $user->display_name : __( 'Unknown', 'live-color-editor' ) );
                            ?>
                        </td>
                        <td><?php echo esc_html( ucfirst( $log->action_type ) ); ?></td>
                        <td>
                            <?php
                            $description = '';
                            $old_color = esc_html( $log->old_color );
                            $new_color = esc_html( $log->new_color );
                            $old_swatch = '<span style="background-color:' . esc_attr($old_color) . '; border: 1px solid #ccc; display: inline-block; width: 15px; height: 15px; margin-right: 5px; vertical-align: middle;"></span>' . $old_color;
                            $new_swatch = '<span style="background-color:' . esc_attr($new_color) . '; border: 1px solid #ccc; display: inline-block; width: 15px; height: 15px; margin-right: 5px; vertical-align: middle;"></span>' . $new_color;

                            switch ( $log->action_type ) {
                                case 'created':
                                    $description = sprintf(
                                        /* translators: 1: old color, 2: new color */
                                        __( 'Set mapping for %1$s to %2$s', 'live-color-editor' ),
                                        $old_swatch,
                                        $new_swatch
                                    );
                                    break;
                                case 'updated':
                                    $description = sprintf(
                                        /* translators: 1: old color, 2: new color */
                                        __( 'Changed mapping for %1$s to %2$s', 'live-color-editor' ),
                                        $old_swatch,
                                        $new_swatch
                                    );
                                    break;
                                case 'deleted':
                                    $description = sprintf(
                                        /* translators: 1: old color, 2: new color */
                                        __( 'Removed mapping for %1$s (was %2$s)', 'live-color-editor' ),
                                        $old_swatch,
                                        $new_swatch
                                    );
                                    break;
                                case 'undone':
                                     $description = sprintf(
                                        /* translators: 1: old color */
                                        __( 'Undid change for %1$s', 'live-color-editor' ),
                                        $old_swatch
                                    );
                                    break;
                            }
                            echo $description; // WPCS: XSS ok.
                            ?>
                        </td>
                        <td>
                            <?php if ( in_array( $log->action_type, ['created', 'updated', 'deleted'] ) ) : ?>
                                <button class="button button-secondary lce-undo-button" data-log-id="<?php echo esc_attr( $log->id ); ?>"><?php esc_html_e( 'Undo', 'live-color-editor' ); ?></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="5"><?php esc_html_e( 'No activity yet.', 'live-color-editor' ); ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
