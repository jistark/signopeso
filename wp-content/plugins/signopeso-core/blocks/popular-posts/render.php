<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$period   = (int) ( $attributes['period'] ?? 7 );
$count    = (int) ( $attributes['count'] ?? 5 );
$post_ids = sp_get_popular_posts( $count, $period );

if ( empty( $post_ids ) ) {
    return;
}
?>

<div class="sp-popular-posts">
    <h4 class="sp-popular-posts__heading">Lo Popular</h4>
    <ol class="sp-popular-posts__list">
        <?php foreach ( $post_ids as $index => $pid ) :
            $title = get_the_title( $pid );
            $link  = get_permalink( $pid );
            if ( ! $title ) continue;
        ?>
            <li class="sp-popular-posts__item">
                <span class="sp-popular-posts__rank"><?php echo esc_html( str_pad( $index + 1, 2, '0', STR_PAD_LEFT ) ); ?></span>
                <a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
            </li>
        <?php endforeach; ?>
    </ol>
</div>
