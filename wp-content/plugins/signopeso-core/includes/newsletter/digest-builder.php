<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get count of posts since last send (for logging).
 */
function sp_get_digest_post_count() {
    $last_sent = get_option( 'sp_newsletter_last_sent', strtotime( '-7 days' ) );
    return count( get_posts( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'date_query'     => array( array( 'after' => date( 'Y-m-d H:i:s', $last_sent ) ) ),
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) ) );
}

/**
 * Build the newsletter digest HTML.
 */
function sp_build_digest_html() {
    $last_sent = get_option( 'sp_newsletter_last_sent', strtotime( '-7 days' ) );

    $posts = get_posts( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'date_query'     => array(
            array( 'after' => date( 'Y-m-d H:i:s', $last_sent ) ),
        ),
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    if ( empty( $posts ) ) {
        return '';
    }

    // Group by format.
    $grouped = array( 'largo' => array(), 'corto' => array(), 'enlace' => array(), 'cobertura' => array() );
    foreach ( $posts as $post ) {
        $formats = wp_get_object_terms( $post->ID, 'sp_formato', array( 'fields' => 'slugs' ) );
        $fmt     = ! empty( $formats ) ? $formats[0] : 'corto';
        $grouped[ $fmt ][] = $post;
    }

    // Build inline-styled HTML.
    ob_start();
    ?>
    <div style="max-width:600px;margin:0 auto;font-family:Inter,-apple-system,sans-serif;color:#1a1a1a;">
        <div style="background:#F06B6B;padding:16px 24px;text-align:center;">
            <span style="font-size:24px;font-weight:900;color:#fff;">#</span><span style="font-size:24px;font-weight:900;color:#1a1a1a;">$</span>
            <span style="font-size:12px;color:rgba(255,255,255,0.8);margin-left:8px;">SignoPeso</span>
        </div>
        <div style="padding:24px;">
    <?php
    $order = array( 'largo', 'cobertura', 'corto', 'enlace' );
    foreach ( $order as $fmt ) {
        if ( empty( $grouped[ $fmt ] ) ) continue;
        foreach ( $grouped[ $fmt ] as $post ) {
            $source = sp_get_source_data( $post->ID );
            ?>
            <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #e0e0e0;">
                <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#999;"><?php echo esc_html( ucfirst( $fmt ) ); ?></span>
                <h2 style="font-family:Georgia,serif;font-size:18px;font-weight:700;margin:6px 0;line-height:1.3;">
                    <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" style="color:#1a1a1a;text-decoration:none;"><?php echo esc_html( $post->post_title ); ?></a>
                </h2>
                <p style="font-size:14px;color:#555;line-height:1.5;margin:0;"><?php echo esc_html( wp_trim_words( $post->post_excerpt ?: $post->post_content, 30 ) ); ?></p>
                <?php if ( $source ) : ?>
                    <p style="margin-top:8px;"><a href="<?php echo esc_url( $source['url_utm'] ); ?>" style="color:#F06B6B;font-size:13px;text-decoration:none;font-weight:600;">Ir a la fuente &rarr;</a></p>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    ?>
        </div>
        <div style="background:#fafafa;padding:16px 24px;text-align:center;font-size:12px;color:#999;">
            <a href="<?php echo esc_url( home_url() ); ?>" style="color:#F06B6B;">signopeso.com</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
