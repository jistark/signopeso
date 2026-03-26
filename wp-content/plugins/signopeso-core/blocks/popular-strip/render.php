<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$count    = (int) ( $attributes['count'] ?? 5 );
$period   = (int) ( $attributes['period'] ?? 7 );
$post_ids = sp_get_popular_posts( $count, $period );

if ( empty( $post_ids ) ) {
    return;
}
?>

<div class="sp-popular-strip">
    <div class="sp-popular-strip__inner">
        <span class="sp-section-label">populares</span>
        <div class="sp-popular-strip__items">
            <?php foreach ( $post_ids as $index => $pid ) :
                $title = get_the_title( $pid );
                $link  = get_permalink( $pid );
                if ( ! $title ) continue;
            ?>
                <span class="sp-popular-strip__item">
                    <span class="sp-popular-strip__rank"><?php echo esc_html( str_pad( $index + 1, 2, '0', STR_PAD_LEFT ) ); ?></span>
                    <a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>
