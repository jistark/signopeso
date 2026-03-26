<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_id = $block->context['postId'] ?? get_the_ID();
$count      = (int) ( $attributes['count'] ?? 4 );

// Mix popular + random for format variety.
$popular_ids = sp_get_popular_posts( $count + 2, 30 );
$popular_ids = array_filter( $popular_ids, function( $id ) use ( $current_id ) {
    return (int) $id !== (int) $current_id;
} );

// Fill with random recent posts to ensure format mix (cached to avoid ORDER BY RAND() per page view).
$exclude   = array_merge( array_values( $popular_ids ), array( $current_id ) );
$cache_key = 'sp_recirc_rand_' . $current_id;
$random    = get_transient( $cache_key );
if ( false === $random ) {
    $random = get_posts( array(
        'numberposts' => $count,
        'post_status' => 'publish',
        'exclude'     => $exclude, // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
        'orderby'     => 'rand',
        'fields'      => 'ids',
    ) );
    set_transient( $cache_key, $random, 5 * MINUTE_IN_SECONDS );
}

// Interleave popular and random for variety.
$all_ids = array();
$pop     = array_values( $popular_ids );
$rnd     = array_values( $random );
$pi      = 0;
$ri      = 0;
for ( $i = 0; $i < $count; $i++ ) {
    if ( $i % 2 === 0 && isset( $pop[ $pi ] ) ) {
        $all_ids[] = $pop[ $pi++ ];
    } elseif ( isset( $rnd[ $ri ] ) ) {
        $all_ids[] = $rnd[ $ri++ ];
    } elseif ( isset( $pop[ $pi ] ) ) {
        $all_ids[] = $pop[ $pi++ ];
    }
}

$all_ids = array_unique( $all_ids );
$all_ids = array_slice( $all_ids, 0, $count );

if ( empty( $all_ids ) ) {
    return;
}
?>

<section class="sp-recirculation">
    <div class="sp-recirculation__inner" data-exclude="<?php echo esc_attr( $current_id ); ?>">
        <h2 class="sp-date-stream__date sp-recirculation__pill">
            <span class="sp-date-stream__arrow">↓</span>&nbsp;<span class="sp-date-stream__label">sigue explorando</span>
        </h2>

        <div class="sp-recirculation__cards">
        <?php
        foreach ( $all_ids as $pid ) :
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
            echo $card_block->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        endforeach;
        ?>
        </div>
    </div>
</section>
