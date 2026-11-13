/**
 * Chuyen che do sang / toi.
 *
 * Ba trang thai: nguoi doc chon Sang, chon Toi, hoac de "theo may".
 * Lua chon duoc ghi nho qua localStorage cho lan truy cap sau.
 */
( function () {
	'use strict';

	var root  = document.documentElement;
	var media = window.matchMedia( '(prefers-color-scheme: dark)' );
	var KEY   = 'htt-theme';

	function read() {
		try {
			var v = localStorage.getItem( KEY );
			return ( v === 'light' || v === 'dark' ) ? v : 'auto';
		} catch ( e ) {
			return 'auto';
		}
	}

	function write( value ) {
		try {
			if ( value === 'auto' ) {
				localStorage.removeItem( KEY );
			} else {
				localStorage.setItem( KEY, value );
			}
		} catch ( e ) {
			// Private browsing blocks storage; the choice just won't persist.
		}
	}

	function apply( choice, buttons ) {
		if ( choice === 'auto' ) {
			root.removeAttribute( 'data-theme' );
		} else {
			root.setAttribute( 'data-theme', choice );
		}

		buttons.forEach( function ( button ) {
			button.setAttribute( 'aria-pressed', String( button.dataset.httTheme === choice ) );
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var buttons = Array.prototype.slice.call(
			document.querySelectorAll( '[data-htt-theme]' )
		);

		if ( ! buttons.length ) {
			return;
		}

		var choice = read();
		apply( choice, buttons );

		buttons.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				choice = button.dataset.httTheme;
				write( choice );
				apply( choice, buttons );
			} );
		} );

		// Follow the OS while the reader has not overridden it.
		media.addEventListener( 'change', function () {
			if ( choice === 'auto' ) {
				apply( choice, buttons );
			}
		} );
	} );
}() );
