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

	// The search only starts filtering and highlighting once this many
	// characters have been typed. Fewer than this is treated as no search.
	var MIN_SEARCH = 3;

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
		// Never initialise twice (Elementor can re-inject the same node).
		if ( root.__apglosInit ) {
			return;
		}
		// In the Elementor editor, preview-state mocks are static: leave them
		// exactly as rendered so they can be styled without the JS reshuffling.
		if ( root.getAttribute( 'data-apglos-preview' ) ) {
			root.__apglosInit = true;
			return;
		}
		root.__apglosInit = true;

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
		 * The enabled (non-empty) letter buttons, in document order.
		 */
		function enabledButtons() {
			var list = [];
			for ( var i = 0; i < letterButtons.length; i++ ) {
				if ( ! letterButtons[ i ].disabled ) {
					list.push( letterButtons[ i ] );
				}
			}
			return list;
		}

		/**
		 * The first enabled letter button, or null.
		 */
		function firstEnabledButton() {
			var enabled = enabledButtons();
			return enabled.length ? enabled[ 0 ] : null;
		}

		/**
		 * Roving tabindex: put exactly one letter button in the tab order.
		 */
		function setRoving( target ) {
			for ( var i = 0; i < letterButtons.length; i++ ) {
				letterButtons[ i ].tabIndex = ( letterButtons[ i ] === target ) ? 0 : -1;
			}
		}

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

			// With a single result, drop to one column so the heading and its
			// entry stay together on the left instead of splitting across two.
			var listEl = root.querySelector( '[data-apglos-list]' );
			if ( listEl ) {
				listEl.classList.toggle( 'apglos-list--single', shown === 1 );
			}

			// Letter bar active state, and keep the tab stop on the active
			// letter so keyboard users land on the current selection.
			var activeButton = null;
			for ( var b = 0; b < letterButtons.length; b++ ) {
				var btn = letterButtons[ b ];
				var isActive = state.letter && btn.getAttribute( 'data-letter' ) === state.letter;
				btn.classList.toggle( 'is-active', !! isActive );
				btn.setAttribute( 'aria-pressed', isActive ? 'true' : 'false' );
				if ( isActive ) {
					activeButton = btn;
				}
			}
			if ( activeButton ) {
				setRoving( activeButton );
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
				// Prefill the box, but only treat it as an active search once it
				// meets the minimum length.
				if ( searchInput ) {
					searchInput.value = q;
				}
				state.q = ( q.trim().length >= MIN_SEARCH ) ? q.trim() : '';
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
					var raw = searchInput.value.trim();
					// Only search once at least MIN_SEARCH characters are typed.
					state.q = ( raw.length >= MIN_SEARCH ) ? raw : '';
					apply();
				}, 120 );
			} );
		}

		// Letter bar clicks.
		for ( var b = 0; b < letterButtons.length; b++ ) {
			( function ( button ) {
				if ( button.disabled ) {
					return;
				}
				button.addEventListener( 'click', function () {
					var letter = button.getAttribute( 'data-letter' );
					// Clicking the active letter clears it (a toggle).
					state.letter = ( state.letter === letter ) ? '' : letter;
					setRoving( button );
					apply();
				} );
			} )( letterButtons[ b ] );
		}

		// Letter bar keyboard navigation (a toolbar pattern): one tab stop for
		// the whole bar, then arrow keys move between the enabled letters, with
		// Home and End jumping to the ends.
		var azContainer = root.querySelector( '[data-apglos-az]' );
		if ( azContainer && letterButtons.length ) {
			// Roving tabindex: only one button is in the tab order at a time.
			setRoving( firstEnabledButton() );

			azContainer.addEventListener( 'keydown', function ( e ) {
				var handled = [ 'ArrowRight', 'ArrowLeft', 'ArrowUp', 'ArrowDown', 'Home', 'End' ];
				if ( handled.indexOf( e.key ) < 0 ) {
					return;
				}
				var enabled = enabledButtons();
				if ( ! enabled.length ) {
					return;
				}
				var idx = enabled.indexOf( document.activeElement );
				if ( idx < 0 ) {
					idx = 0;
				}
				var next = idx;
				if ( 'ArrowRight' === e.key || 'ArrowDown' === e.key ) {
					next = idx + 1;
				} else if ( 'ArrowLeft' === e.key || 'ArrowUp' === e.key ) {
					next = idx - 1;
				} else if ( 'Home' === e.key ) {
					next = 0;
				} else if ( 'End' === e.key ) {
					next = enabled.length - 1;
				}
				// Wrap around the ends.
				if ( next < 0 ) {
					next = enabled.length - 1;
				}
				if ( next >= enabled.length ) {
					next = 0;
				}
				setRoving( enabled[ next ] );
				enabled[ next ].focus();
				e.preventDefault();
			} );
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
	 * If the page was opened at a term anchor (an in-content glossary link),
	 * scroll to that term once the glossaries are built, so the browser honours
	 * the anchor offset even though the entries were laid out by JavaScript.
	 */
	function scrollToHashTerm() {
		var hash = window.location.hash;
		if ( ! hash || hash.indexOf( '#apglos' ) !== 0 ) {
			return;
		}
		var target = document.getElementById( hash.slice( 1 ) );
		if ( target && target.hasAttribute( 'data-apglos-entry' ) ) {
			// Wait a frame so layout has settled, then scroll (scroll-margin-top
			// applies the header offset).
			window.requestAnimationFrame( function () {
				target.scrollIntoView( { block: 'start' } );
			} );
		}
	}

	/**
	 * Boot every glossary currently on the page.
	 */
	function boot() {
		var roots = document.querySelectorAll( '[data-apglos]' );
		for ( var i = 0; i < roots.length; i++ ) {
			initGlossary( roots[ i ] );
		}
		scrollToHashTerm();
	}

	/**
	 * Watch for glossaries added after load, so the widget still works when
	 * Elementor injects or re-renders it in the editor without a page reload.
	 */
	function watchForInjected() {
		if ( ! window.MutationObserver ) {
			return;
		}
		var observer = new MutationObserver( function ( mutations ) {
			for ( var m = 0; m < mutations.length; m++ ) {
				var added = mutations[ m ].addedNodes;
				for ( var n = 0; n < added.length; n++ ) {
					var node = added[ n ];
					if ( 1 !== node.nodeType ) {
						continue; // Elements only.
					}
					if ( node.matches && node.matches( '[data-apglos]' ) ) {
						initGlossary( node );
					}
					if ( node.querySelectorAll ) {
						var inner = node.querySelectorAll( '[data-apglos]' );
						for ( var q = 0; q < inner.length; q++ ) {
							initGlossary( inner[ q ] );
						}
					}
				}
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			boot();
			watchForInjected();
		} );
	} else {
		boot();
		watchForInjected();
	}
} )();
