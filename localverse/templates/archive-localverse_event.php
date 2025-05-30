<?php
/**
 * The template for displaying archive pages for LocalVerse Events.
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
?>
<div id="primary" class="content-area localverse-event-archive">
    <main id="main" class="site-main" role="main">

        <?php if ( have_posts() ) : ?>

            <header class="page-header">
                <?php post_type_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
                <?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
            </header><!-- .page-header -->

            <div class="localverse-events-wrapper">
                <?php
                // Start the Loop.
                while ( have_posts() ) :
                    the_post();

                    // It's good practice to create a template part for the loop item,
                    // e.g., 'template-parts/content-event.php' or similar.
                    // For simplicity here, we'll include the item's markup directly.

                    $event_id = get_the_ID();
                    $start_datetime_str = get_post_meta( $event_id, '_lv_event_start_datetime', true );
                    $location_name      = get_post_meta( $event_id, '_lv_event_location_name', true );
                    $formatted_start_date = '';
                    if ( $start_datetime_str ) {
                        try {
                            $start_dt = new DateTime( $start_datetime_str );
                            // Just the date for archive view, or date & time if preferred
                            $formatted_start_date = date_i18n( get_option('date_format'), $start_dt->getTimestamp() );
                        } catch (Exception $e) {
                            // Fallback or leave empty
                        }
                    }
                ?>
                    <article id="post-<?php echo esc_attr($event_id); ?>" <?php post_class('localverse-event-loop-item'); ?>>
                        <header class="entry-header">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <div class="event-loop-thumbnail">
                                    <a href="<?php the_permalink(); ?>">
                                        <?php the_post_thumbnail('medium'); // Or 'thumbnail' ?>
                                    </a>
                                </div>
                            <?php endif; ?>
                            <?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
                        </header><!-- .entry-header -->

                        <div class="entry-summary">
                            <?php if ( $formatted_start_date ) : ?>
                                <p class="event-loop-date">
                                    <strong><?php _e( 'Date:', 'localverse' ); ?></strong> <?php echo $formatted_start_date; ?>
                                </p>
                            <?php endif; ?>
                            <?php if ( $location_name ) : ?>
                                <p class="event-loop-location">
                                    <strong><?php _e( 'Location:', 'localverse' ); ?></strong> <?php echo esc_html( $location_name ); ?>
                                </p>
                            <?php endif; ?>
                            <?php the_excerpt(); ?>
                        </div><!-- .entry-summary -->

                        <footer class="entry-footer">
                             <a href="<?php the_permalink(); ?>" class="read-more-button"><?php _e('View Event Details', 'localverse'); ?></a>
                        </footer>

                    </article><!-- #post-## -->
                <?php
                endwhile;
                ?>
            </div><!-- .localverse-events-wrapper -->
            <?php

            the_posts_pagination( array(
                'prev_text'          => __( '&laquo; Previous Events', 'localverse' ),
                'next_text'          => __( 'Next Events &raquo;', 'localverse' ),
                'before_page_number' => '<span class="meta-nav screen-reader-text">' . __( 'Page', 'localverse' ) . ' </span>',
            ) );

        else :
            // If no content, include a "No posts found" message or template part.
            ?>
            <section class="no-results not-found">
                <header class="page-header"><h1 class="page-title"><?php _e( 'No Events Found', 'localverse' ); ?></h1></header>
                <div class="page-content"><p><?php _e( 'It seems there are no upcoming or past events matching your criteria.', 'localverse' ); ?></p></div>
            </section>
            <?php
        endif;
        ?>
    </main><!-- #main -->
</div><!-- #primary -->
<style>
    /* Basic styling for event archive - move to CSS file */
    .localverse-event-archive .localverse-event-loop-item { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
    .localverse-event-archive .event-loop-thumbnail { float: left; margin-right: 20px; margin-bottom: 10px; max-width: 200px;}
    .localverse-event-archive .event-loop-thumbnail img { max-width: 100%; height: auto; }
    .localverse-event-archive .entry-summary p { margin-bottom: 5px; }
    .localverse-event-archive .read-more-button { display: inline-block; margin-top: 10px; padding: 8px 15px; background-color: #0073aa; /* Example color */ color: white; text-decoration: none; border-radius: 3px; }
    .localverse-event-archive .read-more-button:hover { background-color: #005177; }
    /* Clearfix for floated thumbnail */
    .localverse-event-loop-item::after { content: ""; display: table; clear: both; }
</style>
<?php get_sidebar(); ?>
<?php get_footer(); ?>
