/**
 * Nut sao chep lien ket bai viet.
 *
 * Dung Clipboard API khi co; neu trinh duyet chan (vi du trang khong
 * chay HTTPS) thi lui ve cach chon chu trong o an roi copy.
 */
( function () {
	'use strict';

	function fallbackCopy( text ) {
		var area = document.createElement( 'textarea' );
		area.value = text;
		area.setAttribute( 'readonly', '' );
		area.style.position = 'fixed';
		area.style.opacity = '0';
		document.body.appendChild( area );
		area.select();

		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( e ) {
			ok = false;
		}

		document.body.removeChild( area );
		return ok;
	}

	function flash( button, message ) {
		var label = button.querySelector( 'span' );

		if ( ! label || button.dataset.busy === '1' ) {
			return;
		}

		var original = label.textContent;
		button.dataset.busy = '1';
		label.textContent = message;
		button.classList.add( 'is-done' );

		setTimeout( function () {
			label.textContent = original;
			button.classList.remove( 'is-done' );
			delete button.dataset.busy;
		}, 1800 );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var buttons = document.querySelectorAll( '.htt-share__copy' );

		Array.prototype.forEach.call( buttons, function ( button ) {
			button.addEventListener( 'click', function () {
				var url = button.dataset.url || window.location.href;

				if ( navigator.clipboard && window.isSecureContext ) {
					navigator.clipboard.writeText( url ).then(
						function () { flash( button, 'Đã sao chép' ); },
						function () { flash( button, fallbackCopy( url ) ? 'Đã sao chép' : 'Không sao chép được' ); }
					);
					return;
				}

				flash( button, fallbackCopy( url ) ? 'Đã sao chép' : 'Không sao chép được' );
			} );
		} );
	} );
}() );
