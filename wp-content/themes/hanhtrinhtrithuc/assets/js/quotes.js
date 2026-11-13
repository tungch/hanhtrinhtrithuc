/**
 * Testimonial tu chay.
 *
 * Dung lai khi nguoi doc ro chuot hoac dang dung ban phim o trong
 * slider, va khong chay khi he dieu hanh bao "giam chuyen dong".
 */
( function () {
	'use strict';

	function initSlider( root ) {
		var slides = Array.prototype.slice.call(
			root.querySelectorAll( '.htt-quotes__slide' )
		);

		if ( slides.length < 2 ) {
			return;
		}

		var dots     = Array.prototype.slice.call( root.querySelectorAll( '.htt-quotes__dot' ) );
		var interval = parseInt( root.dataset.interval, 10 ) || 3000;
		var reduce   = window.matchMedia( '(prefers-reduced-motion: reduce)' );
		var current  = 0;
		var timer    = null;

		function show( next ) {
			if ( next === current ) {
				return;
			}

			slides[ current ].classList.remove( 'is-active' );
			slides[ current ].setAttribute( 'aria-hidden', 'true' );
			slides[ next ].classList.add( 'is-active' );
			slides[ next ].removeAttribute( 'aria-hidden' );

			if ( dots.length ) {
				dots[ current ].classList.remove( 'is-active' );
				dots[ current ].setAttribute( 'aria-selected', 'false' );
				dots[ next ].classList.add( 'is-active' );
				dots[ next ].setAttribute( 'aria-selected', 'true' );
			}

			current = next;
		}

		function next() {
			show( ( current + 1 ) % slides.length );
		}

		function start() {
			if ( timer || reduce.matches ) {
				return;
			}
			timer = window.setInterval( next, interval );
		}

		function stop() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}
		}

		dots.forEach( function ( dot ) {
			dot.addEventListener( 'click', function () {
				show( parseInt( dot.dataset.index, 10 ) || 0 );
				stop();
				start();
			} );
		} );

		// Dung khi nguoi doc dang xem hoac thao tac trong slider.
		root.addEventListener( 'mouseenter', stop );
		root.addEventListener( 'mouseleave', start );
		root.addEventListener( 'focusin', stop );
		root.addEventListener( 'focusout', start );

		// Khong chay khi tab bi an - do khong ai nhin thay.
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				stop();
			} else {
				start();
			}
		} );

		reduce.addEventListener( 'change', function () {
			if ( reduce.matches ) {
				stop();
			} else {
				start();
			}
		} );

		root.classList.add( 'is-ready' );
		start();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var sliders = document.querySelectorAll( '[data-htt-quotes]' );
		Array.prototype.forEach.call( sliders, initSlider );
	} );
}() );
