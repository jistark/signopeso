<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get popular posts, preferring Jetpack stats with fallback.
 */
function sp_get_popular_posts( $count = 5, $period = 7 ) {
    $cache_key = "sp_popular_{$period}d_{$count}";
    $cached    = get_transient( $cache_key );

    if ( false !== $cached ) {
        return $cached;
    }

    $post_ids = array();

    // Try Jetpack Stats.
    if ( function_exists( 'stats_get_csv' ) ) {
        $stats = stats_get_csv( 'postviews', array(
            'days'    => $period,
            'limit'   => $count,
            'post_id' => '',
        ) );

        if ( ! empty( $stats ) ) {
            foreach ( $stats as $stat ) {
                if ( ! empty( $stat['post_id'] ) && 0 !== (int) $stat['post_id'] ) {
                    $post_ids[] = (int) $stat['post_id'];
                }
            }
        }
    }

    // Fallback: most commented.
    if ( empty( $post_ids ) ) {
        $query = new WP_Query( array(
            'post_type'      => 'post',
            'posts_per_page' => $count,
            'orderby'        => 'comment_count',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );
        $post_ids = $query->posts;
    }

    set_transient( $cache_key, $post_ids, HOUR_IN_SECONDS );

    return $post_ids;
}
