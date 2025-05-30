<?php
/**
 * The template for displaying all single Business Listings.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package LocalVerse
 */

get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">

    <?php
    while ( have_posts() ) :
        the_post();

        /**
         * Include the single listing content template.
         * This will be adjusted by the template loader to point to /templates/listing-single.php
         */
        // get_template_part( 'template-parts/content', 'single-listing' ); // Placeholder
        // MODIFIED according to instructions:
        localverse_get_template_part( 'listing', 'single' );

        // Comments template can be added later if reviews are integrated here or separately
        // if ( comments_open() || get_comments_number() ) :
        //  comments_template();
        // endif;

    endwhile; // End of the loop.
    ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php get_sidebar(); ?>
<?php get_footer(); ?>
