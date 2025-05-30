<?php
/**
 * The template for displaying a single listing item in a loop.
 *
 * @link https://example.com/
 * @since 0.1.0
 * @package LocalVerse
 * @subpackage LocalVerse/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Assuming $post is the current post in the loop, or $listing object is passed.
// If LocalVerse_Listing class is not autoloaded, ensure it's included before use.
// require_once LOCALVERSE_PLUGIN_DIR . 'includes/models/Listing.php';
$listing_item = new LocalVerse_Listing( get_the_ID() ); // Create an instance for the current post in the loop

if ( ! $listing_item->is_valid() ) {
    return; // Don't display if not a valid listing
}
?>
<article id="post-<?php echo esc_attr( $listing_item->id ); ?>" <?php post_class( 'localverse-listing-loop-item', $listing_item->id ); ?>>

    <div class="listing-item-thumbnail">
        <a href="<?php echo esc_url( $listing_item->get_permalink() ); ?>">
            <?php
            // Display featured image if available, otherwise a placeholder
            if ( has_post_thumbnail( $listing_item->id ) ) {
                // Use the LocalVerse_Listing model method for consistency if preferred, or standard WP function
                echo $listing_item->get_featured_image_html('medium'); // Or 'thumbnail', 'medium_large', etc.
            } else {
                // Placeholder image or content
                // You can use plugin_dir_url( __FILE__ ) to get base URL for plugin assets if needed
                // For example: echo '<img src="' . esc_url( LOCALVERSE_PLUGIN_URL . 'assets/images/placeholder-medium.png' ) . '" alt="' . esc_attr__( 'No image available', 'localverse' ) . '">';
                echo '<div class="listing-item-no-thumbnail">' . esc_html__( 'No image', 'localverse' ) . '</div>';
            }
            ?>
        </a>
    </div>

    <div class="listing-item-content">
        <header class="listing-item-header">
            <h2 class="listing-item-title">
                <a href="<?php echo esc_url( $listing_item->get_permalink() ); ?>"><?php echo esc_html( $listing_item->get_title() ); ?></a>
                <?php if ( $listing_item->is_verified() ) : ?>
                    <span class="localverse-verified-badge-loop" style="color: #155724; font-size: 0.9em; margin-left: 8px; display: inline-block; vertical-align: middle;" title="<?php esc_attr_e('Verified Listing', 'localverse'); ?>">✔</span>
                <?php endif; ?>
            </h2>
        </header><!-- .listing-item-header -->

        <div class="listing-item-summary">
            <?php
            // Display excerpt. The model could have a get_excerpt() method too.
            // For now, using WordPress standard the_excerpt() might require some setup if outside main loop.
            // Let's use a custom excerpt generated from content if post->excerpt is empty.
            $excerpt = !empty($listing_item->post->post_excerpt) ? $listing_item->post->post_excerpt : wp_trim_words($listing_item->post->post_content, 20, '...');
            echo '<p>' . esc_html( $excerpt ) . '</p>';
            ?>
        </div><!-- .listing-item-summary -->

        <div class="listing-item-meta">
            <?php if ( $city = $listing_item->get_address_city() ) : ?>
                <span class="listing-item-city"><?php _e('City:','localverse');?> <?php echo esc_html( $city ); ?></span>
            <?php endif; ?>

            <?php
            // Displaying the first category
            $categories = get_the_terms( $listing_item->id, 'listing_category' );
            if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
                $primary_category = $categories[0]; // Get the first category
                echo '<span class="listing-item-category">';
                if ( $city && !empty($categories) ) { echo ' | '; } // Separator
                echo esc_html__( 'Category: ', 'localverse' );
                echo '<a href="' . esc_url( get_term_link( $primary_category ) ) . '">' . esc_html( $primary_category->name ) . '</a>';
                echo '</span>';
            }

            // Optionally, display a few tags
            /*
            $tags = get_the_terms( $listing_item->id, 'listing_tag' );
            if ( ! empty( $tags ) && ! is_wp_error( $tags ) ) {
                echo '<span class="listing-item-tags">';
                if ( ($city && !empty($categories)) || !empty($categories) ) { echo ' | '; } // Separator
                echo esc_html__( 'Tags: ', 'localverse' );
                $tag_links = array();
                $i = 0;
                foreach ( $tags as $tag ) {
                    if ($i >= 3) break; // Limit to 3 tags
                    $tag_links[] = '<a href="' . esc_url( get_term_link( $tag ) ) . '">' . esc_html( $tag->name ) . '</a>';
                    $i++;
                }
                echo implode( ', ', $tag_links );
                echo '</span>';
            }
            */
            ?>
        </div><!-- .listing-item-meta -->

    </div><!-- .listing-item-content -->

</article><!-- #post-## -->
