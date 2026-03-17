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
$formato = ! empty( $formats ) ? $formats[0] : 'corto';

$categories = get_the_category( $post_id );
$cat_name   = ! empty( $categories ) ? $categories[0]->name : '';
$cat_link   = ! empty( $categories ) ? get_category_link( $categories[0]->term_id ) : '';

$source     = sp_get_source_data( $post_id );
$author     = get_the_author_meta( 'display_name', $post->post_author );
$permalink  = get_permalink( $post_id );

// Format label styling.
$label_class = 'sp-post-card__label';
if ( 'largo' === $formato || 'cobertura' === $formato ) {
    $label_class .= ' sp-post-card__label--highlight';
}
?>

<article class="sp-post-card sp-post-card--<?php echo esc_attr( $formato ); ?>">
    <div class="sp-post-card__meta-top">
        <span class="<?php echo esc_attr( $label_class ); ?>">
            <?php echo esc_html( ucfirst( $formato ) ); ?>
        </span>
        <?php if ( $cat_name ) : ?>
            <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__category">
                <?php echo esc_html( $cat_name ); ?>
            </a>
        <?php endif; ?>
    </div>

    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>">
            <?php echo esc_html( get_the_title( $post_id ) ); ?>
        </a>
    </h3>

    <?php if ( 'largo' === $formato || 'cobertura' === $formato ) : ?>
        <div class="sp-post-card__excerpt">
            <?php echo wp_kses_post( get_the_excerpt( $post_id ) ); ?>
        </div>
        <a href="<?php echo esc_url( $permalink ); ?>" class="sp-post-card__readmore">
            Sigue leyendo &rarr;
        </a>
    <?php else : ?>
        <div class="sp-post-card__excerpt">
            <?php echo wp_kses_post( get_the_excerpt( $post_id ) ); ?>
        </div>
    <?php endif; ?>

    <?php if ( 'enlace' === $formato && $source ) : ?>
        <?php
        // Render inline source card.
        $has_og = ! empty( $source['title'] );
        if ( $has_og ) : ?>
            <div class="sp-source-card sp-source-card--inline">
                <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener" class="sp-source-card__link">
                    <?php if ( $source['image_id'] ) : ?>
                        <div class="sp-source-card__image">
                            <?php echo wp_get_attachment_image( $source['image_id'], 'thumbnail' ); ?>
                        </div>
                    <?php endif; ?>
                    <div class="sp-source-card__info">
                        <span class="sp-source-card__title"><?php echo esc_html( $source['title'] ); ?></span>
                        <span class="sp-source-card__domain"><?php echo esc_html( $source['domain'] ); ?></span>
                    </div>
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="sp-post-card__footer">
        <span class="sp-post-card__byline">Por <?php echo esc_html( $author ); ?></span>
        <?php if ( $source && 'enlace' !== $formato ) : ?>
            <a href="<?php echo esc_url( $source['url_utm'] ); ?>" class="sp-post-card__source" target="_blank" rel="noopener">
                Ir a la fuente &rarr;
            </a>
        <?php endif; ?>
    </div>
</article>
