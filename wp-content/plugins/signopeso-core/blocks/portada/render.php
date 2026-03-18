<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$lead = sp_get_portada_lead();
if ( ! $lead ) {
    return;
}

// Store lead ID for date-stream exclusion (frontend only).
if ( ! is_admin() ) {
    $GLOBALS['sp_portada_lead_id'] = $lead->ID;
}

$secondary     = sp_get_tambien_hoy( $lead->ID, 4 );
$has_secondary = ! empty( $secondary );
$solo_class    = $has_secondary ? '' : ' sp-portada--solo';

// Lead post data.
$lead_cats    = get_the_category( $lead->ID );
$lead_cat     = ! empty( $lead_cats ) ? $lead_cats[0] : null;
$lead_author  = get_the_author_meta( 'display_name', $lead->post_author );
$lead_time    = get_post_time( 'H:i', false, $lead->ID );
$lead_thumb   = get_the_post_thumbnail_url( $lead->ID, 'large' );
$lead_excerpt = get_the_excerpt( $lead );
?>

<div class="sp-portada<?php echo esc_attr( $solo_class ); ?>">

    <div class="sp-portada-lead">
        <?php if ( $lead_thumb ) : ?>
            <div class="sp-portada-lead__img">
                <img src="<?php echo esc_url( $lead_thumb ); ?>" alt="" loading="eager">
            </div>
        <?php endif; ?>

        <div class="sp-portada-lead__over">
            <span class="sp-portada-lead__author"><?php echo esc_html( $lead_author ); ?></span>
            <span class="sp-portada-lead__time"><?php echo esc_html( $lead_time ); ?></span>
        </div>

        <?php if ( $lead_cat ) : ?>
            <a href="<?php echo esc_url( get_category_link( $lead_cat->term_id ) ); ?>" class="sp-portada-lead__cat-badge">
                <?php echo esc_html( $lead_cat->name ); ?>
            </a>
        <?php endif; ?>

        <h1 class="sp-portada-lead__title">
            <a href="<?php echo esc_url( get_permalink( $lead->ID ) ); ?>">
                <?php echo esc_html( get_the_title( $lead->ID ) ); ?>
            </a>
        </h1>

        <?php if ( $lead_excerpt ) : ?>
            <p class="sp-portada-lead__deck"><?php echo esc_html( $lead_excerpt ); ?></p>
        <?php endif; ?>
    </div>

    <?php if ( $has_secondary ) : ?>
    <div class="sp-portada-sec">
        <div class="sp-portada-sec__label sp-section-label sp-section-label--sal">también hoy</div>

        <?php foreach ( $secondary as $i => $sec_post ) :
            $sec_cats    = get_the_category( $sec_post->ID );
            $sec_cat     = ! empty( $sec_cats ) ? $sec_cats[0] : null;
            $sec_time    = get_post_time( 'H:i', false, $sec_post->ID );
            $sec_thumb   = ( $i === 0 ) ? get_the_post_thumbnail_url( $sec_post->ID, 'medium_large' ) : false;
            $sec_source  = sp_get_source_data( $sec_post->ID );
            $sec_formats = wp_get_object_terms( $sec_post->ID, 'sp_formato', array( 'fields' => 'slugs' ) );
            $sec_formato = ! empty( $sec_formats ) ? $sec_formats[0] : 'corto';
        ?>
            <div class="sp-portada-sec__item">
                <?php if ( $sec_thumb ) : ?>
                    <div class="sp-portada-sec__item-img">
                        <img src="<?php echo esc_url( $sec_thumb ); ?>" alt="" loading="lazy">
                    </div>
                <?php endif; ?>

                <div class="sp-portada-sec__item-over">
                    <span class="sp-portada-sec__cat-pill"><?php echo esc_html( $sec_cat ? $sec_cat->name : '' ); ?></span>
                    <span class="sp-portada-sec__time"><?php echo esc_html( $sec_time ); ?></span>
                </div>

                <h3 class="sp-portada-sec__title">
                    <a href="<?php echo esc_url( get_permalink( $sec_post->ID ) ); ?>">
                        <?php echo esc_html( get_the_title( $sec_post->ID ) ); ?>
                    </a>
                </h3>

                <?php if ( 'enlace' === $sec_formato && $sec_source && ! empty( $sec_source['domain'] ) ) : ?>
                    <div class="sp-portada-sec__source">↗ <?php echo esc_html( $sec_source['domain'] ); ?></div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>
