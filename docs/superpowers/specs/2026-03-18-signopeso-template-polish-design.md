# SignoPeso Template Polish — Design Spec

> Extend the homepage UI polish to single posts, pages, search results, and the 404 page. All templates stay **single-column 720px** (no sidebar outside homepage).

---

## 1. Single Post (`single.html`)

**Goal:** Typography consistency with the homepage card polish. No structural changes.

### Changes

| Element | Current | Target |
|---------|---------|--------|
| Badge (`wp:post-terms` with class `.sp-single__badge`, styled via `.sp-single__badge a`) | Datatype mono, `0.55rem`, `letter-spacing: 1.5px` | Inter 600, `0.65rem`, `letter-spacing: 0.05em` |
| Author (`.sp-single__author`) | Inherits body font | Keep as-is (it's already `0.85rem/700`, reads fine) |
| Time | Datatype | Keep (timestamps stay mono across the site) |

**CSS only** — one rule change in `signopeso.css`. Template unchanged.

---

## 2. Pages (`page.html`)

**Goal:** Minimal visual identity link to the rest of the site.

### Changes

- Add a **3px salmón top-border** on the content group — matches footer and recirculation visual language
- Template change: add `"border":{"top":{"color":"var(--wp--preset--color--primary)","width":"3px"}}` to the outer group's style

**Template only** — one attribute addition. No CSS needed.

---

## 3. Search Results (`search.html` — new template)

**Goal:** Dedicated search experience with result count, query echo, and sort toggle.

### Template Structure

```
[header]
720px constrained group:
  sp/search-header block:
    "12 resultados para «bitcoin»"   ← count + query
    [Relevancia · Fecha]             ← sort toggle links
  sp/date-stream (inheritQuery)      ← card river
[footer]
```

### New Block: `sp/search-header`

Server-rendered block that outputs:

```html
<div class="sp-search-header">
  <h1 class="sp-search-header__title">
    12 resultados para <span class="sp-search-header__query">«bitcoin»</span>
  </h1>
  <div class="sp-search-header__sort">
    <a href="?s=bitcoin&orderby=relevance" class="is-active">Relevancia</a>
    <span class="sp-search-header__sep">·</span>
    <a href="?s=bitcoin&orderby=date">Fecha</a>
  </div>
</div>
```

**PHP logic:**
- `$wp_query->found_posts` for count
- `get_search_query()` for query string (already escaped)
- `$_GET['orderby']` to determine active sort (default: `relevance`)
- Links preserve the `s` param and toggle `orderby`
- Zero results: render "Sin resultados para «query»" + suggestion text

**Registration:** `register_block_type_from_metadata()` in `signopeso-core.php`, new `blocks/search-header/` directory with `block.json` + `render.php`.

### Extend `date-stream` for Search

The `date-stream` block's `inheritQuery` currently handles taxonomy and date archives but **not search**. Add `is_search()` support:

```php
// In date-stream/render.php, inside the inheritQuery branch:
if ( is_search() ) {
    $query_args['s'] = get_search_query();
    if ( ! empty( $_GET['orderby'] ) && in_array( $_GET['orderby'], [ 'date', 'relevance' ], true ) ) {
        $query_args['orderby'] = sanitize_text_field( $_GET['orderby'] );
    }
}
```

### CSS

```css
/* Search header */
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
```

### Zero Results State

When `found_posts === 0`:

```html
<div class="sp-search-header sp-search-header--empty">
  <h1 class="sp-search-header__title">
    Sin resultados para <span class="sp-search-header__query">«query»</span>
  </h1>
  <p class="sp-search-header__hint">Intenta con otros términos o explora las secciones.</p>
</div>
```

No sort toggle when zero results. The `date-stream` block already handles empty queries gracefully (renders nothing).

---

## 4. 404 Page (`404.html`)

**Goal:** Personality + useful recommendations.

### Template Structure

```
[header]
720px constrained group (64px top/bottom padding):
  "Sin señal"                        ← h1, Newsreader 700
  "La página que buscas no existe    ← paragraph, muted
   o fue movida."
  [search box]                       ← placeholder: "Buscar"
  "Quizás te interese"              ← section label
  sp/recirculation-lite              ← 3 recent posts (new lightweight block)
[footer]
```

### New Block: `sp/recirculation-lite`

A lightweight version of recirculation that doesn't depend on post context. Simply queries the 3 most recent posts and renders them as `sp/post-card` blocks (same as recirculation does).

The section label ("quizás te interese") is rendered inside the block — not a separate template element.

```php
// blocks/recirculation-lite/render.php
$recent = new WP_Query( array(
    'posts_per_page' => 3,
    'post_status'    => 'publish',
    'no_found_rows'  => true,
) );

if ( ! $recent->have_posts() ) return;
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
```

This reuses the existing `sp/post-card` rendering pipeline (same pattern as `sp/recirculation`) — all our card polish applies automatically.

### CSS

Minimal — reuses existing `.sp-section-label` styles. The `.sp-recirculation-lite` wrapper just needs spacing:

```css
.sp-recirculation-lite {
    margin-top: 40px;
    padding-top: 24px;
    border-top: 1px solid var(--wp--preset--color--border);
}
.sp-recirculation-lite .sp-section-label {
    margin-bottom: 16px;
}
```

### 404 Text Updates

- Heading: "Sin señal" (replaces "404")
- Subtext: "La página que buscas no existe o fue movida." (replaces generic text)
- Search placeholder: "Buscar" (replaces "Busca en $P")

---

## 5. Platform Note: Search Ordering

WordPress's `orderby=relevance` is valid in `WP_Query` only when `s` is set. On WordPress.com Business (the target platform), search may use Elasticsearch behind the scenes, which handles relevance natively. The sort toggle should work correctly, but if `relevance` ordering is not supported, the toggle degrades gracefully — both options return results, just with potentially identical ordering. No fallback code needed.

---

## File Map

| File | Action |
|------|--------|
| `wp-content/themes/signopeso-theme/assets/css/signopeso.css` | Modify (single badge, search header, recirculation-lite) |
| `wp-content/themes/signopeso-theme/templates/single.html` | No change |
| `wp-content/themes/signopeso-theme/templates/page.html` | Modify (add salmón top-border) |
| `wp-content/themes/signopeso-theme/templates/search.html` | Create |
| `wp-content/themes/signopeso-theme/templates/404.html` | Modify (personality + recommendations) |
| `wp-content/plugins/signopeso-core/blocks/search-header/block.json` | Create |
| `wp-content/plugins/signopeso-core/blocks/search-header/render.php` | Create |
| `wp-content/plugins/signopeso-core/blocks/recirculation-lite/block.json` | Create |
| `wp-content/plugins/signopeso-core/blocks/recirculation-lite/render.php` | Create |
| `wp-content/plugins/signopeso-core/blocks/date-stream/render.php` | Modify (add search support) |
| `wp-content/plugins/signopeso-core/signopeso-core.php` | Modify (register 2 new blocks) |
| `CLAUDE.md` | Modify (document new blocks + template changes) |

## Not Changing

- `archive.html` — already works well with polished cards + date group separators
- `page-archive-all.html` — already works well
- `single.html` structure — only CSS change for badge font
