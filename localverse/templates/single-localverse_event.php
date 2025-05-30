<?php
/**
 * The template for displaying a single LocalVerse Event.
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

global $post; // Make $post object available

// It would be good practice to create a LocalVerse_Event model class
// similar to LocalVerse_Listing to handle data fetching and formatting.
// For now, we'll use get_post_meta directly in the template.

$event_id = $post->ID;

$start_datetime_str = get_post_meta( $event_id, '_lv_event_start_datetime', true );
$end_datetime_str   = get_post_meta( $event_id, '_lv_event_end_datetime', true );
$location_name      = get_post_meta( $event_id, '_lv_event_location_name', true );
$location_address   = get_post_meta( $event_id, '_lv_event_location_address', true );
$event_type         = get_post_meta( $event_id, '_lv_event_type', true );

// Basic date/time formatting (can be enhanced)
$formatted_start_datetime = '';
if ( $start_datetime_str ) {
    try {
        $start_dt = new DateTime( $start_datetime_str );
        // Example format: January 1, 2024, 6:30 PM (uses WordPress date and time format settings)
        $formatted_start_datetime = date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $start_dt->getTimestamp() );
    } catch (Exception $e) {
        $formatted_start_datetime = esc_html( $start_datetime_str ); // Fallback to raw string if format is unexpected
    }
}

$formatted_end_datetime = '';
if ( $end_datetime_str ) {
    try {
        $end_dt = new DateTime( $end_datetime_str );
        $formatted_end_datetime = date_i18n( get_option('date_format') . ' ' . get_option('time_format'), $end_dt->getTimestamp() );
    } catch (Exception $e) {
        $formatted_end_datetime = esc_html( $end_datetime_str ); // Fallback
    }
}

?>
<div id="primary" class="content-area localverse-single-event">
    <main id="main" class="site-main" role="main">
        <article id="post-<?php echo esc_attr( $event_id ); ?>" <?php post_class( '', $event_id ); ?>>
            <header class="entry-header">
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="event-featured-image">
                        <?php the_post_thumbnail('large'); ?>
                    </div>
                <?php endif; ?>
                <?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
            </header><!-- .entry-header -->

            <div class="entry-content event-details">
                <?php if ( $formatted_start_datetime ) : ?>
                    <p class="event-datetime">
                        <strong><?php _e( 'When:', 'localverse' ); ?></strong>
                        <?php echo $formatted_start_datetime; ?>
                        <?php if ( $formatted_end_datetime ) : ?>
                            <?php _e( 'to', 'localverse' ); ?> <?php echo $formatted_end_datetime; ?>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>

                <?php if ( $location_name ) : ?>
                    <p class="event-location-name">
                        <strong><?php _e( 'Location:', 'localverse' ); ?></strong> <?php echo esc_html( $location_name ); ?>
                    </p>
                <?php endif; ?>

                <?php if ( $location_address ) : ?>
                    <p class="event-location-address">
                        <strong><?php _e( 'Address/Details:', 'localverse' ); ?></strong><br>
                        <?php echo nl2br( esc_html( $location_address ) ); ?>
                    </p>
                <?php endif; ?>

                <?php if ( $event_type ) : ?>
                    <p class="event-type">
                        <strong><?php _e( 'Event Type:', 'localverse' ); ?></strong> <?php echo esc_html( $event_type ); ?>
                    </p>
                <?php endif; ?>

                <hr style="margin: 20px 0;">

                <?php the_content(); // Main event description ?>

            </div><!-- .entry-content -->

            <footer class="entry-footer">
                <?php // edit_post_link( ... ); ?>
            </footer><!-- .entry-footer -->
        </article><!-- #post-## -->

        <?php
        // If comments are open or we have at least one comment, load up the comment template.
        if ( comments_open() || get_comments_number() ) :
            comments_template();
        endif;
        ?>
    </main><!-- #main -->
</div><!-- #primary -->
<style>
    /* Basic styling for event details - move to CSS file */
    .localverse-single-event .event-featured-image { margin-bottom: 20px; }
    .localverse-single-event .event-featured-image img { max-width: 100%; height: auto; }
    .localverse-single-event .event-details p { margin-bottom: 10px; }
    .localverse-single-event .event-details strong { font-weight: bold; }
</style>
<?php get_sidebar(); ?>
<?php get_footer(); ?>
