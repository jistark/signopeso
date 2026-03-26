<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_register_rest_routes() {
    register_rest_route( 'signopeso/v1', '/subscribe', array(
        'methods'             => 'POST',
        'callback'            => 'sp_handle_subscribe',
        'permission_callback' => '__return_true',
        'args'                => array(
            'email' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => function( $email ) {
                    return is_email( $email );
                },
            ),
        ),
    ) );

    register_rest_route( 'signopeso/v1', '/recirculation', array(
        'methods'             => 'GET',
        'callback'            => 'sp_handle_recirculation',
        'permission_callback' => '__return_true',
        'args'                => array(
            'exclude' => array(
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ),
            'count' => array(
                'default'           => 4,
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );

    register_rest_route( 'signopeso/v1', '/stream', array(
        'methods'             => 'GET',
        'callback'            => 'sp_handle_stream',
        'permission_callback' => '__return_true',
        'args'                => array(
            'page' => array(
                'default'           => 2,
                'sanitize_callback' => 'absint',
            ),
            'per_page' => array(
                'default'           => 10,
                'sanitize_callback' => 'absint',
            ),
            'exclude' => array(
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ),
        ),
    ) );
}
add_action( 'rest_api_init', 'sp_register_rest_routes' );

function sp_handle_subscribe( WP_REST_Request $request ) {
    $email = $request->get_param( 'email' );

    // Basic rate limiting via transient.
    $ip_key = 'sp_sub_' . md5( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
    if ( get_transient( $ip_key ) ) {
        return new WP_REST_Response( array( 'message' => 'Espera un momento antes de intentar de nuevo.' ), 429 );
    }
    set_transient( $ip_key, true, 60 );

    require_once SP_PLUGIN_DIR . 'includes/newsletter/adapters/resend.php';
    $adapter = new SP_Resend_Adapter();
    $result  = $adapter->add_subscriber( $email );

    if ( $result ) {
        return new WP_REST_Response( array( 'message' => '¡Suscrito!' ), 200 );
    }

    return new WP_REST_Response( array( 'message' => 'Error al suscribir. Intenta más tarde.' ), 500 );
}

/**
 * Infinite scroll: render next page of post cards as HTML.
 *
 * GET /signopeso/v1/stream?page=2&per_page=10
 */
function sp_handle_stream( WP_REST_Request $request ) {
    $page     = max( 1, $request->get_param( 'page' ) );
    $per_page = min( 20, max( 1, $request->get_param( 'per_page' ) ) );
    $exclude  = (int) $request->get_param( 'exclude' );

    $query_args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
    );

    // Exclude portada lead post to prevent duplication in infinite scroll.
    if ( $exclude ) {
        $query_args['post__not_in'] = array( $exclude ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Single portada lead exclusion.
    }

    $query = new WP_Query( $query_args );

    if ( ! $query->have_posts() ) {
        wp_reset_postdata();
        return new WP_REST_Response( '', 200, array( 'Content-Type' => 'text/html; charset=UTF-8' ) );
    }

    ob_start();

    // Use Y-m-d for grouping (consistent with date-stream block).
    $current_date_ymd = '';
    $today            = wp_date( 'Y-m-d' );
    $yesterday        = wp_date( 'Y-m-d', strtotime( '-1 day' ) );

    while ( $query->have_posts() ) {
        $query->the_post();

        $post_date_ymd = get_the_time( 'Y-m-d' );

        if ( $post_date_ymd !== $current_date_ymd ) {
            if ( $current_date_ymd ) {
                echo '</div><!-- /.sp-date-stream__group -->';
            }
            $current_date_ymd = $post_date_ymd;

            if ( $post_date_ymd === $today ) {
                $relative_label = 'hoy';
            } elseif ( $post_date_ymd === $yesterday ) {
                $relative_label = 'ayer';
            } else {
                $relative_label = '';
            }

            $full_date = mb_strtolower( date_i18n( 'l j \d\e F', get_the_time( 'U' ) ) );

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

            printf( '<div class="sp-date-stream__group"><h2 class="sp-date-stream__date">%s</h2>', $date_html );
        }

        $block_instance = new WP_Block(
            array( 'blockName' => 'sp/post-card' ),
            array( 'postId' => get_the_ID() )
        );
        echo $block_instance->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    if ( $current_date_ymd ) {
        echo '</div><!-- /.sp-date-stream__group -->';
    }

    $html = ob_get_clean();
    wp_reset_postdata();

    return new WP_REST_Response( $html, 200, array( 'Content-Type' => 'text/html; charset=UTF-8' ) );
}

/**
 * Recirculation polling: return fresh mixed-format post cards as HTML.
 *
 * GET /signopeso/v1/recirculation?exclude=31&count=4
 */
function sp_handle_recirculation( WP_REST_Request $request ) {
    $exclude = (int) $request->get_param( 'exclude' );
    $count   = min( 8, max( 1, (int) $request->get_param( 'count' ) ) );

    // Cache random post IDs briefly to avoid ORDER BY RAND() on every request.
    $cache_key = 'sp_recirc_' . $exclude . '_' . $count;
    $post_ids  = get_transient( $cache_key );

    if ( false === $post_ids ) {
        $query_args = array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => $count,
            'orderby'        => 'rand',
            'fields'         => 'ids',
        );
        if ( $exclude ) {
            $query_args['post__not_in'] = array( $exclude ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
        }
        $post_ids = get_posts( $query_args );
        set_transient( $cache_key, $post_ids, 5 * MINUTE_IN_SECONDS );
    }

    if ( empty( $post_ids ) ) {
        return new WP_REST_Response( '', 200, array( 'Content-Type' => 'text/html; charset=UTF-8' ) );
    }

    ob_start();

    foreach ( $post_ids as $pid ) {
        $block_instance = new WP_Block(
            array( 'blockName' => 'sp/post-card' ),
            array( 'postId' => $pid )
        );
        echo $block_instance->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    $html = ob_get_clean();

    return new WP_REST_Response( $html, 200, array( 'Content-Type' => 'text/html; charset=UTF-8' ) );
}
