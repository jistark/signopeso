<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_id = $block->context['postId'] ?? get_the_ID();
$count      = (int) ( $attributes['count'] ?? 4 );

// Get popular posts, excluding current.
$popular_ids = sp_get_popular_posts( $count + 1, 30 );
$popular_ids = array_filter( $popular_ids, function( $id ) use ( $current_id ) {
    return (int) $id !== (int) $current_id;
} );
$popular_ids = array_slice( $popular_ids, 0, $count );

// If not enough popular posts, fill with recent.
if ( count( $popular_ids ) < $count ) {
    $exclude = array_merge( $popular_ids, array( $current_id ) );
    $recent  = get_posts( array(
        'numberposts'  => $count - count( $popular_ids ),
        'post_status'  => 'publish',
        'exclude'      => $exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Small array (max 5 IDs).
        'orderby'      => 'date',
        'order'        => 'DESC',
        'fields'       => 'ids',
    ) );
    $popular_ids = array_merge( $popular_ids, $recent );
}

if ( empty( $popular_ids ) ) {
    return;
}
?>

<section class="sp-recirculation">
    <div class="sp-recirculation__inner">
        <h2 class="sp-recirculation__heading">sigue explorando</h2>

        <?php
        // Render each post through the actual sp/post-card block.
        // This guarantees identical output to the homepage river.
        foreach ( $popular_ids as $pid ) :
            $card_block = new WP_Block(
                array(
                    'blockName'    => 'sp/post-card',
                    'attrs'        => array(),
                    'innerBlocks'  => array(),
                    'innerHTML'    => '',
                    'innerContent' => array(),
                ),
                array( 'postId' => $pid )
            );
            echo $card_block->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Block render output is pre-escaped.
        endforeach;
        ?>
    </div>
</section>
