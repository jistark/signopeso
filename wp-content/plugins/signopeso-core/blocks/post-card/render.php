<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_id = $block->context['postId'] ?? get_the_ID();
if ( ! $post_id ) {
    return;
}

$post    = get_post( $post_id );
$formats = wp_get_object_terms( $post_id, 'sp_formato', array( 'fields' => 'slugs' ) );
$formato = ! empty( $formats ) && ! is_wp_error( $formats ) ? $formats[0] : 'corto';

$categories = get_the_category( $post_id );
$cat_name   = ! empty( $categories ) ? $categories[0]->name : '';
$cat_link   = ! empty( $categories ) ? get_category_link( $categories[0]->term_id ) : '';

$source    = sp_get_source_data( $post_id );
$author    = get_the_author_meta( 'display_name', $post->post_author );
$permalink = get_permalink( $post_id );

// Wall-clock timestamp for the post (H:i).
$post_time_display = get_post_time( 'H:i', false, $post_id );

// =====================================================================
// CORTO — dense, tweet-like, borderless. No author, no excerpt, no image.
// =====================================================================
if ( 'corto' === $formato ) :
?>
<article class="sp-post-card sp-post-card--corto">
    <div class="sp-post-card__over">
        <?php if ( $cat_name ) : ?>
            <span class="sp-post-card__cat"><?php echo esc_html( $cat_name ); ?></span>
        <?php endif; ?>
        <span class="sp-post-card__time"><?php echo esc_html( $post_time_display ); ?></span>
    </div>
    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>">
            <?php echo esc_html( get_the_title( $post_id ) ); ?>
        </a>
    </h3>
</article>

<?php
// =====================================================================
// ENLACE — boxed card with source attribution and optional source card.
// =====================================================================
elseif ( 'enlace' === $formato ) :
    $domain_str = $source ? $source['domain'] : '';
?>
<article class="sp-post-card sp-post-card--enlace">
    <div class="sp-post-card__over">
        <?php if ( $cat_name ) : ?>
            <span class="sp-post-card__cat"><?php echo esc_html( $cat_name ); ?></span>
        <?php endif; ?>
        <?php if ( $domain_str ) : ?>
            <span class="sp-post-card__domain">&#8599; <?php echo esc_html( $domain_str ); ?></span>
        <?php endif; ?>
        <span class="sp-post-card__time"><?php echo esc_html( $post_time_display ); ?></span>
    </div>
    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>">
            <?php echo esc_html( get_the_title( $post_id ) ); ?>
        </a>
    </h3>
    <?php if ( $source ) :
        $has_og = ! empty( $source['title'] );
        if ( $has_og ) :
            $og_img_url = '';
            if ( ! empty( $source['image_id'] ) ) {
                $img_data = wp_get_attachment_image_src( $source['image_id'], 'thumbnail' );
                if ( $img_data ) $og_img_url = $img_data[0];
            }
    ?>
    <div class="sp-source-card">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
            <?php if ( $og_img_url ) : ?>
                <div class="sp-source-card__thumb">
                    <img src="<?php echo esc_url( $og_img_url ); ?>" alt="" loading="lazy">
                </div>
            <?php endif; ?>
            <div class="sp-source-card__body">
                <div class="sp-source-card__og-title"><?php echo esc_html( $source['title'] ); ?></div>
                <div class="sp-source-card__domain">&#8599; <?php echo esc_html( $source['domain'] ); ?></div>
            </div>
        </a>
    </div>
    <?php else : ?>
    <p class="sp-source-link">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
            &#8599; <?php echo esc_html( $source['domain'] ); ?>
        </a>
    </p>
    <?php endif; // has_og
    endif; // source ?>
</article>

<?php
// =====================================================================
// LARGO — expanded with optional featured image, excerpt, category badge.
// =====================================================================
elseif ( 'largo' === $formato ) :
    $thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
?>
<?php /* ── Largo ──────────────────────────────────── */ ?>
<article class="sp-post-card sp-post-card--expanded sp-post-card--largo">
    <div class="sp-post-card__header">
        <span class="sp-post-card__author"><?php echo esc_html( $author ); ?></span>
        <span class="sp-post-card__time"><?php echo esc_html( $post_time_display ); ?></span>
    </div>
    <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__badge"><?php echo esc_html( $cat_name ); ?></a>
    <div class="sp-post-card__body-row">
        <div class="sp-post-card__text">
            <h3 class="sp-post-card__title">
                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
            </h3>
            <?php $excerpt = get_the_excerpt( $post_id ); if ( $excerpt ) : ?>
                <div class="sp-post-card__excerpt"><?php echo esc_html( $excerpt ); ?></div>
            <?php endif; ?>
            <div class="sp-post-card__footer">
                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="sp-post-card__read">Sigue leyendo &rarr;</a>
            </div>
        </div>
        <?php if ( $thumb_url ) : ?>
            <div class="sp-post-card__thumb">
                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>
    </div>
</article>

<?php
// =====================================================================
// COBERTURA — same as Largo but with live indicator in footer.
// =====================================================================
elseif ( 'cobertura' === $formato ) :
    $thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
?>
<?php /* ── Cobertura ──────────────────────────────── */ ?>
<article class="sp-post-card sp-post-card--expanded sp-post-card--cobertura">
    <div class="sp-post-card__header">
        <span class="sp-post-card__author"><?php echo esc_html( $author ); ?></span>
        <span class="sp-post-card__time"><?php echo esc_html( $post_time_display ); ?></span>
    </div>
    <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__badge"><?php echo esc_html( $cat_name ); ?></a>
    <div class="sp-post-card__body-row">
        <div class="sp-post-card__text">
            <h3 class="sp-post-card__title">
                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
            </h3>
            <?php $excerpt = get_the_excerpt( $post_id ); if ( $excerpt ) : ?>
                <div class="sp-post-card__excerpt"><?php echo esc_html( $excerpt ); ?></div>
            <?php endif; ?>
            <div class="sp-post-card__footer">
                <span class="sp-post-card__live">
                    <span class="sp-post-card__live-dot"></span> En vivo &mdash; se actualiza
                </span>
                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="sp-post-card__read">Sigue leyendo &rarr;</a>
            </div>
        </div>
        <?php if ( $thumb_url ) : ?>
            <div class="sp-post-card__thumb">
                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>
    </div>
</article>

<?php
// =====================================================================
// FALLBACK — unknown formato, render as Corto to avoid a blank card.
// =====================================================================
else :
?>
<article class="sp-post-card sp-post-card--corto">
    <div class="sp-post-card__over">
        <?php if ( $cat_name ) : ?>
            <span class="sp-post-card__cat"><?php echo esc_html( $cat_name ); ?></span>
        <?php endif; ?>
        <span class="sp-post-card__time"><?php echo esc_html( $post_time_display ); ?></span>
    </div>
    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>">
            <?php echo esc_html( get_the_title( $post_id ) ); ?>
        </a>
    </h3>
</article>
<?php endif; ?>
