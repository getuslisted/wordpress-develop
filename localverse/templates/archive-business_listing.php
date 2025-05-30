<?php
/**
 * The template for displaying archive pages for Business Listings.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package LocalVerse
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

    <?php if ( have_posts() ) : ?>

        <header class="page-header">
            <?php
                // the_archive_title( '<h1 class="page-title">', '</h1>' );
                // the_archive_description( '<div class="archive-description">', '</div>' );
                // For CPT, we might want a static title or one from settings
                echo '<h1 class="page-title">' . esc_html_x( 'Business Listings', 'archive title', 'localverse' ) . '</h1>';
            ?>
        </header><!-- .page-header -->

        <?php
        /* Start the Loop */
        while ( have_posts() ) :
            the_post();

            /**
             * Include the Post-Format-specific template for the content.
             * If you want to override this in a child theme, then include a file
             * called content-___.php (where ___ is the Post Format) and that will be used instead.
             * For now, we'll try to load content-listing.php or a part from our templates dir.
             */
            // This will be adjusted by the template loader later to point to /templates/listing-loop.php
            // get_template_part( 'template-parts/content', 'listing' ); // Placeholder, will be fixed by loader
            // MODIFIED according to instructions:
            localverse_get_template_part( 'listing', 'loop' );

        endwhile;

        the_posts_navigation();

    else :

        get_template_part( 'template-parts/content', 'none' );

    endif;
    ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
