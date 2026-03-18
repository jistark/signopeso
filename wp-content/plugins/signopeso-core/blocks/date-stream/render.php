<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$posts_per_page = $attributes['postsPerPage'] ?? 10;
$inherit_query  = $attributes['inheritQuery'] ?? false;
$paged          = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

$query_args = array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => $posts_per_page,
    'paged'          => $paged,
);

// Inherit archive query context (category, tag, date, etc.).
if ( $inherit_query ) {
    $queried = get_queried_object();
    if ( $queried instanceof WP_Term ) {
        $query_args['tax_query'] = array(
            array(
                'taxonomy' => $queried->taxonomy,
                'terms'    => $queried->term_id,
            ),
        );
    }
    // Support date archives.
    if ( is_year() ) {
        $query_args['year'] = get_query_var( 'year' );
    } elseif ( is_month() ) {
        $query_args['year']     = get_query_var( 'year' );
        $query_args['monthnum'] = get_query_var( 'monthnum' );
    }
    // Support search queries.
    if ( is_search() ) {
        $query_args['s'] = get_search_query();
        if ( ! empty( $_GET['orderby'] ) && in_array( $_GET['orderby'], array( 'date', 'relevance' ), true ) ) {
            $query_args['orderby'] = sanitize_text_field( $_GET['orderby'] );
        }
    }
}

// Exclude portada lead post if set (merge with any existing exclusions).
if ( ! empty( $GLOBALS['sp_portada_lead_id'] ) ) {
    $existing = $query_args['post__not_in'] ?? array();
    $query_args['post__not_in'] = array_unique( array_merge( $existing, array( (int) $GLOBALS['sp_portada_lead_id'] ) ) );
}

$query = new WP_Query( $query_args );

if ( ! $query->have_posts() ) {
    echo '<p>No hay publicaciones aún.</p>';
    return;
}

$current_date = '';

echo '<div class="sp-date-stream">';

while ( $query->have_posts() ) {
    $query->the_post();

    // Date header.
    $post_date = date_i18n( 'l j \d\e F, Y', get_the_time( 'U' ) );
    if ( $post_date !== $current_date ) {
        if ( $current_date ) {
            echo '</div><!-- /.sp-date-stream__group -->';
        }
        $current_date = $post_date;
        printf(
            '<div class="sp-date-stream__group"><h2 class="sp-date-stream__date"><span class="sp-section-label sp-section-label--sal">%s</span></h2>',
            esc_html( mb_strtolower( ucfirst( $post_date ) ) )
        );
    }

    // Render post card.
    $block_instance = new WP_Block(
        array( 'blockName' => 'sp/post-card' ),
        array( 'postId' => get_the_ID() )
    );
    echo $block_instance->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

// Close last group.
if ( $current_date ) {
    echo '</div><!-- /.sp-date-stream__group -->';
}

// Pagination.
$total_pages = $query->max_num_pages;
if ( $total_pages > 1 ) {
    echo '<nav class="sp-date-stream__pagination">';
    echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        'total'   => $total_pages,
        'current' => $paged,
    ) );
    echo '</nav>';
}

echo '</div><!-- /.sp-date-stream -->';

wp_reset_postdata();
