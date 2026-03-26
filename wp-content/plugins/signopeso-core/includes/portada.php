<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the portada lead post.
 * Priority: sticky → latest largo/cobertura → latest post.
 */
function sp_get_portada_lead() {
    // 1. Sticky posts.
    $sticky = get_option( 'sticky_posts' );
    if ( ! empty( $sticky ) ) {
        $lead = get_posts( array(
            'post__in'       => $sticky,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ) );
        if ( ! empty( $lead ) ) {
            return $lead[0];
        }
    }

    // 2. Latest largo or cobertura.
    $lead = get_posts( array(
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'tax_query'      => array( array(
            'taxonomy' => 'sp_formato',
            'field'    => 'slug',
            'terms'    => array( 'largo', 'cobertura' ),
        ) ),
    ) );
    if ( ! empty( $lead ) ) {
        return $lead[0];
    }

    // 3. Fallback: latest post.
    $lead = get_posts( array(
        'post_status'    => 'publish',
        'posts_per_page' => 1,
    ) );
    return ! empty( $lead ) ? $lead[0] : null;
}

/**
 * Get "También Hoy" posts (excluding lead).
 * Tries today → 48h → most recent.
 */
function sp_get_tambien_hoy( $exclude_id, $count = 4 ) {
    $args = array(
        'post_status'    => 'publish',
        'posts_per_page' => $count,
        'post__not_in'   => array( $exclude_id ),
    );

    // Try today.
    $today_args = $args;
    $today_args['date_query'] = array( array(
        'after' => 'today',
    ) );
    $posts = get_posts( $today_args );
    if ( count( $posts ) >= 2 ) {
        return $posts;
    }

    // Try last 48h.
    $recent_args = $args;
    $recent_args['date_query'] = array( array(
        'after' => '2 days ago',
    ) );
    $posts = get_posts( $recent_args );
    if ( count( $posts ) >= 2 ) {
        return $posts;
    }

    // Fallback: most recent.
    return get_posts( $args );
}
