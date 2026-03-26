# SignoPeso UI Polish — Design Spec

## Overview

A comprehensive UI/UX refinement of the SignoPeso homepage v2. Not a redesign — a polish pass that addresses density, visual hierarchy, sidebar bloat, section cohesion, and micro-details. The editorial Sherwood DNA stays; the execution gets sharper with Axios-like scannability and breathing room.

**Direction:** Between Sherwood and Axios. Editorial serious with curation as the soul, but more visual rhythm and air than a pure newsroom layout.

**References:** Sherwood News (typographic craft), Axios (scanning speed, card proportions), Código Morse (curation spirit).

---

## Scope

### In scope
- Portada proportions and density
- Post card rendering refinements (all 4 formats)
- Source card compression
- Sidebar cleanup and simplification
- Header/footer polish
- Transversal polish: typography hierarchy, color discipline, spacing grid, border refinement
- Responsive adjustments

### Out of scope
- "Qué Estamos Leyendo" feature dev (separate project)
- Story clustering (v2 Phase 2)
- Dark mode, social sharing, marginalia (YAGNI per CLAUDE.md)
- New blocks or data model changes

### Removed modules
- **"Cómo Leernos"** — removed from sidebar. Served its educational purpose; now occupies premium space without adding value.
- **"Qué Estamos Leyendo"** — removed from sidebar now. Will be rebuilt as a standalone feature with more prominence in a separate project.
- **Popular strip (`sp/popular-strip`)** — removed from homepage. Doesn't fit the Axios-like curation model. The block remains available for other templates but is no longer in `index.html`.

---

## Section 1: Portada

### Lead story

| Property | Current | New |
|---|---|---|
| Image aspect ratio | 2:1 | **3:2** — reduces height ~30%, image accompanies rather than dominates |
| Headline size | 3rem | **2.5rem** Newsreader 700 — tighter, more "front page" tension |
| Headline line-height | 0.92 | **0.90** |
| Deck | 1.05rem, unclamped | **0.95rem**, max 2 lines (`-webkit-line-clamp: 2`) |

### "También Hoy"

| Property | Current | New |
|---|---|---|
| First item image | 16:9 thumbnail | **Removed** — all items are pure headlines for dense scanning contrast against the visual lead |
| Title size | 1.05rem Newsreader 600 | **1rem** Newsreader 600 |
| Item separators | None (stacked) | **1px border-bottom** between items |
| Section label | small-caps salmón | Unchanged |

**Net effect:** Portada is more compact (less scroll), better visual-textual balance, También Hoy reads as a proper headline block.

---

## Section 2: Post Cards (River)

### Largo (and Cobertura)

The biggest visual change. Largo cards shift from full-width hero image to a horizontal layout.

| Property | Current | New |
|---|---|---|
| Image placement | Full-width 21:9 above title | **Right-aligned thumbnail**, 280px wide, 3:2 aspect ratio. Title + excerpt on the left. |
| No-image fallback | N/A | Full-width text-only card (no empty space) |
| Title size | 1.75rem | **1.4rem** Newsreader 700 — still the most prominent card, but doesn't shout |
| Title line-height | 0.95 | **0.95** (unchanged) |
| Excerpt | 0.95rem, unclamped | **0.9rem**, max 2 lines (`line-clamp: 2`) — enough to intrigue, not to tell all |
| "Sigue leyendo →" | Prominent | **Datatype 0.75rem salmón** — present but subdued |
| Cobertura | Pulsing dot + "En vivo" | Unchanged behavior, same new layout |

**Layout structure (largo with image):**
```
┌─────────────────────────────────────────────┐
│ Author (Datatype)              Timestamp     │
│ [CATEGORY badge yellow]                      │
│                                              │
│ Headline Newsreader 700     ┌──────────────┐ │
│ 1.4rem tight leading        │              │ │
│                             │  Image 3:2   │ │
│ Excerpt text Inter 0.9rem   │  280px wide  │ │
│ max two lines clamped...    │              │ │
│                             └──────────────┘ │
│ Sigue leyendo → (subtle)                     │
├─────────────────────────────────────────────┤
```

**Layout structure (largo without image):**
```
┌─────────────────────────────────────────────┐
│ Author (Datatype)              Timestamp     │
│ [CATEGORY badge yellow]                      │
│                                              │
│ Headline Newsreader 700 1.4rem               │
│                                              │
│ Excerpt text Inter 0.9rem max two lines...   │
│                                              │
│ Sigue leyendo → (subtle)                     │
├─────────────────────────────────────────────┤
```

### Corto

Stays compact but gains enough visual weight to feel like content, not metadata.

| Property | Current | New |
|---|---|---|
| Title weight | Newsreader 600 | **Newsreader 700** — more punch |
| Title size | 1rem | 1rem (unchanged) |
| Teaser line | None | **Optional 1-line teaser**: Inter 0.85rem muted, max 80 chars (`line-clamp: 1`). Uses post excerpt if available; omitted if empty. |
| Padding | 10px 0 | **16px 0** (aligns to 8px grid) |

### Enlace

The source card gets radically compressed so the curator's editorial voice (the post title) is the protagonist.

| Property | Current | New |
|---|---|---|
| Post title | Newsreader 600, 1.1rem | Unchanged |
| Source card layout | Full high-res (16:9 image) or low-res (text + 100px thumb) — both large | **Always horizontal**: 80px square OG thumbnail (left) + OG title + domain (right). Single line of context. |
| Source card excerpt | OG excerpt, 2-line clamp | **Removed** — domain + OG title + "↗" is sufficient |
| Source card size | ~150-250px tall | **~60-70px tall** — a visual footnote, not a competing card |
| Source card border | 1px, hover yellow | Border **`#F0F0F0`**, background **`#FAFAFA`** — feels like a citation block |
| Hover | Border → yellow | Unchanged on the enlace card itself; source card hover removed (it's subordinate) |

**Source card new structure:**
```
┌──────────────────────────────────────┐
│ ┌────────┐  OG Title (Newsreader)    │
│ │  80px  │  ↗ techcrunch.com         │
│ │  thumb │  (Datatype salmón)        │
│ └────────┘                           │
└──────────────────────────────────────┘
```

### River rhythm

| Property | Current | New |
|---|---|---|
| Card spacing | Inconsistent (10px, 14px, 22px) | **Uniform 24px** between all cards |
| Date headers | Small-caps salmón, minimal separation | **`border-top: 2px solid #E0E0E0`** above + **32px margin-top** — clear "new day" break |

---

## Section 3: Sidebar

### Modules removed
- **"Cómo Leernos"** — gone
- **"Qué Estamos Leyendo"** — out of scope (future feature dev)

### Modules kept (3 total)

**1. Search**
- Placeholder shortened: "Buscar"
- Input more slender (padding: 8px 12px instead of 10px)
- Visible magnifying glass icon (pseudo-element or inline SVG)

**2. Newsletter (yellow bg)**
- Heading: "Recibe #$P" — Newsreader 700, 1.1rem
- Subheading: single line, Datatype 0.8rem
- **Input + button on a single horizontal row** (flex) instead of vertical stack
- Button: "Suscríbete" Datatype 0.75rem, or just "→"

**3. Ad slot**
- Falls to bottom
- Fallback placeholder more discreet: lighter text, dashed border instead of solid

### Sticky behavior
- `position: sticky; top: 24px` — sidebar accompanies scroll instead of disappearing.

---

## Section 4: Header

**Mostly preserved** — it's the strongest part of the current design.

| Change | Detail |
|---|---|
| Secondary nav (Archivos, Acerca) | **Moved to footer**. Header becomes logo + categories only. Cleaner, more focused. |
| Nav item spacing | Uniformed gaps between category links |
| Ad slot placeholder | **max-height cap** + more discreet fallback (no ugly empty bordered box) |

---

## Section 5: Footer

| Property | Current | New |
|---|---|---|
| Brand section | Logo + tagline as vertical block | **Single line**: logo + tagline inline. Tagline in Datatype. |
| Links | 2 columns, cramped | 2 columns with **more gap** between columns and items. Now includes Archivos + Acerca (from header). |
| Column headers | Small-caps muted | Unchanged |
| Newsletter CTA | Yellow box, functional | Stays — reinforces funnel at page-end context. Polished to match sidebar version. |
| Copyright row | Inline with content | **Separated by `border-top: 1px solid rgba(255,255,255,0.1)`**. More breathing room. |
| Spacing | Tight, irregular | **More padding vertical (48px top, 32px bottom)**, more gap between columns |

---

## Section 6: Transversal Polish

### Typography discipline

| Element | Current | New |
|---|---|---|
| Category pills | Datatype (monospace) | **Inter 600 uppercase 0.65rem, tracking 0.05em** — distinguishes labels from timestamps |
| Datatype usage | Everywhere (timestamps, authors, categories, rankings, domains) | **Reserved for metadata only**: timestamps, author names, domains, "↗", pagination numbers. Categories move to Inter. Author names stay Datatype — they are metadata. |
| Title line-heights | Inconsistent across cards | **Consistent**: 0.90 for portada lead, 0.95 for largo, 1.25 for corto/enlace |

### Color discipline

| Element | Current | New |
|---|---|---|
| Salmón usage | Section labels, link hover, live dots, rankings, blockquotes, source domains — diluted by overuse | **Reserved for**: section labels, live indicator, hover states only |
| Normal link color | Mixed | **`#1A1A1A`** with underline on hover — salmón for hover only |
| Yellow badges | All largo/cobertura cards | **Only largo and cobertura**. Cortos and enlaces show category as plain colored text — creates instant hierarchy: yellow badge = depth content |

### Spacing grid

| Current | New |
|---|---|
| Arbitrary values: 10px, 14px, 18px, 20px, 22px | **8px base unit**. All spacing aligns to multiples: 8, 16, 24, 32, 40, 48. |

### Borders and surfaces

| Element | Current | New |
|---|---|---|
| Enlace card border | `#E0E0E0` | **`#EBEBEB`** — more subtle |
| Source card (embedded) | Same border weight as enlace | **Border `#F0F0F0`, background `#FAFAFA`** — visually subordinate, reads as citation |
| Square corners | Throughout | **Unchanged** — SignoPeso DNA |

---

## Responsive Adjustments

| Breakpoint | Change |
|---|---|
| ≤1024px | Portada grid → single column. Sidebar below river. Footer stacks. |
| ≤768px | **Largo cards**: horizontal layout (image right) → vertical (image top 3:2, text below). Cortos and enlaces unchanged (already compact). Portada lead title → 1.8rem. |
| Touch (hover: none) | Active states use highlight-pale tint for tap feedback |

---

## Files Affected

### Theme (`wp-content/themes/signopeso-theme/`)
| File | Changes |
|---|---|
| `assets/css/signopeso.css` | Major — all sections get spacing/typography/color refinements |
| `templates/index.html` | Remove `sp/popular-strip` block |
| `parts/header.html` | Remove secondary nav links |
| `parts/sidebar.html` | Remove "Cómo Leernos" block, remove "Qué Estamos Leyendo" block |
| `parts/footer.html` | Add secondary nav links, refine spacing/layout |
| `theme.json` | No changes expected (tokens remain the same) |

### Plugin (`wp-content/plugins/signopeso-core/`)
| File | Changes |
|---|---|
| `blocks/post-card/render.php` | Largo layout restructure (horizontal image), corto teaser line, enlace source card compression |
| `blocks/portada/render.php` | Lead image 3:2, headline size, deck clamp, También Hoy image removal + separators |
| `blocks/source-card/render.php` | Radical simplification — always horizontal, 80px thumb, no excerpt |
| `blocks/newsletter-form/render.php` | Horizontal input+button layout |
| `blocks/popular-strip/render.php` | No code changes (block remains, just removed from index.html template) |

---

## Success Criteria

1. **Density**: Homepage shows noticeably more content above the fold vs current (portada 3:2 + compressed cards)
2. **Hierarchy**: A reader can identify the most important story and scan secondary headlines in <3 seconds
3. **Rhythm**: Scrolling through the river feels like a deliberate editorial cadence, not a random pile of cards
4. **Cohesion**: The page reads as one designed surface from header to footer
5. **Polish**: No inconsistent spacing, orphaned borders, or competing visual weights
