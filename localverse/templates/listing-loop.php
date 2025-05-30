<?php
/**
 * Business Listing Loop Item Template
 *
 * @package LocalVerse
 */
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <header class="entry-header">
        <?php the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' ); ?>
    </header><!-- .entry-header -->

    <div class="entry-summary">
        <?php the_excerpt(); ?>
    </div><!-- .entry-summary -->

    <div class="entry-meta">
        <!-- Placeholder for categories, tags, etc. -->
    </div><!-- .entry-meta -->
</article><!-- #post-<?php the_ID(); ?> -->
