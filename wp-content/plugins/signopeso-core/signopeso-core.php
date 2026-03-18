<?php
/**
 * Plugin Name: SignoPeso Core
 * Description: Core functionality for SignoPeso ($P) — post formats, source embeds, newsletter, ads, and custom blocks.
 * Version: 1.0.0
 * Author: JI Stark
 * Text Domain: signopeso
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SP_VERSION', '1.0.0' );
define( 'SP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Core includes.
require_once SP_PLUGIN_DIR . 'includes/post-formats.php';
require_once SP_PLUGIN_DIR . 'includes/rewrite-rules.php';
require_once SP_PLUGIN_DIR . 'includes/source-embed.php';
require_once SP_PLUGIN_DIR . 'includes/ad-slots.php';
require_once SP_PLUGIN_DIR . 'includes/popular-posts.php';
require_once SP_PLUGIN_DIR . 'includes/rest-api.php';
require_once SP_PLUGIN_DIR . 'includes/newsletter/settings.php';
require_once SP_PLUGIN_DIR . 'includes/newsletter/cron.php';
require_once SP_PLUGIN_DIR . 'includes/portada.php';

/**
 * Register all SignoPeso blocks.
 */
function sp_register_blocks() {
    $blocks = array(
        'source-card',
        'post-card',
        'date-stream',
        'ad-slot',
        'popular-posts',
        'newsletter-form',
        'full-archive',
        'recirculation',
        'recirculation-lite',
        'portada',
        'popular-strip',
        'search-header',
    );

    foreach ( $blocks as $block ) {
        register_block_type( SP_PLUGIN_DIR . 'blocks/' . $block );
    }
}
add_action( 'init', 'sp_register_blocks' );

/**
 * Register custom block category.
 */
function sp_block_categories( $categories ) {
    array_unshift( $categories, array(
        'slug'  => 'signopeso',
        'title' => 'SignoPeso',
    ) );
    return $categories;
}
add_filter( 'block_categories_all', 'sp_block_categories' );
