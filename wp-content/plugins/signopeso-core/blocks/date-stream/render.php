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
        $query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Archive taxonomy filter, paginated.
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
            $query_args['orderby'] = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
        }
    }
}

// Exclude portada lead post if set (merge with any existing exclusions).
if ( ! empty( $GLOBALS['sp_portada_lead_id'] ) ) {
    $existing = $query_args['post__not_in'] ?? array();
    $query_args['post__not_in'] = array_unique( array_merge( $existing, array( (int) $GLOBALS['sp_portada_lead_id'] ) ) ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Single portada lead exclusion.
}

$query = new WP_Query( $query_args );

if ( ! $query->have_posts() ) {
    echo '<p>No hay publicaciones aún.</p>';
    return;
}

$current_date_ymd = '';

$today     = wp_date( 'Y-m-d' );
$yesterday = wp_date( 'Y-m-d', strtotime( '-1 day' ) );

echo '<div class="sp-date-stream">';

while ( $query->have_posts() ) {
    $query->the_post();

    // Build Y-m-d key for grouping and relative comparison.
    $post_date_ymd = get_the_time( 'Y-m-d' );

    if ( $post_date_ymd !== $current_date_ymd ) {
        // Close previous group.
        if ( $current_date_ymd ) {
            echo '</div><!-- /.sp-date-stream__group -->';
        }

        $current_date_ymd = $post_date_ymd;

        $full_date = mb_strtolower( date_i18n( 'l j \d\e F', get_the_time( 'U' ) ) );

        // Build relative label.
        if ( $post_date_ymd === $today ) {
            $relative_label = 'hoy';
        } elseif ( $post_date_ymd === $yesterday ) {
            $relative_label = 'ayer';
        } else {
            $relative_label = '';
        }

        // Compose the structured date header HTML.
        if ( $relative_label ) {
            $date_html = sprintf(
                '<span class="sp-date-stream__arrow">↓</span>&nbsp;<span class="sp-date-stream__label">noticias de&nbsp;</span><span class="sp-date-stream__relative">%s</span><span class="sp-date-stream__label">,&nbsp;</span><span class="sp-date-stream__day">%s</span>',
                esc_html( $relative_label ),
                esc_html( $full_date )
            );
        } else {
            $date_html = sprintf(
                '<span class="sp-date-stream__arrow">↓</span>&nbsp;<span class="sp-date-stream__label">noticias del&nbsp;</span><span class="sp-date-stream__day">%s</span>',
                esc_html( $full_date )
            );
        }

        printf( '<div class="sp-date-stream__group"><h2 class="sp-date-stream__date">%s</h2>', $date_html ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- All parts escaped above.
    }

    // Render post card.
    $block_instance = new WP_Block(
        array( 'blockName' => 'sp/post-card' ),
        array( 'postId' => get_the_ID() )
    );
    echo $block_instance->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

// Close last group.
if ( $current_date_ymd ) {
    echo '</div><!-- /.sp-date-stream__group -->';
}

// Infinite scroll loader (replaces pagination).
$total_pages = $query->max_num_pages;
$lead_id     = ! empty( $GLOBALS['sp_portada_lead_id'] ) ? (int) $GLOBALS['sp_portada_lead_id'] : 0;
if ( $total_pages > 1 && $paged < $total_pages ) {
    printf(
        '<div class="sp-loader sp2-loader" data-next-page="%d" data-max-pages="%d" data-per-page="%d" data-exclude="%d">
            <div class="sp-loader__circle sp2-loader__circle">
                <span class="sp-loader__symbol sp2-loader__symbol">$</span>
            </div>
        </div>',
        $paged + 1,
        $total_pages,
        $posts_per_page,
        $lead_id
    );
}

echo '</div><!-- /.sp-date-stream -->';

wp_reset_postdata();
