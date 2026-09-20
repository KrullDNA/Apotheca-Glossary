# Apotheca® Glossary, project brief

WordPress plugin, version 1.0.0
Client: Apotheca® · 20 September 2026 · Krull Design & Advertising

| Field | Detail |
|---|---|
| Plugin name | Apotheca® Glossary |
| Slug | `apotheca-glossary` |
| Prefix | `apglos_` for functions, hooks, options and handles |
| Text domain | `apotheca-glossary` |
| First version | 1.0.0 |

---

## Purpose

A searchable, filterable glossary of cosmetic terminology for apothecacosmetics.com, aimed at a reader who is ingredient-literate but does not work in cosmetics. She meets a word like TEWL or occlusive in a review, on a label or in one of our own posts, and she wants a plain answer without leaving the site.

The plugin stores the terms, gives her three ways to find one, and quietly links every mention of a glossary term in our blog posts back to its definition. The glossary doubles as an SEO asset: 172 definition pages, each answering a question people actually type into a search bar, all linking inward to the rest of the site.

## Scope

| Question | Answer |
|---|---|
| Front end or admin | Both |
| WooCommerce dependency | No |
| External JS libraries | None. Vanilla JS, no jQuery, no build step |
| Database storage | Custom post type plus custom taxonomy. No custom tables |
| Settings page | Yes, under the Glossary menu |
| Elementor | One Atomic widget, registered into KDNA Tools |
| Shortcode | `[apotheca_glossary]` as a non-Elementor fallback |

## How it works for the visitor

One page, three ways in, no page reloads. All filtering happens in the browser.

- **Search field.** Type-as-you-go, matching term, synonyms and definition text, with matched characters highlighted.
- **A to Z plus 0-9 bar.** Empty letters hidden or greyed, set in the widget. J, Q, X, Y and Z are currently empty. 0-9 holds one entry, "1% line".
- **Category dropdown.** The ten categories, plus All.

Filters combine rather than compete. A Clear all link resets. Every state is written to the URL so a filtered view can be linked and shared.

### How a term displays

No accordions. Every definition is on the page already. Term in bold on its own line, definition beneath, gap before the next entry.

| Element | Default | Notes |
|---|---|---|
| Term | On, always | Bold by default, fully styleable |
| Definition | On, always | Runs through `wptexturize` so apostrophes curl |
| Category label | Off | Switchable, links to that filtered view |
| Also known as | Off | Synonyms, comma separated, styleable prefix label |
| Related terms | Off | Linked, jumping to that entry on the page |
| Letter headings | On | A, B, C section markers |
| Result count | On | "Showing 31 of 172 terms" |

### The ten categories

Skin anatomy (22) · Moisture and barrier (13) · How formulas work (25) · Actives and ingredients (31) · Sun protection (13) · Label and packaging (13) · Claims and certification (25) · Textures and formats (8) · Skin concerns (16) · Routine and habits (6)

## Data model

Custom post type, not a custom table. It buys a real editor per term, a URL per definition, revision history and search visibility.

| Object | Detail |
|---|---|
| Post type | `apglos_term`, public, archive, slug `/glossary/` |
| Post title | The term |
| Post content | The definition |
| Taxonomy | `apglos_category`, hierarchical, slug `/glossary-category/` |
| Meta `also_known_as` | Pipe-delimited synonyms, used by search and linkify |
| Meta `related_terms` | Pipe-delimited term names, resolved to links on render |
| Meta `first_char` | Cached first character, uppercase, digits normalised to `0-9` |

## The CSV

`apotheca-glossary-terms.csv`, 172 rows, UTF-8, no BOM. All fields quoted, pipe as the internal delimiter.

| Column | Required | Notes |
|---|---|---|
| `term` | Yes | Post title. Unique |
| `slug` | Yes | Post slug. Unique |
| `category` | Yes | Matched to taxonomy, created if missing |
| `also_known_as` | No | Pipe delimited |
| `related_terms` | No | Pipe delimited, must match other `term` values exactly |
| `definition` | Yes | Plain text, straight apostrophes |

**Two things the importer must handle**

1. Strip a BOM if present. Excel adds one on UTF-8 save and it corrupts the first column name.
2. Store straight apostrophes and let WordPress curl them on output. Converting at import time double-curls.

**Importer behaviour**

- Admin page under Glossary, nonce protected, `manage_options` only
- Preview before committing: create / update / skip counts
- Match on slug. Exists, update. Does not, create. Never silently duplicate
- Three modes: create only, create and update, replace all (double confirmation)
- Validation report for `related_terms` that do not resolve
- Download sample CSV, exporting the current glossary in the same format

## Elementor widget

One widget, not several, because search, filters and results are a single linked interaction. Atomic markup, `has_widget_inner_wrapper()` implemented, single wrapper div, no CSS targeting `.elementor-widget-container`.

**Content controls.** Show/hide each of: search field, A to Z bar, 0-9 button, category dropdown, result count, letter headings, category label, also known as, related terms. Empty letters hide or grey. Editable placeholder, prefix labels and empty-results message. Limit to one category. Preview state (editor only): default, searching, no results.

**Style sections**, every dimensional control responsive:

| Section | Controls |
|---|---|
| Wrapper | Background classic and gradient, border, radius, box shadow, padding, margin |
| Search field | Typography, text, placeholder, background, border, radius, padding, min height, focus state |
| Letter buttons | Typography, gap, padding, radius, border, plus normal / hover / active / disabled |
| Category dropdown | Typography, text, background, border, radius, padding, min height, focus |
| Clear all link | Typography, colour, hover, spacing |
| Result count | Typography, colour, alignment, spacing |
| Letter headings | Typography, colour, background, border bottom, padding, spacing |
| Term | Typography, colour, spacing below |
| Definition | Typography, colour, max width, spacing below |
| Category label | Typography, colour, background, padding, radius, hover |
| Also known as | Prefix and value typography and colour, spacing |
| Related terms | Prefix, link typography, link colour, hover, separator, spacing |
| Entry spacing | Gap between entries, divider on/off, colour, width, style |
| Search highlight | Background and text colour for matched characters |
| Empty state | Typography, colour, alignment, padding |

## Linkify

Largest SEO gain in the build, and the easiest to get wrong, so it runs with guard rails.

- First occurrence only, per post
- Cap per post, default 3, adjustable
- Longest match wins ("hyaluronic acid" beats "acid")
- Never inside headings, existing links, alt text, shortcodes, code blocks or the glossary itself
- Case insensitive match, original casing preserved
- Opens in a new tab: `target="_blank"` with `rel="noopener"`, so the reader keeps her place in the article. Settings-controlled, defaults to on
- A visually hidden "opens in a new tab" note inside the link for screen readers, plus an optional external-link icon
- Per-post opt out, plus a global post type exclusion list
- Runs on `the_content` at render time, never written back into the stored post
- Cached in a transient, cleared on term save or delete
- **Ships switched off.** Turn it on once the glossary is populated and a few posts are live

## Search visibility

- `DefinedTerm` and `DefinedTermSet` schema on the glossary page and each term
- Indexable URL per term at `/glossary/term-slug/`
- Filtered views canonical to the unfiltered glossary
- Yoast compatible, breadcrumbs on single term pages

## File structure

```
apotheca-glossary/
├─ apotheca-glossary.php          main file, constants, includes, hooks
├─ includes/
│  ├─ class-post-type.php         CPT and taxonomy registration
│  ├─ class-meta.php              meta boxes and meta registration
│  ├─ class-importer.php          CSV parse, preview, import, export
│  ├─ class-renderer.php          shared markup for shortcode and widget
│  ├─ class-shortcode.php         [apotheca_glossary]
│  ├─ class-linkify.php           the_content filter and guard rails
│  ├─ class-schema.php            DefinedTerm output
│  └─ class-settings.php          options page
├─ elementor/
│  ├─ class-elementor-loader.php  category registration, widget loading
│  └─ widgets/
│     └─ class-glossary-widget.php
├─ admin/
│  ├─ import-page.php
│  └─ admin-style.css
├─ assets/
│  ├─ js/apotheca-glossary.js     filtering, search, URL state
│  └─ css/apotheca-glossary.css   structural CSS only, styling via Elementor
├─ data/
│  └─ apotheca-glossary-terms.csv sample and seed data
└─ README.txt
```

## Build stages

### Stage 1, Foundation, v1.0.0
CPT, taxonomy, meta fields, admin columns, ten categories seeded on activation. Testable by adding a term by hand.

> Build Stage 1 of the Apotheca Glossary plugin per the brief: register the `apglos_term` custom post type and `apglos_category` taxonomy, register the three meta fields with sanitisation, add a meta box for also known as and related terms, add sortable admin columns for category and first character, and seed the ten categories on activation. Deliver as `apotheca-glossary-1.0.0.zip`.

### Stage 2, CSV importer, v1.1.0
Upload screen, preview table, three modes, validation report, sample export. Testable by importing the supplied 172-row file.

> Build Stage 2, the CSV importer: admin page under the Glossary menu, nonce protected and restricted to `manage_options`, file upload, BOM stripping, preview table showing create, update and skip counts, three modes (create only, create and update, replace all with double confirmation), a validation report for unresolved `related_terms`, and a Download sample CSV export of the current glossary. Deliver as `apotheca-glossary-1.1.0.zip`.

### Stage 3, Front end and shortcode, v1.2.0
The glossary itself via shortcode, all three filters working, URL reflecting state.

> Build Stage 3, the front end: a renderer class producing the glossary markup, the `[apotheca_glossary]` shortcode, the search field with match highlighting, the A to Z and 0-9 bar with empty letters handled, the category dropdown, result count, letter headings, Clear all, and URL state so a filtered view can be linked. Vanilla JS, no jQuery, scripts enqueued only where the glossary renders. Deliver as `apotheca-glossary-1.2.0.zip`.

### Stage 4, Elementor widget, v1.3.0
Everything from Stage 3 as an Atomic widget in KDNA Tools, with every toggle and style control.

> Build Stage 4, the Elementor widget: one Atomic widget registered into the KDNA Tools category, `has_widget_inner_wrapper()` implemented, single wrapper div, no CSS targeting `.elementor-widget-container`. Include every content toggle and every style section in the brief, all dimensional controls responsive, hover, focus, active and disabled states where applicable, and an editor-only Preview state control covering default, searching and no results. Deliver as `apotheca-glossary-1.3.0.zip`.

### Stage 5, Linkify and schema, v1.4.0
Automatic in-content linking with guard rails, plus schema and canonicals.

> Build Stage 5: the linkify filter on `the_content` with first occurrence only, a configurable per-post cap defaulting to three, longest match wins, exclusions for headings, existing links, alt text, shortcodes, code blocks and the glossary post type itself, a per-post opt-out checkbox, a global post type exclusion list, transient caching cleared on term save or delete, `target="_blank"` with `rel="noopener"` and a visually hidden "opens in a new tab" note (both settings-controlled, defaulting to on), and it must ship switched off by default. Add `DefinedTerm` and `DefinedTermSet` schema and canonical handling for filtered views. Deliver as `apotheca-glossary-1.4.0.zip`.

### Stage 6, Settings and polish, v1.5.0
Settings page, accessibility pass, README, final packaging.

> Build Stage 6: the settings page collecting the linkify options and the glossary slug, an accessibility pass (keyboard navigation on the letter bar, `aria-live` on the result count, visible focus states, correct roles), the README.txt, and the final packaging check that the version header, the version constant and the zip filename all agree. Deliver as `apotheca-glossary-1.5.0.zip`.

## Testing checklist

- [ ] Plugin activates with no errors, version on the Plugins screen matches the zip filename
- [ ] The ten categories appear after activation
- [ ] A term added by hand saves, including also known as and related terms
- [ ] The supplied CSV imports, reporting 172 created and 0 skipped
- [ ] Re-importing the same file reports 172 updated and 0 created, no duplicates
- [ ] Search, A to Z and category dropdown each filter correctly, and combine correctly
- [ ] Empty letters behave as set, hidden or greyed
- [ ] A filtered view can be copied from the address bar and reopened in the same state
- [ ] Every widget toggle switches its element on and off on the front end
- [ ] Every style control visibly changes what it claims to
- [ ] Preview state renders in the editor and has no effect on the front end
- [ ] Linkify links the first mention only, respects the cap, skips headings and existing links
- [ ] Linkify links open in a new tab, with the original article still open behind
- [ ] Switching linkify off restores the original post text exactly
- [ ] A single term page loads at `/glossary/term-slug/` with valid schema
- [ ] No JS console errors, no PHP warnings or notices
- [ ] Works on a standard theme with Elementor deactivated, via the shortcode
- [ ] Mobile: letter bar wraps or scrolls without breaking the layout

## Notes and open items

**Regulatory.** The glossary defines condition names including melasma, malassezia and hyperpigmentation. Definitions are explanatory, not claims, but in Australia a product's presentation contributes to whether it is assessed as a cosmetic or a therapeutic good. Keep the glossary on its own page, keep product links out of the entries, and have the wording checked before launch.

**Sunscreen entries.** The TGA opened a consultation in March 2026 on changing how sunscreens are regulated in Australia. The primary and secondary sunscreen definitions reflect the position as at September 2026 and should be re-checked before the glossary goes live.

**Content.** 172 terms is a strong launch set, not a finished one. J, Q, X, Y and Z are currently empty. Worth adding over time rather than padding now.
