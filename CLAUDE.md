# SignoPeso ($P)

Economy + tech blog. "Corto y digerible, para el resto de nosotros."

## Architecture

Two deliverables, strict separation:

- **`wp-content/themes/sp2/`** — Block theme (FSE). Presentation only. No PHP logic for business rules.
- **`wp-content/plugins/signopeso-core/`** — Plugin. All functionality: taxonomy, blocks, source embeds, newsletter, ads, popular posts, ticker data.
- **`wp-content/plugins/sp-chop/`** — Editorial AI pipeline: email/URL ingestion, content extraction, draft generation.
- ~~`wp-content/themes/signopeso-theme/`~~ — Legacy v1 theme. Superseded by sp2.

## Design Source of Truth

Figma file: `M5m0Wja4r46RvS2UAZBOiu`, node `5-278` ("Signopeso Mobile").
When CLAUDE.md conflicts with Figma, Figma wins.

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
| `sp/portada` | Editorial lead zone — auto-selects featured story (sticky -> largo/cobertura -> latest) + "También Hoy" secondary headlines |
| `sp/popular-strip` | Horizontal popular posts divider with salmon-numbered rankings (01-05). Not used in homepage. |
| `sp/source-card` | Compact OG citation card for source URL (80px thumb + title + domain) |
| `sp/post-card` | Format-driven post card — category is always the visible label, format drives visual treatment |
| `sp/date-stream` | Date-grouped post stream with `inheritQuery` for archives. Excludes portada lead via `$GLOBALS['sp_portada_lead_id']`. Infinite scroll via REST `/stream`. |
| `sp/ad-slot` | Renders configured ad code by position |
| `sp/popular-posts` | Jetpack stats -> ranked popular posts (sidebar version, kept for archives) |
| `sp/newsletter-form` | Subscription form -> REST API -> Resend (supports subheading) |
| `sp/full-archive` | Year/month collapsible post archive |
| `sp/recirculation` | Post-article recommendations using actual `sp/post-card` via `WP_Block` |
| `sp/search-header` | Search results header: count + query echo + relevance/date sort toggle |
| `sp/recirculation-lite` | Lightweight recent posts for non-post pages (e.g., 404). No post context required. |

### Post Card Rendering (format-driven, category-labeled)

Per Figma node `5-278`:

- **Corto**: Category pill + optional square thumbnail (121px) beside headline + excerpt (40 words) + author, relative timestamp. Dense card.
- **Enlace**: Favicon + `-> domain` URL row + headline + full-width OG social image + author, timestamp + excerpt.
- **Largo**: Category pill + author/timestamp (top row) + full-width image + headline + excerpt + "sigue leyendo ->" expand button. Vertical layout.
- **Cobertura**: Same as Largo but with pulsing salmon dot + "en vivo -- se actualiza" in footer.

### Key Includes
| File | Responsibility |
|---|---|
| `includes/post-formats.php` | `sp_formato` taxonomy registration |
| `includes/source-embed.php` | Meta box, async OG fetch, `sp_get_source_data()` |
| `includes/rewrite-rules.php` | `/%category%/%postname%/` permalinks, `/tema/` tag rewrite |
| `includes/rest-api.php` | `POST /signopeso/v1/subscribe`, `GET /signopeso/v1/stream`, `GET /signopeso/v1/recirculation` |
| `includes/newsletter/` | Settings, digest builder, cron, Resend adapter |
| `includes/ad-slots.php` | 3 ad positions with settings page |
| `includes/popular-posts.php` | Jetpack stats query with transient cache |
| `includes/portada.php` | `sp_get_portada_lead()`, `sp_get_tambien_hoy()` helpers |
| `includes/ticker-data.php` | Live FX rates (Banxico SIE) + weather (OpenWeatherMap) for header ticker |

### Post Meta Keys
- `_sp_source_url` — Source URL
- `_sp_source_og_title`, `_sp_source_og_desc`, `_sp_source_og_image_id`, `_sp_source_og_domain` — Cached OG data
- `_sp_source_og_status` — `pending`, `fetched`, or `failed`

## Theme (`sp2`)

### Design System

**Color roles:**
- Primary `#fc9f9f` (salmon) — header gradient, logo accent, link hover, footer accents
- Secondary `#000000` — heading text, body text
- Highlight `#f2ff00` (yellow) — category badges, newsletter bg, keyword highlights
- Card Pink `#f9e9e9` — enlace card background
- Link `#0000ff` — inline links
- Background `#ffffff`, Surface `#ffffff`, Border `#e0e0e0`, Text `#444444`, Muted `#999999`, Faint `#cccccc`

**Typography:**
- Golos Text (UI) — body text, headings, category pills, meta. Default font. Global lowercase transform.
- Noticia Text (Body) — article body prose in single posts
- Lexend Peta (Logo) — wordmark only

**Visual patterns:**
- Light pink gradient header (not dark masthead)
- Hamburger menu (mobile-first) with accordion category/about sections
- Elevator ticker in header: date, FX rate, weather, last update — seamless loop
- Borderless cards — typography IS the design
- Yellow category pill in meta row
- Thin 1px rules as separators
- "sigue leyendo ->" expand buttons on largo/cobertura cards
- "saber +" clustered links section (favicon + title rows)
- Round corners on buttons (border-radius: 10px), square on cards
- Recirculation = the river (same `sp/post-card` block)

### Templates
- `index.html` — Single-column 720px: date-stream (infinite scroll). No portada, no sidebar.
- `single.html` — Centered 720px: category + date + title + author + featured image + content + source-card + tags + ad + recirculation
- `page.html` — Centered 720px
- `archive.html` — Uses `sp/date-stream` with `inheritQuery`
- `search.html` — Search results: search-header (count + sort) + date-stream with inheritQuery
- `404.html` — "Sin señal" heading, search, recent posts via recirculation-lite
- `page-archive-all.html` — Custom template for Archivos page

### Template Parts
- `header.html` — Logo (SIGNOPE$0) + hamburger menu. Accordion nav (categorias + about). Ticker bar. Pink gradient bg.
- `footer.html` — Motto ("economia + tecnologia, corta y digerible, para el resto de nosotros") + copyright + legal links

### URL Structure
- Posts: `/{category}/{slug}/` (e.g., `/tecnologia/apple-lanza-m4-ultra/`)
- Categories: `/{category}/`
- Tags: `/tema/{tag-slug}/`
- Formats: `/formato/{slug}/` (secondary)

## Conventions

- All text in Spanish. Use `date_i18n()` for dates.
- CSS classes use BEM-ish: `.sp-post-card__title`, `.sp-source-card__domain`
- Plugin functions prefixed `sp_`, theme functions prefixed `sp2_`
- No social sharing buttons (YAGNI)
- No dark mode (YAGNI)
- Source attribution always via UTM: `?utm_source=signopeso&utm_medium=referral`

## Docs
- Figma: `https://www.figma.com/design/M5m0Wja4r46RvS2UAZBOiu/Signopeso?node-id=5-278&m=dev`
- Homepage v2 design spec: `docs/superpowers/specs/2026-03-17-signopeso-homepage-v2-design.md`
- Homepage v2 plan: `docs/superpowers/plans/2026-03-17-signopeso-homepage-v2.md`
- UI polish plan: `docs/superpowers/plans/2026-03-18-signopeso-ui-polish.md`
- Template polish design spec: `docs/superpowers/specs/2026-03-18-signopeso-template-polish-design.md`
- Template polish plan: `docs/superpowers/plans/2026-03-18-signopeso-template-polish.md`
