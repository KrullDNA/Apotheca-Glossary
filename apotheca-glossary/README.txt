=== Apotheca Glossary ===
Contributors: krulldna
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A searchable, filterable glossary of cosmetic terminology, with automatic in-content linking of glossary terms in your posts.

== Description ==

Apotheca Glossary gives your visitors a plain answer to a word they have met on a label, in a review or in one of your posts, without leaving the site. It stores each term as its own page, gives the reader three ways to find one, and can quietly link every first mention of a glossary term in your blog posts back to its definition.

The glossary doubles as an SEO asset: one indexable definition page per term, each linking inward to the rest of the site, with structured data so search engines understand them as definitions.

**For the visitor**

One page, three ways in, no page reloads. All filtering happens in the browser.

* A type-as-you-go search that matches the term, its synonyms and the definition text, with the matched characters highlighted.
* An A to Z plus 0 to 9 bar, with empty letters greyed out or hidden.
* A category dropdown.

Filters combine rather than compete, a Clear all link resets them, and every filtered view is written to the URL so it can be copied from the address bar and shared.

**For the editor**

* A custom post type, so every term has a real editor, its own URL, revision history and search visibility.
* A CSV importer with a preview before anything is written, three modes (create only, create and update, replace all), a validation report, and a matching export.
* An Elementor widget in the KDNA Tools category, with a full set of style controls, or the `[apotheca_glossary]` shortcode as a non-Elementor fallback.
* Optional automatic in-content linking, with guard rails, that ships switched off.

== Features ==

* Custom post type `apglos_term` and hierarchical taxonomy `apglos_category`, no custom database tables.
* Ten glossary categories seeded on activation.
* CSV import and export, with byte order mark handling and a full preview.
* Front-end search, A to Z and 0 to 9 bar, category filter, result count, letter headings and Clear all, all with shareable URL state.
* One Elementor Atomic widget with every content toggle and style section, a per-device columns option, and an editor-only preview of the searching and no-results states.
* Automatic in-content linking on the first mention of each term, with a per-post cap, longest-match-wins, exclusions for headings, existing links, code, alt text, shortcodes and the glossary's own output, a per-post opt-out and a global post-type exclusion list. Ships switched off.
* DefinedTerm and DefinedTermSet structured data, and canonical handling so filtered views point back at the unfiltered glossary.
* Vanilla JavaScript, no jQuery and no build step.

== Installation ==

1. Go to Plugins, Add New, Upload Plugin, and upload `apotheca-glossary-1.0.0.zip`.
2. Activate the plugin. Ten glossary categories appear, and a Glossary menu is added.
3. Add terms by hand under Glossary, or import them under Glossary, Import / Export.
4. Place the glossary on a page with the Apotheca Glossary Elementor widget (under KDNA Tools) or the `[apotheca_glossary]` shortcode.
5. When you are ready, turn on in-content linking under Glossary, Settings.

== Frequently Asked Questions ==

= Does it need Elementor? =

No. The glossary works through the `[apotheca_glossary]` shortcode on any theme. Elementor only adds the styleable widget.

= Does it need WooCommerce? =

No.

= How do I import my terms? =

Go to Glossary, Import / Export, upload your CSV, and check the preview before confirming. The columns are term, slug, category, also_known_as, related_terms and definition. The also_known_as and related_terms fields are pipe delimited.

= Why are some letters greyed out? =

Those letters have no terms yet. You can switch empty letters between greyed and hidden in the Elementor widget.

= Will in-content linking change my saved posts? =

No. Links are added at display time only, so switching linking off restores the original text exactly.

= Can I stop linking on a particular post? =

Yes. Each post has a Glossary linking box with an opt-out checkbox and a per-post cap override. You can also exclude whole post types under Glossary, Settings.

== Shortcode ==

`[apotheca_glossary]`

Attributes (all optional): `show_search`, `show_az_bar`, `show_09`, `show_category`, `show_count`, `show_headings`, `show_category_label`, `show_also_known_as`, `show_related` (yes or no); `empty_letters` (grey or hide); `category` (a category slug, to limit the glossary to one category); and the text labels `search_placeholder`, `aka_label`, `related_label`, `clear_label` and `empty_message`.

== Accessibility ==

* The letter bar is keyboard navigable with the arrow keys, plus Home and End, as a single tab stop.
* The result count is a live region, announced to screen readers as the results change.
* Every interactive element has a visible focus state.
* The search field sits in a search landmark, the letter bar is a labelled group, and the letter headings give the page a proper heading structure.

== Changelog ==

= 1.0.4 =
* The search highlight colours from the widget now always show, instead of falling back to the default yellow, while still beating a theme that styles the bare mark element. The colours flow through custom properties that the widget sets, so nothing is hardcoded. On activation the plugin also clears Elementor's cached CSS so the change takes effect without a manual Regenerate CSS step.

= 1.0.3 =
* Hardened the search highlight so it is never painted as a solid black box by a theme or Elementor global style that sets the bare mark element to a dark background with !important. The default highlight now always shows, and the widget's own highlight colours still override it.

= 1.0.2 =
* The search now only starts filtering and highlighting once at least three characters have been typed.

= 1.0.1 =
* Search highlight colours now override a theme that styles the mark element, so the chosen background and text colours always show.
* Added normal and hover background controls to the Clear all link, plus border, radius and padding, so it can be styled as a button, and stopped a theme's button hover background showing through by default.
* When a filter leaves a single result, the glossary drops to one column so the letter heading and its entry stay together on the left.

= 1.0.0 =
* First release.
