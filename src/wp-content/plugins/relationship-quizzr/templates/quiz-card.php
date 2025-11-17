<?php
/**
 * Quiz card template.
 *
 * @var WP_Post $quiz
 */

if ( ! isset( $quiz ) ) {
    return;
}

$permalink  = get_permalink( $quiz );
$categories = wp_get_post_terms( $quiz->ID, 'rq_category', array( 'fields' => 'names' ) );
$excerpt    = $quiz->post_excerpt ? $quiz->post_excerpt : wp_trim_words( $quiz->post_content, 20 );
?>
<div class="col-md-6 col-lg-4">
    <div class="card h-100">
        <?php if ( has_post_thumbnail( $quiz ) ) : ?>
            <a href="<?php echo esc_url( $permalink ); ?>" class="card-img-top d-block overflow-hidden">
                <?php echo get_the_post_thumbnail( $quiz, 'medium_large', array( 'class' => 'img-fluid' ) ); ?>
            </a>
        <?php endif; ?>
        <div class="card-body d-flex flex-column">
            <div class="mb-2">
                <?php foreach ( $categories as $category ) : ?>
                    <span class="badge bg-light text-dark me-1"><?php echo esc_html( $category ); ?></span>
                <?php endforeach; ?>
            </div>
            <h3 class="h5 card-title"><a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $quiz ) ); ?></a></h3>
            <p class="card-text text-muted flex-grow-1"><?php echo esc_html( $excerpt ); ?></p>
            <a href="<?php echo esc_url( $permalink ); ?>" class="btn btn-primary mt-3 align-self-start"><?php esc_html_e( 'Play now', 'relationship-quizzr' ); ?></a>
        </div>
    </div>
</div>
