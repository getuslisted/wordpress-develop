<?php
/**
 * Profile card template.
 *
 * @var array $profile
 */

if ( empty( $profile ) ) {
    echo '<div class="rq-empty">' . esc_html__( 'Profile unavailable.', 'relationship-quizzr' ) . '</div>';
    return;
}
?>
<div class="rq-profile-card">
    <div class="d-flex align-items-center mb-4">
        <img src="<?php echo esc_url( $profile['avatar'] ); ?>" alt="<?php echo esc_attr( $profile['display_name'] ); ?>" class="rounded-circle me-3" width="64" height="64">
        <div>
            <h2 class="h4 mb-0"><?php echo esc_html( $profile['display_name'] ); ?></h2>
            <?php if ( ! empty( $profile['pronouns'] ) ) : ?>
                <p class="text-muted mb-0"><?php echo esc_html( $profile['pronouns'] ); ?></p>
            <?php endif; ?>
            <?php if ( ! empty( $profile['location'] ) ) : ?>
                <p class="text-muted mb-0"><?php echo esc_html( $profile['location'] ); ?></p>
            <?php endif; ?>
        </div>
    </div>
    <?php if ( ! empty( $profile['bio'] ) ) : ?>
        <p class="mb-4"><?php echo wp_kses_post( wpautop( $profile['bio'] ) ); ?></p>
    <?php endif; ?>
    <div class="row text-center mb-4">
        <div class="col">
            <div class="fw-bold h4 mb-0"><?php echo esc_html( $profile['quizzes_taken'] ); ?></div>
            <span class="text-muted small"><?php esc_html_e( 'Quizzes played', 'relationship-quizzr' ); ?></span>
        </div>
        <div class="col">
            <div class="fw-bold h4 mb-0"><?php echo esc_html( $profile['compatibility'] ); ?>%</div>
            <span class="text-muted small"><?php esc_html_e( 'Average compatibility', 'relationship-quizzr' ); ?></span>
        </div>
        <div class="col">
            <div class="fw-bold h4 mb-0"><?php echo esc_html( $profile['favorite_topic'] ); ?></div>
            <span class="text-muted small"><?php esc_html_e( 'Favorite topic', 'relationship-quizzr' ); ?></span>
        </div>
    </div>
    <?php if ( ! empty( $profile['badges'] ) ) : ?>
        <div class="mb-3">
            <h3 class="h6 text-uppercase text-muted"><?php esc_html_e( 'Badges', 'relationship-quizzr' ); ?></h3>
            <?php foreach ( $profile['badges'] as $badge ) : ?>
                <span class="rq-badge"><?php echo esc_html( $badge['label'] ); ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
