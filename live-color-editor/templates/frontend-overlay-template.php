<?php
/**
 * Template for the frontend editor overlay.
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/templates
 */

$mappings = get_option( 'lce_color_mappings', array() );
?>

<div id="lce-frontend-editor">
    <div id="lce-editor-toggle">
        <?php esc_html_e( 'Live Color Editor', 'live-color-editor' ); ?>
    </div>
    <div id="lce-editor-panel">
        <form id="lce-frontend-form">
            <input type="hidden" name="action" value="lce_save_mappings">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'lce_save_mappings_nonce' ); ?>">

            <h3><?php esc_html_e( 'Live Color Editor', 'live-color-editor' ); ?></h3>
            <p><?php esc_html_e( 'Change colors live. Click "Save & Refresh" to see changes.', 'live-color-editor' ); ?></p>

            <div id="lce-frontend-mappings-wrapper">
                <?php if ( ! empty( $mappings ) ) : ?>
                    <?php foreach ( $mappings as $old_color => $new_color ) : ?>
                        <div class="lce-frontend-mapping-row">
                            <input type="text" name="lce_color_mappings[old_color][]" value="<?php echo esc_attr( $old_color ); ?>" class="lce-color-picker" placeholder="<?php esc_attr_e( 'Old Color', 'live-color-editor' ); ?>" />
                            <span>&rarr;</span>
                            <input type="text" name="lce_color_mappings[new_color][]" value="<?php echo esc_attr( $new_color ); ?>" class="lce-color-picker" placeholder="<?php esc_attr_e( 'New Color', 'live-color-editor' ); ?>" />
                            <button type="button" class="button lce-frontend-remove-row">X</button>
                        </div>
                    <?php endforeach; ?>
                <?php else : ?>
                     <div class="lce-frontend-mapping-row">
                        <input type="text" name="lce_color_mappings[old_color][]" value="" class="lce-color-picker" placeholder="<?php esc_attr_e( 'Old Color', 'live-color-editor' ); ?>" />
                        <span>&rarr;</span>
                        <input type="text" name="lce_color_mappings[new_color][]" value="" class="lce-color-picker" placeholder="<?php esc_attr_e( 'New Color', 'live-color-editor' ); ?>" />
                        <button type="button" class="button lce-frontend-remove-row">X</button>
                    </div>
                <?php endif; ?>
            </div>

            <button type="button" id="lce-frontend-add-row" class="button"><?php esc_html_e( 'Add Color', 'live-color-editor' ); ?></button>
            <button type="button" id="lce-frontend-scan-colors" class="button button-secondary"><?php esc_html_e( 'Scan Page', 'live-color-editor' ); ?></button>
            <button type="submit" id="lce-frontend-save" class="button button-primary"><?php esc_html_e( 'Save & Refresh', 'live-color-editor' ); ?></button>
        </form>
    </div>
</div>

<script type="text/template" id="lce-frontend-mapping-template">
    <div class="lce-frontend-mapping-row">
        <input type="text" name="lce_color_mappings[old_color][]" value="" class="lce-color-picker" placeholder="<?php esc_attr_e( 'Old Color', 'live-color-editor' ); ?>" />
        <span>&rarr;</span>
        <input type="text" name="lce_color_mappings[new_color][]" value="" class="lce-color-picker" placeholder="<?php esc_attr_e( 'New Color', 'live-color-editor' ); ?>" />
        <button type="button" class="button lce-frontend-remove-row">X</button>
    </div>
</script>
