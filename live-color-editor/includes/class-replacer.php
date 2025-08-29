<?php
/**
 * The color replacer functionality of the plugin.
 *
 * @link       https://example.com/
 * @since      1.0.0
 *
 * @package    Live_Color_Editor
 * @subpackage Live_Color_Editor/includes
 */

class LCE_Replacer {

    /**
     * The main replacement function.
     *
     * @param string $buffer The content buffer.
     * @return string The modified buffer.
     */
    public function replace( $buffer ) {
        $color_mappings = get_option( 'lce_color_mappings', array() );

        if ( empty( $color_mappings ) ) {
            return $buffer;
        }

        // In the future, this will be a more robust regex.
        // For now, simple string replacement.
        $old_colors = array_keys( $color_mappings );
        $new_colors = array_values( $color_mappings );

        return str_ireplace( $old_colors, $new_colors, $buffer );
    }

    /**
     * Scans content for hex or rgb color codes.
     *
     * @param string $content The content to scan.
     * @return array An array of unique colors found.
     */
    public function find_colors( $content ) {
        $colors = array();

        // Regex for hex codes (#fff, #ffffff)
        preg_match_all( '/#([a-f0-9]{6}|[a-f0-9]{3})\b/i', $content, $matches_hex );
        if ( ! empty( $matches_hex[0] ) ) {
            $colors = array_merge( $colors, $matches_hex[0] );
        }

        // Regex for rgb(a) codes
        preg_match_all( '/rgba?\((\s*\d+\s*,){2,3}\s*[\d\.]+\s*\)/i', $content, $matches_rgb );
        if ( ! empty( $matches_rgb[0] ) ) {
            $colors = array_merge( $colors, $matches_rgb[0] );
        }

        return array_unique( array_map( 'strtolower', $colors ) );
    }
}
