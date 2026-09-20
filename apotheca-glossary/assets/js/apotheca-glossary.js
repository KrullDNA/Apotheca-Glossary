/**
 * Apotheca Glossary, front-end filtering.
 *
 * Everything runs in the browser, with no page reloads and no jQuery. Each
 * glossary instance on the page is wired up independently. The first instance
 * on a page also syncs its state to the URL, so a filtered view can be copied
 * from the address bar and reopened in the same state.
 */
( function () {
	'use strict';

	// Shared translatable templates from wp_localize_script.
	var L10N = window.apglosL10n || {
		showing: 'Showing %1$s of %2$s terms',
		showingOne: 'Showing %1$s of %2$s term'
	};

	// Only the first instance on a page claims the URL, so instances never
	// fight over the query string.
	var urlClaimed = false;

	/**
	 * Escape a string for safe use inside a regular expression.
	 * (Kept for completeness; matching itself uses indexOf, not RegExp.)
	 */
	function escapeRegExp( str ) {
		return str.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
	}

	/**
	 * Remove any highlight marks previously added inside an element, putting
	 * the plain text back so the next search starts clean.
	 */
	function clearMarks( el ) {
		var marks = el.querySelectorAll( 'mark.apglos-mark' );
		for ( var i = 0; i < marks.length; i++ ) {
			var mark = marks[ i ];
			var text = document.createTextNode( mark.textContent );
			mark.parentNode.replaceChild( text, mark );
		}
		if ( marks.length ) {
			el.normalize(); // Merge the split text nodes back together.
		}
	}

	/**
	 * Wrap every case-insensitive occurrence of `query` inside an element's
	 * text in <mark> tags, without disturbing any existing markup. Works on
	 * text nodes only, so links and structure are left intact.
	 */
	function highlight( el, query ) {
		clearMarks( el );

		if ( ! query ) {
			return;
		}

		var lowerQuery = query.toLowerCase();
		var queryLen = query.length;

		// Collect the text nodes first, then modify, so we never walk into the
		// marks we are inserting.
		var walker = document.createTreeWalker( el, NodeFilter.SHOW_TEXT, null, false );
		var nodes = [];
		while ( walker.nextNode() ) {
			nodes.push( walker.currentNode );
		}

		nodes.forEach( function ( node ) {
			var text = node.nodeValue;
			var lower = text.toLowerCase();
			var idx = lower.indexOf( lowerQuery );
			if ( idx < 0 ) {
				return;
			}

			var frag = document.createDocumentFragment();
			var last = 0;
			while ( idx >= 0 ) {
				if ( idx > last ) {
					frag.appendChild( document.createTextNode( text.slice( last, idx ) ) );
				}
				var mark = document.createElement( 'mark' );
				mark.className = 'apglos-mark';
				mark.textContent = text.slice( idx, idx + queryLen );
				frag.appendChild( mark );
				last = idx + queryLen;
				idx = lower.indexOf( lowerQuery, last );
			}
			if ( last < text.length ) {
				frag.appendChild( document.createTextNode( text.slice( last ) ) );
			}
			node.parentNode.replaceChild( frag, node );
		} );
	}

	/**
	 * Fill a "%1$s of %2$s" style template.
	 */
	function formatCount( template, shown, total ) {
		return template
			.replace( '%1$s', String( shown ) )
			.replace( '%2$s', String( total ) );
	}

	/**
	 * Wire up a single glossary instance.
	 */
	function initGlossary( root ) {
		// Grab the pieces we need. Any of the controls may be absent, since the
		// widget can switch them off.
		var searchInput = root.querySelector( '[data-apglos-search]' );
		var categorySelect = root.querySelector( '[data-apglos-category]' );
		var letterButtons = root.querySelectorAll( '[data-apglos-az] .apglos__letter' );
		var clearButton = root.querySelector( '[data-apglos-clear]' );
		var countEl = root.querySelector( '[data-apglos-count]' );
		var emptyEl = root.querySelector( '[data-apglos-empty]' );
		var entries = root.querySelectorAll( '[data-apglos-entry]' );
		var headings = root.querySelectorAll( '[data-apglos-heading]' );

		var total = parseInt( root.getAttribute( 'data-total' ), 10 ) || entries.length;

		// This instance owns the URL only if no earlier one already does.
		var ownsUrl = false;
		if ( ! urlClaimed ) {
			urlClaimed = true;
			ownsUrl = true;
		}

		// The current filter state.
		var state = { q: '', letter: '', cat: '' };

		/**
		 * Decide whether one entry passes all active filters.
		 */
		function entryMatches( entry ) {
			// Search: matches the combined term/synonym/definition blob.
			if ( state.q ) {
				var blob = entry.getAttribute( 'data-search' ) || '';
				if ( blob.indexOf( state.q.toLowerCase() ) < 0 ) {
					return false;
				}
			}
			// Letter.
			if ( state.letter ) {
				if ( ( entry.getAttribute( 'data-letter' ) || '' ) !== state.letter ) {
					return false;
				}
			}
			// Category (an entry may carry several, space separated).
			if ( state.cat ) {
				var cats = ( entry.getAttribute( 'data-category' ) || '' ).split( ' ' );
				if ( cats.indexOf( state.cat ) < 0 ) {
					return false;
				}
			}
			return true;
		}

		/**
		 * Apply the current state to the DOM: show/hide entries and headings,
		 * update the count, the empty message, the highlight, the letter bar,
		 * the Clear all button, and the URL.
		 */
		function apply() {
			var shown = 0;
			var visibleByLetter = {};

			for ( var i = 0; i < entries.length; i++ ) {
				var entry = entries[ i ];
				var match = entryMatches( entry );
				entry.hidden = ! match;

				if ( match ) {
					shown++;
					var letter = entry.getAttribute( 'data-letter' ) || '';
					visibleByLetter[ letter ] = true;

					// Highlight the search term inside the visible entry.
					highlight( entry, state.q );
				} else {
					// Nothing visible, so drop any leftover marks.
					clearMarks( entry );
				}
			}

			// A letter heading shows only if its group has a visible entry.
			for ( var h = 0; h < headings.length; h++ ) {
				var hLetter = headings[ h ].getAttribute( 'data-letter' ) || '';
				headings[ h ].hidden = ! visibleByLetter[ hLetter ];
			}

			// Result count.
			if ( countEl ) {
				var template = ( total === 1 ) ? L10N.showingOne : L10N.showing;
				countEl.textContent = formatCount( template, shown, total );
			}

			// Empty message.
			if ( emptyEl ) {
				emptyEl.hidden = shown !== 0;
			}

			// Letter bar active state.
			for ( var b = 0; b < letterButtons.length; b++ ) {
				var btn = letterButtons[ b ];
				var isActive = state.letter && btn.getAttribute( 'data-letter' ) === state.letter;
				btn.classList.toggle( 'is-active', !! isActive );
				btn.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
			}

			// Show Clear all only when at least one filter is active.
			if ( clearButton ) {
				var anyActive = !! ( state.q || state.letter || state.cat );
				clearButton.hidden = ! anyActive;
			}

			// Reflect the state in the URL, if this instance owns it.
			if ( ownsUrl ) {
				writeUrl();
			}
		}

		/**
		 * Write the current state into the URL without reloading.
		 */
		function writeUrl() {
			if ( ! window.history || ! window.history.replaceState ) {
				return;
			}
			var params = new URLSearchParams( window.location.search );

			if ( state.q ) {
				params.set( 'q', state.q );
			} else {
				params.delete( 'q' );
			}
			if ( state.letter ) {
				params.set( 'letter', state.letter );
			} else {
				params.delete( 'letter' );
			}
			if ( state.cat ) {
				params.set( 'cat', state.cat );
			} else {
				params.delete( 'cat' );
			}

			var query = params.toString();
			var newUrl = window.location.pathname + ( query ? '?' + query : '' ) + window.location.hash;
			window.history.replaceState( null, '', newUrl );
		}

		/**
		 * Read any starting state out of the URL.
		 */
		function readUrl() {
			if ( ! ownsUrl ) {
				return;
			}
			var params = new URLSearchParams( window.location.search );

			var q = params.get( 'q' );
			var letter = params.get( 'letter' );
			var cat = params.get( 'cat' );

			if ( q ) {
				state.q = q;
				if ( searchInput ) {
					searchInput.value = q;
				}
			}
			if ( letter ) {
				// Only accept a letter the bar actually offers.
				for ( var b = 0; b < letterButtons.length; b++ ) {
					if ( letterButtons[ b ].getAttribute( 'data-letter' ) === letter && ! letterButtons[ b ].disabled ) {
						state.letter = letter;
						break;
					}
				}
			}
			if ( cat && categorySelect ) {
				// Only accept a category the dropdown actually offers.
				for ( var o = 0; o < categorySelect.options.length; o++ ) {
					if ( categorySelect.options[ o ].value === cat ) {
						state.cat = cat;
						categorySelect.value = cat;
						break;
					}
				}
			}
		}

		// ---- Wire up the controls --------------------------------------

		// Search, debounced so highlighting keeps up with fast typing.
		if ( searchInput ) {
			var searchTimer = null;
			searchInput.addEventListener( 'input', function () {
				if ( searchTimer ) {
					window.clearTimeout( searchTimer );
				}
				searchTimer = window.setTimeout( function () {
					state.q = searchInput.value.trim();
					apply();
				}, 120 );
			} );
		}

		// Letter bar.
		for ( var b = 0; b < letterButtons.length; b++ ) {
			( function ( button ) {
				if ( button.disabled ) {
					return;
				}
				button.addEventListener( 'click', function () {
					var letter = button.getAttribute( 'data-letter' );
					// Clicking the active letter clears it (a toggle).
					state.letter = ( state.letter === letter ) ? '' : letter;
					apply();
				} );
			} )( letterButtons[ b ] );
		}

		// Category dropdown.
		if ( categorySelect ) {
			categorySelect.addEventListener( 'change', function () {
				state.cat = categorySelect.value;
				apply();
			} );
		}

		// Clear all.
		if ( clearButton ) {
			clearButton.addEventListener( 'click', function () {
				state.q = '';
				state.letter = '';
				state.cat = '';
				if ( searchInput ) {
					searchInput.value = '';
				}
				if ( categorySelect ) {
					categorySelect.value = '';
				}
				apply();
				if ( searchInput ) {
					searchInput.focus();
				}
			} );
		}

		// Category labels inside entries: filter to that category in place.
		var catLinks = root.querySelectorAll( '[data-apglos-cat-link]' );
		for ( var c = 0; c < catLinks.length; c++ ) {
			( function ( link ) {
				var slug = link.getAttribute( 'data-apglos-cat-link' );
				// Only hijack the click when the dropdown can honour it.
				if ( ! categorySelect ) {
					return;
				}
				link.addEventListener( 'click', function ( e ) {
					e.preventDefault();
					state.cat = slug;
					categorySelect.value = slug;
					apply();
					root.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				} );
			} )( catLinks[ c ] );
		}

		// Related-term links: clear filters so the target is visible, then jump.
		var relatedLinks = root.querySelectorAll( '[data-apglos-related]' );
		for ( var r = 0; r < relatedLinks.length; r++ ) {
			( function ( link ) {
				link.addEventListener( 'click', function ( e ) {
					var targetId = link.getAttribute( 'href' ).slice( 1 );
					var target = document.getElementById( targetId );
					if ( ! target ) {
						return; // Let the browser try the anchor as-is.
					}
					e.preventDefault();
					// Reset filters so the target entry is on the page.
					state.q = '';
					state.letter = '';
					state.cat = '';
					if ( searchInput ) {
						searchInput.value = '';
					}
					if ( categorySelect ) {
						categorySelect.value = '';
					}
					apply();
					target.scrollIntoView( { behavior: 'smooth', block: 'start' } );
					target.classList.add( 'is-target' );
					window.setTimeout( function () {
						target.classList.remove( 'is-target' );
					}, 1600 );
				} );
			} )( relatedLinks[ r ] );
		}

		// ---- Go ---------------------------------------------------------
		readUrl();
		apply();
	}

	/**
	 * Boot every glossary on the page.
	 */
	function boot() {
		var roots = document.querySelectorAll( '[data-apglos]' );
		for ( var i = 0; i < roots.length; i++ ) {
			initGlossary( roots[ i ] );
		}
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
