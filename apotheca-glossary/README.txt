=== Apotheca Glossary ===
Contributors: krulldna
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.7.1
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

Attributes (all optional): `show_search`, `show_az_bar`, `show_09`, `show_category`, `show_count`, `show_headings`, `show_category_label`, `show_also_known_as`, `show_related`, `show_to_top` (yes or no); `empty_letters` (grey or hide); `category` (a category slug, to limit the glossary to one category); and the text labels `search_placeholder`, `aka_label`, `related_label`, `clear_label`, `to_top_label` and `empty_message`.

== Accessibility ==

* The letter bar is keyboard navigable with the arrow keys, plus Home and End, as a single tab stop.
* The result count is a live region, announced to screen readers as the results change.
* Every interactive element has a visible focus state.
* The search field sits in a search landmark, the letter bar is a labelled group, and the letter headings give the page a proper heading structure.

== Changelog ==

= 1.7.1 =
* The Back to top button now scrolls right to the top of the page. Added a Box shadow control to its style section, carried across when the button is moved to the body so the chosen shadow is kept.

= 1.7.0 =
* Added an optional floating Back to top button, fixed to the bottom right of the browser, that fades in once the reader scrolls down and returns them to the glossary's search. It ships switched off, under Elements. The button is moved to the page body at load so a transformed section cannot break its fixed position. A Back to top button style section adds per-device distance from the bottom and from the right, plus button size, corner radius, background and border colours (normal and hover), and icon size and colour (normal and hover). Also available via the shortcode with show_to_top="yes".

= 1.6.1 =
* The selected term highlight now has a comfortable, even gap on all four sides between the colour and the term, so the highlighted term has room to breathe. Added a Selected term corner radius control to the widget's Search highlight section, so the highlight's rounded corners can be adjusted or squared off.

= 1.6.0 =
* The term a reader is brought to (from an in-content link or a related term) now stays highlighted so it is clear which term is meant, instead of a highlight that flashes and fades. The highlight clears as soon as they search or filter. Added a Selected term highlight colour control to the widget's Search highlight section.

= 1.5.3 =
* Fixed the in-content link parameter clashing with the glossary post type. The scroll parameter was named apglos_term, the same as the post type, so WordPress rendered the single term instead of the glossary page. It is now apglos_scroll, so links open the full glossary page and scroll to the term as intended.

= 1.5.2 =
* The link, dictionary and schema caches are now tied to the plugin version, so updating the plugin refreshes them automatically. Previously a files-only update (which does not run activation) could keep serving the old in-content link format until the plugin was reactivated.

= 1.5.1 =
* In-content links now scroll to the term with the whole glossary still on screen to browse (with the header offset), instead of filtering everything else out. Filtering everything to one word is what the search box is for; a link should take you to the term in context. The link uses ?apglos_term=slug and the widget finds the term by slug, so it no longer depends on a fixed HTML anchor.

= 1.5.0 =
* In-content links now open the glossary page as a search (?q=Term) instead of jumping to a term anchor. The widget filters to the term and highlights it, with the reader landing on the full glossary to explore, and it no longer depends on fragile anchor markup or on the glossary being the first element on the page. Every glossary on a page now reads the URL query, and a term arriving in the URL is active whatever its length.

= 1.4.0 =
* Added glossary link appearance controls to the settings page: underline (on by default, so the links are visible), a link colour and a hover colour. Leaving the colours blank keeps the surrounding text colour.

= 1.3.0 =
* In-content linking now also works inside JetEngine (and Elementor) dynamic field widgets, which many themes use to render body copy. A short-field guard means small fields like a date, a reading time or a single tag are left alone, only real body copy is linked. A new "Link inside dynamic fields" setting (on by default) controls it, and the linkable widget list and the minimum field length are both filterable.

= 1.2.0 =
* In-content linking now also works inside Elementor Text Editor widgets. Elementor renders those without WordPress's standard content filter, so glossary links were not being added to pages built that way. Linking now runs on Text Editor widget output too, with the per-post link cap and the first-occurrence rule shared across every text widget in the post.

= 1.1.0 =
* Added a Glossary page URL setting. When set, in-content links point to that page and scroll to the term (with the anchor offset), so the reader lands on the full glossary and can search other terms, instead of a single-term page. Leave it blank to keep linking to each term's own page. Each entry now carries a stable anchor for this.

= 1.0.5 =
* Jumping to a related term now stops 100px below the top by default, so a fixed or transparent header no longer covers the term. Added a per-device Anchor offset control in the widget's Layout section to adjust that distance.

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
