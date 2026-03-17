<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$heading     = esc_html( $attributes['heading'] ?? 'Suscríbete' );
$subheading  = esc_html( $attributes['subheading'] ?? '' );
$cta         = esc_html( $attributes['ctaText'] ?? 'Suscríbete' );
$placeholder = esc_attr( $attributes['placeholder'] ?? 'tu@email.com' );
$rest_url    = esc_url( rest_url( 'signopeso/v1/subscribe' ) );
$nonce       = wp_create_nonce( 'wp_rest' );
?>

<div class="sp-newsletter-form" data-rest-url="<?php echo $rest_url; ?>" data-nonce="<?php echo $nonce; ?>">
    <p class="sp-newsletter-form__heading"><?php echo $heading; ?></p>
    <?php if ( $subheading ) : ?>
        <p class="sp-newsletter-form__subheading"><?php echo $subheading; ?></p>
    <?php endif; ?>
    <form class="sp-newsletter-form__form">
        <input type="email" name="email" placeholder="<?php echo $placeholder; ?>" required class="sp-newsletter-form__input" />
        <button type="submit" class="sp-newsletter-form__button"><?php echo $cta; ?></button>
    </form>
    <p class="sp-newsletter-form__message" style="display:none;"></p>
</div>
