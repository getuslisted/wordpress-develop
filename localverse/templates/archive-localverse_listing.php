<?php
/**
 * The template for displaying archive pages for LocalVerse Listings.
 *
 * @link https://example.com/
 * @since 0.1.0
 * @package LocalVerse
 * @subpackage LocalVerse/templates
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

get_header();

// Optional: Include the Listing model if not already loaded,
// especially if methods from it are called directly here or for setup.
// require_once LOCALVERSE_PLUGIN_DIR . 'includes/models/Listing.php';
?>
<div id="primary" class="content-area localverse-listing-archive">
    <main id="main" class="site-main" role="main">

        <?php if ( have_posts() ) : ?>

            <header class="page-header">
                <?php
                    // Use post_type_archive_title() for CPT archives
                    post_type_archive_title( '<h1 class="page-title">', '</h1>' );
                    // Display archive description if it exists
                    the_archive_description( '<div class="archive-description">', '</div>' );
                ?>
            </header><!-- .page-header -->

            <div class="localverse-listings-wrapper">
                <?php
                // Start the Loop.
                while ( have_posts() ) :
                    the_post();

                    /**
                     * Include the Post-Format-specific template for the content.
                     * If you want to override this in a child theme, then include a file
                     * called content-___.php (where ___ is the Post Format name) and that will be used instead.
                     *
                     * For LocalVerse, we'll directly include our loop item template.
                     */

                    // Ensure the path to the template part is correct.
                    // LOCALVERSE_PLUGIN_DIR should be defined in the main plugin file.
                    $template_path = LOCALVERSE_PLUGIN_DIR . 'templates/listing-loop-item.php';
                    if ( file_exists( $template_path ) ) {
                        include( $template_path );
                    } else {
                        // Fallback if template is missing
                        echo '<p>Error: Listing loop item template not found.</p>';
                    }

                endwhile;
                ?>
            </div><!-- .localverse-listings-wrapper -->
            <?php

            // Previous/next page navigation.
            the_posts_pagination(
                array(
                    'prev_text'          => __( 'Previous page', 'localverse' ),
                    'next_text'          => __( 'Next page', 'localverse' ),
                    'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'localverse' ) . ' </span>',
                )
            );

        // If no content, include the "No posts found" template.
        else :
            // This could be a generic "no posts found" template part or inline message
            ?>
            <section class="no-results not-found">
                <header class="page-header">
                    <h1 class="page-title"><?php _e( 'Nothing Found', 'localverse' ); ?></h1>
                </header><!-- .page-header -->

                <div class="page-content">
                    <?php if ( is_home() && current_user_can( 'publish_posts' ) ) : ?>
                        <p>
                        <?php
                        printf(
                            /* translators: 1: link to WP admin new post page. */
                            __( 'Ready to publish your first post? <a href="%1$s">Get started here</a>.', 'localverse' ),
                            esc_url( admin_url( 'post-new.php' ) )
                        );
                        ?>
                        </p>
                    <?php elseif ( is_search() ) : ?>
                        <p><?php _e( 'Sorry, but nothing matched your search terms. Please try again with some different keywords.', 'localverse' ); ?></p>
                        <?php get_search_form(); ?>
                    <?php else : ?>
                        <p><?php _e( 'It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.', 'localverse' ); ?></p>
                        <?php get_search_form(); ?>
                    <?php endif; ?>
                </div><!-- .page-content -->
            </section><!-- .no-results -->
            <?php
        endif;
        ?>
    </main><!-- #main -->
</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
