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
$cat_name   = ! empty( $categories ) ? esc_html( $categories[0]->name ) : '';
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
            <span class="sp-post-card__cat"><?php echo $cat_name; ?></span>
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
    $has_og     = $source && ! empty( $source['title'] );
    $domain_str = $source ? $source['domain'] : '';
?>
<article class="sp-post-card sp-post-card--enlace">
    <div class="sp-post-card__over">
        <?php if ( $cat_name ) : ?>
            <span class="sp-post-card__cat"><?php echo $cat_name; ?></span>
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
        if ( $has_og ) :
            $is_highres = sp_source_card_is_highres( $post_id );
            if ( $is_highres ) :
                // High-res: image on top (16:9), full text below.
?>
    <div class="sp-source-card sp-source-card--highres">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
            <?php if ( $source['image_id'] ) : ?>
                <div class="sp-source-card__img-lg">
                    <?php echo wp_get_attachment_image( $source['image_id'], 'large', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
                </div>
            <?php endif; ?>
            <div class="sp-source-card__body">
                <div class="sp-source-card__og-title"><?php echo esc_html( $source['title'] ); ?></div>
                <div class="sp-source-card__og-author"><?php echo esc_html( $source['domain'] ); ?></div>
                <?php if ( $source['desc'] ) : ?>
                    <div class="sp-source-card__og-excerpt"><?php echo esc_html( wp_trim_words( $source['desc'], 25 ) ); ?></div>
                <?php endif; ?>
                <div class="sp-source-card__og-url">
                    <span><?php echo esc_html( $source['domain'] ); ?></span>
                    <span class="sp-source-card__go">Ir a la fuente &rarr;</span>
                </div>
            </div>
        </a>
    </div>
<?php
            else :
                // Low-res: text left, small image right.
?>
    <div class="sp-source-card sp-source-card--lowres">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
            <div class="sp-source-card__body">
                <div class="sp-source-card__og-title"><?php echo esc_html( $source['title'] ); ?></div>
                <div class="sp-source-card__og-author"><?php echo esc_html( $source['domain'] ); ?></div>
                <?php if ( $source['desc'] ) : ?>
                    <div class="sp-source-card__og-excerpt"><?php echo esc_html( wp_trim_words( $source['desc'], 25 ) ); ?></div>
                <?php endif; ?>
                <div class="sp-source-card__og-url">
                    <span><?php echo esc_html( $source['domain'] ); ?></span>
                    <span class="sp-source-card__go">Ir a la fuente &rarr;</span>
                </div>
            </div>
            <?php if ( $source['image_id'] ) : ?>
                <div class="sp-source-card__img-sm">
                    <?php echo wp_get_attachment_image( $source['image_id'], 'thumbnail', false, array( 'loading' => 'lazy', 'alt' => '' ) ); ?>
                </div>
            <?php endif; ?>
        </a>
    </div>
<?php
            endif; // highres/lowres
        else :
            // No OG data — simple fallback link.
?>
    <p class="sp-source-link">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
            Ir a la fuente &rarr; <?php echo esc_html( $source['domain'] ); ?>
        </a>
    </p>
<?php
        endif; // has_og
    endif; // source
?>
</article>

<?php
// =====================================================================
// LARGO — expanded with optional featured image, excerpt, category badge.
// =====================================================================
elseif ( 'largo' === $formato ) :
    $thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
?>
<article class="sp-post-card sp-post-card--expanded sp-post-card--largo">
    <div class="sp-post-card__header">
        <span class="sp-post-card__author"><?php echo esc_html( $author ); ?></span>
        <span class="sp-post-card__time"><?php echo esc_html( $post_time_display ); ?></span>
    </div>
    <?php if ( $cat_name ) : ?>
        <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__badge"><?php echo $cat_name; ?></a>
    <?php endif; ?>
    <?php if ( $thumb_url ) : ?>
        <div class="sp-post-card__img">
            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
        </div>
    <?php endif; ?>
    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>">
            <?php echo esc_html( get_the_title( $post_id ) ); ?>
        </a>
    </h3>
    <div class="sp-post-card__excerpt">
        <?php echo wp_kses_post( get_the_excerpt( $post_id ) ); ?>
    </div>
    <div class="sp-post-card__footer">
        <a href="<?php echo esc_url( $permalink ); ?>" class="sp-post-card__read">Sigue leyendo &rarr;</a>
    </div>
</article>

<?php
// =====================================================================
// COBERTURA — same as Largo but with live indicator in footer.
// =====================================================================
elseif ( 'cobertura' === $formato ) :
    $thumb_url = get_the_post_thumbnail_url( $post_id, 'large' );
?>
<article class="sp-post-card sp-post-card--expanded sp-post-card--cobertura">
    <div class="sp-post-card__header">
        <span class="sp-post-card__author"><?php echo esc_html( $author ); ?></span>
        <span class="sp-post-card__time"><?php echo esc_html( $post_time_display ); ?></span>
    </div>
    <?php if ( $cat_name ) : ?>
        <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__badge"><?php echo $cat_name; ?></a>
    <?php endif; ?>
    <?php if ( $thumb_url ) : ?>
        <div class="sp-post-card__img">
            <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
        </div>
    <?php endif; ?>
    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>">
            <?php echo esc_html( get_the_title( $post_id ) ); ?>
        </a>
    </h3>
    <div class="sp-post-card__excerpt">
        <?php echo wp_kses_post( get_the_excerpt( $post_id ) ); ?>
    </div>
    <div class="sp-post-card__footer">
        <span class="sp-post-card__live">
            <span class="sp-post-card__live-dot"></span> En vivo &mdash; se actualiza
        </span>
        <a href="<?php echo esc_url( $permalink ); ?>" class="sp-post-card__read">Sigue leyendo &rarr;</a>
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
            <span class="sp-post-card__cat"><?php echo $cat_name; ?></span>
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
