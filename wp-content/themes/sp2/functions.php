<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_theme_support( 'post-thumbnails' );

/**
 * Enqueue Google Fonts: Golos Text, Noticia Text, Lexend Peta.
 */
function sp2_enqueue_fonts() {
    wp_enqueue_style(
        'sp2-google-fonts',
        'https://fonts.googleapis.com/css2?family=Golos+Text:wght@400;500;600;700;800&family=Noticia+Text:ital,wght@0,400;0,700;1,400&family=Lexend+Peta:wght@400;800&display=swap',
        array(),
        null
    );
}
add_action( 'wp_enqueue_scripts', 'sp2_enqueue_fonts' );
add_action( 'enqueue_block_editor_assets', 'sp2_enqueue_fonts' );

/**
 * Set locale to es_CL for date formatting.
 */
function sp2_set_locale( $locale ) {
    return 'es_CL';
}
if ( 'es_CL' !== get_locale() && 'es_ES' !== get_locale() ) {
    add_filter( 'locale', 'sp2_set_locale' );
}

/**
 * Enqueue theme stylesheet.
 */
function sp2_enqueue_styles() {
    wp_enqueue_style(
        'sp2-styles',
        get_theme_file_uri( 'assets/css/sp2.css' ),
        array(),
        '2.0.0'
    );
}
add_action( 'wp_enqueue_scripts', 'sp2_enqueue_styles' );

/**
 * Enqueue theme JavaScript.
 */
function sp2_enqueue_scripts() {
    wp_enqueue_script(
        'sp2-scripts',
        get_theme_file_uri( 'assets/js/sp2.js' ),
        array(),
        '2.0.0',
        true
    );
}
add_action( 'wp_enqueue_scripts', 'sp2_enqueue_scripts' );
