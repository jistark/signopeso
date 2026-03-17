# SignoPeso ($P) — Design Spec

## Overview

SignoPeso ($P) is the spiritual successor to Código Morse (codigomorse.net, 2007–2011), a Chilean Spanish-language tech blog. $P evolves the original "tecnología, corta y digerible" philosophy into economy + technology coverage, maintaining the DNA of short, digestible, well-differentiated content formats.

**Environment:** WordPress.com Business
**Architecture:** Block theme (FSE) + companion plugin
**Language:** Spanish (Chile)

### Design Lineage

$P draws selectively from three predecessors. What we take and what we leave:

**From Código Morse (codigomorse.net, 2007–2011) — the soul:**
- Content philosophy: "corto y digerible, para el resto de nosotros"
- Post format labels (Corto, Enlace, Largo) as immediate visual differentiators
- Chronological stream grouped by date — the core homepage pattern
- Source attribution: "Ir a la fuente →"
- Text-first posts: images are content, not decoration. No mandatory thumbnails.
- Homepage is stream-focused, not magazine/grid

**From Reviú (reviu.tv, ~2016–2018) — selective UI patterns:**
- Take: `/%category%/%postname%/` URL structure (SEO)
- Take: Dual navigation — primary nav by content categories + secondary nav for static pages
- Take: Subtle card definition — posts as section boxes with `surface` (#fff) background against page `background` (#fafafa) for visual separation
- Leave: Hero featured area with giant cards — doesn't fit stream philosophy
- Leave: Mandatory thumbnails on every post card — CM was text-first
- Leave: Sticky social sharing sidebar
- Leave: Product scorebox (YAGNI)

**From Sherwood News (sherwood.news) — editorial polish:**
- Take: Bold serif headlines for editorial character
- Take: Yellow accent for highlights/destaques
- Take: Generous whitespace and breathing room
- Leave: Magazine grid layout
- Leave: Neon intensity — $P's salmon is warmer and more approachable

## Content Philosophy

From the original CM "About":

> Sabemos que tu tiempo es oro. Todos los tipos de contenido que publicamos son altamente identificables y discriminables, y están escritos de manera simple y directa (con una dosis justa de humor) y son muy cortos, para que cualquiera pueda entenderlos, rápida y divertidamente.

$P preserves this philosophy. Content is short and digestible ("para el resto de nosotros"), with clearly differentiated post formats and a focus on economy + technology.

## Deliverables

### 1. `signopeso-theme` — Block Theme (FSE)

Presentation only. No business logic.

#### Design Tokens (`theme.json`)

| Token | Value | Usage |
|---|---|---|
| `primary` | `#F06B6B` (salmon) | Header bar, CTAs, Largo label, link hover |
| `secondary` | `#1A1A1A` | Body text, logo `$` symbol |
| `highlight` | `#FFEB3B` (yellow) | Text highlights, special badges |
| `background` | `#FAFAFA` | Page background |
| `surface` | `#FFFFFF` | Cards, source embed, inputs |
| `border` | `#E0E0E0` | Separators, card borders |
| `muted` | `#999999` | Bylines, dates, metadata |

#### Typography

- **Headlines:** Serif bold (Playfair Display / Lora / similar). Weight 700–800, tight tracking. Editorial character à la Sherwood News.
- **Body:** Sans-serif (Inter / system UI stack). Legible, neutral.
- **Meta:** Monospace or condensed sans for dates, labels, bylines. Uppercase, wide letter-spacing.

#### Templates

#### Visual Patterns

- **Section boxes:** Post cards use `surface` (#fff) background against page `background` (#fafafa) for subtle card definition. Same treatment for source embed cards, newsletter widget, and sidebar sections. No heavy shadows — just the background contrast and optional 1px `border` color.
- **Text-first stream:** Posts in the homepage stream do NOT require thumbnails. Images appear only when they're part of the content (inline in the post body). This preserves CM's "the writing is the content" ethos.
- **Generous whitespace:** Padding and gaps between elements create breathing room.

**`index.html` (Homepage):**
- Salmon header bar with `#$` logo, nav (Archivos, Acerca, Newsletter), search
- Two-column layout: stream (2/3) + sidebar (1/3)
- Stream uses `sp/date-stream` block (posts grouped by date headers)
- Each post rendered via `sp/post-card` block (format-aware) inside section boxes
- Sidebar: newsletter form, popular posts, ad slot

**`single.html` (Single post):**
- Salmon header
- Centered content column (~720px max-width), no sidebar
- Date + format label at top
- Headline (serif bold)
- Byline
- Post content
- Source card (if source URL exists)
- Tags
- Ad slot below content
- Comments

**`page.html` (Static pages):**
- Same layout as single (centered, no sidebar)
- Used for: Acerca, Publicidad, Contacto, Archivos, Tips

**`archive.html`:**
- Same as page layout
- Query loop filtered by taxonomy/tag

**`404.html`:**
- Centered, minimal

#### Template Parts

- `header.html` — Salmon background bar, `#$` logo, primary nav (categories: Tecnología, Economía, etc.), secondary nav (Archivos, Acerca, Newsletter), search block
- `footer.html` — `#$` · Acerca · Publicidad · RSS · CC BY-NC-SA
- `sidebar.html` — Newsletter form + popular posts + ad slot

#### Responsive Behavior

- **Desktop (>1024px):** Stream 2/3 + sidebar 1/3
- **Tablet (768–1024px):** Stream full-width, sidebar below
- **Mobile (<768px):** Vertical stack, header compacts

### 2. `signopeso-core` — Plugin

All functionality that makes $P be $P. Portable across themes.

#### File Structure

```
signopeso-core/
├── signopeso-core.php              ← Bootstrap, activation hooks
├── includes/
│   ├── post-formats.php            ← sp_formato taxonomy
│   ├── source-embed.php            ← Meta box, async OG fetch, image sideload, cache
│   ├── popular-posts.php           ← Jetpack Stats query + transient cache
│   ├── ad-slots.php                ← 3 positions, settings, toggle
│   ├── rewrite-rules.php           ← /tema/ tag rewrite, /%category%/%postname%/ permalink setup
│   ├── rest-api.php                ← REST endpoints (e.g., /signopeso/v1/subscribe)
│   └── newsletter/
│       ├── settings.php            ← Admin page: on/off, frequency, time, API config
│       ├── digest-builder.php      ← HTML generation from recent posts
│       ├── cron.php                ← WP-Cron scheduling and dispatch
│       └── adapters/
│           ├── sender-interface.php    ← Newsletter sender interface (digest dispatch)
│           ├── subscriber-interface.php ← Newsletter subscriber interface (add contacts)
│           └── resend.php              ← Resend API adapter implementing both (v1)
└── blocks/
    ├── post-card/                  ← Format-aware post rendering
    │   ├── block.json
    │   └── render.php
    ├── date-stream/                ← Date-grouped query stream
    │   ├── block.json
    │   └── render.php
    ├── source-card/                ← OG preview card
    │   ├── block.json
    │   └── render.php
    ├── popular-posts/              ← Sidebar popular posts
    │   ├── block.json
    │   └── render.php
    ├── newsletter-form/            ← Subscription form
    │   ├── block.json
    │   ├── render.php
    │   └── view.js
    ├── ad-slot/                    ← Ad position renderer
    │   ├── block.json
    │   └── render.php
    └── full-archive/               ← Year/month grouped post archive
        ├── block.json
        └── render.php
```

#### Component Details

##### Post Formats (`sp_formato` Taxonomy)

Custom taxonomy registered on `init` with 4 pre-populated terms:

| Format | Slug | Behavior |
|---|---|---|
| Corto | `corto` | Standard short post, 1–3 paragraphs. No "read more". |
| Enlace | `enlace` | Requires source URL. Shows source card with OG tags. Prominent "Ir a la fuente →". |
| Largo | `largo` | Uses `<!--more-->` for excerpt in stream. Shows "Sigue leyendo →". |
| Cobertura | `cobertura` | Like Largo but with distinct visual label. For live event coverage. |

- Radio buttons in editor (single selection only)
- Default: `corto` when none selected
- Format archives available at `/formato/{slug}/` but NOT used in permalinks

##### Permalink Structure

SEO-friendly permalinks using WordPress categories (not `sp_formato`):

- **Post permalink:** `/%category%/%postname%/` (e.g., `/tecnologia/apple-lanza-m4-ultra/`, `/economia/banco-central-baja-tasa/`)
- **Category archive:** `/{category}/` (e.g., `/tecnologia/`, `/economia/`, `/videojuegos/`)
- **Tag archive:** `/tema/{tag-slug}/` — legacy CM URL pattern via rewrite rule
- **Format archive:** `/formato/{slug}/` — available but secondary to categories

Categories are the primary content taxonomy for navigation and URLs. Example categories: Tecnología, Economía, Videojuegos, Apps, Telcos. Formats (`sp_formato`) classify the *type* of post (Corto, Enlace, Largo, Cobertura), not the *topic*.

##### Source Embed System

1. **Meta box** in post editor: URL input field "Fuente original"
2. **On `save_post`:** Schedules async fetch via `wp_schedule_single_event()`
3. **Async fetch job:**
   - `wp_remote_get()` to source URL
   - Parse `og:title`, `og:description`, `og:image`, `og:site_name`
   - Extract clean domain (e.g., `bcentral.cl`)
   - Sideload OG image to Media Library via `media_sideload_image()`
   - Store in post meta: `_sp_source_url`, `_sp_source_og_title`, `_sp_source_og_desc`, `_sp_source_og_image_id`, `_sp_source_og_domain`
4. **Cache:** OG data persisted in post meta indefinitely. "Refresh" button in meta box to re-fetch.
5. **Render:** `sp/source-card` block reads cached meta and renders preview card. Appends `?utm_source=signopeso&utm_medium=referral` to outbound URL.
6. **Fallback:** If no OG tags available, renders simple link: "Ir a la fuente → dominio.cl"

##### `sp/post-card` Block

Server-rendered block (render callback in PHP). Used inside the date-stream.

- Reads current post's `sp_formato` term
- Renders appropriate structure:
  - **Corto:** Format label + headline + excerpt + byline + source link (if exists)
  - **Enlace:** Format label + headline + excerpt + source card (OG embed) + byline
  - **Largo:** Format label + headline + excerpt + "Sigue leyendo →" + byline
  - **Cobertura:** Like Largo with distinct "Cobertura" label styling

##### `sp/date-stream` Block

Server-rendered block that replaces the standard Query Loop.

- Runs `WP_Query` for latest posts (paginated)
- Groups posts by publication date
- Renders date headers between groups (e.g., "Lunes 17 de Marzo, 2025")
- Each post rendered via `sp/post-card` block's render function
- Supports pagination

##### Newsletter Digest System

**Settings page** (Ajustes > SignoPeso Newsletter):
- Toggle on/off
- Frequency: daily / weekly (Monday)
- Send time (hour selector, site timezone)
- Resend API key
- Resend Audience ID
- From name / email
- Test send button

**Digest builder:**
- Queries posts published since last send
- Groups by format: Largos first, then Cortos, then Enlaces
- Generates inline-styled HTML (email-client compatible)
- Branded: salmon header, serif headlines, source cards simplified

**Cron:**
- Registers `sp_newsletter_cron` via `wp_schedule_event()`
- At configured time: builds digest, dispatches via Resend adapter
- Logging: last send timestamp, status, post count

**Newsletter form block (`sp/newsletter-form`):**
- Renders subscription widget for sidebar
- Form submits to a WP REST API endpoint (`/wp-json/signopeso/v1/subscribe`) registered by the plugin
- The REST endpoint server-side proxies the request to Resend Audiences API using the stored API key (key never exposed to the browser)
- Configurable via block attributes: CTA text, placeholder text

**Adapter pattern — two interfaces for distinct responsibilities:**
- `SP_Newsletter_Sender` interface: `send_digest($html, $subject)` — used by the cron system to dispatch digests
- `SP_Newsletter_Subscriber` interface: `add_subscriber($email)` — used by the REST endpoint to add contacts
- `SP_Resend_Adapter` implements both interfaces for Resend API (v1)
- New services added by implementing the interfaces

##### Popular Posts (`sp/popular-posts` Block)

- Queries Jetpack Stats via `stats_get_csv()` for most-viewed posts
- Configurable period: 24h, 7 days, 30 days (block attribute)
- Configurable count: default 5
- Transient cache: 1 hour
- Fallback (no Jetpack): `WP_Query` with `orderby => comment_count`

##### Ad Slots

- 3 registered positions: `header-leaderboard`, `single-below-content`, `sidebar`
- Settings page: HTML/JS textarea per position, toggle on/off per position
- `sp/ad-slot` block: select position from dropdown, renders the configured code

## Static Pages

- **Acerca** — About $P, philosophy, team
- **Publicidad** — Advertising policy (inherited from CM's transparency approach)
- **Contacto** — Contact form
- **Archivos** — Post archive page using a custom `archive-all.html` template. Renders a year-by-month grouped list of all posts (title + format label + date), collapsed by year with the current year expanded. Uses a server-rendered block `sp/full-archive` from the plugin.
- **Tips** — Submit news/tips

## Third-Party Plugin Dependencies

| Plugin | Purpose | Notes |
|---|---|---|
| RankMath | SEO | User choice |
| Jetpack | Stats for popular posts | Included in WordPress.com |

## Explicitly Out of Scope (YAGNI)

- Dark mode / theme toggle
- Multi-language support
- Custom comment system (use WordPress core)
- Social sharing buttons
- Related posts algorithm
- Multiple newsletter adapter implementations beyond Resend
