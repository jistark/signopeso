<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_theme_support( 'post-thumbnails' );

/**
 * Enqueue Google Fonts (Newsreader, Inter, Datatype).
 */
function sp_theme_enqueue_fonts() {
    wp_enqueue_style(
        'sp-google-fonts',
        'https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,600;0,6..72,700;0,6..72,800;1,6..72,400&family=Inter:wght@400;500;600;700&family=Datatype:wght@300;400;500;600;700&display=swap',
        array(),
        null
    );
}
add_action( 'wp_enqueue_scripts', 'sp_theme_enqueue_fonts' );

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
