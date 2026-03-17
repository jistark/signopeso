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
