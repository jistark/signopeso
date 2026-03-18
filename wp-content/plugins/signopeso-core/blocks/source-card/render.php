<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Source Card — compact OG citation.
 */
$post_id = get_the_ID();
$source  = sp_get_source_data( $post_id );
if ( ! $source ) return;

$has_og = ! empty( $source['title'] );

// Fallback: simple link if no OG data
if ( ! $has_og ) : ?>
    <p class="sp-source-link">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
            &#8599; <?php echo esc_html( $source['domain'] ); ?>
        </a>
    </p>
<?php return; endif;

$image_url = '';
if ( ! empty( $source['image_id'] ) ) {
    $img = wp_get_attachment_image_src( $source['image_id'], 'thumbnail' );
    if ( $img ) $image_url = $img[0];
}
?>
<div class="sp-source-card">
    <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
        <?php if ( $image_url ) : ?>
            <div class="sp-source-card__thumb">
                <img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>
        <div class="sp-source-card__body">
            <div class="sp-source-card__og-title"><?php echo esc_html( $source['title'] ); ?></div>
            <div class="sp-source-card__domain">&#8599; <?php echo esc_html( $source['domain'] ); ?></div>
        </div>
    </a>
</div>
<?php
