<?php
/**
 * Leaderboard template.
 *
 * @var array $entries
 */

if ( empty( $entries ) ) {
    echo '<div class="rq-empty">' . esc_html__( 'No leaderboard data yet. Take a quiz to earn points!', 'relationship-quizzr' ) . '</div>';
    return;
}
?>
<div class="rq-leaderboard table-responsive">
    <table class="table table-striped align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th scope="col"><?php esc_html_e( '#', 'relationship-quizzr' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Player', 'relationship-quizzr' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Points', 'relationship-quizzr' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Level', 'relationship-quizzr' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $entries as $index => $entry ) : ?>
                <tr>
                    <th scope="row"><?php echo esc_html( $index + 1 ); ?></th>
                    <td>
                        <div class="d-flex align-items-center">
                            <?php echo get_avatar( $entry['user_id'], 40, '', '', array( 'class' => 'rounded-circle me-3' ) ); ?>
                            <span><?php echo esc_html( $entry['name'] ); ?></span>
                        </div>
                    </td>
                    <td><strong><?php echo esc_html( $entry['points'] ); ?></strong></td>
                    <td><?php echo esc_html( $entry['level'] ); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
