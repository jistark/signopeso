# SignoPeso ($P) Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a WordPress block theme + companion plugin for SignoPeso ($P), an economy + tech blog on WordPress.com Business.

**Architecture:** Two deliverables — `signopeso-theme` (block theme FSE, presentation only) and `signopeso-core` (plugin, all functionality). Plugin provides custom blocks consumed by theme templates. Plugin is built first since the theme depends on it.

**Tech Stack:** WordPress 6.x, PHP 8.x, Block Theme (FSE), theme.json v3, server-rendered blocks (render_callback), WP REST API, WP-Cron, Resend API.

**Spec:** `docs/superpowers/specs/2026-03-17-signopeso-design.md`

---

## Chunk 1: Plugin Foundation

### Task 1: Plugin Bootstrap

**Files:**
- Create: `wp-content/plugins/signopeso-core/signopeso-core.php`

- [ ] **Step 1: Create plugin bootstrap file**

```php
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
```

- [ ] **Step 2: Create directory structure**

Run:
```bash
mkdir -p wp-content/plugins/signopeso-core/includes
mkdir -p wp-content/plugins/signopeso-core/blocks
```

- [ ] **Step 3: Create placeholder include files**

Create `includes/post-formats.php` and `includes/rewrite-rules.php` as empty PHP files with opening `<?php` tag.

- [ ] **Step 4: Activate plugin via WordPress admin**

Navigate to WordPress admin > Plugins and activate "SignoPeso Core". Verify no errors.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/signopeso-core/
git commit -m "feat: scaffold signopeso-core plugin with bootstrap and directory structure"
```

---

### Task 2: Post Formats Taxonomy (`sp_formato`)

**Files:**
- Modify: `wp-content/plugins/signopeso-core/includes/post-formats.php`

- [ ] **Step 1: Register the taxonomy**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register sp_formato taxonomy.
 */
function sp_register_formato_taxonomy() {
    $labels = array(
        'name'          => 'Formatos',
        'singular_name' => 'Formato',
        'search_items'  => 'Buscar formatos',
        'all_items'     => 'Todos los formatos',
        'edit_item'     => 'Editar formato',
        'update_item'   => 'Actualizar formato',
        'add_new_item'  => 'Agregar formato',
        'new_item_name' => 'Nuevo formato',
        'menu_name'     => 'Formatos',
    );

    register_taxonomy( 'sp_formato', 'post', array(
        'labels'            => $labels,
        'public'            => true,
        'hierarchical'      => false,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => array( 'slug' => 'formato' ),
        'meta_box_cb'       => 'sp_formato_meta_box',
    ) );
}
add_action( 'init', 'sp_register_formato_taxonomy' );

/**
 * Pre-populate default terms on plugin activation.
 */
function sp_populate_formato_terms() {
    $terms = array(
        'corto'     => 'Corto',
        'enlace'    => 'Enlace',
        'largo'     => 'Largo',
        'cobertura' => 'Cobertura',
    );

    foreach ( $terms as $slug => $name ) {
        if ( ! term_exists( $slug, 'sp_formato' ) ) {
            wp_insert_term( $name, 'sp_formato', array( 'slug' => $slug ) );
        }
    }
}
register_activation_hook( SP_PLUGIN_DIR . 'signopeso-core.php', 'sp_populate_formato_terms' );

/**
 * Also populate on init if terms are missing (handles fresh installs).
 */
function sp_maybe_populate_formato_terms() {
    if ( get_option( 'sp_formato_terms_populated' ) ) {
        return;
    }
    sp_populate_formato_terms();
    update_option( 'sp_formato_terms_populated', true );
}
add_action( 'init', 'sp_maybe_populate_formato_terms', 20 );

/**
 * Render radio buttons instead of checkboxes for sp_formato.
 */
function sp_formato_meta_box( $post, $box ) {
    $terms    = get_terms( array(
        'taxonomy'   => 'sp_formato',
        'hide_empty' => false,
    ) );
    $current  = wp_get_object_terms( $post->ID, 'sp_formato', array( 'fields' => 'slugs' ) );
    $selected = ! empty( $current ) ? $current[0] : 'corto';

    wp_nonce_field( 'sp_formato_save', 'sp_formato_nonce' );

    echo '<div id="sp-formato-radios">';
    foreach ( $terms as $term ) {
        printf(
            '<label style="display:block;margin:6px 0;"><input type="radio" name="sp_formato" value="%s" %s /> %s</label>',
            esc_attr( $term->slug ),
            checked( $selected, $term->slug, false ),
            esc_html( $term->name )
        );
    }
    echo '</div>';
}

/**
 * Save the selected formato on post save.
 */
function sp_save_formato( $post_id ) {
    if ( ! isset( $_POST['sp_formato_nonce'] ) || ! wp_verify_nonce( $_POST['sp_formato_nonce'], 'sp_formato_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $formato = isset( $_POST['sp_formato'] ) ? sanitize_text_field( $_POST['sp_formato'] ) : 'corto';
    wp_set_object_terms( $post_id, $formato, 'sp_formato' );
}
add_action( 'save_post', 'sp_save_formato' );

/**
 * Default to 'corto' for new posts that have no formato set.
 */
function sp_default_formato( $post_id, $post, $update ) {
    if ( $update ) {
        return;
    }
    if ( 'post' !== $post->post_type ) {
        return;
    }

    $terms = wp_get_object_terms( $post_id, 'sp_formato' );
    if ( empty( $terms ) ) {
        wp_set_object_terms( $post_id, 'corto', 'sp_formato' );
    }
}
add_action( 'wp_insert_post', 'sp_default_formato', 10, 3 );
```

- [ ] **Step 2: Verify taxonomy appears in admin**

Deactivate and reactivate the plugin to trigger activation hook. Go to Posts > Add New. Verify "Formatos" meta box appears with radio buttons for Corto, Enlace, Largo, Cobertura. Verify Corto is selected by default.

- [ ] **Step 3: Commit**

```bash
git add wp-content/plugins/signopeso-core/includes/post-formats.php
git commit -m "feat: register sp_formato taxonomy with radio buttons and default terms"
```

---

### Task 3: Permalink Structure & Rewrite Rules

**Files:**
- Modify: `wp-content/plugins/signopeso-core/includes/rewrite-rules.php`

- [ ] **Step 1: Implement permalink and rewrite setup**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Set permalink structure to /%category%/%postname%/ on activation.
 */
function sp_set_permalink_structure() {
    global $wp_rewrite;
    $wp_rewrite->set_permalink_structure( '/%category%/%postname%/' );
    $wp_rewrite->flush_rules();
}
register_activation_hook( SP_PLUGIN_DIR . 'signopeso-core.php', 'sp_set_permalink_structure' );

/**
 * Add /tema/ rewrite for tag archives (legacy CM URL pattern).
 */
function sp_add_tema_rewrite() {
    add_rewrite_rule(
        '^tema/([^/]+)/?$',
        'index.php?tag=$matches[1]',
        'top'
    );
    add_rewrite_rule(
        '^tema/([^/]+)/page/([0-9]+)/?$',
        'index.php?tag=$matches[1]&paged=$matches[2]',
        'top'
    );
}
add_action( 'init', 'sp_add_tema_rewrite' );

/**
 * Flush rewrite rules on activation.
 */
function sp_flush_rewrites() {
    sp_add_tema_rewrite();
    flush_rewrite_rules();
}
register_activation_hook( SP_PLUGIN_DIR . 'signopeso-core.php', 'sp_flush_rewrites' );
```

- [ ] **Step 2: Verify rewrites**

Deactivate and reactivate plugin. Go to Settings > Permalinks and verify structure is `/%category%/%postname%/`. Create a test post with a category and verify the URL is `/category-slug/post-slug/`.

- [ ] **Step 3: Commit**

```bash
git add wp-content/plugins/signopeso-core/includes/rewrite-rules.php
git commit -m "feat: configure SEO-friendly permalinks and /tema/ tag rewrite"
```

---

### Task 4: Source Embed — Meta Box & OG Fetch

**Files:**
- Create: `wp-content/plugins/signopeso-core/includes/source-embed.php`
- Modify: `wp-content/plugins/signopeso-core/signopeso-core.php` (add require)

- [ ] **Step 1: Add require to bootstrap**

Add to `signopeso-core.php` after the existing requires:
```php
require_once SP_PLUGIN_DIR . 'includes/source-embed.php';
```

- [ ] **Step 2: Implement source embed system**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register the source URL meta box.
 */
function sp_add_source_meta_box() {
    add_meta_box(
        'sp_source_url',
        'Fuente Original',
        'sp_render_source_meta_box',
        'post',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'sp_add_source_meta_box' );

/**
 * Render the source URL meta box.
 */
function sp_render_source_meta_box( $post ) {
    wp_nonce_field( 'sp_source_save', 'sp_source_nonce' );

    $source_url = get_post_meta( $post->ID, '_sp_source_url', true );
    $og_title   = get_post_meta( $post->ID, '_sp_source_og_title', true );
    $og_domain  = get_post_meta( $post->ID, '_sp_source_og_domain', true );
    $og_status  = get_post_meta( $post->ID, '_sp_source_og_status', true );

    echo '<p>';
    printf(
        '<input type="url" name="sp_source_url" value="%s" style="width:100%%;" placeholder="https://ejemplo.com/articulo-original" />',
        esc_attr( $source_url )
    );
    echo '</p>';

    if ( $og_title ) {
        printf( '<p style="color:#666;">OG: %s (%s)</p>', esc_html( $og_title ), esc_html( $og_domain ) );
    }

    if ( 'pending' === $og_status ) {
        echo '<p style="color:#999;"><em>Obteniendo datos de la fuente...</em></p>';
    }

    if ( $source_url ) {
        echo '<p><label><input type="checkbox" name="sp_source_refresh" value="1" /> Refrescar datos OG</label></p>';
    }
}

/**
 * Save source URL and schedule OG fetch.
 */
function sp_save_source_url( $post_id ) {
    if ( ! isset( $_POST['sp_source_nonce'] ) || ! wp_verify_nonce( $_POST['sp_source_nonce'], 'sp_source_save' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    $new_url = isset( $_POST['sp_source_url'] ) ? esc_url_raw( $_POST['sp_source_url'] ) : '';
    $old_url = get_post_meta( $post_id, '_sp_source_url', true );
    $refresh = ! empty( $_POST['sp_source_refresh'] );

    update_post_meta( $post_id, '_sp_source_url', $new_url );

    // Schedule OG fetch if URL is new or refresh requested.
    if ( $new_url && ( $new_url !== $old_url || $refresh ) ) {
        update_post_meta( $post_id, '_sp_source_og_status', 'pending' );
        wp_schedule_single_event( time(), 'sp_fetch_og_data', array( $post_id ) );
    }

    // Clear OG data if URL removed.
    if ( ! $new_url ) {
        delete_post_meta( $post_id, '_sp_source_og_title' );
        delete_post_meta( $post_id, '_sp_source_og_desc' );
        delete_post_meta( $post_id, '_sp_source_og_image_id' );
        delete_post_meta( $post_id, '_sp_source_og_domain' );
        delete_post_meta( $post_id, '_sp_source_og_status' );
    }
}
add_action( 'save_post', 'sp_save_source_url' );

/**
 * Async OG data fetch handler.
 */
function sp_do_fetch_og_data( $post_id ) {
    $url = get_post_meta( $post_id, '_sp_source_url', true );
    if ( ! $url ) {
        return;
    }

    $response = wp_remote_get( $url, array(
        'timeout'    => 15,
        'user-agent' => 'SignoPeso/1.0 (OG Fetch)',
    ) );

    if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
        update_post_meta( $post_id, '_sp_source_og_status', 'failed' );
        return;
    }

    $html = wp_remote_retrieve_body( $response );
    $og   = sp_parse_og_tags( $html );

    // Extract domain.
    $parsed = wp_parse_url( $url );
    $domain = isset( $parsed['host'] ) ? preg_replace( '/^www\./', '', $parsed['host'] ) : '';

    update_post_meta( $post_id, '_sp_source_og_title', sanitize_text_field( $og['title'] ?? '' ) );
    update_post_meta( $post_id, '_sp_source_og_desc', sanitize_text_field( $og['description'] ?? '' ) );
    update_post_meta( $post_id, '_sp_source_og_domain', sanitize_text_field( $domain ) );

    // Sideload image if available.
    if ( ! empty( $og['image'] ) ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $image_id = media_sideload_image( $og['image'], $post_id, '', 'id' );
        if ( ! is_wp_error( $image_id ) ) {
            update_post_meta( $post_id, '_sp_source_og_image_id', $image_id );
        }
    }

    update_post_meta( $post_id, '_sp_source_og_status', 'fetched' );
}
add_action( 'sp_fetch_og_data', 'sp_do_fetch_og_data' );

/**
 * Parse OG meta tags from HTML.
 */
function sp_parse_og_tags( $html ) {
    $og = array();

    $tags = array(
        'title'       => 'og:title',
        'description' => 'og:description',
        'image'       => 'og:image',
        'site_name'   => 'og:site_name',
    );

    foreach ( $tags as $key => $property ) {
        if ( preg_match( '/<meta[^>]+property=["\']' . preg_quote( $property, '/' ) . '["\'][^>]+content=["\']([^"\']*)["\']/', $html, $match ) ) {
            $og[ $key ] = $match[1];
        } elseif ( preg_match( '/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']' . preg_quote( $property, '/' ) . '["\']/', $html, $match ) ) {
            $og[ $key ] = $match[1];
        }
    }

    // Fallback to <title> tag if no og:title.
    if ( empty( $og['title'] ) && preg_match( '/<title[^>]*>([^<]+)<\/title>/', $html, $match ) ) {
        $og['title'] = trim( $match[1] );
    }

    return $og;
}

/**
 * Get source data for a post (used by blocks).
 */
function sp_get_source_data( $post_id ) {
    $url = get_post_meta( $post_id, '_sp_source_url', true );
    if ( ! $url ) {
        return null;
    }

    return array(
        'url'      => $url,
        'title'    => get_post_meta( $post_id, '_sp_source_og_title', true ),
        'desc'     => get_post_meta( $post_id, '_sp_source_og_desc', true ),
        'image_id' => get_post_meta( $post_id, '_sp_source_og_image_id', true ),
        'domain'   => get_post_meta( $post_id, '_sp_source_og_domain', true ),
        'status'   => get_post_meta( $post_id, '_sp_source_og_status', true ),
        'url_utm'  => add_query_arg( array(
            'utm_source' => 'signopeso',
            'utm_medium' => 'referral',
        ), $url ),
    );
}
```

- [ ] **Step 3: Verify meta box in editor**

Create a new post. Verify "Fuente Original" meta box appears. Enter a URL (e.g., `https://www.apple.com`), save the post. Reload and verify the OG data gets fetched (may require a second page load for the async cron to fire).

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/signopeso-core/includes/source-embed.php
git add wp-content/plugins/signopeso-core/signopeso-core.php
git commit -m "feat: source embed system with meta box, async OG fetch, and image sideload"
```

---

## Chunk 2: Core Blocks

### Task 5: Source Card Block (`sp/source-card`)

**Files:**
- Create: `wp-content/plugins/signopeso-core/blocks/source-card/block.json`
- Create: `wp-content/plugins/signopeso-core/blocks/source-card/render.php`

- [ ] **Step 1: Create block.json**

```json
{
    "apiVersion": 3,
    "name": "sp/source-card",
    "title": "Source Card",
    "category": "signopeso",
    "description": "Renders an OG preview card for the post's source URL.",
    "textdomain": "signopeso",
    "supports": {
        "html": false,
        "align": false
    },
    "render": "file:./render.php"
}
```

- [ ] **Step 2: Create render.php**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_id = get_the_ID();
$source  = sp_get_source_data( $post_id );

if ( ! $source ) {
    return;
}

$has_og = ! empty( $source['title'] );
?>

<?php if ( $has_og ) : ?>
    <div class="sp-source-card">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener" class="sp-source-card__link">
            <?php if ( $source['image_id'] ) : ?>
                <div class="sp-source-card__image">
                    <?php echo wp_get_attachment_image( $source['image_id'], 'thumbnail' ); ?>
                </div>
            <?php endif; ?>
            <div class="sp-source-card__info">
                <span class="sp-source-card__title"><?php echo esc_html( $source['title'] ); ?></span>
                <?php if ( $source['desc'] ) : ?>
                    <span class="sp-source-card__desc"><?php echo esc_html( wp_trim_words( $source['desc'], 20 ) ); ?></span>
                <?php endif; ?>
                <span class="sp-source-card__domain"><?php echo esc_html( $source['domain'] ); ?></span>
            </div>
        </a>
    </div>
<?php else : ?>
    <p class="sp-source-link">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
            Ir a la fuente &rarr; <?php echo esc_html( $source['domain'] ); ?>
        </a>
    </p>
<?php endif; ?>
```

- [ ] **Step 3: Register block in plugin bootstrap**

Add to `signopeso-core.php`:

```php
/**
 * Register all SignoPeso blocks.
 */
function sp_register_blocks() {
    $blocks = array(
        'source-card',
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
```

- [ ] **Step 4: Verify block renders**

Create a post with a source URL that has OG tags. Insert `<!-- wp:sp/source-card /-->` in the content. View the post on the frontend. Verify the card renders with title, description, image, and domain.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/source-card/
git add wp-content/plugins/signopeso-core/signopeso-core.php
git commit -m "feat: source-card block renders OG preview with UTM attribution"
```

---

### Task 6: Post Card Block (`sp/post-card`)

**Files:**
- Create: `wp-content/plugins/signopeso-core/blocks/post-card/block.json`
- Create: `wp-content/plugins/signopeso-core/blocks/post-card/render.php`

- [ ] **Step 1: Create block.json**

```json
{
    "apiVersion": 3,
    "name": "sp/post-card",
    "title": "Post Card",
    "category": "signopeso",
    "description": "Format-aware post card. Renders differently based on sp_formato taxonomy.",
    "textdomain": "signopeso",
    "usesContext": ["postId"],
    "supports": {
        "html": false
    },
    "render": "file:./render.php"
}
```

- [ ] **Step 2: Create render.php**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$post_id = $block->context['postId'] ?? get_the_ID();
if ( ! $post_id ) {
    return;
}

$post    = get_post( $post_id );
$formats = wp_get_object_terms( $post_id, 'sp_formato', array( 'fields' => 'slugs' ) );
$formato = ! empty( $formats ) ? $formats[0] : 'corto';

$categories = get_the_category( $post_id );
$cat_name   = ! empty( $categories ) ? $categories[0]->name : '';
$cat_link   = ! empty( $categories ) ? get_category_link( $categories[0]->term_id ) : '';

$source     = sp_get_source_data( $post_id );
$author     = get_the_author_meta( 'display_name', $post->post_author );
$permalink  = get_permalink( $post_id );

// Format label styling.
$label_class = 'sp-post-card__label';
if ( 'largo' === $formato || 'cobertura' === $formato ) {
    $label_class .= ' sp-post-card__label--highlight';
}
?>

<article class="sp-post-card sp-post-card--<?php echo esc_attr( $formato ); ?>">
    <div class="sp-post-card__meta-top">
        <span class="<?php echo esc_attr( $label_class ); ?>">
            <?php echo esc_html( ucfirst( $formato ) ); ?>
        </span>
        <?php if ( $cat_name ) : ?>
            <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__category">
                <?php echo esc_html( $cat_name ); ?>
            </a>
        <?php endif; ?>
    </div>

    <h3 class="sp-post-card__title">
        <a href="<?php echo esc_url( $permalink ); ?>">
            <?php echo esc_html( get_the_title( $post_id ) ); ?>
        </a>
    </h3>

    <?php if ( 'largo' === $formato || 'cobertura' === $formato ) : ?>
        <div class="sp-post-card__excerpt">
            <?php echo wp_kses_post( get_the_excerpt( $post_id ) ); ?>
        </div>
        <a href="<?php echo esc_url( $permalink ); ?>" class="sp-post-card__readmore">
            Sigue leyendo &rarr;
        </a>
    <?php else : ?>
        <div class="sp-post-card__excerpt">
            <?php echo wp_kses_post( get_the_excerpt( $post_id ) ); ?>
        </div>
    <?php endif; ?>

    <?php if ( 'enlace' === $formato && $source ) : ?>
        <?php
        // Render inline source card.
        $has_og = ! empty( $source['title'] );
        if ( $has_og ) : ?>
            <div class="sp-source-card sp-source-card--inline">
                <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener" class="sp-source-card__link">
                    <?php if ( $source['image_id'] ) : ?>
                        <div class="sp-source-card__image">
                            <?php echo wp_get_attachment_image( $source['image_id'], 'thumbnail' ); ?>
                        </div>
                    <?php endif; ?>
                    <div class="sp-source-card__info">
                        <span class="sp-source-card__title"><?php echo esc_html( $source['title'] ); ?></span>
                        <span class="sp-source-card__domain"><?php echo esc_html( $source['domain'] ); ?></span>
                    </div>
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="sp-post-card__footer">
        <span class="sp-post-card__byline">Por <?php echo esc_html( $author ); ?></span>
        <?php if ( $source && 'enlace' !== $formato ) : ?>
            <a href="<?php echo esc_url( $source['url_utm'] ); ?>" class="sp-post-card__source" target="_blank" rel="noopener">
                Ir a la fuente &rarr;
            </a>
        <?php endif; ?>
    </div>
</article>
```

- [ ] **Step 3: Register the block**

Add `'post-card'` to the `$blocks` array in `sp_register_blocks()` in `signopeso-core.php`.

- [ ] **Step 4: Verify with a test post**

Create posts with different formats (Corto, Enlace with source URL, Largo). Verify the block renders different structures for each format. Test by inserting `<!-- wp:sp/post-card /-->` on a page.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/post-card/
git add wp-content/plugins/signopeso-core/signopeso-core.php
git commit -m "feat: post-card block with format-aware rendering (corto, enlace, largo, cobertura)"
```

---

### Task 7: Date Stream Block (`sp/date-stream`)

**Files:**
- Create: `wp-content/plugins/signopeso-core/blocks/date-stream/block.json`
- Create: `wp-content/plugins/signopeso-core/blocks/date-stream/render.php`

- [ ] **Step 1: Create block.json**

```json
{
    "apiVersion": 3,
    "name": "sp/date-stream",
    "title": "Date Stream",
    "category": "signopeso",
    "description": "Chronological post stream grouped by date with format-aware cards.",
    "textdomain": "signopeso",
    "attributes": {
        "postsPerPage": {
            "type": "number",
            "default": 10
        },
        "inheritQuery": {
            "type": "boolean",
            "default": false
        }
    },
    "supports": {
        "html": false
    },
    "render": "file:./render.php"
}
```

- [ ] **Step 2: Create render.php**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$posts_per_page = $attributes['postsPerPage'] ?? 10;
$inherit_query  = $attributes['inheritQuery'] ?? false;
$paged          = get_query_var( 'paged' ) ? get_query_var( 'paged' ) : 1;

$query_args = array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => $posts_per_page,
    'paged'          => $paged,
);

// Inherit archive query context (category, tag, date, etc.).
if ( $inherit_query ) {
    $queried = get_queried_object();
    if ( $queried instanceof WP_Term ) {
        $query_args['tax_query'] = array(
            array(
                'taxonomy' => $queried->taxonomy,
                'terms'    => $queried->term_id,
            ),
        );
    }
    // Support date archives.
    if ( is_year() ) {
        $query_args['year'] = get_query_var( 'year' );
    } elseif ( is_month() ) {
        $query_args['year']     = get_query_var( 'year' );
        $query_args['monthnum'] = get_query_var( 'monthnum' );
    }
}

$query = new WP_Query( $query_args );

if ( ! $query->have_posts() ) {
    echo '<p>No hay publicaciones aún.</p>';
    return;
}

$current_date = '';

echo '<div class="sp-date-stream">';

while ( $query->have_posts() ) {
    $query->the_post();

    // Date header.
    $post_date = date_i18n( 'l j \d\e F, Y', get_the_time( 'U' ) );
    if ( $post_date !== $current_date ) {
        if ( $current_date ) {
            echo '</div><!-- /.sp-date-stream__group -->';
        }
        $current_date = $post_date;
        printf(
            '<div class="sp-date-stream__group"><h2 class="sp-date-stream__date">%s</h2>',
            esc_html( ucfirst( $post_date ) )
        );
    }

    // Render post card.
    $block_instance = new WP_Block(
        array( 'blockName' => 'sp/post-card' ),
        array( 'postId' => get_the_ID() )
    );
    echo $block_instance->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

// Close last group.
if ( $current_date ) {
    echo '</div><!-- /.sp-date-stream__group -->';
}

// Pagination.
$total_pages = $query->max_num_pages;
if ( $total_pages > 1 ) {
    echo '<nav class="sp-date-stream__pagination">';
    echo paginate_links( array( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        'total'   => $total_pages,
        'current' => $paged,
    ) );
    echo '</nav>';
}

echo '</div><!-- /.sp-date-stream -->';

wp_reset_postdata();
```

- [ ] **Step 3: Register the block**

Add `'date-stream'` to the `$blocks` array in `sp_register_blocks()`.

- [ ] **Step 4: Verify stream renders**

Create several test posts across different dates with different formats. Insert `<!-- wp:sp/date-stream /-->` on the homepage or a test page. Verify posts appear grouped by date with date headers in Spanish. Verify pagination works if more than 10 posts.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/date-stream/
git add wp-content/plugins/signopeso-core/signopeso-core.php
git commit -m "feat: date-stream block groups posts by date with format-aware cards"
```

---

## Chunk 3: Block Theme

### Task 8: Theme Foundation — `theme.json` and `style.css`

**Files:**
- Create: `wp-content/themes/signopeso-theme/theme.json`
- Create: `wp-content/themes/signopeso-theme/style.css`
- Create: `wp-content/themes/signopeso-theme/functions.php`

- [ ] **Step 1: Create style.css (theme header)**

```css
/*
Theme Name: SignoPeso
Theme URI: https://signopeso.com
Author: JI Stark
Description: Economía + tech, corto y digerible. Block theme for SignoPeso ($P).
Version: 1.0.0
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
License: GNU General Public License v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: signopeso
*/
```

- [ ] **Step 2: Create theme.json**

```json
{
    "$schema": "https://schemas.wp.org/wp/6.7/theme.json",
    "version": 3,
    "settings": {
        "appearanceTools": true,
        "color": {
            "defaultDuotone": false,
            "defaultGradients": false,
            "defaultPalette": false,
            "palette": [
                { "color": "#F06B6B", "name": "Primary (Salmon)", "slug": "primary" },
                { "color": "#1A1A1A", "name": "Secondary", "slug": "secondary" },
                { "color": "#FFEB3B", "name": "Highlight", "slug": "highlight" },
                { "color": "#FAFAFA", "name": "Background", "slug": "background" },
                { "color": "#FFFFFF", "name": "Surface", "slug": "surface" },
                { "color": "#E0E0E0", "name": "Border", "slug": "border" },
                { "color": "#999999", "name": "Muted", "slug": "muted" }
            ]
        },
        "typography": {
            "defaultFontSizes": false,
            "fontFamilies": [
                {
                    "fontFamily": "\"Lora\", Georgia, \"Times New Roman\", serif",
                    "slug": "heading",
                    "name": "Heading"
                },
                {
                    "fontFamily": "Inter, -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Oxygen-Sans, Ubuntu, Cantarell, \"Helvetica Neue\", sans-serif",
                    "slug": "body",
                    "name": "Body"
                },
                {
                    "fontFamily": "\"JetBrains Mono\", \"SF Mono\", Monaco, \"Cascadia Code\", Consolas, \"Courier New\", monospace",
                    "slug": "meta",
                    "name": "Meta"
                }
            ],
            "fontSizes": [
                { "size": "0.75rem", "slug": "small", "name": "Small" },
                { "size": "1rem", "slug": "medium", "name": "Medium" },
                { "size": "1.25rem", "slug": "large", "name": "Large" },
                { "size": "1.75rem", "slug": "x-large", "name": "X-Large" },
                { "size": "2.25rem", "slug": "xx-large", "name": "XX-Large" }
            ]
        },
        "layout": {
            "contentSize": "720px",
            "wideSize": "1100px"
        },
        "spacing": {
            "units": ["px", "rem", "%"]
        }
    },
    "styles": {
        "color": {
            "background": "var(--wp--preset--color--background)",
            "text": "var(--wp--preset--color--secondary)"
        },
        "typography": {
            "fontFamily": "var(--wp--preset--font-family--body)",
            "fontSize": "var(--wp--preset--font-size--medium)",
            "lineHeight": "1.7"
        },
        "elements": {
            "heading": {
                "typography": {
                    "fontFamily": "var(--wp--preset--font-family--heading)",
                    "fontWeight": "700",
                    "lineHeight": "1.2"
                },
                "color": {
                    "text": "var(--wp--preset--color--secondary)"
                }
            },
            "link": {
                "color": {
                    "text": "var(--wp--preset--color--secondary)"
                },
                ":hover": {
                    "color": {
                        "text": "var(--wp--preset--color--primary)"
                    }
                }
            }
        },
        "blocks": {}
    },
    "templateParts": [
        { "name": "header", "title": "Header", "area": "header" },
        { "name": "footer", "title": "Footer", "area": "footer" },
        { "name": "sidebar", "title": "Sidebar" }
    ],
    "customTemplates": [
        { "name": "archive-all", "title": "Full Archive", "postTypes": ["page"] }
    ]
}
```

- [ ] **Step 3: Create minimal functions.php**

```php
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
```

- [ ] **Step 4: Create required template/parts directories**

```bash
mkdir -p wp-content/themes/signopeso-theme/templates
mkdir -p wp-content/themes/signopeso-theme/parts
mkdir -p wp-content/themes/signopeso-theme/patterns
```

- [ ] **Step 5: Commit**

```bash
git add wp-content/themes/signopeso-theme/
git commit -m "feat: scaffold signopeso-theme with theme.json design tokens and typography"
```

---

### Task 9: Header Template Part

**Files:**
- Create: `wp-content/themes/signopeso-theme/parts/header.html`

- [ ] **Step 1: Create header.html**

```html
<!-- wp:group {"style":{"color":{"background":"var(--wp--preset--color--primary)"},"spacing":{"padding":{"top":"12px","bottom":"12px","left":"24px","right":"24px"}}},"layout":{"type":"constrained","contentSize":"1100px"}} -->
<div class="wp-block-group" style="background-color:var(--wp--preset--color--primary);padding-top:12px;padding-right:24px;padding-bottom:12px;padding-left:24px">

    <!-- wp:group {"layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
    <div class="wp-block-group">

        <!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"}} -->
        <div class="wp-block-group">
            <!-- wp:paragraph {"style":{"typography":{"fontWeight":"900","fontSize":"1.75rem","letterSpacing":"-1px"}}} -->
            <p style="font-size:1.75rem;font-weight:900;letter-spacing:-1px"><a href="/" style="text-decoration:none;"><span style="color:#fff">#</span><span style="color:#1a1a1a">$</span></a></p>
            <!-- /wp:paragraph -->
        </div>
        <!-- /wp:group -->

        <!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"},"style":{"spacing":{"blockGap":"4px"}}} -->
        <div class="wp-block-group">
            <!-- Primary nav: categories -->
            <!-- wp:navigation {"style":{"typography":{"fontSize":"0.85rem","fontWeight":"600"},"elements":{"link":{"color":{"text":"var(--wp--preset--color--surface)"},":hover":{"color":{"text":"var(--wp--preset--color--secondary)"}}}}},"layout":{"type":"flex"}} -->
                <!-- wp:navigation-link {"label":"Tecnología","url":"/tecnologia"} /-->
                <!-- wp:navigation-link {"label":"Economía","url":"/economia"} /-->
                <!-- wp:navigation-link {"label":"Videojuegos","url":"/videojuegos"} /-->
            <!-- /wp:navigation -->

            <!-- wp:paragraph {"style":{"typography":{"fontSize":"0.85rem"}},"textColor":"surface"} -->
            <p class="has-surface-color has-text-color" style="font-size:0.85rem">·</p>
            <!-- /wp:paragraph -->

            <!-- Secondary nav: static pages -->
            <!-- wp:navigation {"style":{"typography":{"fontSize":"0.8rem"},"elements":{"link":{"color":{"text":"rgba(255,255,255,0.7)"},":hover":{"color":{"text":"var(--wp--preset--color--surface)"}}}}},"layout":{"type":"flex"}} -->
                <!-- wp:navigation-link {"label":"Archivos","url":"/archivos"} /-->
                <!-- wp:navigation-link {"label":"Acerca","url":"/acerca"} /-->
            <!-- /wp:navigation -->
        </div>
        <!-- /wp:group -->

    </div>
    <!-- /wp:group -->

    <!-- wp:search {"label":"Buscar","showLabel":false,"placeholder":"Busca en $P","buttonText":"Buscar","style":{"border":{"radius":"4px"}},"backgroundColor":"surface","fontSize":"small"} /-->

</div>
<!-- /wp:group -->

<!-- wp:sp/ad-slot {"position":"header-leaderboard"} /-->
```

- [ ] **Step 2: Verify header renders**

Activate the theme (WordPress admin > Appearance > Themes). Visit the site. Verify salmon header bar with site title and navigation links.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/signopeso-theme/parts/header.html
git commit -m "feat: salmon header with logo, navigation, and search"
```

---

### Task 10: Footer Template Part

**Files:**
- Create: `wp-content/themes/signopeso-theme/parts/footer.html`

- [ ] **Step 1: Create footer.html**

```html
<!-- wp:group {"style":{"spacing":{"padding":{"top":"24px","bottom":"24px","left":"24px","right":"24px"}},"border":{"top":{"color":"var(--wp--preset--color--border)","width":"1px"}}},"layout":{"type":"constrained","contentSize":"1100px"}} -->
<div class="wp-block-group" style="border-top-color:var(--wp--preset--color--border);border-top-width:1px;padding-top:24px;padding-right:24px;padding-bottom:24px;padding-left:24px">

    <!-- wp:group {"layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"}} -->
    <div class="wp-block-group">
        <!-- wp:paragraph {"style":{"typography":{"fontSize":"0.8rem"}},"textColor":"muted"} -->
        <p class="has-muted-color has-text-color" style="font-size:0.8rem"><strong>#$</strong> · <a href="/acerca">Acerca</a> · <a href="/publicidad">Publicidad</a> · <a href="/feed/">RSS</a> · CC BY-NC-SA</p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->

</div>
<!-- /wp:group -->
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/signopeso-theme/parts/footer.html
git commit -m "feat: minimal footer with links and CC license"
```

---

### Task 11: Sidebar Template Part

**Files:**
- Create: `wp-content/themes/signopeso-theme/parts/sidebar.html`

- [ ] **Step 1: Create sidebar.html**

```html
<!-- wp:group {"style":{"spacing":{"blockGap":"24px"}},"className":"sp-sidebar"} -->
<div class="wp-block-group sp-sidebar">

    <!-- wp:sp/newsletter-form {"ctaText":"Suscríbete","placeholder":"tu@email.com","heading":"Economía + tech, en tu inbox."} /-->

    <!-- wp:sp/popular-posts {"period":"7","count":5} /-->

    <!-- wp:sp/ad-slot {"position":"sidebar"} /-->

</div>
<!-- /wp:group -->
```

Note: The newsletter-form, popular-posts, and ad-slot blocks will be created in later tasks. This template will render empty placeholders until those blocks exist.

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/signopeso-theme/parts/sidebar.html
git commit -m "feat: sidebar template part with newsletter, popular posts, and ad slot"
```

---

### Task 12: Homepage Template (`index.html`)

**Files:**
- Create: `wp-content/themes/signopeso-theme/templates/index.html`

- [ ] **Step 1: Create index.html**

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"1100px"},"style":{"spacing":{"padding":{"top":"32px","bottom":"32px","left":"24px","right":"24px"}}}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:24px;padding-bottom:32px;padding-left:24px">

    <!-- wp:columns {"style":{"spacing":{"blockGap":{"left":"32px"}}}} -->
    <div class="wp-block-columns">

        <!-- wp:column {"width":"66.66%"} -->
        <div class="wp-block-column" style="flex-basis:66.66%">
            <!-- wp:sp/date-stream {"postsPerPage":10} /-->
        </div>
        <!-- /wp:column -->

        <!-- wp:column {"width":"33.33%"} -->
        <div class="wp-block-column" style="flex-basis:33.33%">
            <!-- wp:template-part {"slug":"sidebar"} /-->
        </div>
        <!-- /wp:column -->

    </div>
    <!-- /wp:columns -->

</div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

- [ ] **Step 2: Verify homepage renders**

Visit the site homepage. Verify two-column layout with date stream on the left and sidebar on the right. Verify posts are grouped by date.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/signopeso-theme/templates/index.html
git commit -m "feat: homepage template with date stream and sidebar"
```

---

### Task 13: Single Post Template

**Files:**
- Create: `wp-content/themes/signopeso-theme/templates/single.html`

- [ ] **Step 1: Create single.html**

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"},"style":{"spacing":{"padding":{"top":"32px","bottom":"32px","left":"24px","right":"24px"}}}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:24px;padding-bottom:32px;padding-left:24px">

    <!-- wp:group {"style":{"spacing":{"blockGap":"8px","margin":{"bottom":"24px"}}},"className":"sp-single-meta"} -->
    <div class="wp-block-group sp-single-meta" style="margin-bottom:24px">
        <!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"},"style":{"spacing":{"blockGap":"12px"}},"fontSize":"small"} -->
        <div class="wp-block-group">
            <!-- wp:post-date {"format":"j M Y","style":{"typography":{"textTransform":"uppercase","letterSpacing":"1px","fontFamily":"var(--wp--preset--font-family--meta)"}},"textColor":"muted","fontSize":"small"} /-->
            <!-- wp:post-terms {"term":"sp_formato","style":{"typography":{"textTransform":"uppercase","letterSpacing":"0.5px"}},"fontSize":"small"} /-->
        </div>
        <!-- /wp:group -->
    </div>
    <!-- /wp:group -->

    <!-- wp:post-title {"level":1,"style":{"typography":{"fontSize":"2.25rem","fontWeight":"800","letterSpacing":"-0.5px","lineHeight":"1.15"}},"fontFamily":"heading"} /-->

    <!-- wp:group {"style":{"spacing":{"margin":{"top":"12px","bottom":"32px"}}},"fontSize":"small","textColor":"muted"} -->
    <div class="wp-block-group" style="margin-top:12px;margin-bottom:32px">
        <!-- wp:paragraph {"fontSize":"small","textColor":"muted"} -->
        <p class="has-muted-color has-text-color has-small-font-size">Por <strong><!-- wp:post-author-name {"isLink":false} /--></strong></p>
        <!-- /wp:paragraph -->
    </div>
    <!-- /wp:group -->

    <!-- wp:post-content {"layout":{"type":"constrained"}} /-->

    <!-- wp:sp/source-card /-->

    <!-- wp:post-terms {"term":"post_tag","prefix":"Etiquetas: ","style":{"spacing":{"margin":{"top":"32px"}}},"fontSize":"small","textColor":"muted"} /-->

    <!-- wp:sp/ad-slot {"position":"single-below-content"} /-->

    <!-- wp:comments /-->

</div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

- [ ] **Step 2: Verify single post renders**

Click on any post from the homepage. Verify centered layout with date, format label, headline, byline, content, source card (if exists), tags, and comments.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/signopeso-theme/templates/single.html
git commit -m "feat: single post template with source card and tags"
```

---

### Task 14: Page, Archive, and 404 Templates

**Files:**
- Create: `wp-content/themes/signopeso-theme/templates/page.html`
- Create: `wp-content/themes/signopeso-theme/templates/archive.html`
- Create: `wp-content/themes/signopeso-theme/templates/404.html`

- [ ] **Step 1: Create page.html**

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"},"style":{"spacing":{"padding":{"top":"32px","bottom":"32px","left":"24px","right":"24px"}}}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:24px;padding-bottom:32px;padding-left:24px">

    <!-- wp:post-title {"level":1,"style":{"typography":{"fontSize":"2.25rem","fontWeight":"800","letterSpacing":"-0.5px"}},"fontFamily":"heading"} /-->

    <!-- wp:post-content {"layout":{"type":"constrained"}} /-->

</div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

- [ ] **Step 2: Create archive.html**

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"},"style":{"spacing":{"padding":{"top":"32px","bottom":"32px","left":"24px","right":"24px"}}}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:24px;padding-bottom:32px;padding-left:24px">

    <!-- wp:query-title {"type":"archive","style":{"typography":{"fontSize":"2rem","fontWeight":"800"}},"fontFamily":"heading"} /-->

    <!-- wp:sp/date-stream {"postsPerPage":10,"inheritQuery":true} /-->

</div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

- [ ] **Step 3: Create 404.html**

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"},"style":{"spacing":{"padding":{"top":"64px","bottom":"64px","left":"24px","right":"24px"}}}} -->
<div class="wp-block-group" style="padding-top:64px;padding-right:24px;padding-bottom:64px;padding-left:24px">

    <!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"2.25rem","fontWeight":"800"}},"fontFamily":"heading"} -->
    <h1 class="wp-block-heading" style="font-size:2.25rem;font-weight:800">404</h1>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"textColor":"muted"} -->
    <p class="has-muted-color has-text-color">No encontramos lo que buscas. Quizás una búsqueda te ayude.</p>
    <!-- /wp:paragraph -->

    <!-- wp:search {"label":"Buscar","showLabel":false,"placeholder":"Busca en $P","buttonText":"Buscar"} /-->

</div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/signopeso-theme/templates/
git commit -m "feat: page, archive, and 404 templates"
```

---

## Chunk 4: Theme Styles

### Task 15: Custom CSS for Post Cards, Source Cards, and Stream

**Files:**
- Create: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`
- Modify: `wp-content/themes/signopeso-theme/functions.php` (enqueue)

- [ ] **Step 1: Create signopeso.css**

```css
/* === Section Boxes === */
.sp-post-card {
    background: var(--wp--preset--color--surface);
    border: 1px solid var(--wp--preset--color--border);
    border-radius: 4px;
    padding: 20px 24px;
    margin-bottom: 16px;
}

/* === Post Card === */
.sp-post-card__meta-top {
    display: flex;
    gap: 8px;
    align-items: center;
    margin-bottom: 8px;
}

.sp-post-card__label {
    font-family: var(--wp--preset--font-family--meta);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background: var(--wp--preset--color--background);
    color: var(--wp--preset--color--muted);
    padding: 2px 8px;
    border-radius: 3px;
}

.sp-post-card__label--highlight {
    background: var(--wp--preset--color--primary);
    color: var(--wp--preset--color--surface);
}

.sp-post-card__category {
    font-family: var(--wp--preset--font-family--meta);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--wp--preset--color--primary);
    text-decoration: none;
}

.sp-post-card__title {
    font-family: var(--wp--preset--font-family--heading);
    font-size: 1.25rem;
    font-weight: 800;
    line-height: 1.2;
    letter-spacing: -0.3px;
    margin: 0 0 8px;
}

.sp-post-card__title a {
    color: var(--wp--preset--color--secondary);
    text-decoration: none;
}

.sp-post-card__title a:hover {
    color: var(--wp--preset--color--primary);
}

.sp-post-card__excerpt {
    font-size: 0.95rem;
    color: #555;
    line-height: 1.6;
    margin-bottom: 8px;
}

.sp-post-card__readmore {
    font-size: 0.85rem;
    color: var(--wp--preset--color--primary);
    text-decoration: none;
    font-weight: 600;
    display: inline-block;
    margin-bottom: 8px;
}

.sp-post-card__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 10px;
    border-top: 1px solid var(--wp--preset--color--border);
}

.sp-post-card__byline {
    font-family: var(--wp--preset--font-family--meta);
    font-size: 0.75rem;
    color: var(--wp--preset--color--muted);
}

.sp-post-card__source {
    font-size: 0.75rem;
    color: var(--wp--preset--color--primary);
    text-decoration: none;
    font-weight: 600;
}

/* === Source Card === */
.sp-source-card {
    border: 1px solid var(--wp--preset--color--border);
    border-radius: 6px;
    overflow: hidden;
    margin: 12px 0;
}

.sp-source-card__link {
    display: flex;
    gap: 12px;
    padding: 12px;
    text-decoration: none;
    color: inherit;
}

.sp-source-card__link:hover {
    background: var(--wp--preset--color--background);
}

.sp-source-card__image {
    flex-shrink: 0;
    width: 60px;
    height: 60px;
    border-radius: 4px;
    overflow: hidden;
}

.sp-source-card__image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sp-source-card__info {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;
}

.sp-source-card__title {
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--wp--preset--color--secondary);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sp-source-card__desc {
    font-size: 0.8rem;
    color: var(--wp--preset--color--muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.sp-source-card__domain {
    font-family: var(--wp--preset--font-family--meta);
    font-size: 0.7rem;
    color: var(--wp--preset--color--muted);
}

.sp-source-link a {
    color: var(--wp--preset--color--primary);
    text-decoration: none;
    font-weight: 600;
    font-size: 0.85rem;
}

/* === Date Stream === */
.sp-date-stream__date {
    font-family: var(--wp--preset--font-family--meta);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--wp--preset--color--muted);
    border-bottom: 1px solid var(--wp--preset--color--border);
    padding-bottom: 6px;
    margin-bottom: 16px;
    margin-top: 24px;
}

.sp-date-stream__group:first-child .sp-date-stream__date {
    margin-top: 0;
}

.sp-date-stream__pagination {
    margin-top: 32px;
    text-align: center;
}

.sp-date-stream__pagination .page-numbers {
    display: inline-block;
    padding: 6px 12px;
    margin: 0 2px;
    text-decoration: none;
    color: var(--wp--preset--color--secondary);
    border: 1px solid var(--wp--preset--color--border);
    border-radius: 4px;
    font-size: 0.85rem;
}

.sp-date-stream__pagination .page-numbers.current {
    background: var(--wp--preset--color--primary);
    color: var(--wp--preset--color--surface);
    border-color: var(--wp--preset--color--primary);
}

/* === Sidebar === */
.sp-sidebar > * {
    background: var(--wp--preset--color--surface);
    border: 1px solid var(--wp--preset--color--border);
    border-radius: 4px;
    padding: 16px;
}

/* === Responsive === */
@media (max-width: 1024px) {
    .wp-block-columns {
        flex-direction: column;
    }
    .wp-block-column {
        flex-basis: 100% !important;
    }
}
```

- [ ] **Step 2: Enqueue the stylesheet**

Add to `functions.php`:

```php
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
```

- [ ] **Step 3: Create assets directory and verify**

```bash
mkdir -p wp-content/themes/signopeso-theme/assets/css
```

Visit the site and verify post cards have white backgrounds, proper spacing, salmon labels, serif headlines, and clean separators.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/signopeso-theme/assets/
git add wp-content/themes/signopeso-theme/functions.php
git commit -m "feat: custom CSS for post cards, source cards, date stream, and responsive layout"
```

---

## Chunk 5: Sidebar Blocks

### Task 16: Ad Slot Block (`sp/ad-slot`)

**Files:**
- Create: `wp-content/plugins/signopeso-core/includes/ad-slots.php`
- Create: `wp-content/plugins/signopeso-core/blocks/ad-slot/block.json`
- Create: `wp-content/plugins/signopeso-core/blocks/ad-slot/render.php`
- Modify: `wp-content/plugins/signopeso-core/signopeso-core.php` (add require + register block)

- [ ] **Step 1: Add require to bootstrap**

Add to `signopeso-core.php`:
```php
require_once SP_PLUGIN_DIR . 'includes/ad-slots.php';
```

- [ ] **Step 2: Create ad-slots.php**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Register ad slots settings page.
 */
function sp_register_ad_settings() {
    register_setting( 'sp_ads', 'sp_ad_slots', array(
        'type'              => 'array',
        'sanitize_callback' => 'sp_sanitize_ad_slots',
        'default'           => array(),
    ) );

    add_options_page(
        'SignoPeso Ads',
        'SP Ads',
        'manage_options',
        'sp-ads',
        'sp_render_ad_settings_page'
    );
}
add_action( 'admin_menu', 'sp_register_ad_settings' );
add_action( 'admin_init', function() {
    register_setting( 'sp_ads', 'sp_ad_slots' );
});

function sp_sanitize_ad_slots( $input ) {
    $sanitized = array();
    $positions = array( 'header-leaderboard', 'single-below-content', 'sidebar' );

    foreach ( $positions as $pos ) {
        $sanitized[ $pos ] = array(
            'enabled' => ! empty( $input[ $pos ]['enabled'] ),
            'code'    => $input[ $pos ]['code'] ?? '',
        );
    }

    return $sanitized;
}

function sp_render_ad_settings_page() {
    $slots = get_option( 'sp_ad_slots', array() );
    $positions = array(
        'header-leaderboard'   => 'Header Leaderboard (728x90)',
        'single-below-content' => 'Single Post — Below Content (468x60)',
        'sidebar'              => 'Sidebar (300x250)',
    );
    ?>
    <div class="wrap">
        <h1>SignoPeso — Ad Slots</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'sp_ads' ); ?>
            <?php foreach ( $positions as $slug => $label ) :
                $slot = $slots[ $slug ] ?? array( 'enabled' => false, 'code' => '' );
            ?>
                <h2><?php echo esc_html( $label ); ?></h2>
                <label>
                    <input type="checkbox" name="sp_ad_slots[<?php echo esc_attr( $slug ); ?>][enabled]" value="1" <?php checked( $slot['enabled'] ); ?> />
                    Activado
                </label>
                <br><br>
                <textarea name="sp_ad_slots[<?php echo esc_attr( $slug ); ?>][code]" rows="5" cols="80"><?php echo esc_textarea( $slot['code'] ); ?></textarea>
                <hr>
            <?php endforeach; ?>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/**
 * Get ad code for a position.
 */
function sp_get_ad_code( $position ) {
    $slots = get_option( 'sp_ad_slots', array() );
    $slot  = $slots[ $position ] ?? null;

    if ( ! $slot || empty( $slot['enabled'] ) || empty( $slot['code'] ) ) {
        return '';
    }

    return $slot['code'];
}
```

- [ ] **Step 3: Create block.json**

```json
{
    "apiVersion": 3,
    "name": "sp/ad-slot",
    "title": "Ad Slot",
    "category": "signopeso",
    "description": "Renders an ad slot by position.",
    "textdomain": "signopeso",
    "attributes": {
        "position": {
            "type": "string",
            "default": "sidebar"
        }
    },
    "supports": { "html": false },
    "render": "file:./render.php"
}
```

- [ ] **Step 4: Create render.php**

```php
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
```

- [ ] **Step 5: Register block, verify, commit**

Add `'ad-slot'` to the `$blocks` array. Go to Settings > SP Ads, enable a slot with test HTML, verify it renders on the frontend.

```bash
git add wp-content/plugins/signopeso-core/includes/ad-slots.php
git add wp-content/plugins/signopeso-core/blocks/ad-slot/
git add wp-content/plugins/signopeso-core/signopeso-core.php
git commit -m "feat: ad slot system with settings page and block renderer"
```

---

### Task 17: Popular Posts Block (`sp/popular-posts`)

**Files:**
- Create: `wp-content/plugins/signopeso-core/includes/popular-posts.php`
- Create: `wp-content/plugins/signopeso-core/blocks/popular-posts/block.json`
- Create: `wp-content/plugins/signopeso-core/blocks/popular-posts/render.php`
- Modify: `wp-content/plugins/signopeso-core/signopeso-core.php`

- [ ] **Step 1: Add require, create popular-posts.php**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get popular posts, preferring Jetpack stats with fallback.
 */
function sp_get_popular_posts( $count = 5, $period = 7 ) {
    $cache_key = "sp_popular_{$period}d_{$count}";
    $cached    = get_transient( $cache_key );

    if ( false !== $cached ) {
        return $cached;
    }

    $post_ids = array();

    // Try Jetpack Stats.
    if ( function_exists( 'stats_get_csv' ) ) {
        $stats = stats_get_csv( 'postviews', array(
            'days'    => $period,
            'limit'   => $count,
            'post_id' => '',
        ) );

        if ( ! empty( $stats ) ) {
            foreach ( $stats as $stat ) {
                if ( ! empty( $stat['post_id'] ) && 0 !== (int) $stat['post_id'] ) {
                    $post_ids[] = (int) $stat['post_id'];
                }
            }
        }
    }

    // Fallback: most commented.
    if ( empty( $post_ids ) ) {
        $query = new WP_Query( array(
            'post_type'      => 'post',
            'posts_per_page' => $count,
            'orderby'        => 'comment_count',
            'order'          => 'DESC',
            'fields'         => 'ids',
        ) );
        $post_ids = $query->posts;
    }

    set_transient( $cache_key, $post_ids, HOUR_IN_SECONDS );

    return $post_ids;
}
```

- [ ] **Step 2: Create block.json and render.php**

block.json:
```json
{
    "apiVersion": 3,
    "name": "sp/popular-posts",
    "title": "Popular Posts",
    "category": "signopeso",
    "description": "Shows most popular posts from Jetpack Stats.",
    "textdomain": "signopeso",
    "attributes": {
        "period": { "type": "string", "default": "7" },
        "count": { "type": "number", "default": 5 }
    },
    "supports": { "html": false },
    "render": "file:./render.php"
}
```

render.php:
```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$period   = (int) ( $attributes['period'] ?? 7 );
$count    = (int) ( $attributes['count'] ?? 5 );
$post_ids = sp_get_popular_posts( $count, $period );

if ( empty( $post_ids ) ) {
    return;
}
?>

<div class="sp-popular-posts">
    <h4 class="sp-popular-posts__heading">Lo popular</h4>
    <ul class="sp-popular-posts__list">
        <?php foreach ( $post_ids as $pid ) :
            $title = get_the_title( $pid );
            $link  = get_permalink( $pid );
            if ( ! $title ) continue;
        ?>
            <li class="sp-popular-posts__item">
                <a href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $title ); ?></a>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
```

- [ ] **Step 3: Register block, verify, commit**

Add requires and register block. Verify sidebar shows popular posts list.

```bash
git add wp-content/plugins/signopeso-core/includes/popular-posts.php
git add wp-content/plugins/signopeso-core/blocks/popular-posts/
git add wp-content/plugins/signopeso-core/signopeso-core.php
git commit -m "feat: popular posts block with Jetpack stats and comment_count fallback"
```

---

### Task 18: Newsletter Form Block (`sp/newsletter-form`)

**Files:**
- Create: `wp-content/plugins/signopeso-core/includes/rest-api.php`
- Create: `wp-content/plugins/signopeso-core/includes/newsletter/settings.php`
- Create: `wp-content/plugins/signopeso-core/includes/newsletter/adapters/subscriber-interface.php`
- Create: `wp-content/plugins/signopeso-core/includes/newsletter/adapters/sender-interface.php`
- Create: `wp-content/plugins/signopeso-core/includes/newsletter/adapters/resend.php`
- Create: `wp-content/plugins/signopeso-core/blocks/newsletter-form/block.json`
- Create: `wp-content/plugins/signopeso-core/blocks/newsletter-form/render.php`
- Create: `wp-content/plugins/signopeso-core/blocks/newsletter-form/view.js`
- Modify: `wp-content/plugins/signopeso-core/signopeso-core.php`

- [ ] **Step 1: Create adapter interfaces**

`subscriber-interface.php`:
```php
<?php
interface SP_Newsletter_Subscriber {
    public function add_subscriber( string $email ): bool;
}
```

`sender-interface.php`:
```php
<?php
interface SP_Newsletter_Sender {
    public function send_digest( string $html, string $subject ): bool;
}
```

- [ ] **Step 2: Create Resend adapter**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once __DIR__ . '/subscriber-interface.php';
require_once __DIR__ . '/sender-interface.php';

class SP_Resend_Adapter implements SP_Newsletter_Subscriber, SP_Newsletter_Sender {

    private string $api_key;
    private string $audience_id;
    private string $from_email;
    private string $from_name;

    public function __construct() {
        $settings          = get_option( 'sp_newsletter_settings', array() );
        $this->api_key     = $settings['resend_api_key'] ?? '';
        $this->audience_id = $settings['resend_audience_id'] ?? '';
        $this->from_email  = $settings['from_email'] ?? '';
        $this->from_name   = $settings['from_name'] ?? 'SignoPeso';
    }

    public function add_subscriber( string $email ): bool {
        if ( ! $this->api_key || ! $this->audience_id ) {
            return false;
        }

        $response = wp_remote_post( "https://api.resend.com/audiences/{$this->audience_id}/contacts", array(
            'headers' => array(
                'Authorization' => "Bearer {$this->api_key}",
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array( 'email' => $email ) ),
        ) );

        return ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 201 ), true );
    }

    public function send_digest( string $html, string $subject ): bool {
        if ( ! $this->api_key || ! $this->from_email ) {
            return false;
        }

        // Get all contacts from the audience.
        $contacts_response = wp_remote_get( "https://api.resend.com/audiences/{$this->audience_id}/contacts", array(
            'headers' => array( 'Authorization' => "Bearer {$this->api_key}" ),
        ) );

        if ( is_wp_error( $contacts_response ) ) {
            return false;
        }

        $contacts_body = json_decode( wp_remote_retrieve_body( $contacts_response ), true );
        $emails        = array();

        if ( ! empty( $contacts_body['data'] ) ) {
            foreach ( $contacts_body['data'] as $contact ) {
                if ( ! empty( $contact['email'] ) && empty( $contact['unsubscribed'] ) ) {
                    $emails[] = $contact['email'];
                }
            }
        }

        if ( empty( $emails ) ) {
            return false;
        }

        // Send via Resend batch or individual.
        $response = wp_remote_post( 'https://api.resend.com/emails', array(
            'headers' => array(
                'Authorization' => "Bearer {$this->api_key}",
                'Content-Type'  => 'application/json',
            ),
            'body' => wp_json_encode( array(
                'from'    => "{$this->from_name} <{$this->from_email}>",
                'to'      => $emails,
                'subject' => $subject,
                'html'    => $html,
            ) ),
        ) );

        return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
    }
}
```

- [ ] **Step 3: Create newsletter settings page**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_register_newsletter_settings() {
    register_setting( 'sp_newsletter', 'sp_newsletter_settings' );

    add_options_page(
        'SignoPeso Newsletter',
        'SP Newsletter',
        'manage_options',
        'sp-newsletter',
        'sp_render_newsletter_settings'
    );
}
add_action( 'admin_menu', 'sp_register_newsletter_settings' );
add_action( 'admin_init', function() {
    register_setting( 'sp_newsletter', 'sp_newsletter_settings' );
});

function sp_render_newsletter_settings() {
    $settings = get_option( 'sp_newsletter_settings', array() );
    ?>
    <div class="wrap">
        <h1>SignoPeso — Newsletter</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'sp_newsletter' ); ?>
            <table class="form-table">
                <tr>
                    <th>Activar Newsletter</th>
                    <td><label><input type="checkbox" name="sp_newsletter_settings[enabled]" value="1" <?php checked( $settings['enabled'] ?? false ); ?> /> Activado</label></td>
                </tr>
                <tr>
                    <th>Frecuencia</th>
                    <td>
                        <select name="sp_newsletter_settings[frequency]">
                            <option value="daily" <?php selected( $settings['frequency'] ?? '', 'daily' ); ?>>Diario</option>
                            <option value="weekly" <?php selected( $settings['frequency'] ?? '', 'weekly' ); ?>>Semanal (Lunes)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Hora de envío</th>
                    <td><input type="time" name="sp_newsletter_settings[send_time]" value="<?php echo esc_attr( $settings['send_time'] ?? '08:00' ); ?>" /></td>
                </tr>
                <tr>
                    <th>Resend API Key</th>
                    <td><input type="password" name="sp_newsletter_settings[resend_api_key]" value="<?php echo esc_attr( $settings['resend_api_key'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th>Resend Audience ID</th>
                    <td><input type="text" name="sp_newsletter_settings[resend_audience_id]" value="<?php echo esc_attr( $settings['resend_audience_id'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th>From Email</th>
                    <td><input type="email" name="sp_newsletter_settings[from_email]" value="<?php echo esc_attr( $settings['from_email'] ?? '' ); ?>" class="regular-text" /></td>
                </tr>
                <tr>
                    <th>From Name</th>
                    <td><input type="text" name="sp_newsletter_settings[from_name]" value="<?php echo esc_attr( $settings['from_name'] ?? 'SignoPeso' ); ?>" class="regular-text" /></td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <hr>
        <h2>Test</h2>
        <form method="post">
            <?php wp_nonce_field( 'sp_newsletter_test', 'sp_test_nonce' ); ?>
            <p><button type="submit" name="sp_test_send" class="button">Enviar digest de prueba</button></p>
        </form>
        <?php
        if ( isset( $_POST['sp_test_send'] ) && wp_verify_nonce( $_POST['sp_test_nonce'] ?? '', 'sp_newsletter_test' ) ) {
            require_once SP_PLUGIN_DIR . 'includes/newsletter/digest-builder.php';
            require_once SP_PLUGIN_DIR . 'includes/newsletter/adapters/resend.php';
            $test_html = sp_build_digest_html();
            if ( ! $test_html ) {
                echo '<div class="notice notice-warning"><p>No hay posts nuevos para el digest.</p></div>';
            } else {
                $test_adapter = new SP_Resend_Adapter();
                $test_result  = $test_adapter->send_digest( $test_html, '[TEST] SignoPeso — ' . date_i18n( 'j \d\e F, Y' ) );
                echo $test_result
                    ? '<div class="notice notice-success"><p>Digest de prueba enviado.</p></div>'
                    : '<div class="notice notice-error"><p>Error al enviar.</p></div>';
            }
        }
        ?>
    </div>
    <?php
}
```

- [ ] **Step 4: Create REST API endpoint**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function sp_register_rest_routes() {
    register_rest_route( 'signopeso/v1', '/subscribe', array(
        'methods'             => 'POST',
        'callback'            => 'sp_handle_subscribe',
        'permission_callback' => '__return_true',
        'args'                => array(
            'email' => array(
                'required'          => true,
                'sanitize_callback' => 'sanitize_email',
                'validate_callback' => function( $email ) {
                    return is_email( $email );
                },
            ),
        ),
    ) );
}
add_action( 'rest_api_init', 'sp_register_rest_routes' );

function sp_handle_subscribe( WP_REST_Request $request ) {
    $email = $request->get_param( 'email' );

    // Basic rate limiting via transient.
    $ip_key = 'sp_sub_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
    if ( get_transient( $ip_key ) ) {
        return new WP_REST_Response( array( 'message' => 'Espera un momento antes de intentar de nuevo.' ), 429 );
    }
    set_transient( $ip_key, true, 60 );

    require_once SP_PLUGIN_DIR . 'includes/newsletter/adapters/resend.php';
    $adapter = new SP_Resend_Adapter();
    $result  = $adapter->add_subscriber( $email );

    if ( $result ) {
        return new WP_REST_Response( array( 'message' => '¡Suscrito!' ), 200 );
    }

    return new WP_REST_Response( array( 'message' => 'Error al suscribir. Intenta más tarde.' ), 500 );
}
```

- [ ] **Step 5: Create newsletter form block**

block.json:
```json
{
    "apiVersion": 3,
    "name": "sp/newsletter-form",
    "title": "Newsletter Form",
    "category": "signopeso",
    "description": "Email subscription form for the newsletter.",
    "textdomain": "signopeso",
    "attributes": {
        "heading": { "type": "string", "default": "Economía + tech, en tu inbox." },
        "ctaText": { "type": "string", "default": "Suscríbete" },
        "placeholder": { "type": "string", "default": "tu@email.com" }
    },
    "supports": { "html": false },
    "viewScript": "file:./view.js",
    "render": "file:./render.php"
}
```

render.php:
```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$heading     = esc_html( $attributes['heading'] ?? 'Economía + tech, en tu inbox.' );
$cta         = esc_html( $attributes['ctaText'] ?? 'Suscríbete' );
$placeholder = esc_attr( $attributes['placeholder'] ?? 'tu@email.com' );
$rest_url    = esc_url( rest_url( 'signopeso/v1/subscribe' ) );
$nonce       = wp_create_nonce( 'wp_rest' );
?>

<div class="sp-newsletter-form" data-rest-url="<?php echo $rest_url; ?>" data-nonce="<?php echo $nonce; ?>">
    <p class="sp-newsletter-form__heading"><strong><?php echo $heading; ?></strong></p>
    <form class="sp-newsletter-form__form">
        <input type="email" name="email" placeholder="<?php echo $placeholder; ?>" required class="sp-newsletter-form__input" />
        <button type="submit" class="sp-newsletter-form__button"><?php echo $cta; ?></button>
    </form>
    <p class="sp-newsletter-form__message" style="display:none;"></p>
</div>
```

view.js:
```js
document.addEventListener( 'DOMContentLoaded', () => {
    document.querySelectorAll( '.sp-newsletter-form' ).forEach( ( el ) => {
        const form = el.querySelector( 'form' );
        const msg = el.querySelector( '.sp-newsletter-form__message' );
        const restUrl = el.dataset.restUrl;
        const nonce = el.dataset.nonce;

        form.addEventListener( 'submit', async ( e ) => {
            e.preventDefault();
            const email = form.querySelector( 'input[name="email"]' ).value;

            try {
                const res = await fetch( restUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': nonce,
                    },
                    body: JSON.stringify( { email } ),
                } );
                const data = await res.json();
                msg.textContent = data.message;
                msg.style.display = 'block';
                if ( res.ok ) {
                    form.style.display = 'none';
                }
            } catch {
                msg.textContent = 'Error de conexión.';
                msg.style.display = 'block';
            }
        } );
    } );
} );
```

- [ ] **Step 6: Add all requires to bootstrap and register blocks**

Add to `signopeso-core.php`:
```php
require_once SP_PLUGIN_DIR . 'includes/rest-api.php';
require_once SP_PLUGIN_DIR . 'includes/newsletter/settings.php';
```

Add `'newsletter-form'` to the `$blocks` array.

- [ ] **Step 7: Verify newsletter form works**

Configure Resend API key in Settings > SP Newsletter. Visit homepage sidebar. Enter an email in the form. Verify the REST endpoint responds and the success message appears.

- [ ] **Step 8: Commit**

```bash
git add wp-content/plugins/signopeso-core/includes/newsletter/
git add wp-content/plugins/signopeso-core/includes/rest-api.php
git add wp-content/plugins/signopeso-core/blocks/newsletter-form/
git add wp-content/plugins/signopeso-core/signopeso-core.php
git commit -m "feat: newsletter subscription form with REST endpoint and Resend adapter"
```

---

## Chunk 6: Newsletter Digest & Full Archive

### Task 19: Newsletter Digest Builder & Cron

**Files:**
- Create: `wp-content/plugins/signopeso-core/includes/newsletter/digest-builder.php`
- Create: `wp-content/plugins/signopeso-core/includes/newsletter/cron.php`
- Modify: `wp-content/plugins/signopeso-core/signopeso-core.php`

- [ ] **Step 1: Create digest-builder.php**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Build the newsletter digest HTML.
 */
/**
 * Get count of posts since last send (for logging).
 */
function sp_get_digest_post_count() {
    $last_sent = get_option( 'sp_newsletter_last_sent', strtotime( '-7 days' ) );
    return count( get_posts( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'date_query'     => array( array( 'after' => date( 'Y-m-d H:i:s', $last_sent ) ) ),
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ) ) );
}

function sp_build_digest_html() {
    $last_sent = get_option( 'sp_newsletter_last_sent', strtotime( '-7 days' ) );

    $posts = get_posts( array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'date_query'     => array(
            array( 'after' => date( 'Y-m-d H:i:s', $last_sent ) ),
        ),
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ) );

    if ( empty( $posts ) ) {
        return '';
    }

    // Group by format.
    $grouped = array( 'largo' => array(), 'corto' => array(), 'enlace' => array(), 'cobertura' => array() );
    foreach ( $posts as $post ) {
        $formats = wp_get_object_terms( $post->ID, 'sp_formato', array( 'fields' => 'slugs' ) );
        $fmt     = ! empty( $formats ) ? $formats[0] : 'corto';
        $grouped[ $fmt ][] = $post;
    }

    // Build inline-styled HTML.
    ob_start();
    ?>
    <div style="max-width:600px;margin:0 auto;font-family:Inter,-apple-system,sans-serif;color:#1a1a1a;">
        <div style="background:#F06B6B;padding:16px 24px;text-align:center;">
            <span style="font-size:24px;font-weight:900;color:#fff;">#</span><span style="font-size:24px;font-weight:900;color:#1a1a1a;">$</span>
            <span style="font-size:12px;color:rgba(255,255,255,0.8);margin-left:8px;">SignoPeso</span>
        </div>
        <div style="padding:24px;">
    <?php
    $order = array( 'largo', 'cobertura', 'corto', 'enlace' );
    foreach ( $order as $fmt ) {
        if ( empty( $grouped[ $fmt ] ) ) continue;
        foreach ( $grouped[ $fmt ] as $post ) {
            $source = sp_get_source_data( $post->ID );
            ?>
            <div style="margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #e0e0e0;">
                <span style="font-size:11px;text-transform:uppercase;letter-spacing:0.5px;color:#999;"><?php echo esc_html( ucfirst( $fmt ) ); ?></span>
                <h2 style="font-family:Georgia,serif;font-size:18px;font-weight:700;margin:6px 0;line-height:1.3;">
                    <a href="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" style="color:#1a1a1a;text-decoration:none;"><?php echo esc_html( $post->post_title ); ?></a>
                </h2>
                <p style="font-size:14px;color:#555;line-height:1.5;margin:0;"><?php echo esc_html( wp_trim_words( $post->post_excerpt ?: $post->post_content, 30 ) ); ?></p>
                <?php if ( $source ) : ?>
                    <p style="margin-top:8px;"><a href="<?php echo esc_url( $source['url_utm'] ); ?>" style="color:#F06B6B;font-size:13px;text-decoration:none;font-weight:600;">Ir a la fuente &rarr;</a></p>
                <?php endif; ?>
            </div>
            <?php
        }
    }
    ?>
        </div>
        <div style="background:#fafafa;padding:16px 24px;text-align:center;font-size:12px;color:#999;">
            <a href="<?php echo esc_url( home_url() ); ?>" style="color:#F06B6B;">signopeso.com</a>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
```

- [ ] **Step 2: Create cron.php**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Schedule the newsletter cron event.
 */
function sp_schedule_newsletter_cron() {
    $settings = get_option( 'sp_newsletter_settings', array() );

    if ( empty( $settings['enabled'] ) ) {
        wp_clear_scheduled_hook( 'sp_newsletter_send' );
        return;
    }

    if ( ! wp_next_scheduled( 'sp_newsletter_send' ) ) {
        $frequency = ( $settings['frequency'] ?? 'daily' ) === 'weekly' ? 'weekly' : 'daily';
        $send_time = $settings['send_time'] ?? '08:00';

        // Calculate next send timestamp.
        $today     = current_time( 'Y-m-d' );
        $timestamp = strtotime( "{$today} {$send_time}" );

        if ( $timestamp < current_time( 'timestamp' ) ) {
            $timestamp += DAY_IN_SECONDS;
        }

        wp_schedule_event( $timestamp, $frequency, 'sp_newsletter_send' );
    }
}
add_action( 'admin_init', 'sp_schedule_newsletter_cron' );

/**
 * Send the newsletter digest.
 */
function sp_send_newsletter_digest() {
    require_once SP_PLUGIN_DIR . 'includes/newsletter/digest-builder.php';
    require_once SP_PLUGIN_DIR . 'includes/newsletter/adapters/resend.php';

    $html = sp_build_digest_html();
    if ( ! $html ) {
        return;
    }

    $settings = get_option( 'sp_newsletter_settings', array() );
    $subject  = 'SignoPeso — ' . date_i18n( 'j \d\e F, Y' );

    $adapter = new SP_Resend_Adapter();
    $result  = $adapter->send_digest( $html, $subject );

    // Log result.
    update_option( 'sp_newsletter_last_sent', current_time( 'timestamp' ) );
    update_option( 'sp_newsletter_last_status', $result ? 'success' : 'failed' );
    update_option( 'sp_newsletter_last_post_count', sp_get_digest_post_count() );
}
add_action( 'sp_newsletter_send', 'sp_send_newsletter_digest' );
```

- [ ] **Step 3: Add require to bootstrap**

```php
require_once SP_PLUGIN_DIR . 'includes/newsletter/cron.php';
```

- [ ] **Step 4: Verify cron is scheduled**

Enable newsletter in Settings > SP Newsletter with API key and audience ID. Verify via Query Monitor or `wp_next_scheduled()` that the cron event is registered.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/signopeso-core/includes/newsletter/digest-builder.php
git add wp-content/plugins/signopeso-core/includes/newsletter/cron.php
git add wp-content/plugins/signopeso-core/signopeso-core.php
git commit -m "feat: newsletter digest builder and WP-Cron dispatch via Resend"
```

---

### Task 20: Full Archive Block (`sp/full-archive`)

**Files:**
- Create: `wp-content/plugins/signopeso-core/blocks/full-archive/block.json`
- Create: `wp-content/plugins/signopeso-core/blocks/full-archive/render.php`
- Create: `wp-content/themes/signopeso-theme/templates/page-archive-all.html`

- [ ] **Step 1: Create block.json**

```json
{
    "apiVersion": 3,
    "name": "sp/full-archive",
    "title": "Full Archive",
    "category": "signopeso",
    "description": "Year/month grouped post archive.",
    "textdomain": "signopeso",
    "supports": { "html": false },
    "render": "file:./render.php"
}
```

- [ ] **Step 2: Create render.php**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$years = array();
$posts = get_posts( array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'fields'         => 'ids',
) );

foreach ( $posts as $pid ) {
    $year  = get_the_date( 'Y', $pid );
    $month = get_the_date( 'F', $pid );
    $years[ $year ][ $month ][] = $pid;
}

$current_year = date( 'Y' );
?>

<div class="sp-full-archive">
    <?php foreach ( $years as $year => $months ) :
        $open = ( $year === $current_year ) ? 'open' : '';
    ?>
        <details class="sp-full-archive__year" <?php echo $open; ?>>
            <summary class="sp-full-archive__year-heading"><?php echo esc_html( $year ); ?></summary>
            <?php foreach ( $months as $month => $pids ) : ?>
                <div class="sp-full-archive__month">
                    <h4 class="sp-full-archive__month-heading"><?php echo esc_html( $month ); ?></h4>
                    <ul class="sp-full-archive__list">
                        <?php foreach ( $pids as $pid ) :
                            $formats = wp_get_object_terms( $pid, 'sp_formato', array( 'fields' => 'slugs' ) );
                            $fmt     = ! empty( $formats ) ? $formats[0] : 'corto';
                        ?>
                            <li>
                                <span class="sp-post-card__label"><?php echo esc_html( ucfirst( $fmt ) ); ?></span>
                                <a href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a>
                                <span class="sp-full-archive__date"><?php echo esc_html( get_the_date( 'j M', $pid ) ); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </details>
    <?php endforeach; ?>
</div>
```

- [ ] **Step 3: Register block and create archive template**

Add `'full-archive'` to the `$blocks` array.

Create `templates/page-archive-all.html`:
```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"},"style":{"spacing":{"padding":{"top":"32px","bottom":"32px","left":"24px","right":"24px"}}}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:24px;padding-bottom:32px;padding-left:24px">

    <!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"2.25rem","fontWeight":"800"}},"fontFamily":"heading"} -->
    <h1 class="wp-block-heading" style="font-size:2.25rem;font-weight:800">Archivos</h1>
    <!-- /wp:heading -->

    <!-- wp:sp/full-archive /-->

</div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

- [ ] **Step 4: Create "Archivos" page in WordPress admin**

Create a new page titled "Archivos" with slug `archivos`. Assign the "Full Archive" custom template. Verify the year/month grouped list renders with current year expanded.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/full-archive/
git add wp-content/plugins/signopeso-core/signopeso-core.php
git add wp-content/themes/signopeso-theme/templates/page-archive-all.html
git commit -m "feat: full archive block with year/month grouped posts and custom template"
```

---

## Chunk 7: CSS Polish & Final Integration

### Task 21: Newsletter Form and Archive CSS

**Files:**
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Add remaining CSS**

Append to `signopeso.css`:

```css
/* === Newsletter Form === */
.sp-newsletter-form {
    background: var(--wp--preset--color--primary);
    border-radius: 6px;
    padding: 16px;
    border: none;
}

.sp-newsletter-form__heading {
    color: var(--wp--preset--color--surface);
    font-size: 0.9rem;
    margin: 0 0 8px;
}

.sp-newsletter-form__form {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sp-newsletter-form__input {
    padding: 8px 10px;
    border: none;
    border-radius: 4px;
    font-size: 0.85rem;
    background: rgba(255,255,255,0.9);
}

.sp-newsletter-form__button {
    padding: 8px;
    border: none;
    border-radius: 4px;
    background: var(--wp--preset--color--secondary);
    color: var(--wp--preset--color--surface);
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
}

.sp-newsletter-form__button:hover {
    opacity: 0.9;
}

.sp-newsletter-form__message {
    color: var(--wp--preset--color--surface);
    font-size: 0.85rem;
    margin-top: 8px;
}

/* === Popular Posts === */
.sp-popular-posts__heading {
    font-family: var(--wp--preset--font-family--meta);
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--wp--preset--color--muted);
    margin: 0 0 10px;
    font-weight: 700;
}

.sp-popular-posts__list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sp-popular-posts__item {
    padding: 8px 0;
    border-bottom: 1px solid var(--wp--preset--color--border);
    font-size: 0.9rem;
}

.sp-popular-posts__item:last-child {
    border-bottom: none;
}

.sp-popular-posts__item a {
    color: var(--wp--preset--color--secondary);
    text-decoration: none;
}

.sp-popular-posts__item a:hover {
    color: var(--wp--preset--color--primary);
}

/* === Ad Slot === */
.sp-ad-slot {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 60px;
}

/* === Full Archive === */
.sp-full-archive__year {
    margin-bottom: 16px;
}

.sp-full-archive__year-heading {
    font-family: var(--wp--preset--font-family--heading);
    font-size: 1.5rem;
    font-weight: 700;
    cursor: pointer;
    padding: 8px 0;
}

.sp-full-archive__month-heading {
    font-family: var(--wp--preset--font-family--meta);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 1.5px;
    color: var(--wp--preset--color--muted);
    margin: 16px 0 8px;
}

.sp-full-archive__list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.sp-full-archive__list li {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 0;
    border-bottom: 1px solid var(--wp--preset--color--border);
    font-size: 0.9rem;
}

.sp-full-archive__list li a {
    flex: 1;
    color: var(--wp--preset--color--secondary);
    text-decoration: none;
}

.sp-full-archive__list li a:hover {
    color: var(--wp--preset--color--primary);
}

.sp-full-archive__date {
    font-family: var(--wp--preset--font-family--meta);
    font-size: 0.7rem;
    color: var(--wp--preset--color--muted);
    white-space: nowrap;
}

/* === Highlight (yellow) === */
mark, .sp-highlight {
    background: var(--wp--preset--color--highlight);
    padding: 0 3px;
}
```

- [ ] **Step 2: Commit**

```bash
git add wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: CSS for newsletter form, popular posts, archive, and yellow highlights"
```

---

### Task 22: Final Integration & Smoke Test

- [ ] **Step 1: Create test content**

In WordPress admin, create:
- 3 categories: Tecnología, Economía, Videojuegos
- 5+ posts across dates and formats (2 Cortos, 1 Enlace with source URL, 1 Largo, 1 Cobertura)
- Static pages: Acerca, Publicidad, Contacto, Archivos (with Full Archive template), Tips

- [ ] **Step 2: Smoke test checklist**

Verify each of these:
- [ ] Homepage: salmon header renders, stream + sidebar layout, posts grouped by date
- [ ] Post cards: format labels visible, different rendering per format
- [ ] Enlace post: source card with OG preview renders
- [ ] Largo post: "Sigue leyendo →" visible in stream
- [ ] Single post: centered layout, no sidebar, source card, tags, comments
- [ ] Sidebar: newsletter form, popular posts (fallback to comment count), ad slot
- [ ] Category archive (`/tecnologia/`): filtered posts render
- [ ] Archivos page: year/month grouped list with current year open
- [ ] 404 page: renders with search
- [ ] Mobile: responsive layout, single column
- [ ] Permalink format: `/{category}/{slug}/`
- [ ] Footer: links and CC license

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "feat: SignoPeso v1.0.0 — theme and plugin complete"
```
