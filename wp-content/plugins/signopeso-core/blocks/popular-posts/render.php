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
    <h4 class="sp-popular-posts__heading">Lo popular</h4>
    <ul class="sp-popular-posts__list">
        <?php foreach ( $post_ids as $pid ) :
            $title = get_the_title( $pid );
            $link  = get_permalink( $pid );
            if ( ! $title ) continue;
        ?>
            <li class="sp-popular-posts__item">
                <a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
