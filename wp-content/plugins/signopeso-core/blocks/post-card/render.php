<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_id = $block->context['postId'] ?? get_the_ID();
if ( ! $post_id ) {
    return;
}

$post = get_post( $post_id );
if ( ! $post ) {
    return;
}
$formats = wp_get_object_terms( $post_id, 'sp_formato', array( 'fields' => 'slugs' ) );
$formato = ! empty( $formats ) && ! is_wp_error( $formats ) ? $formats[0] : 'corto';

$categories = get_the_category( $post_id );
$cat_name   = ! empty( $categories ) ? $categories[0]->name : '';
$cat_link   = ! empty( $categories ) ? get_category_link( $categories[0]->term_id ) : '';

$source    = sp_get_source_data( $post_id );
$author    = get_the_author_meta( 'display_name', $post->post_author );
$permalink = get_permalink( $post_id );

$post_time_display = get_post_time( 'H:i', false, $post_id );

// Relative time: "hace 2h", "hace 15m", etc.
$post_timestamp = get_post_time( 'U', true, $post_id );
$diff           = time() - $post_timestamp;
if ( $diff < 3600 ) {
    $relative_time = sprintf( 'hace %dm', max( 1, floor( $diff / 60 ) ) );
} elseif ( $diff < 86400 ) {
    $relative_time = sprintf( 'hace %dh', floor( $diff / 3600 ) );
} else {
    $relative_time = $post_time_display;
}

// =====================================================================
// CORTO — dense, category pill + optional square thumb + excerpt.
// =====================================================================
if ( 'corto' === $formato ) :
    $thumb_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
    $excerpt   = wp_trim_words( get_the_excerpt( $post_id ), 40, '…' );
?>
<article class="sp-post-card sp-post-card--corto" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-permalink="<?php echo esc_url( $permalink ); ?>">
    <div class="sp-post-card__meta">
        <?php if ( $cat_name && $cat_link ) : ?>
            <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__cat-pill"><?php echo esc_html( $cat_name ); ?></a>
        <?php elseif ( $cat_name ) : ?>
            <span class="sp-post-card__cat-pill"><?php echo esc_html( $cat_name ); ?></span>
        <?php endif; ?>
    </div>
    <div class="sp-post-card__image-headline<?php echo $thumb_url ? ' sp-post-card__image-headline--has-thumb' : ''; ?>">
        <?php if ( $thumb_url ) : ?>
            <div class="sp-post-card__square-thumb">
                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>
        <h3 class="sp-post-card__title">
            <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
        </h3>
    </div>
    <?php if ( $excerpt ) : ?>
        <div class="sp-post-card__excerpt"><?php echo esc_html( $excerpt ); ?></div>
    <?php endif; ?>
    <span class="sp-post-card__author-time"><?php echo esc_html( $author ); ?>, <?php echo esc_html( $relative_time ); ?></span>
</article>

<?php
// =====================================================================
// ENLACE — source URL row + favicon + title + social image + excerpt.
// =====================================================================
elseif ( 'enlace' === $formato ) :
    $has_source    = ! empty( $source );
    $domain        = $has_source ? $source['domain'] : '';
    $source_url    = $has_source ? $source['url_utm'] : '';
    $favicon_url   = $domain ? sp_get_domain_icon_url( $domain ) : '';
    $display_url   = $has_source ? preg_replace( '#^https?://#', '', $source['url'] ) : '';
    $og_image_url  = '';
    if ( $has_source && ! empty( $source['image_id'] ) ) {
        $img_data = wp_get_attachment_image_src( $source['image_id'], 'medium' );
        if ( $img_data ) {
            $og_image_url = $img_data[0];
        }
    }
    // Use featured image as fallback if no OG image sideloaded.
    if ( ! $og_image_url ) {
        $og_image_url = get_the_post_thumbnail_url( $post_id, 'medium' ) ?: '';
    }
    // Source author (from OG data, not WP user) for display credit.
    $source_author = $has_source && ! empty( $source['author'] ) ? $source['author'] : '';
    $source_site   = $has_source && ! empty( $source['site_name'] ) ? $source['site_name'] : $domain;
    $excerpt       = wp_trim_words( get_the_excerpt( $post_id ), 40, '…' );
?>
<article class="sp-post-card sp-post-card--enlace" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-permalink="<?php echo esc_url( $permalink ); ?>">
    <?php if ( $domain ) : ?>
        <div class="sp-post-card__source-url">
            <img class="sp-post-card__source-favicon" src="<?php echo esc_url( $favicon_url ); ?>" alt="" width="28" height="28">
            <span class="sp-post-card__source-arrow">↗</span>
            <a class="sp-post-card__source-domain-link" href="<?php echo esc_url( $source_url ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $display_url ); ?></a>
        </div>
    <?php endif; ?>
    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
    </h3>
    <?php if ( $og_image_url ) : ?>
        <div class="sp-post-card__social-image">
            <img src="<?php echo esc_url( $og_image_url ); ?>" alt="" loading="lazy">
        </div>
    <?php endif; ?>
    <span class="sp-post-card__author-time">
        <?php if ( $source_author ) : ?>
            <?php echo esc_html( $source_author ); ?>, <?php echo esc_html( $source_site ); ?> · <?php echo esc_html( $relative_time ); ?>
        <?php else : ?>
            <?php echo esc_html( $source_site ?: $author ); ?>, <?php echo esc_html( $relative_time ); ?>
        <?php endif; ?>
    </span>
    <?php if ( $excerpt ) : ?>
        <div class="sp-post-card__excerpt"><?php echo esc_html( $excerpt ); ?></div>
    <?php endif; ?>
</article>

<?php
// =====================================================================
// LARGO — full-width image + meta row + headline + excerpt + read link.
// =====================================================================
elseif ( 'largo' === $formato ) :
    $thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
    $excerpt   = get_the_excerpt( $post_id );
?>
<article class="sp-post-card sp-post-card--largo" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-permalink="<?php echo esc_url( $permalink ); ?>">
    <div class="sp-post-card__meta">
        <?php if ( $cat_name && $cat_link ) : ?>
            <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__cat-pill"><?php echo esc_html( $cat_name ); ?></a>
        <?php elseif ( $cat_name ) : ?>
            <span class="sp-post-card__cat-pill"><?php echo esc_html( $cat_name ); ?></span>
        <?php endif; ?>
        <span class="sp-post-card__author-time"><?php echo esc_html( $author ); ?>, <?php echo esc_html( $relative_time ); ?></span>
    </div>
    <?php if ( $thumb_url ) : ?>
        <div class="sp-post-card__image">
            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
        </div>
    <?php endif; ?>
    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
    </h3>
    <?php if ( $excerpt ) : ?>
        <div class="sp-post-card__excerpt"><?php echo esc_html( $excerpt ); ?></div>
    <?php endif; ?>
    <div class="sp-post-card__full-content" hidden><?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered content ?></div>
    <a href="<?php echo esc_url( $permalink ); ?>" class="sp-post-card__read">sigue leyendo <span>&rarr;</span></a>
</article>

<?php
// =====================================================================
// COBERTURA — same as largo with pulsing live indicator.
// =====================================================================
elseif ( 'cobertura' === $formato ) :
    $thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
    $excerpt   = get_the_excerpt( $post_id );
?>
<article class="sp-post-card sp-post-card--cobertura" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-permalink="<?php echo esc_url( $permalink ); ?>">
    <div class="sp-post-card__meta">
        <?php if ( $cat_name && $cat_link ) : ?>
            <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__cat-pill"><?php echo esc_html( $cat_name ); ?></a>
        <?php elseif ( $cat_name ) : ?>
            <span class="sp-post-card__cat-pill"><?php echo esc_html( $cat_name ); ?></span>
        <?php endif; ?>
        <span class="sp-post-card__author-time"><?php echo esc_html( $author ); ?>, <?php echo esc_html( $relative_time ); ?></span>
    </div>
    <?php if ( $thumb_url ) : ?>
        <div class="sp-post-card__image">
            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
        </div>
    <?php endif; ?>
    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
    </h3>
    <?php if ( $excerpt ) : ?>
        <div class="sp-post-card__excerpt"><?php echo esc_html( $excerpt ); ?></div>
    <?php endif; ?>
    <div class="sp-post-card__full-content" hidden><?php echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- filtered content ?></div>
    <div class="sp-post-card__live">
        <span class="sp-post-card__live-dot"></span> en vivo &mdash; se actualiza
    </div>
    <a href="<?php echo esc_url( $permalink ); ?>" class="sp-post-card__read">sigue leyendo <span>&rarr;</span></a>
</article>

<?php
// =====================================================================
// FALLBACK — unknown formato, render as corto to avoid a blank card.
// =====================================================================
else :
    $thumb_url = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
    $excerpt   = wp_trim_words( get_the_excerpt( $post_id ), 40, '…' );
?>
<article class="sp-post-card sp-post-card--corto" data-post-id="<?php echo esc_attr( $post_id ); ?>" data-permalink="<?php echo esc_url( $permalink ); ?>">
    <div class="sp-post-card__meta">
        <?php if ( $cat_name && $cat_link ) : ?>
            <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__cat-pill"><?php echo esc_html( $cat_name ); ?></a>
        <?php elseif ( $cat_name ) : ?>
            <span class="sp-post-card__cat-pill"><?php echo esc_html( $cat_name ); ?></span>
        <?php endif; ?>
    </div>
    <div class="sp-post-card__image-headline<?php echo $thumb_url ? ' sp-post-card__image-headline--has-thumb' : ''; ?>">
        <?php if ( $thumb_url ) : ?>
            <div class="sp-post-card__square-thumb">
                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>
        <h3 class="sp-post-card__title">
            <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
        </h3>
    </div>
    <?php if ( $excerpt ) : ?>
        <div class="sp-post-card__excerpt"><?php echo esc_html( $excerpt ); ?></div>
    <?php endif; ?>
    <span class="sp-post-card__author-time"><?php echo esc_html( $author ); ?>, <?php echo esc_html( $relative_time ); ?></span>
</article>
<?php endif; ?>
