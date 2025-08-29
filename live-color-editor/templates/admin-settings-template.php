<?php
/**
 * Template for the admin settings page.
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/templates
 */

$mappings = get_option( 'lce_color_mappings', array() );
?>

<div id="lce-color-mappings-wrapper">
    <?php if ( ! empty( $mappings ) ) : ?>
        <?php foreach ( $mappings as $old_color => $new_color ) : ?>
            <div class="lce-color-mapping-row">
                <div class="lce-color-inputs">
                    <input type="text" name="lce_color_mappings[old_color][]" value="<?php echo esc_attr( $old_color ); ?>" class="lce-color-picker" placeholder="<?php esc_attr_e( 'Old Color', 'live-color-editor' ); ?>" />
                    <span>&rarr;</span>
                    <input type="text" name="lce_color_mappings[new_color][]" value="<?php echo esc_attr( $new_color ); ?>" class="lce-color-picker" placeholder="<?php esc_attr_e( 'New Color', 'live-color-editor' ); ?>" />
                </div>
                <button type="button" class="button lce-remove-row-button"><?php esc_html_e( 'Remove', 'live-color-editor' ); ?></button>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <div class="lce-color-mapping-row">
            <div class="lce-color-inputs">
                <input type="text" name="lce_color_mappings[old_color][]" value="" class="lce-color-picker" placeholder="<?php esc_attr_e( 'Old Color', 'live-color-editor' ); ?>" />
                <span>&rarr;</span>
                <input type="text" name="lce_color_mappings[new_color][]" value="" class="lce-color-picker" placeholder="<?php esc_attr_e( 'New Color', 'live-color-editor' ); ?>" />
            </div>
            <button type="button" class="button lce-remove-row-button"><?php esc_html_e( 'Remove', 'live-color-editor' ); ?></button>
        </div>
    <?php endif; ?>
</div>

<button type="button" id="lce-add-row-button" class="button"><?php esc_html_e( 'Add New Color Mapping', 'live-color-editor' ); ?></button>
<button type="button" id="lce-scan-colors-button" class="button button-secondary"><?php esc_html_e( 'Scan Homepage for Colors', 'live-color-editor' ); ?></button>


<script type="text/template" id="lce-color-mapping-template">
    <div class="lce-color-mapping-row">
        <div class="lce-color-inputs">
            <input type="text" name="lce_color_mappings[old_color][]" value="" class="lce-color-picker" placeholder="<?php esc_attr_e( 'Old Color', 'live-color-editor' ); ?>" />
            <span>&rarr;</span>
            <input type="text" name="lce_color_mappings[new_color][]" value="" class="lce-color-picker" placeholder="<?php esc_attr_e( 'New Color', 'live-color-editor' ); ?>" />
        </div>
        <button type="button" class="button lce-remove-row-button"><?php esc_html_e( 'Remove', 'live-color-editor' ); ?></button>
    </div>
</script>
