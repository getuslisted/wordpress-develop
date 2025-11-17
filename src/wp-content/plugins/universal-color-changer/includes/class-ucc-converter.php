<?php
/**
 * Color conversion helpers.
 *
 * @package UniversalColorChanger
 */

namespace UCC;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

/**
 * Converter utilities.
 */
class Converter {
/**
 * Regex for hex colors.
 *
 * @var string
 */
const HEX_REGEX = '/(#)([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})(?![A-Fa-f0-9])/';

/**
 * Regex for rgb(a).
 *
 * @var string
 */
const RGB_REGEX = '/rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0?\.\d+|1|0))?\s*\)/i';

    /**
     * Normalize color to canonical representation.
     *
     * Returned format is `#rrggbb` for opaque colors and `#rrggbb@alpha` for
     * rgba values with transparency to keep variants distinct while still
     * grouping by the base color.
     *
     * @param string $color Color string.
     * @return string|null
     */
    public static function canonicalize( $color ) {
        $color          = trim( $color );
        $alpha_segment  = null;

        if ( false !== strpos( $color, '@' ) ) {
            list( $color, $alpha_segment ) = explode( '@', $color, 2 );
            $color         = trim( $color );
            $alpha_segment = trim( (string) $alpha_segment );
            if ( '' === $alpha_segment ) {
                $alpha_segment = null;
            }
        }

        if ( preg_match( self::HEX_REGEX, $color, $matches ) ) {
            $hex = strtolower( self::expand_hex( $matches[0] ) );

            if ( null !== $alpha_segment ) {
                $alpha = max( 0, min( 1, (float) $alpha_segment ) );
                if ( $alpha < 1 ) {
                    return $hex . '@' . self::format_alpha( $alpha );
                }
            }

            return $hex;
        }

        if ( preg_match( self::RGB_REGEX, $color ) ) {
            $components = self::parse_rgb( $color );
            if ( $components ) {
                $hex = strtolower( self::rgb_to_hex( $components ) );

                if ( isset( $components['a'] ) && null !== $components['a'] && $components['a'] < 1 ) {
                    return $hex . '@' . self::format_alpha( $components['a'] );
                }

                if ( null !== $alpha_segment ) {
                    $alpha = max( 0, min( 1, (float) $alpha_segment ) );
                    if ( $alpha < 1 ) {
                        return $hex . '@' . self::format_alpha( $alpha );
                    }
                }

                return $hex;
            }
        }

        return null;
    }

/**
 * Expand short hex to full length.
 *
 * @param string $hex Hex string (with #).
 * @return string
 */
public static function expand_hex( $hex ) {
$hex = ltrim( $hex, '#' );
if ( 3 === strlen( $hex ) ) {
$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
}

return '#' . strtolower( $hex );
}

/**
 * Compress hex if possible.
 *
 * @param string $hex Hex string (with #).
 * @return string
 */
public static function compress_hex( $hex ) {
$hex = strtolower( ltrim( $hex, '#' ) );
if ( preg_match( '/^(\w)\1(\w)\2(\w)\3$/', $hex, $matches ) ) {
return '#' . $matches[1] . $matches[2] . $matches[3];
}

return '#' . $hex;
}

/**
 * Convert rgb array to hex.
 *
 * @param array $components RGB components.
 * @return string
 */
public static function rgb_to_hex( array $components ) {
return sprintf( '#%02x%02x%02x', $components['r'], $components['g'], $components['b'] );
}

/**
 * Parse rgb(a) string.
 *
 * @param string $rgb RGB or RGBA string.
 * @return array|null
 */
public static function parse_rgb( $rgb ) {
if ( ! preg_match( '/rgba?\((.*)\)/i', $rgb, $matches ) ) {
return null;
}

$parts = explode( ',', $matches[1] );
$parts = array_map( 'trim', $parts );

if ( count( $parts ) < 3 ) {
return null;
}

$r = min( 255, max( 0, (int) $parts[0] ) );
$g = min( 255, max( 0, (int) $parts[1] ) );
$b = min( 255, max( 0, (int) $parts[2] ) );

$alpha = null;
if ( isset( $parts[3] ) ) {
$alpha = floatval( $parts[3] );
}

return array(
'r' => $r,
'g' => $g,
'b' => $b,
'a' => $alpha,
);
}

/**
 * Convert hex to rgb array.
 *
 * @param string $hex Hex string.
 * @return array
 */
public static function hex_to_rgb( $hex ) {
$hex = ltrim( self::expand_hex( $hex ), '#' );

return array(
'r' => hexdec( substr( $hex, 0, 2 ) ),
'g' => hexdec( substr( $hex, 2, 2 ) ),
'b' => hexdec( substr( $hex, 4, 2 ) ),
);
}

/**
 * Preserve formatting of the original color.
 *
 * @param string $original Original color string.
 * @param string $target Canonical target (hex or rgb string validated).
 * @return string
 */
    public static function apply_format( $original, $target ) {
        $original              = trim( $original );
        $canonical_target      = self::canonicalize( $target );
        $target_alpha_segment  = null;

        if ( $canonical_target && false !== strpos( $canonical_target, '@' ) ) {
            list( $canonical_target, $target_alpha_segment ) = explode( '@', $canonical_target, 2 );
        }

        if ( preg_match( self::HEX_REGEX, $original, $matches ) ) {
            if ( ! $canonical_target ) {
                $canonical_target = self::canonicalize( $matches[0] );
            }

            if ( ! $canonical_target ) {
                return $matches[0];
            }

            $hex      = self::expand_hex( $canonical_target );
            $original = $matches[0];
            if ( strlen( ltrim( $original, '#' ) ) === 3 ) {
                $hex = self::compress_hex( $hex );
            }

            if ( strtoupper( $original ) === $original ) {
                return strtoupper( $hex );
            }

            if ( $original === strtolower( $original ) ) {
                return strtolower( $hex );
            }

            return $hex;
        }

        if ( preg_match( self::RGB_REGEX, $original ) ) {
            $components = self::parse_rgb( $original );
            if ( ! $components ) {
                return $original;
            }

            $target_alpha = null;
            if ( 0 === strpos( strtolower( $target ), '#' ) ) {
                $rgb = self::hex_to_rgb( $target );
            } else {
                $rgb = self::parse_rgb( $target );
                if ( $rgb && isset( $rgb['a'] ) ) {
                    $target_alpha = $rgb['a'];
                }
            }

            if ( ! $rgb ) {
                return $original;
            }

            if ( null === $target_alpha && null !== $target_alpha_segment && '' !== $target_alpha_segment ) {
                $target_alpha = (float) $target_alpha_segment;
            }

            $values    = array( (string) ( $rgb['r'] ?? $components['r'] ), (string) ( $rgb['g'] ?? $components['g'] ), (string) ( $rgb['b'] ?? $components['b'] ) );
            $has_alpha = false !== stripos( $original, 'rgba' );
            if ( $has_alpha ) {
                $alpha     = null !== $target_alpha ? $target_alpha : ( $components['a'] ?? 1 );
                $values[] = self::format_alpha( $alpha );
            }

            $formatted = preg_replace_callback(
                '/(0?\.\d+|\d+)/',
                function( $match ) use ( &$values ) {
                    if ( empty( $values ) ) {
                        return $match[0];
                    }
                    return array_shift( $values );
                },
                $original,
                $has_alpha ? 4 : 3
            );

            return $formatted;
        }

        return $target;
    }

    /**
     * Format alpha channel value consistently.
     *
     * @param float $alpha Alpha component.
     * @return string
     */
    private static function format_alpha( $alpha ) {
        $alpha = max( 0, min( 1, (float) $alpha ) );

        if ( abs( $alpha - round( $alpha ) ) < 0.0005 ) {
            return (string) (int) round( $alpha );
        }

        return rtrim( rtrim( sprintf( '%.3f', $alpha ), '0' ), '.' );
    }

/**
 * Sanitize target color.
 *
 * @param string $color Color input.
 * @return string|WP_Error
 */
public static function sanitize_target_color( $color ) {
$color = trim( $color );
if ( '' === $color ) {
return new WP_Error( 'ucc_invalid_color', __( 'Color is required.', 'universal-color-changer' ) );
}

$hex = sanitize_hex_color( $color );
if ( $hex ) {
return $hex;
}

if ( preg_match( '/^rgba?\((.*)\)$/i', $color, $matches ) ) {
$parts = array_map( 'trim', explode( ',', $matches[1] ) );
if ( count( $parts ) < 3 ) {
return new WP_Error( 'ucc_invalid_color', __( 'Invalid RGB color.', 'universal-color-changer' ) );
}

foreach ( array_slice( $parts, 0, 3 ) as $index => $part ) {
if ( '' === $part || ! is_numeric( $part ) ) {
return new WP_Error( 'ucc_invalid_color', __( 'RGB values must be numbers.', 'universal-color-changer' ) );
}

$value = (int) $part;
if ( $value < 0 || $value > 255 ) {
return new WP_Error( 'ucc_invalid_color', __( 'RGB values must be between 0 and 255.', 'universal-color-changer' ) );
}
}

if ( isset( $parts[3] ) ) {
$alpha = (float) $parts[3];
if ( $alpha < 0 || $alpha > 1 ) {
return new WP_Error( 'ucc_invalid_color', __( 'Alpha channel must be between 0 and 1.', 'universal-color-changer' ) );
}
}

return strtolower( $color );
}

return new WP_Error( 'ucc_invalid_color', __( 'Unsupported color format.', 'universal-color-changer' ) );
}
}
