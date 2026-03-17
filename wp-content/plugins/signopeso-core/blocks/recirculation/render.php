<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$current_id = $block->context['postId'] ?? get_the_ID();
$count      = (int) ( $attributes['count'] ?? 4 );

// Get popular posts, excluding current.
$popular_ids = sp_get_popular_posts( $count + 1, 30 );
$popular_ids = array_filter( $popular_ids, function( $id ) use ( $current_id ) {
    return (int) $id !== (int) $current_id;
} );
$popular_ids = array_slice( $popular_ids, 0, $count );

// If not enough popular posts, fill with recent.
if ( count( $popular_ids ) < $count ) {
    $exclude = array_merge( $popular_ids, array( $current_id ) );
    $recent  = get_posts( array(
        'numberposts'  => $count - count( $popular_ids ),
        'post_status'  => 'publish',
        'exclude'      => $exclude,
        'orderby'      => 'date',
        'order'        => 'DESC',
        'fields'       => 'ids',
    ) );
    $popular_ids = array_merge( $popular_ids, $recent );
}

if ( empty( $popular_ids ) ) {
    return;
}
?>

<section class="sp-recirculation">
    <div class="sp-recirculation__inner">
        <h2 class="sp-recirculation__heading">Sigue Explorando</h2>

        <?php foreach ( $popular_ids as $pid ) :
            $post_obj   = get_post( $pid );
            $title      = get_the_title( $pid );
            $link       = get_permalink( $pid );
            $author     = get_the_author_meta( 'display_name', $post_obj->post_author );
            $categories = get_the_category( $pid );
            $cat_name   = ! empty( $categories ) ? $categories[0]->name : '';
            $cat_link   = ! empty( $categories ) ? get_category_link( $categories[0]->term_id ) : '';
            $formats    = wp_get_object_terms( $pid, 'sp_formato', array( 'fields' => 'slugs' ) );
            $formato    = ! empty( $formats ) ? $formats[0] : 'corto';
            $is_expanded = in_array( $formato, array( 'largo', 'cobertura' ), true );

            if ( ! $title ) continue;

            // Relative time.
            $post_time = get_post_time( 'U', false, $pid );
            $diff      = time() - $post_time;
            if ( $diff < 3600 ) {
                $rel_time = max( 1, (int) floor( $diff / 60 ) ) . 'm';
            } elseif ( $diff < 86400 ) {
                $rel_time = (int) floor( $diff / 3600 ) . 'h';
            } else {
                $rel_time = (int) floor( $diff / 86400 ) . 'd';
            }

            if ( $is_expanded ) :
                // Expanded card — same as river.
        ?>
            <article class="sp-post-card sp-post-card--expanded sp-post-card--<?php echo esc_attr( $formato ); ?>">
                <div class="sp-post-card__header">
                    <span class="sp-post-card__author"><?php echo esc_html( $author ); ?></span>
                    <span class="sp-post-card__time"><?php echo esc_html( $rel_time ); ?></span>
                </div>
                <span class="sp-post-card__badge"><?php echo esc_html( ucfirst( $formato ) ); ?></span>
                <h3 class="sp-post-card__title">
                    <a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
                </h3>
                <div class="sp-post-card__excerpt"><?php echo wp_kses_post( get_the_excerpt( $pid ) ); ?></div>
                <div class="sp-post-card__footer">
                    <?php if ( $cat_name ) : ?>
                        <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__category"><?php echo esc_html( $cat_name ); ?></a>
                    <?php endif; ?>
                </div>
            </article>
        <?php else :
                // Compact card — same as river.
        ?>
            <article class="sp-post-card sp-post-card--compact sp-post-card--<?php echo esc_attr( $formato ); ?>">
                <div class="sp-post-card__header">
                    <span class="sp-post-card__author"><?php echo esc_html( $author ); ?></span>
                    <span class="sp-post-card__time"><?php echo esc_html( $rel_time ); ?></span>
                </div>
                <h3 class="sp-post-card__title">
                    <a href="<?php echo esc_url( $link ); ?>">
                        <span class="sp-post-card__inline-badge"><?php echo esc_html( ucfirst( $formato ) ); ?></span>
                        <?php if ( $cat_name ) : ?>
                            <span class="sp-post-card__inline-category"><?php echo esc_html( $cat_name ); ?></span> —
                        <?php endif; ?>
                        <?php echo esc_html( $title ); ?>
                    </a>
                </h3>
            </article>
        <?php endif; ?>

        <?php endforeach; ?>
    </div>
</section>
