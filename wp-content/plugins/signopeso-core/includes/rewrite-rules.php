<?php
/**
 * Permalink Structure & Rewrite Rules
 *
 * - Sets /%category%/%postname%/ as the permalink structure on activation.
 * - Adds /tema/{tag-slug}/ and /tema/{tag-slug}/page/{N}/ rewrite rules.
 * - Flushes rewrite rules on activation after registering custom rules.
 *
 * @package SignoPeso
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ---------------------------------------------------------------------------
// 1. Permalink structure — set on activation
// ---------------------------------------------------------------------------

/**
 * Sets the global permalink structure to /%category%/%postname%/.
 *
 * Must be called before flush_rewrite_rules() so the new structure is in
 * effect when the rewrite rules are regenerated.
 */
function sp_set_permalink_structure(): void {
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure( '/%category%/%postname%/' );
    $wp_rewrite->flush_rules( false ); // write .htaccess without flushing the full cache
}

// ---------------------------------------------------------------------------
// 2. /tema/ tag rewrite rules
// ---------------------------------------------------------------------------

/**
 * Registers rewrite rules that map /tema/{tag-slug}/ and
 * /tema/{tag-slug}/page/{N}/ to the native WordPress tag archive.
 *
 * Must run on every request so the rules are always available, but the actual
 * DB flush only happens on activation (see sp_flush_rewrites()).
 */
function sp_add_tema_rewrite(): void {
    // Paginated: /tema/some-tag/page/3/
    add_rewrite_rule(
        '^tema/([^/]+)/page/([0-9]+)/?$',
        'index.php?tag=$matches[1]&paged=$matches[2]',
        'top'
    );

    // Root: /tema/some-tag/
    add_rewrite_rule(
        '^tema/([^/]+)/?$',
        'index.php?tag=$matches[1]',
        'top'
    );
}
add_action( 'init', 'sp_add_tema_rewrite' );

// ---------------------------------------------------------------------------
// 3. Flush rewrite rules on activation
// ---------------------------------------------------------------------------

/**
 * Activation callback: sets permalink structure, registers custom rules, then
 * flushes so all rules are written to the database and .htaccess.
 */
function sp_flush_rewrites(): void {
    sp_set_permalink_structure();
    sp_add_tema_rewrite();
    flush_rewrite_rules( true );
}
register_activation_hook(
    SP_PLUGIN_DIR . 'signopeso-core.php',
    'sp_flush_rewrites'
);
