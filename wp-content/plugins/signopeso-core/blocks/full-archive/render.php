<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$years = get_transient( 'sp_full_archive' );
if ( false === $years ) {
    $years = array();
    $posts = get_posts( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'fields'         => 'ids',
    ) );

    foreach ( $posts as $pid ) {
        $year  = get_the_date( 'Y', $pid );
        $month = get_the_date( 'F', $pid );
        $years[ $year ][ $month ][] = $pid;
    }
    set_transient( 'sp_full_archive', $years, HOUR_IN_SECONDS );
}

$current_year = gmdate( 'Y' );
?>

<div class="sp-full-archive">
    <?php foreach ( $years as $year => $months ) :
        $open = ( $year === $current_year ) ? 'open' : '';
    ?>
        <details class="sp-full-archive__year" <?php echo esc_attr( $open ); ?>>
            <summary class="sp-full-archive__year-heading"><?php echo esc_html( $year ); ?></summary>
            <?php foreach ( $months as $month => $pids ) : ?>
                <div class="sp-full-archive__month">
                    <h4 class="sp-full-archive__month-heading"><?php echo esc_html( $month ); ?></h4>
                    <ul class="sp-full-archive__list">
                        <?php foreach ( $pids as $pid ) :
                            $formats = wp_get_object_terms( $pid, 'sp_formato', array( 'fields' => 'slugs' ) );
                            $fmt     = ! empty( $formats ) ? $formats[0] : 'corto';
                        ?>
                            <li>
                                <span class="sp-post-card__label"><?php echo esc_html( ucfirst( $fmt ) ); ?></span>
                                <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a>
                                <span class="sp-full-archive__date"><?php echo esc_html( get_the_date( 'j M', $pid ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </details>
    <?php endforeach; ?>
</div>
