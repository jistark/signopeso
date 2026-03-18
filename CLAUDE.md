# SignoPeso ($P)

Economy + tech blog. "Corto y digerible, para el resto de nosotros."

## Architecture

Two deliverables, strict separation:

- **`wp-content/themes/signopeso-theme/`** — Block theme (FSE). Presentation only. No PHP logic for business rules.
- **`wp-content/plugins/signopeso-core/`** — Plugin. All functionality: taxonomy, blocks, source embeds, newsletter, ads, popular posts.

## Environment

- **Platform:** WordPress.com Business
- **WordPress:** 6.9+ with Gutenberg
- **PHP:** 8.x
- **CLI:** Use `studio wp <command> --path=/Users/ji/Sites/signopeso` for wp-cli. NOT `wp` directly.
- **Third-party plugins:** RankMath (SEO), Jetpack (stats)

## Plugin (`signopeso-core`)

### Custom Taxonomy
- `sp_formato` — Corto, Enlace, Largo, Cobertura. Radio buttons in editor, default Corto.

### Custom Blocks (all server-rendered via `render.php`)
| Block | Purpose |
|---|---|
| `sp/portada` | Editorial lead zone — auto-selects featured story (sticky → largo/cobertura → latest) + "También Hoy" secondary headlines |
| `sp/popular-strip` | Horizontal popular posts divider with salmón-numbered rankings (01-05) |
| `sp/source-card` | OG preview card for source URL (dual layout: high-res image top / low-res image right) |
| `sp/post-card` | Format-driven post card — category is always the visible label, format drives visual treatment |
| `sp/date-stream` | Date-grouped post stream with `inheritQuery` for archives. Excludes portada lead via `$GLOBALS['sp_portada_lead_id']`. |
| `sp/ad-slot` | Renders configured ad code by position |
| `sp/popular-posts` | Jetpack stats → ranked popular posts (sidebar version, kept for archives) |
| `sp/newsletter-form` | Subscription form → REST API → Resend (supports subheading) |
| `sp/full-archive` | Year/month collapsible post archive |
| `sp/recirculation` | Post-article recommendations using actual `sp/post-card` via `WP_Block` |

### Post Card Rendering (v2 — format-driven, category-labeled)
- **Corto**: Dense, tweet-like. Borderless. Category pill + timestamp + title only. No author, no image.
- **Enlace**: Boxed card. Category + `↗ domain` + timestamp. Source OG card below (high-res or low-res layout). Yellow border on hover.
- **Largo**: Expanded, borderless. Author + timestamp, yellow category badge, optional 21:9 featured image, excerpt, "Sigue leyendo →".
- **Cobertura**: Same as Largo but with pulsing salmón dot + "En vivo — se actualiza" in footer.

### Key Includes
| File | Responsibility |
|---|---|
| `includes/post-formats.php` | `sp_formato` taxonomy registration |
| `includes/source-embed.php` | Meta box, async OG fetch, `sp_get_source_data()` |
| `includes/rewrite-rules.php` | `/%category%/%postname%/` permalinks, `/tema/` tag rewrite |
| `includes/rest-api.php` | `POST /signopeso/v1/subscribe` with rate limiting |
| `includes/newsletter/` | Settings, digest builder, cron, Resend adapter |
| `includes/ad-slots.php` | 3 ad positions with settings page |
| `includes/popular-posts.php` | Jetpack stats query with transient cache |
| `includes/portada.php` | `sp_get_portada_lead()`, `sp_get_tambien_hoy()` helpers |

### Post Meta Keys
- `_sp_source_url` — Source URL
- `_sp_source_og_title`, `_sp_source_og_desc`, `_sp_source_og_image_id`, `_sp_source_og_domain` — Cached OG data
- `_sp_source_og_status` — `pending`, `fetched`, or `failed`

## Theme (`signopeso-theme`)

### Design System (Sherwood News-inspired)

**Color roles:**
- Primary `#F06B6B` (salmón) — masthead nav bar, logo #, footer accent, blockquote borders, ranking numbers
- Highlight `#FFEB3B` (yellow) — format badges, compact card hover, newsletter bg
- Highlight Pale `#FFF9C4` — hover tint on interactive elements
- Secondary `#1A1A1A` — masthead bg, heading text, body text
- Background `#FAFAFA`, Surface `#FFFFFF`, Border `#E0E0E0`, Muted `#999999`

**Typography:**
- Newsreader 700 — display headlines (3rem portada lead, 1.75rem expanded cards, line-height ~0.92-0.95)
- Newsreader 600 — secondary headlines (1.05rem compact/enlace cards)
- Newsreader 700 small-caps — section labels (date headers, "También Hoy", "Populares", sidebar headings, footer column heads)
- Inter — body text (18px/1.75 single, 1rem river)
- Datatype (variable mono) — author names, timestamps, category pills, domain attributions, rankings, pagination

**Visual patterns (Sherwood DNA):**
- Dark masthead + salmón nav strip (header)
- Borderless expanded cards — typography IS the design
- Author top-left bold, time top-right
- Yellow category badges (format drives visual treatment silently, category is the label)
- Thin 1px rules as separators
- Recirculation = the river (same `sp/post-card` block)
- Square corners throughout (border-radius: 0)

### Templates
- `index.html` — Portada (lead + "También Hoy") → popular strip → river (2/3) + sidebar (1/3) → dark footer
- `single.html` — Centered 720px, author/time/badge/title/deck/category/body, recirculation below
- `page.html` — Centered 720px
- `archive.html` — Uses `sp/date-stream` with `inheritQuery`
- `404.html` — Minimal with search
- `page-archive-all.html` — Custom template for Archivos page

### Template Parts
- `header.html` — Dark masthead (#$) + salmón nav strip (categories)
- `sidebar.html` — Search, newsletter (yellow), "Cómo Leernos" format explainer, curated links (with Techmeme), ad slot
- `footer.html` — Dark #1A1A1A multi-column: brand+tagline, link columns (Secciones + #$P), newsletter CTA (yellow), copyright + "Santiago, Chile"

### URL Structure
- Posts: `/{category}/{slug}/` (e.g., `/tecnologia/apple-lanza-m4-ultra/`)
- Categories: `/{category}/`
- Tags: `/tema/{tag-slug}/`
- Formats: `/formato/{slug}/` (secondary)

## Conventions

- All text in Spanish (Chile). Use `date_i18n()` for dates.
- CSS classes use BEM-ish: `.sp-post-card__title`, `.sp-source-card__domain`
- Plugin functions prefixed `sp_`
- No social sharing buttons (YAGNI)
- No dark mode (YAGNI)
- No marginalia (YAGNI — removed during UX refresh)
- Source attribution always via UTM: `?utm_source=signopeso&utm_medium=referral`

## Docs
- Design spec (v1): `docs/superpowers/specs/2026-03-17-signopeso-design.md`
- Homepage v2 design spec: `docs/superpowers/specs/2026-03-17-signopeso-homepage-v2-design.md`
- Implementation plan (v1.0.0): `docs/superpowers/plans/2026-03-17-signopeso-implementation.md`
- UX refresh plan (completed): `docs/superpowers/plans/2026-03-17-signopeso-ux-refresh.md`
- Homepage v2 plan: `docs/superpowers/plans/2026-03-17-signopeso-homepage-v2.md`
