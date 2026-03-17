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
?>

<?php if ( $has_og ) : ?>
    <div class="sp-source-card">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener" class="sp-source-card__link">
            <?php if ( $source['image_id'] ) : ?>
                <div class="sp-source-card__image">
                    <?php echo wp_get_attachment_image( $source['image_id'], 'thumbnail' ); ?>
                </div>
            <?php endif; ?>
            <div class="sp-source-card__info">
                <span class="sp-source-card__title"><?php echo esc_html( $source['title'] ); ?></span>
                <?php if ( $source['desc'] ) : ?>
                    <span class="sp-source-card__desc"><?php echo esc_html( wp_trim_words( $source['desc'], 20 ) ); ?></span>
                <?php endif; ?>
                <span class="sp-source-card__domain"><?php echo esc_html( $source['domain'] ); ?></span>
            </div>
        </a>
    </div>
<?php else : ?>
    <p class="sp-source-link">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
            Ir a la fuente &rarr; <?php echo esc_html( $source['domain'] ); ?>
        </a>
    </p>
<?php endif; ?>
