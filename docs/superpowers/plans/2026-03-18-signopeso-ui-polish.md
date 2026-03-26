# SignoPeso UI Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Comprehensive UI/UX refinement — better density, hierarchy, rhythm, cohesion, and polish across the entire homepage.

**Architecture:** Pure CSS + template + render.php changes. No new blocks, no data model changes. The existing block architecture stays; we refine how each block renders and is styled.

**Tech Stack:** WordPress block theme (FSE), PHP render.php blocks, vanilla CSS, WordPress template parts (HTML block markup).

**Spec:** `docs/superpowers/specs/2026-03-18-signopeso-ui-polish-design.md`

---

## File Map

| File | Responsibility | Action |
|---|---|---|
| `wp-content/themes/signopeso-theme/assets/css/signopeso.css` | All visual styling | Modify (major) |
| `wp-content/themes/signopeso-theme/templates/index.html` | Homepage template | Modify (remove popular-strip) |
| `wp-content/themes/signopeso-theme/parts/header.html` | Masthead + nav | Modify (remove secondary nav) |
| `wp-content/themes/signopeso-theme/parts/sidebar.html` | Sidebar modules | Modify (strip to 3 modules) |
| `wp-content/themes/signopeso-theme/parts/footer.html` | Dark footer | Modify (add nav links, spacing) |
| `wp-content/plugins/signopeso-core/blocks/portada/render.php` | Portada block | Modify (proportions, También Hoy) |
| `wp-content/plugins/signopeso-core/blocks/post-card/render.php` | Post card block | Modify (largo layout, corto teaser, enlace source) |
| `wp-content/plugins/signopeso-core/blocks/source-card/render.php` | Source OG card | Modify (radical simplification) |
| `wp-content/plugins/signopeso-core/blocks/newsletter-form/render.php` | Newsletter form | Modify (horizontal layout) |

---

### Task 1: Template cleanup — remove popular strip and strip sidebar

Remove the popular strip from the homepage and clean up the sidebar template part (remove Cómo Leernos and Qué Estamos Leyendo).

**Files:**
- Modify: `wp-content/themes/signopeso-theme/templates/index.html`
- Modify: `wp-content/themes/signopeso-theme/parts/sidebar.html`

- [ ] **Step 1: Remove popular strip from index.html**

In `templates/index.html`, delete the `sp/popular-strip` block line:
```html
<!-- wp:sp/popular-strip {"count":5,"period":"7"} /-->
```

- [ ] **Step 2: Strip sidebar.html to 3 modules**

Replace the entire `parts/sidebar.html` content. Keep only: search, newsletter form, ad slot.

```html
<!-- wp:group {"style":{"spacing":{"blockGap":"24px"}},"className":"sp-sidebar"} -->
<div class="wp-block-group sp-sidebar">

<!-- wp:search {"label":"Buscar","showLabel":false,"placeholder":"Buscar","buttonText":"","style":{"border":{"radius":"0px","width":"1px"}},"borderColor":"border","fontSize":"small","className":"sp-sidebar__search"} /-->

<!-- wp:sp/newsletter-form {"ctaText":"Suscríbete","placeholder":"tu@email.com","heading":"Recibe #$P","subheading":"Corto y digerible, para el resto de nosotros."} /-->

<!-- wp:sp/ad-slot {"position":"sidebar"} /-->

</div>
<!-- /wp:group -->
```

Key changes from current:
- `blockGap` → `24px` (was `20px`, aligns to 8px grid)
- Search placeholder → "Buscar" (was "Busca en #$P")
- Newsletter heading → "Recibe #$P" (was "Suscríbete a #$P")
- Removed: formatos box (lines 8-23 current) and curated links (lines 25-40 current)

- [ ] **Step 3: Verify in browser**

Run: `open http://localhost:8881/`
Expected: Homepage shows portada → river+sidebar directly (no popular strip). Sidebar shows only search + newsletter + ad slot.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/signopeso-theme/templates/index.html wp-content/themes/signopeso-theme/parts/sidebar.html
git commit -m "refactor: remove popular strip from homepage, strip sidebar to 3 modules"
```

---

### Task 2: Header cleanup — remove secondary nav

Move Archivos/Acerca links out of the header (they'll go to footer in Task 6).

**Files:**
- Modify: `wp-content/themes/signopeso-theme/parts/header.html`

- [ ] **Step 1: Remove secondary nav from header.html**

In `parts/header.html`, find the masthead group's inner flex layout. Remove the `wp:navigation` block that contains Archivos and Acerca links (the `sp-header__nav-secondary` navigation). The logo paragraph stays.

The masthead inner group should contain ONLY the logo paragraph — remove the navigation block entirely. Since the logo is now the only child, simplify the inner flex group to just contain the logo.

- [ ] **Step 2: Verify in browser**

Run: `open http://localhost:8881/`
Expected: Header shows only #$ logo in masthead + salmón category nav bar. No "Archivos" / "Acerca" links visible.

- [ ] **Step 3: Commit**

```bash
git add wp-content/themes/signopeso-theme/parts/header.html
git commit -m "refactor: remove secondary nav from header (moves to footer)"
```

---

### Task 3: Portada refinements — proportions and También Hoy

Tighten the portada: smaller lead image, tighter headline, clamped deck, and pure-headline También Hoy.

**Files:**
- Modify: `wp-content/plugins/signopeso-core/blocks/portada/render.php`
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Update portada render.php — lead section**

In `blocks/portada/render.php`, no PHP changes needed for the lead — the aspect ratio and sizes are controlled by CSS. But we do need to change the deck to add line-clamp classes.

Find the deck paragraph (around line 55):
```php
<p class="sp-portada-lead__deck"><?php echo esc_html( $lead_excerpt ); ?></p>
```
No PHP change needed — we'll clamp via CSS.

- [ ] **Step 2: Update portada render.php — remove También Hoy first-item image**

In `blocks/portada/render.php`, find the También Hoy loop (around lines 64-90). Currently the first item (`$i === 0`) gets a featured image. Remove this conditional image rendering so ALL items are pure headlines.

Find the block that checks `$i === 0` for the thumbnail and remove the image div entirely. The `$sec_thumb` variable assignment and the `<div class="sp-portada-sec__item-img">` block should be removed.

- [ ] **Step 3: Update portada CSS**

In `assets/css/signopeso.css`, update these rules:

**Lead image** — change aspect ratio:
```css
/* Was: aspect-ratio: 2 / 1; */
.sp-portada-lead__img { aspect-ratio: 3 / 2; }
```

**Lead title** — tighter:
```css
/* Was: font-size: 3rem; line-height: 0.92; */
.sp-portada-lead__title { font-size: 2.5rem; line-height: 0.90; }
```

**Deck** — clamp to 2 lines:
```css
.sp-portada-lead__deck {
    font-size: 0.95rem; /* was 1.05rem */
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
```

**También Hoy titles** — 1rem:
```css
/* Was: font-size: 1.05rem; */
.sp-portada-sec__title { font-size: 1rem; }
```

**También Hoy item separators** — the items already have `border-bottom: 1px` from the current CSS (`.sp-portada-sec__item` has this), so this is already correct. Verify no change needed.

**Remove También Hoy image styles** — delete or neutralize `.sp-portada-sec__item-img` rules since we removed the image from render.php.

- [ ] **Step 4: Verify in browser**

Run: `open http://localhost:8881/`
Expected: Portada lead image is less tall (3:2), headline is tighter (2.5rem), deck clamps at 2 lines. También Hoy has no images — all pure headlines with separators.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/portada/render.php wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: portada polish — 3:2 lead image, tighter headline, clamped deck, headline-only También Hoy"
```

---

### Task 4: Source card radical simplification

Compress the source card from a full OG preview to a compact horizontal citation.

**Files:**
- Modify: `wp-content/plugins/signopeso-core/blocks/source-card/render.php`
- Modify: `wp-content/plugins/signopeso-core/blocks/post-card/render.php` (inline source card in enlace)
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Rewrite source-card/render.php**

Replace the entire render logic. The new card is always horizontal: 80px square thumbnail (left) + OG title + domain (right). No excerpt, no "Ir a la fuente" link, no high-res/low-res distinction.

```php
<?php
/**
 * Source Card — compact OG citation.
 */
$post_id = get_the_ID();
$source  = sp_get_source_data( $post_id );
if ( ! $source ) return;

$has_og = ! empty( $source['title'] );

// Fallback: simple link if no OG data
if ( ! $has_og ) : ?>
    <p class="sp-source-link">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
            &#8599; <?php echo esc_html( $source['domain'] ); ?>
        </a>
    </p>
<?php return; endif;

$image_url = '';
if ( ! empty( $source['image_id'] ) ) {
    $img = wp_get_attachment_image_src( $source['image_id'], 'thumbnail' );
    if ( $img ) $image_url = $img[0];
}
?>
<div class="sp-source-card">
    <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
        <?php if ( $image_url ) : ?>
            <div class="sp-source-card__thumb">
                <img src="<?php echo esc_url( $image_url ); ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>
        <div class="sp-source-card__body">
            <div class="sp-source-card__og-title"><?php echo esc_html( $source['title'] ); ?></div>
            <div class="sp-source-card__domain">&#8599; <?php echo esc_html( $source['domain'] ); ?></div>
        </div>
    </a>
</div>
<?php
```

- [ ] **Step 2: Update inline source card in post-card/render.php (enlace format)**

In `blocks/post-card/render.php`, the enlace format (around lines 49-132) renders the source card inline. Find the source card rendering section and replace both the highres and lowres variants with the same compact structure.

Find the `$has_og` conditional block inside the enlace format and replace both branches (highres and lowres) with the single compact layout:

```php
<?php if ( $has_og ) : ?>
    <div class="sp-source-card">
        <a href="<?php echo esc_url( $source['url_utm'] ); ?>" target="_blank" rel="noopener">
            <?php if ( $og_img_url ) : ?>
                <div class="sp-source-card__thumb">
                    <img src="<?php echo esc_url( $og_img_url ); ?>" alt="" loading="lazy">
                </div>
            <?php endif; ?>
            <div class="sp-source-card__body">
                <div class="sp-source-card__og-title"><?php echo esc_html( $source['title'] ); ?></div>
                <div class="sp-source-card__domain">&#8599; <?php echo esc_html( $source['domain'] ); ?></div>
            </div>
        </a>
    </div>
<?php endif; ?>
```

Remove the `sp_source_card_is_highres()` call and the highres/lowres branching. The image URL can come from the existing `$source['image_id']` — get a `thumbnail` size.

- [ ] **Step 3: Update source card CSS**

Replace the existing source card CSS (around lines 600-762) with the compact version:

```css
/* ── Source Card (compact citation) ────────────── */
.sp-source-card {
    border: 1px solid #F0F0F0;
    background: #FAFAFA;
    margin-top: 12px;
    overflow: hidden;
}
.sp-source-card a {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 10px 12px;
    text-decoration: none;
    color: inherit;
}
.sp-source-card__thumb {
    width: 80px;
    height: 80px;
    flex-shrink: 0;
    overflow: hidden;
}
.sp-source-card__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.sp-source-card__og-title {
    font-size: 0.9rem;
    font-weight: 600;
    line-height: 1.25;
    margin-bottom: 4px;
    color: var(--wp--preset--color--secondary);
}
.sp-source-card__domain {
    font-size: 0.65rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--wp--preset--color--primary);
}
.sp-source-link {
    margin-top: 8px;
    font-size: 0.75rem;
}
.sp-source-link a {
    color: var(--wp--preset--color--primary);
    text-decoration: none;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
```

Delete the old `.sp-source-card--highres`, `.sp-source-card--lowres`, `.sp-source-card__img-lg`, `.sp-source-card__img-sm`, `.sp-source-card__og-excerpt`, `.sp-source-card__og-author`, `.sp-source-card__og-url`, `.sp-source-card__go` rules — they're no longer used.

- [ ] **Step 4: Verify in browser**

Run: `open http://localhost:8881/`
Expected: Enlace posts show a compact ~60-70px tall source card with thumbnail left, title + domain right. No excerpt, no "Ir a la fuente" link. On the single post page, the standalone source card block also renders compact.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/source-card/render.php wp-content/plugins/signopeso-core/blocks/post-card/render.php wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: compress source card — compact horizontal citation, no excerpt"
```

---

### Task 5: Post card — largo horizontal layout

The biggest visual change: largo cards shift from full-width hero image to horizontal layout with right-aligned thumbnail.

**Files:**
- Modify: `wp-content/plugins/signopeso-core/blocks/post-card/render.php`
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Restructure largo HTML in render.php**

In `blocks/post-card/render.php`, find the largo format rendering (around lines 138-165). Restructure to wrap title + excerpt + read-link in a text column, and the image becomes a side thumbnail.

Replace the largo block with:

```php
<?php /* ── Largo ──────────────────────────────────── */ ?>
<article class="sp-post-card sp-post-card--expanded sp-post-card--largo">
    <div class="sp-post-card__header">
        <span class="sp-post-card__author"><?php echo esc_html( $author_name ); ?></span>
        <span class="sp-post-card__time"><?php echo esc_html( $time_str ); ?></span>
    </div>
    <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__badge"><?php echo esc_html( $cat_name ); ?></a>
    <div class="sp-post-card__body-row">
        <div class="sp-post-card__text">
            <h3 class="sp-post-card__title">
                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
            </h3>
            <?php if ( $excerpt ) : ?>
                <div class="sp-post-card__excerpt"><?php echo esc_html( $excerpt ); ?></div>
            <?php endif; ?>
            <div class="sp-post-card__footer">
                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="sp-post-card__read">Sigue leyendo &rarr;</a>
            </div>
        </div>
        <?php if ( $thumb_url ) : ?>
            <div class="sp-post-card__thumb">
                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>
    </div>
</article>
```

Key changes:
- New `.sp-post-card__body-row` wrapper around text + image (flex container)
- New `.sp-post-card__text` wrapper for title + excerpt + footer
- New `.sp-post-card__thumb` replaces `.sp-post-card__img`
- Image is no longer full-width above title — it's a side thumbnail

- [ ] **Step 2: Apply same structure to cobertura**

In `blocks/post-card/render.php`, find the cobertura format (around lines 171-201). Apply the same `.sp-post-card__body-row` structure. The only difference is the footer content (live dot instead of "Sigue leyendo").

```php
<?php /* ── Cobertura ──────────────────────────────── */ ?>
<article class="sp-post-card sp-post-card--expanded sp-post-card--cobertura">
    <div class="sp-post-card__header">
        <span class="sp-post-card__author"><?php echo esc_html( $author_name ); ?></span>
        <span class="sp-post-card__time"><?php echo esc_html( $time_str ); ?></span>
    </div>
    <a href="<?php echo esc_url( $cat_link ); ?>" class="sp-post-card__badge"><?php echo esc_html( $cat_name ); ?></a>
    <div class="sp-post-card__body-row">
        <div class="sp-post-card__text">
            <h3 class="sp-post-card__title">
                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>"><?php echo esc_html( get_the_title( $post_id ) ); ?></a>
            </h3>
            <?php if ( $excerpt ) : ?>
                <div class="sp-post-card__excerpt"><?php echo esc_html( $excerpt ); ?></div>
            <?php endif; ?>
            <div class="sp-post-card__footer">
                <span class="sp-post-card__live">
                    <span class="sp-post-card__live-dot"></span> En vivo &mdash; se actualiza
                </span>
                <a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" class="sp-post-card__read">Sigue leyendo &rarr;</a>
            </div>
        </div>
        <?php if ( $thumb_url ) : ?>
            <div class="sp-post-card__thumb">
                <img src="<?php echo esc_url( $thumb_url ); ?>" alt="" loading="lazy">
            </div>
        <?php endif; ?>
    </div>
</article>
```

- [ ] **Step 3: Update expanded card CSS**

In `assets/css/signopeso.css`, update the expanded card styles (around lines 291-400):

```css
/* ── Post Card Expanded (Largo / Cobertura) ──── */
.sp-post-card--expanded {
    padding: 0 0 24px;
    margin-bottom: 24px;
    border-bottom: 1px solid var(--wp--preset--color--border);
}
.sp-post-card--expanded .sp-post-card__badge {
    font-family: Inter, -apple-system, sans-serif;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: var(--wp--preset--color--highlight);
    color: var(--wp--preset--color--secondary);
    padding: 3px 8px;
    text-decoration: none;
    display: inline-block;
    margin-bottom: 8px;
}
.sp-post-card__body-row {
    display: flex;
    gap: 24px;
    align-items: flex-start;
}
.sp-post-card__text {
    flex: 1;
    min-width: 0;
}
.sp-post-card__thumb {
    width: 280px;
    flex-shrink: 0;
    aspect-ratio: 3 / 2;
    overflow: hidden;
}
.sp-post-card__thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.sp-post-card--expanded .sp-post-card__title {
    font-size: 1.4rem;
    font-weight: 700;
    line-height: 0.95;
    margin-bottom: 8px;
}
.sp-post-card--expanded .sp-post-card__excerpt {
    font-size: 0.9rem;
    color: #555;
    line-height: 1.5;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 8px;
}
.sp-post-card--expanded .sp-post-card__read {
    font-size: 0.75rem;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: var(--wp--preset--color--primary);
    text-decoration: none;
}
```

Delete the old `.sp-post-card--expanded .sp-post-card__image` rule (21:9 aspect ratio full-width image — no longer used).

- [ ] **Step 4: Verify in browser**

Run: `open http://localhost:8881/`
Expected: Largo cards show horizontal layout — title + excerpt left, 280px 3:2 thumbnail right. Largo cards without images are full-width text-only. Cobertura cards look the same but with pulsing live dot.

- [ ] **Step 5: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/post-card/render.php wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: largo horizontal layout — 3:2 thumbnail right, text left"
```

---

### Task 6: Post card — corto teaser + enlace refinements

Add optional teaser to cortos and update enlace card styling.

**Files:**
- Modify: `wp-content/plugins/signopeso-core/blocks/post-card/render.php`
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Add teaser line to corto in render.php**

In `blocks/post-card/render.php`, find the corto format (around lines 29-43). After the title `<h3>`, add a conditional teaser:

```php
<?php
$corto_excerpt = get_the_excerpt( $post_id );
if ( $corto_excerpt ) : ?>
    <p class="sp-post-card__teaser"><?php echo esc_html( wp_trim_words( $corto_excerpt, 12, '…' ) ); ?></p>
<?php endif; ?>
```

Place this right after the closing `</h3>` tag of the corto title.

- [ ] **Step 2: Update corto CSS**

In `assets/css/signopeso.css`, update the corto styles:

```css
.sp-post-card--corto {
    padding: 16px 0; /* was 10px 0 */
    border-bottom: 1px solid var(--wp--preset--color--border);
}
.sp-post-card--corto .sp-post-card__title {
    font-size: 1rem;
    font-weight: 700; /* was 600 */
    line-height: 1.25;
}
.sp-post-card__teaser {
    font-size: 0.85rem;
    color: var(--wp--preset--color--muted);
    margin-top: 4px;
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
```

- [ ] **Step 3: Update enlace card CSS**

In `assets/css/signopeso.css`, update enlace styles:

```css
.sp-post-card--enlace {
    background: var(--wp--preset--color--surface);
    border: 1px solid #EBEBEB; /* was var(--wp--preset--color--border) = #E0E0E0 */
    padding: 16px;
    margin: 24px 0; /* was 14px 0, aligns to 8px grid */
}
```

- [ ] **Step 4: Update category pills to Inter (enlace + corto)**

Change category pills from Datatype to Inter for corto and enlace formats:

```css
.sp-post-card--corto .sp-post-card__cat,
.sp-post-card--enlace .sp-post-card__cat {
    font-family: Inter, -apple-system, sans-serif;
    font-size: 0.65rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
```

- [ ] **Step 5: Verify in browser**

Run: `open http://localhost:8881/`
Expected: Corto cards have bolder titles (700 weight) with a subtle teaser line below when excerpt exists. Enlace cards have slightly more subtle border. Category pills in corto/enlace use Inter instead of monospace.

- [ ] **Step 6: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/post-card/render.php wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: corto teaser line + enlace border refinement + Inter category pills"
```

---

### Task 7: Newsletter form — horizontal layout

Change the newsletter form from vertical stack to horizontal input + button row.

**Files:**
- Modify: `wp-content/plugins/signopeso-core/blocks/newsletter-form/render.php`
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Update newsletter form render.php**

No structural PHP changes needed — the form already has input + button. The layout change is pure CSS. But update the heading attribute default:

In `blocks/newsletter-form/render.php`, change:
```php
$heading = esc_html( $attributes['heading'] ?? 'Suscríbete' );
```
This is already overridden by the sidebar template's `heading` attribute, so no change needed here.

- [ ] **Step 2: Update newsletter CSS for horizontal layout**

In `assets/css/signopeso.css`, update the newsletter form styles:

```css
.sp-newsletter-form__heading {
    font-size: 1.1rem; /* was 1.05rem */
    font-weight: 700;
}
.sp-newsletter-form__subheading {
    font-size: 0.8rem;
    margin-bottom: 8px;
}
.sp-newsletter-form__form {
    display: flex;
    flex-direction: row; /* was column */
    gap: 8px;
}
.sp-newsletter-form__input {
    flex: 1;
    padding: 8px 12px; /* was 10px 12px */
    font-size: 0.85rem;
}
.sp-newsletter-form__button {
    width: auto; /* was 100% */
    padding: 8px 16px;
    font-size: 0.75rem;
    white-space: nowrap;
}
```

- [ ] **Step 3: Verify in browser**

Run: `open http://localhost:8881/`
Expected: Sidebar newsletter shows heading + subheading, then input and button side by side in a single row.

- [ ] **Step 4: Commit**

```bash
git add wp-content/plugins/signopeso-core/blocks/newsletter-form/render.php wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: newsletter form horizontal layout — input + button in single row"
```

---

### Task 8: Footer polish — add nav links, refine spacing

Add secondary nav links (from header), refine spacing, add copyright separator.

**Files:**
- Modify: `wp-content/themes/signopeso-theme/parts/footer.html`
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Update footer.html**

In `parts/footer.html`, make these changes:

1. **Brand section** — make logo + tagline inline by putting tagline in same paragraph or using flex:
```html
<!-- wp:group {"className":"sp-footer__brand","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"center"},"style":{"spacing":{"blockGap":"12px"}}} -->
```

2. **Links grid — add Archivos and Acerca** to the #$P column. Add them to the existing list:
```
Acerca
Publicidad
Archivos    ← already there
Enviar un dato
RSS
```
Verify Acerca is already in the list. If not, add it.

3. **Copyright section** — add border-top separator. Update the bottom group style:
```html
<!-- wp:group {"className":"sp-footer__bottom","layout":{"type":"flex","justifyContent":"space-between"},"style":{"spacing":{"padding":{"top":"16px"}},"border":{"top":{"color":"rgba(255,255,255,0.1)","width":"1px"}}}} -->
```

4. **Inner padding** — increase to 48px top, 32px bottom:
```html
<!-- wp:group {"className":"sp-footer__inner","style":{"spacing":{"padding":{"top":"48px","bottom":"32px","left":"24px","right":"24px"}}}} -->
```

5. **Links grid gap** — increase to 64px:
```html
<!-- wp:group {"className":"sp-footer__links-grid","layout":{"type":"flex","flexWrap":"nowrap","verticalAlignment":"top"},"style":{"spacing":{"blockGap":"64px"}}} -->
```

- [ ] **Step 2: Update footer CSS**

In `assets/css/signopeso.css`, update footer styles:

```css
.sp-footer__inner {
    padding: 48px 24px 32px; /* was 32px 24px */
}
.sp-footer__brand {
    display: flex;
    align-items: center;
    gap: 12px;
}
.sp-footer__tagline {
    font-size: 0.75rem;
}
```

- [ ] **Step 3: Verify in browser**

Run: `open http://localhost:8881/`
Expected: Footer has more breathing room, brand is inline (logo + tagline), copyright row has subtle separator line, links grid has more spacing.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/signopeso-theme/parts/footer.html wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: footer polish — more spacing, inline brand, copyright separator"
```

---

### Task 9: Sidebar sticky + ad slot polish

Make sidebar sticky and refine ad slot placeholder.

**Files:**
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Add sidebar sticky behavior**

In `assets/css/signopeso.css`, add:

```css
.sp-sidebar {
    position: sticky;
    top: 24px;
    align-self: flex-start;
}
```

Note: `align-self: flex-start` is needed when the sidebar is in a flex/grid column to prevent it from stretching to the full column height.

- [ ] **Step 2: Refine ad slot fallback**

Update the ad slot placeholder styling:

```css
.sp-ad-slot--empty {
    min-height: 200px; /* was 250px */
    border: 1px dashed #E0E0E0; /* was solid */
    background: transparent; /* was surface */
    color: #CCC; /* lighter text */
    font-size: 0.7rem;
}
```

- [ ] **Step 3: Verify in browser**

Run: `open http://localhost:8881/`
Expected: Sidebar sticks to top of viewport while scrolling the river. Ad slot placeholder (if no ad code) is more discreet with dashed border.

- [ ] **Step 4: Commit**

```bash
git add wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: sticky sidebar + discreet ad slot placeholder"
```

---

### Task 10: Transversal CSS polish — spacing, color, typography discipline

The final pass that ties everything together: 8px grid, color discipline, consistent typography.

**Files:**
- Modify: `wp-content/themes/signopeso-theme/assets/css/signopeso.css`

- [ ] **Step 1: Date stream headers**

Update date headers to create clearer day breaks:

```css
.sp-date-stream__group + .sp-date-stream__group {
    margin-top: 32px;
    padding-top: 16px;
    border-top: 2px solid var(--wp--preset--color--border);
}
```

- [ ] **Step 2: Card spacing uniformity**

Ensure all card formats use consistent 24px vertical rhythm:

```css
.sp-post-card--corto { margin-bottom: 0; } /* border-bottom handles separation */
.sp-post-card--enlace { margin: 24px 0; }
.sp-post-card--expanded { padding-bottom: 24px; margin-bottom: 24px; }
```

- [ ] **Step 3: Color discipline — salmón reserved for accents**

Find and update link colors that currently use salmón for normal state. Links should be `#1A1A1A` by default, salmón on hover only:

```css
.sp-post-card__title a {
    color: var(--wp--preset--color--secondary);
    text-decoration: none;
}
.sp-post-card__title a:hover {
    color: var(--wp--preset--color--primary);
}
```

The "Sigue leyendo →" link keeps salmón (it's an accent/action element).

- [ ] **Step 4: Enlace hover transition refinement**

```css
.sp-post-card--enlace {
    transition: border-color 0.15s ease;
}
.sp-post-card--enlace:hover {
    border-color: var(--wp--preset--color--highlight);
}
```

- [ ] **Step 5: Responsive — largo vertical on mobile**

Add responsive rule for largo cards at mobile:

```css
@media (max-width: 768px) {
    .sp-post-card__body-row {
        flex-direction: column;
    }
    .sp-post-card__thumb {
        width: 100%;
        aspect-ratio: 3 / 2;
    }
    .sp-post-card--expanded .sp-post-card__title {
        font-size: 1.2rem;
    }
}
```

- [ ] **Step 6: Verify full homepage in browser**

Run: `open http://localhost:8881/`

Walk through the entire page top to bottom:
- Header: logo only, no secondary nav
- Portada: 3:2 lead, tighter headline, clamped deck, headline-only También Hoy
- River: largo horizontal (thumb right), corto with teaser, enlace with compact source card
- Sidebar: sticky, search + newsletter (horizontal) + ad
- Footer: inline brand, more spacing, copyright separator
- Responsive: resize to mobile, verify largo goes vertical

- [ ] **Step 7: Commit**

```bash
git add wp-content/themes/signopeso-theme/assets/css/signopeso.css
git commit -m "feat: transversal CSS polish — 8px grid, color discipline, responsive largo, date header breaks"
```

---

### Task 11: CLAUDE.md update

Update project documentation to reflect the changes.

**Files:**
- Modify: `CLAUDE.md`

- [ ] **Step 1: Update CLAUDE.md**

Update these sections:

1. **Custom Blocks table** — remove `sp/popular-strip` from the homepage description (note it's still available but not in index.html)
2. **Post Card Rendering** — update largo description (horizontal layout, 3:2 thumb right), corto (optional teaser), enlace (compact source card)
3. **Templates** — note that `index.html` no longer includes popular-strip
4. **Template Parts — sidebar.html** — update to reflect only 3 modules (search, newsletter, ad)
5. **Template Parts — header.html** — note secondary nav removed

- [ ] **Step 2: Commit**

```bash
git add CLAUDE.md
git commit -m "docs: update CLAUDE.md for UI polish — card layouts, sidebar cleanup, header simplification"
```
