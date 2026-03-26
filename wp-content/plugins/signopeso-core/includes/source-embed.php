<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the source URL meta box.
 */
function sp_add_source_meta_box() {
    add_meta_box(
        'sp_source_url',
        'Fuente Original',
        'sp_render_source_meta_box',
        'post',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'sp_add_source_meta_box' );

/**
 * Render the source URL meta box.
 */
function sp_render_source_meta_box( $post ) {
    wp_nonce_field( 'sp_source_save', 'sp_source_nonce' );

    $source_url = get_post_meta( $post->ID, '_sp_source_url', true );
    $og_title   = get_post_meta( $post->ID, '_sp_source_og_title', true );
    $og_domain  = get_post_meta( $post->ID, '_sp_source_og_domain', true );
    $og_status  = get_post_meta( $post->ID, '_sp_source_og_status', true );

    echo '<p>';
    printf(
        '<input type="url" name="sp_source_url" value="%s" style="width:100%%;" placeholder="https://ejemplo.com/articulo-original" />',
        esc_attr( $source_url )
    );
    echo '</p>';

    if ( $og_title ) {
        printf( '<p style="color:#666;">OG: %s (%s)</p>', esc_html( $og_title ), esc_html( $og_domain ) );
    }

    if ( 'pending' === $og_status ) {
        echo '<p style="color:#999;"><em>Obteniendo datos de la fuente...</em></p>';
    }

    if ( $source_url ) {
        echo '<p><label><input type="checkbox" name="sp_source_refresh" value="1" /> Refrescar datos OG</label></p>';
    }
}

/**
 * Save source URL and schedule OG fetch.
 */
function sp_save_source_url( $post_id ) {
    if ( ! isset( $_POST['sp_source_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['sp_source_nonce'] ) ), 'sp_source_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $new_url = isset( $_POST['sp_source_url'] ) ? esc_url_raw( wp_unslash( $_POST['sp_source_url'] ) ) : '';
    $old_url = get_post_meta( $post_id, '_sp_source_url', true );
    $refresh = ! empty( $_POST['sp_source_refresh'] );

    update_post_meta( $post_id, '_sp_source_url', $new_url );

    // Schedule OG fetch if URL is new or refresh requested.
    if ( $new_url && ( $new_url !== $old_url || $refresh ) ) {
        update_post_meta( $post_id, '_sp_source_og_status', 'pending' );
        wp_schedule_single_event( time(), 'sp_fetch_og_data', array( $post_id ) );
    }

    // Clear OG data if URL removed.
    if ( ! $new_url ) {
        delete_post_meta( $post_id, '_sp_source_og_title' );
        delete_post_meta( $post_id, '_sp_source_og_desc' );
        delete_post_meta( $post_id, '_sp_source_og_image_id' );
        delete_post_meta( $post_id, '_sp_source_og_domain' );
        delete_post_meta( $post_id, '_sp_source_og_status' );
    }
}
add_action( 'save_post', 'sp_save_source_url' );

/**
 * Auto-fetch OG data when a post has _sp_source_url but no OG data yet.
 * Catches posts created programmatically (e.g., sp-chop pipeline) that
 * bypass the meta box save_post hook.
 */
function sp_maybe_auto_fetch_og( $post_id, $post, $update ) {
    if ( 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
        return;
    }

    $url    = get_post_meta( $post_id, '_sp_source_url', true );
    $status = get_post_meta( $post_id, '_sp_source_og_status', true );

    // Has URL but no OG status = never fetched. Do it now.
    if ( $url && ! $status ) {
        update_post_meta( $post_id, '_sp_source_og_status', 'pending' );
        // Fetch immediately (synchronous) since cron may not run on WP.com.
        sp_do_fetch_og_data( $post_id );
    }
}
add_action( 'save_post', 'sp_maybe_auto_fetch_og', 20, 3 );

/**
 * Async OG data fetch handler.
 */
function sp_do_fetch_og_data( $post_id ) {
    $url = get_post_meta( $post_id, '_sp_source_url', true );
    if ( ! $url ) {
        return;
    }

    $response = wp_remote_get( $url, array(
        'timeout'    => 15,
        'user-agent' => 'SignoPeso/1.0 (OG Fetch)',
    ) );

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        update_post_meta( $post_id, '_sp_source_og_status', 'failed' );
        return;
    }

    $html = wp_remote_retrieve_body( $response );
    $og   = sp_parse_og_tags( $html );

    // Extract domain.
    $parsed = wp_parse_url( $url );
    $domain = isset( $parsed['host'] ) ? preg_replace( '/^www\./', '', $parsed['host'] ) : '';

    update_post_meta( $post_id, '_sp_source_og_title', sanitize_text_field( $og['title'] ?? '' ) );
    update_post_meta( $post_id, '_sp_source_og_desc', sanitize_text_field( $og['description'] ?? '' ) );
    update_post_meta( $post_id, '_sp_source_og_domain', sanitize_text_field( $domain ) );
    update_post_meta( $post_id, '_sp_source_og_author', sanitize_text_field( $og['author'] ?? '' ) );
    update_post_meta( $post_id, '_sp_source_og_site_name', sanitize_text_field( $og['site_name'] ?? $domain ) );

    // Sideload image if available.
    if ( ! empty( $og['image'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $image_id = media_sideload_image( $og['image'], $post_id, '', 'id' );
        if ( ! is_wp_error( $image_id ) ) {
            update_post_meta( $post_id, '_sp_source_og_image_id', $image_id );
        }
    }

    update_post_meta( $post_id, '_sp_source_og_status', 'fetched' );
}
add_action( 'sp_fetch_og_data', 'sp_do_fetch_og_data' );

/**
 * Parse OG meta tags from HTML.
 */
function sp_parse_og_tags( $html ) {
    $og = array();

    // OG property tags.
    $property_tags = array(
        'title'       => 'og:title',
        'description' => 'og:description',
        'image'       => 'og:image',
        'site_name'   => 'og:site_name',
        'author'      => 'article:author',
    );

    foreach ( $property_tags as $key => $property ) {
        if ( preg_match( '/<meta[^>]+property=["\']' . preg_quote( $property, '/' ) . '["\'][^>]+content=["\']([^"\']*)["\']/', $html, $match ) ) {
            $og[ $key ] = $match[1];
        } elseif ( preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']' . preg_quote( $property, '/' ) . '["\']/', $html, $match ) ) {
            $og[ $key ] = $match[1];
        }
    }

    // Name-based meta tags (twitter, author, etc.).
    $name_tags = array(
        'twitter_creator' => 'twitter:creator',
        'twitter_site'    => 'twitter:site',
        'cms_author'      => 'author',
    );

    foreach ( $name_tags as $key => $name ) {
        if ( preg_match( '/<meta[^>]+name=["\']' . preg_quote( $name, '/' ) . '["\'][^>]+content=["\']([^"\']*)["\']/', $html, $match ) ) {
            $og[ $key ] = $match[1];
        } elseif ( preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+name=["\']' . preg_quote( $name, '/' ) . '["\']/', $html, $match ) ) {
            $og[ $key ] = $match[1];
        }
    }

    // Fallback to <title> tag if no og:title.
    if ( empty( $og['title'] ) && preg_match( '/<title[^>]*>([^<]+)<\/title>/', $html, $match ) ) {
        $og['title'] = trim( $match[1] );
    }

    // Resolve author: article:author → meta[name=author] → twitter:creator.
    if ( empty( $og['author'] ) ) {
        $og['author'] = $og['cms_author'] ?? $og['twitter_creator'] ?? '';
    }

    return $og;
}

/**
 * Check if a post's source OG image is high resolution (width >= 600px).
 *
 * @param int $post_id Post ID.
 * @return bool True if image width >= 600px.
 */
function sp_source_card_is_highres( $post_id ) {
    $image_id = get_post_meta( $post_id, '_sp_source_og_image_id', true );
    if ( ! $image_id ) {
        return false;
    }
    $meta = wp_get_attachment_metadata( $image_id );
    return $meta && isset( $meta['width'] ) && $meta['width'] >= 600;
}

/**
 * Get source data for a post (used by blocks).
 */
function sp_get_source_data( $post_id ) {
    $url = get_post_meta( $post_id, '_sp_source_url', true );
    if ( ! $url ) {
        return null;
    }

    $domain = get_post_meta( $post_id, '_sp_source_og_domain', true );

    // Auto-derive domain from URL if not stored yet.
    if ( ! $domain ) {
        $parsed = wp_parse_url( $url );
        $domain = isset( $parsed['host'] ) ? preg_replace( '/^www\./', '', $parsed['host'] ) : '';
    }

    return array(
        'url'       => $url,
        'title'     => get_post_meta( $post_id, '_sp_source_og_title', true ),
        'desc'      => get_post_meta( $post_id, '_sp_source_og_desc', true ),
        'image_id'  => get_post_meta( $post_id, '_sp_source_og_image_id', true ),
        'domain'    => $domain,
        'author'    => get_post_meta( $post_id, '_sp_source_og_author', true ),
        'site_name' => get_post_meta( $post_id, '_sp_source_og_site_name', true ) ?: $domain,
        'status'    => get_post_meta( $post_id, '_sp_source_og_status', true ),
        'url_utm'   => add_query_arg( array(
            'utm_source' => 'signopeso',
            'utm_medium' => 'referral',
        ), $url ),
    );
}
