<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_id = get_the_ID();
$source  = sp_get_source_data( $post_id );

if ( ! $source ) {
    return;
}

$has_og = ! empty( $source['title'] );

if ( ! $has_og ) :
    // No OG data at all — simple link fallback.
?>
<p class="sp-source-link">
    <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
        Ir a la fuente &rarr; <?php echo esc_html( $source['domain'] ); ?>
    </a>
</p>
<?php
    return;
endif;

$is_highres = sp_source_card_is_highres( $post_id );

if ( $is_highres ) :
    // High-res (image width >= 600px): image on top (16:9), full text below.
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
    // Low-res (image width < 600px or no dimensions): text left, small image right.
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
<?php endif; ?>
