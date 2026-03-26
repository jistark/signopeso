# SignoPeso Template Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Extend the homepage UI polish to single posts, pages, search results, and the 404 page — consistent typography, dedicated search UX, and a 404 with personality.

**Architecture:** Pure CSS + template + render.php changes plus two new lightweight blocks (`sp/search-header`, `sp/recirculation-lite`). All templates stay single-column 720px. No sidebar outside homepage.

**Tech Stack:** WordPress block theme (FSE), PHP render.php blocks, vanilla CSS, WordPress template parts (HTML block markup).

**Spec:** `docs/superpowers/specs/2026-03-18-signopeso-template-polish-design.md`

---

## File Map

| File | Responsibility | Action |
|---|---|---|
| `wp-content/themes/signopeso-theme/assets/css/signopeso.css` | All visual styling | Modify (single badge, search header, recirculation-lite, search hint) |
| `wp-content/themes/signopeso-theme/templates/page.html` | Page template | Modify (add salmón top-border) |
| `wp-content/themes/signopeso-theme/templates/search.html` | Search results template | Create |
| `wp-content/themes/signopeso-theme/templates/404.html` | 404 template | Modify (personality + recommendations) |
| `wp-content/plugins/signopeso-core/blocks/search-header/block.json` | Search header block metadata | Create |
| `wp-content/plugins/signopeso-core/blocks/search-header/render.php` | Search header block render | Create |
| `wp-content/plugins/signopeso-core/blocks/recirculation-lite/block.json` | Recirculation lite block metadata | Create |
| `wp-content/plugins/signopeso-core/blocks/recirculation-lite/render.php` | Recirculation lite block render | Create |
| `wp-content/plugins/signopeso-core/blocks/date-stream/render.php` | Date stream block | Modify (add search support to inheritQuery) |
| `wp-content/plugins/signopeso-core/signopeso-core.php` | Plugin main file | Modify (register 2 new blocks) |
| `CLAUDE.md` | Project documentation | Modify (document new blocks + templates) |

---

### Task 1: Single post badge — Inter font consistency

CSS-only change. Switch the single post format badge from Datatype mono to Inter to match homepage card badges.

**Files:**
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Update single badge CSS**

In `signopeso.css`, find the `.sp-single__badge a` rule (around line 1060). Replace:

```css
.sp-single__badge a {
    display: inline-block;
    font-family: var(--wp--preset--font-family--meta);
    color: var(--wp--preset--color--secondary);
    background: var(--wp--preset--color--highlight);
    padding: 3px 8px;
    text-decoration: none;
    margin-bottom: 16px;
}
```

With:

```css
.sp-single__badge a {
    display: inline-block;
    font-family: Inter, -apple-system, sans-serif;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--wp--preset--color--secondary);
    background: var(--wp--preset--color--highlight);
    padding: 3px 8px;
    text-decoration: none;
    margin-bottom: 16px;
}
```

Key changes: `font-family` from `--meta` (Datatype) to Inter, explicit `font-size: 0.65rem`, `font-weight: 600`, `letter-spacing: 0.05em` — matches `.sp-post-card--expanded .sp-post-card__badge`.

- [ ] **Step 2: Verify in browser**

Open http://localhost:8881/ and navigate to any single post. The format badge (e.g., "Largo", "Enlace") should now render in Inter instead of monospace Datatype.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: single post badge — Inter font for consistency with card badges"
```

---

### Task 2: Page template — salmón top-border

Add a visual identity marker to the pages template.

**Files:**
- Modify: `wp-content/themes/signopeso-theme/templates/page.html`

- [ ] **Step 1: Add salmón border to page outer group**

In `page.html`, update the outer group block (line 3) to add a 3px salmón top-border. Change:

```html
<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"},"style":{"spacing":{"padding":{"top":"32px","bottom":"32px","left":"24px","right":"24px"}}}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:24px;padding-bottom:32px;padding-left:24px">
```

To:

```html
<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"},"style":{"spacing":{"padding":{"top":"32px","bottom":"32px","left":"24px","right":"24px"}},"border":{"top":{"color":"var(--wp--preset--color--primary)","width":"3px"}}}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:24px;padding-bottom:32px;padding-left:24px;border-top-color:var(--wp--preset--color--primary);border-top-width:3px">
```

- [ ] **Step 2: Verify in browser**

Open http://localhost:8881/acerca (or any page). A 3px salmón line should appear at the top of the content area, matching the footer/recirculation visual language.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/signopeso-theme/templates/page.html
git commit -m "feat: page template — add salmón top-border for visual identity"
```

---

### Task 3: Extend date-stream for search queries

The `sp/date-stream` block's `inheritQuery` currently handles taxonomy and date archives but not search. Add `is_search()` support so the search template can reuse date-stream.

**Files:**
- Modify: `wp-content/plugins/signopeso-core/blocks/date-stream/render.php`

- [ ] **Step 1: Add search support to inheritQuery branch**

In `date-stream/render.php`, find the `inheritQuery` block (lines 18-35). After the date archive support (the `is_month()` block ending around line 34), add search support:

```php
    // Support search queries.
    if ( is_search() ) {
        $query_args['s'] = get_search_query();
        if ( ! empty( $_GET['orderby'] ) && in_array( $_GET['orderby'], array( 'date', 'relevance' ), true ) ) {
            $query_args['orderby'] = sanitize_text_field( $_GET['orderby'] );
        }
    }
```

Place this inside the `if ( $inherit_query )` block, after the date archive code and before the closing `}` of the `inheritQuery` block.

- [ ] **Step 2: Verify in browser**

Search for a term at http://localhost:8881/?s=test — the date-stream should now show search results instead of all recent posts. Try http://localhost:8881/?s=test&orderby=date to verify date ordering works.

- [ ] **Step 3: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/date-stream/render.php
git commit -m "feat: date-stream — add search query support to inheritQuery"
```

---

### Task 4: Create sp/search-header block

New server-rendered block that shows result count, query echo, and sort toggle for search pages.

**Files:**
- Create: `wp-content/plugins/signopeso-core/blocks/search-header/block.json`
- Create: `wp-content/plugins/signopeso-core/blocks/search-header/render.php`
- Modify: `wp-content/plugins/signopeso-core/signopeso-core.php`

- [ ] **Step 1: Create block.json**

Create `blocks/search-header/block.json`:

```json
{
    "apiVersion": 3,
    "name": "sp/search-header",
    "title": "Search Header",
    "category": "signopeso",
    "description": "Search results header with count, query echo, and sort toggle.",
    "textdomain": "signopeso-core",
    "supports": {
        "html": false
    },
    "render": "file:./render.php"
}
```

- [ ] **Step 2: Create render.php**

Create `blocks/search-header/render.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! is_search() ) {
    return;
}

$search_query = get_search_query();
$found_posts  = $GLOBALS['wp_query']->found_posts ?? 0;
$current_sort = isset( $_GET['orderby'] ) && in_array( $_GET['orderby'], array( 'date', 'relevance' ), true )
    ? sanitize_text_field( $_GET['orderby'] )
    : 'relevance';

$base_url = home_url( '/' );
?>
<div class="sp-search-header<?php echo $found_posts === 0 ? ' sp-search-header--empty' : ''; ?>">
    <h1 class="sp-search-header__title">
        <?php if ( $found_posts > 0 ) : ?>
            <?php echo esc_html( $found_posts ); ?> <?php echo $found_posts === 1 ? 'resultado' : 'resultados'; ?> para
            <span class="sp-search-header__query">&laquo;<?php echo esc_html( $search_query ); ?>&raquo;</span>
        <?php else : ?>
            Sin resultados para
            <span class="sp-search-header__query">&laquo;<?php echo esc_html( $search_query ); ?>&raquo;</span>
        <?php endif; ?>
    </h1>

    <?php if ( $found_posts > 0 ) : ?>
    <div class="sp-search-header__sort">
        <a href="<?php echo esc_url( add_query_arg( array( 's' => $search_query, 'orderby' => 'relevance' ), $base_url ) ); ?>"
           class="<?php echo 'relevance' === $current_sort ? 'is-active' : ''; ?>">Relevancia</a>
        <span class="sp-search-header__sep">&middot;</span>
        <a href="<?php echo esc_url( add_query_arg( array( 's' => $search_query, 'orderby' => 'date' ), $base_url ) ); ?>"
           class="<?php echo 'date' === $current_sort ? 'is-active' : ''; ?>">Fecha</a>
    </div>
    <?php else : ?>
    <p class="sp-search-header__hint">Intenta con otros términos o explora las secciones.</p>
    <?php endif; ?>
</div>
<?php
```

- [ ] **Step 3: Register block in signopeso-core.php**

In `signopeso-core.php`, find the `$blocks` array (around line 38). Add `'search-header'` to the array:

```php
    $blocks = array(
        'source-card',
        'post-card',
        'date-stream',
        'ad-slot',
        'popular-posts',
        'newsletter-form',
        'full-archive',
        'recirculation',
        'portada',
        'popular-strip',
        'search-header',
    );
```

- [ ] **Step 4: Add search header CSS**

In `signopeso.css`, add the search header styles. Place them before the SINGLE POST section comment:

```css
/* ================================================================
   SEARCH HEADER
   ================================================================ */
.sp-search-header {
    margin-bottom: 8px;
}
.sp-search-header__title {
    font-family: var(--wp--preset--font-family--heading);
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.1;
    margin-bottom: 12px;
}
.sp-search-header__query {
    color: var(--wp--preset--color--primary);
}
.sp-search-header__sort {
    font-family: var(--wp--preset--font-family--body);
    font-size: 0.75rem;
    margin-bottom: 24px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--wp--preset--color--border);
}
.sp-search-header__sort a {
    color: var(--wp--preset--color--muted);
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    font-weight: 600;
}
.sp-search-header__sort a.is-active {
    color: var(--wp--preset--color--secondary);
}
.sp-search-header__sep {
    margin: 0 8px;
    color: var(--wp--preset--color--muted);
}
.sp-search-header__hint {
    font-size: 0.9rem;
    color: var(--wp--preset--color--muted);
    margin-bottom: 24px;
}
```

- [ ] **Step 5: Verify in browser**

Search at http://localhost:8881/?s=test — the search header should show "N resultados para «test»" with Relevancia/Fecha toggle below. Try `?s=asdfghjkl` to verify zero results shows "Sin resultados para «asdfghjkl»" with the hint text and no sort toggle.

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/search-header/ wp-content/plugins/signopeso-core/signopeso-core.php wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: sp/search-header block — result count, query echo, sort toggle"
```

---

### Task 5: Create search.html template

New search results template using the search-header block and date-stream with inheritQuery.

**Files:**
- Create: `wp-content/themes/signopeso-theme/templates/search.html`

- [ ] **Step 1: Create search.html**

Create `templates/search.html`:

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"},"style":{"spacing":{"padding":{"top":"32px","bottom":"32px","left":"24px","right":"24px"}},"border":{"top":{"color":"var(--wp--preset--color--primary)","width":"3px"}}}} -->
<div class="wp-block-group" style="padding-top:32px;padding-right:24px;padding-bottom:32px;padding-left:24px;border-top-color:var(--wp--preset--color--primary);border-top-width:3px">

    <!-- wp:sp/search-header /-->

    <!-- wp:sp/date-stream {"postsPerPage":10,"inheritQuery":true} /-->

</div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

- [ ] **Step 2: Verify in browser**

Search at http://localhost:8881/?s=test — should now use the new search template: salmón top-border, search header with count + toggle, post cards in date-stream format, polished footer.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/signopeso-theme/templates/search.html
git commit -m "feat: search.html template — dedicated search results page"
```

---

### Task 6: Create sp/recirculation-lite block

Lightweight recirculation for the 404 page — shows 3 recent posts without requiring post context.

**Files:**
- Create: `wp-content/plugins/signopeso-core/blocks/recirculation-lite/block.json`
- Create: `wp-content/plugins/signopeso-core/blocks/recirculation-lite/render.php`
- Modify: `wp-content/plugins/signopeso-core/signopeso-core.php`
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Create block.json**

Create `blocks/recirculation-lite/block.json`:

```json
{
    "apiVersion": 3,
    "name": "sp/recirculation-lite",
    "title": "Recirculation Lite",
    "category": "signopeso",
    "description": "Lightweight recent posts for pages without post context (e.g., 404).",
    "textdomain": "signopeso-core",
    "supports": {
        "html": false
    },
    "render": "file:./render.php"
}
```

- [ ] **Step 2: Create render.php**

Create `blocks/recirculation-lite/render.php`:

```php
<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$recent = new WP_Query( array(
    'posts_per_page' => 3,
    'post_status'    => 'publish',
    'no_found_rows'  => true,
) );

if ( ! $recent->have_posts() ) {
    return;
}
?>
<div class="sp-recirculation-lite">
    <div class="sp-section-label">quizás te interese</div>
    <?php
    while ( $recent->have_posts() ) :
        $recent->the_post();
        $card_block = new WP_Block(
            array(
                'blockName'    => 'sp/post-card',
                'attrs'        => array(),
                'innerBlocks'  => array(),
                'innerHTML'    => '',
                'innerContent' => array(),
            ),
            array( 'postId' => get_the_ID() )
        );
        echo $card_block->render(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    endwhile;
    wp_reset_postdata();
    ?>
</div>
<?php
```

- [ ] **Step 3: Register block in signopeso-core.php**

Add `'recirculation-lite'` to the `$blocks` array (after `'search-header'`).

- [ ] **Step 4: Add recirculation-lite CSS**

In `signopeso.css`, add after the RECIRCULATION section:

```css
/* ================================================================
   RECIRCULATION LITE — lightweight version for 404 / non-post pages
   ================================================================ */
.sp-recirculation-lite {
    margin-top: 40px;
    padding-top: 24px;
    border-top: 1px solid var(--wp--preset--color--border);
}
.sp-recirculation-lite .sp-section-label {
    margin-bottom: 16px;
}
```

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/recirculation-lite/ wp-content/plugins/signopeso-core/signopeso-core.php wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: sp/recirculation-lite block — lightweight recent posts for 404"
```

---

### Task 7: 404 page — personality + recommendations

Update the 404 template with character and useful content.

**Files:**
- Modify: `wp-content/themes/signopeso-theme/templates/404.html`

- [ ] **Step 1: Rewrite 404.html**

Replace the entire content of `templates/404.html`:

```html
<!-- wp:template-part {"slug":"header","area":"header"} /-->

<!-- wp:group {"layout":{"type":"constrained","contentSize":"720px"},"style":{"spacing":{"padding":{"top":"64px","bottom":"64px","left":"24px","right":"24px"}}}} -->
<div class="wp-block-group" style="padding-top:64px;padding-right:24px;padding-bottom:64px;padding-left:24px">

    <!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"2.25rem","fontWeight":"800"}},"fontFamily":"heading"} -->
    <h1 class="wp-block-heading" style="font-size:2.25rem;font-weight:800">Sin señal</h1>
    <!-- /wp:heading -->

    <!-- wp:paragraph {"textColor":"muted"} -->
    <p class="has-muted-color has-text-color">La página que buscas no existe o fue movida.</p>
    <!-- /wp:paragraph -->

    <!-- wp:search {"label":"Buscar","showLabel":false,"placeholder":"Buscar","buttonText":"Buscar"} /-->

    <!-- wp:sp/recirculation-lite /-->

</div>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","area":"footer"} /-->
```

Key changes from current:
- Heading: "404" → "Sin señal"
- Subtext: generic → "La página que buscas no existe o fue movida."
- Search placeholder: "Busca en $P" → "Buscar"
- Added: `sp/recirculation-lite` block for 3 recent posts with "quizás te interese" label

- [ ] **Step 2: Verify in browser**

Navigate to http://localhost:8881/this-page-does-not-exist — should show "Sin señal" heading, descriptive paragraph, search box, and 3 recent post cards under "quizás te interese".

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/signopeso-theme/templates/404.html
git commit -m "feat: 404 page — 'Sin señal' personality + recent post recommendations"
```

---

### Task 8: CLAUDE.md update

Document all changes.

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Update CLAUDE.md**

1. **Custom Blocks table** — Add two new blocks:
   - `| `sp/search-header` | Search results header: count + query echo + relevance/date sort toggle |`
   - `| `sp/recirculation-lite` | Lightweight recent posts for non-post pages (e.g., 404) |`

2. **Templates section** — Update:
   - `search.html` → `search.html` — Search results: search-header + date-stream with inheritQuery
   - `404.html` — update description: "Sin señal" heading, search, recent posts via recirculation-lite

3. **Typography section** — If not already done, ensure Inter line mentions single post badges

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: update CLAUDE.md for template polish — search, 404, new blocks"
```
