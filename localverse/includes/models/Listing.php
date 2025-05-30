<?php
/**
 * LocalVerse Listing Model
 *
 * Represents a business listing within the LocalVerse plugin.
 * This class will be responsible for handling data related to listings,
 * such as retrieving custom fields, managing status (verified/unverified), etc.
 *
 * @link       https://example.com
 * @since      0.1.0
 *
 * @package    LocalVerse
 * @subpackage LocalVerse/includes/models
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class LocalVerse_Listing {

    /**
     * The ID of this listing (WP_Post ID).
     *
     * @since    0.1.0
     * @access   public
     * @var      int
     */
    public $id;

    /**
     * The WP_Post object for this listing.
     *
     * @since    0.1.0
     * @access   public
     * @var      WP_Post|null
     */
    public $post;

    /**
     * Cached meta data for the listing.
     * @since 0.1.0
     * @access private
     * @var array
     */
    private $meta_cache = array();

    /**
     * Constructor.
     *
     * @since 0.1.0
     * @param int|WP_Post $listing_id The ID or WP_Post object of the listing.
     */
    public function __construct( $listing_id = 0 ) {
        if ( $listing_id instanceof WP_Post ) {
            $this->id   = $listing_id->ID;
            $this->post = $listing_id;
        } elseif ( is_numeric( $listing_id ) && $listing_id > 0 ) {
            $this->id   = intval( $listing_id );
            $this->post = get_post( $this->id );
        }

        // Ensure the post type is correct
        if ( ! $this->post || 'localverse_listing' !== $this->post->post_type ) {
            $this->id   = 0;
            $this->post = null;
        }
    }

    /**
     * Check if the listing is valid.
     *
     * @since 0.1.0
     * @return bool True if the listing is valid, false otherwise.
     */
    public function is_valid() {
        return ! empty( $this->post ) && $this->id > 0;
    }

    /**
     * Get a specific meta value for the listing.
     * Uses a simple cache to avoid multiple get_post_meta calls for the same key.
     *
     * @since 0.1.0
     * @param string $key The meta key.
     * @param bool $single Whether to return a single value.
     * @return mixed The meta value(s).
     */
    protected function get_meta( $key, $single = true ) {
        if ( ! $this->is_valid() ) {
            return null;
        }
        if ( array_key_exists( $key, $this->meta_cache ) ) {
            return $this->meta_cache[$key];
        }
        $value = get_post_meta( $this->id, $key, $single );
        $this->meta_cache[$key] = $value;
        return $value;
    }

    /**
     * Get the title of the listing.
     *
     * @since 0.1.0
     * @return string The title of the listing.
     */
    public function get_title() {
        return $this->is_valid() ? $this->post->post_title : '';
    }

    /**
     * Get the content (description) of the listing.
     *
     * @since 0.1.0
     * @return string The content of the listing.
     */
    public function get_description() {
        return $this->is_valid() ? apply_filters( 'the_content', $this->post->post_content ) : '';
    }

    /**
     * Get the permalink of the listing.
     *
     * @since 0.1.0
     * @return string The permalink of the listing.
     */
    public function get_permalink() {
        return $this->is_valid() ? get_permalink( $this->id ) : '';
    }

    /**
     * Get the featured image ID.
     * @since 0.1.0
     * @return int|false The featured image ID or false if not set.
     */
    public function get_featured_image_id() {
        return $this->is_valid() ? get_post_thumbnail_id( $this->id ) : false;
    }

    /**
     * Get the featured image URL.
     * @since 0.1.0
     * @param string $size The image size to retrieve.
     * @return string The URL of the featured image or empty string.
     */
    public function get_featured_image_url( $size = 'thumbnail' ) {
        if ( ! $this->is_valid() ) return '';
        $image_id = $this->get_featured_image_id();
        if ( $image_id ) {
            return wp_get_attachment_image_url( $image_id, $size );
        }
        return '';
    }

    /**
     * Get the featured image HTML.
     * @since 0.1.0
     * @param string $size The image size to retrieve.
     * @param array $attr Additional attributes for the image tag.
     * @return string The HTML img tag for the featured image or empty string.
     */
    public function get_featured_image_html( $size = 'thumbnail', $attr = array() ) {
        if ( ! $this->is_valid() ) return '';
        return get_the_post_thumbnail( $this->id, $size, $attr );
    }


    // --- Address Methods ---
    public function get_address_street() {
        return $this->get_meta( '_lv_address_street' );
    }

    public function get_address_city() {
        return $this->get_meta( '_lv_address_city' );
    }

    public function get_address_state() {
        return $this->get_meta( '_lv_address_state' );
    }

    public function get_address_zip() {
        return $this->get_meta( '_lv_address_zip' );
    }

    public function get_address_country() {
        return $this->get_meta( '_lv_address_country' );
    }

    /**
     * Get the full formatted address.
     * @since 0.1.0
     * @param string $separator Line separator for address parts. Defaults to ", ".
     * @return string Formatted address.
     */
    public function get_formatted_address( $separator = ', ' ) {
        if ( ! $this->is_valid() ) return '';
        $address_parts = array_filter( array(
            $this->get_address_street(),
            $this->get_address_city(),
            $this->get_address_state(),
            $this->get_address_zip(),
            $this->get_address_country(),
        ) );
        return implode( $separator, $address_parts );
    }

    // --- Contact Methods ---
    public function get_contact_phone() {
        return $this->get_meta( '_lv_contact_phone' );
    }

    public function get_contact_email() {
        return $this->get_meta( '_lv_contact_email' );
    }

    public function get_contact_website_url() {
        return $this->get_meta( '_lv_contact_website' );
    }

    // --- Operating Hours Method ---
    /**
     * Get operating hours.
     * Returns raw value, could be enhanced to parse structured data later.
     * @since 0.1.0
     * @return string Operating hours text.
     */
    public function get_operating_hours() {
        return $this->get_meta( '_lv_operating_hours' );
    }

    /**
     * Get operating hours formatted as HTML (nl2br).
     * @since 0.1.0
     * @return string Formatted operating hours.
     */
    public function get_operating_hours_html() {
        $hours = $this->get_operating_hours();
        return $hours ? nl2br( esc_html( $hours ) ) : '';
    }

    // Add more methods here later for:
    // - Handling verification status
    // - Retrieving images/attachments
    // - Interacting with reviews associated with this listing
}
?>
