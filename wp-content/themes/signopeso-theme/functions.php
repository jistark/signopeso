<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Enqueue Google Fonts (Lora for headings).
 */
function signopeso_enqueue_fonts() {
    wp_enqueue_style(
        'signopeso-google-fonts',
        'https://fonts.googleapis.com/css2?family=Lora:wght@400;500;600;700&display=swap',
        array(),
        null
    );
}
add_action( 'wp_enqueue_scripts', 'signopeso_enqueue_fonts' );

/**
 * Set locale to es_CL for date formatting.
 */
function signopeso_set_locale( $locale ) {
    return 'es_CL';
}
// Only activate if locale isn't already Spanish.
if ( 'es_CL' !== get_locale() && 'es_ES' !== get_locale() ) {
    add_filter( 'locale', 'signopeso_set_locale' );
}

/**
 * Enqueue theme stylesheet.
 */
function signopeso_enqueue_styles() {
    wp_enqueue_style(
        'signopeso-styles',
        get_theme_file_uri( 'assets/css/signopeso.css' ),
        array(),
        defined( 'SP_VERSION' ) ? SP_VERSION : '1.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'signopeso_enqueue_styles' );
