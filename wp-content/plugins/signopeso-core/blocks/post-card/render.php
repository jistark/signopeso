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

// Tier: largo and cobertura get expanded treatment.
$is_expanded = in_array( $formato, array( 'largo', 'cobertura' ), true );
$tier_class  = $is_expanded ? 'sp-post-card--expanded' : 'sp-post-card--compact';

// Relative time (e.g., "2h", "1d").
$post_time  = get_post_time( 'U', false, $post_id );
$diff       = time() - $post_time;
if ( $diff < 3600 ) {
    $rel_time = max( 1, (int) floor( $diff / 60 ) ) . 'm';
} elseif ( $diff < 86400 ) {
    $rel_time = (int) floor( $diff / 3600 ) . 'h';
} else {
    $rel_time = (int) floor( $diff / 86400 ) . 'd';
}

if ( $is_expanded ) :
    // === EXPANDED CARD (Largo, Cobertura) — borderless river item ===
?>
<article class="sp-post-card <?php echo esc_attr( $tier_class ); ?> sp-post-card--<?php echo esc_attr( $formato ); ?>">
    <div class="sp-post-card__header">
        <span class="sp-post-card__author"><?php echo esc_html( $author ); ?></span>
        <span class="sp-post-card__time"><?php echo esc_html( $rel_time ); ?></span>
    </div>

    <span class="sp-post-card__badge"><?php echo esc_html( ucfirst( $formato ) ); ?></span>

    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>">
            <?php echo esc_html( get_the_title( $post_id ) ); ?>
        </a>
    </h3>

    <div class="sp-post-card__excerpt">
        <?php echo wp_kses_post( get_the_excerpt( $post_id ) ); ?>
    </div>

    <div class="sp-post-card__footer">
        <?php if ( $cat_name ) : ?>
            <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__category">
                <?php echo esc_html( $cat_name ); ?>
            </a>
        <?php endif; ?>
        <?php if ( $source ) : ?>
            <a href="<?php echo esc_url( $source['url_utm'] ); ?>" class="sp-post-card__source" target="_blank" rel="noopener">
                Fuente &rarr;
            </a>
        <?php endif; ?>
    </div>
</article>

<?php else :
    // === COMPACT CARD (Corto, Enlace) — thin border box ===
?>
<article class="sp-post-card <?php echo esc_attr( $tier_class ); ?> sp-post-card--<?php echo esc_attr( $formato ); ?>">
    <div class="sp-post-card__header">
        <span class="sp-post-card__author"><?php echo esc_html( $author ); ?></span>
        <span class="sp-post-card__time"><?php echo esc_html( $rel_time ); ?></span>
    </div>

    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>">
            <span class="sp-post-card__inline-badge"><?php echo esc_html( ucfirst( $formato ) ); ?></span>
            <?php if ( $cat_name ) : ?>
                <span class="sp-post-card__inline-category"><?php echo esc_html( $cat_name ); ?></span> —
            <?php endif; ?>
            <?php echo esc_html( get_the_title( $post_id ) ); ?>
        </a>
    </h3>

    <?php if ( 'enlace' === $formato && $source ) : ?>
        <?php
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
</article>
<?php endif; ?>
