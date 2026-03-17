<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$position = $attributes['position'] ?? 'sidebar';
$code     = sp_get_ad_code( $position );

if ( ! $code ) {
    return;
}

printf( '<div class="sp-ad-slot sp-ad-slot--%s">%s</div>', esc_attr( $position ), $code ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Admin-controlled ad code
