/**
 * Nut "In theo yeu cau" o cuoi bai viet.
 *
 * Panel duoc in san cung trang, script chi lo viec dong/mo. Khong co
 * JavaScript thi panel hien luon - nguoi doc van dat hang duoc.
 */
( function () {
	'use strict';

	var wrap = document.querySelector( '[data-htt-print]' );

	if ( ! wrap ) {
		return;
	}

	var toggle  = wrap.querySelector( '.htt-print__toggle' );
	var overlay = wrap.querySelector( '.htt-print__overlay' );
	var panel   = wrap.querySelector( '.htt-print__panel' );
	var close   = wrap.querySelector( '.htt-print__close' );

	if ( ! toggle || ! overlay ) {
		return;
	}

	/* --- Sach 3D: khung iframe nam de len anh tinh --- */

	var media   = overlay.querySelector( '.htt-print__media' );
	var book3d  = overlay.querySelector( '.htt-print__book3d' );
	var frame   = book3d ? book3d.querySelector( 'iframe' ) : null;
	var bookState = 'closed';
	var waiting;

	// Ca bo Three.js nang hon nua megabyte, nen iframe chi duoc tai khi
	// nguoi doc that su mo popup - khong phai moi lan vao bai viet.
	function loadBook() {
		if ( ! frame || frame.src ) {
			return;
		}

		frame.src = frame.getAttribute( 'data-src' );
		book3d.hidden = false;

		// Sach khong len duoc (khong co WebGL, mang hong, loi cau hinh) thi
		// giu nguyen anh tinh ben duoi - van dat hang duoc nhu thuong.
		waiting = window.setTimeout( function () {
			showBook( false );
		}, 8000 );
	}

	function showBook( on ) {
		if ( ! media || ! book3d ) {
			return;
		}

		window.clearTimeout( waiting );
		media.classList.toggle( 'is-3d', on );
		book3d.hidden = ! on;
	}

	if ( frame ) {
		window.addEventListener( 'message', function ( event ) {
			// Chi nghe chinh iframe nay, khong nghe bat ky trang nao khac.
			if ( event.source !== frame.contentWindow ) {
				return;
			}

			var data = event.data;

			if ( ! data || 'book3d' !== data.source ) {
				return;
			}

			if ( 'ready' === data.type ) {
				showBook( true );
			} else if ( 'failed' === data.type ) {
				showBook( false );
			} else if ( 'state' === data.type ) {
				bookState = data.state;

				// Sach mo ra thi trai ngang hai trang, can gap doi chieu
				// rong moi doc duoc chu; luc gap lai thi tra khung ve
				// khuon vuong cho vua day anh nho ben duoi.
				var reading = 'closed' !== bookState;

				if ( media ) {
					media.classList.toggle( 'is-reading', reading );

					// Khung nam ngang khi sach gio hai trang doi nhau, nam
					// doc khi gio tung trang mot. Che do "auto" theo khung,
					// nen khung doc - o do mot trang de doc hon.
					media.classList.toggle(
						'is-spread',
						reading && 'double' === book3d.getAttribute( 'data-spread' )
					);
				}

				// Panel cung phai rong ra. Gan class thang vao day thay vi
				// chi dua vao :has() o CSS, de trinh duyet cu van noi duoc.
				if ( panel ) {
					panel.classList.toggle( 'is-reading', reading );
					panel.classList.toggle(
						'is-spread',
						reading && 'double' === book3d.getAttribute( 'data-spread' )
					);
				}
			}
		} );
	}

	// Gap sach lai de lan mo popup sau bat dau tu bia, khong phai tu
	// giua trang dang doc do.
	// instant = true: ve bia ngay, bo hieu ung gap. Dung khi dong popup -
	// luc do iframe da khuat nen khong ai thay hieu ung, ma mot hieu ung
	// chay trong khung hinh da co ve 0x0 thi de lam sach ket lai giua
	// chung. Esc lan dau thi nguoc lai: popup van mo, nguoi doc nhin thay
	// cuon sach gap lai nen hieu ung co y nghia.
	function foldBook( instant ) {
		if ( frame && frame.contentWindow && 'closed' !== bookState ) {
			frame.contentWindow.postMessage(
				{ target: 'book3d', type: 'close', instant: !! instant },
				'*'
			);
		}
	}

	function open() {
		overlay.hidden = false;
		toggle.setAttribute( 'aria-expanded', 'true' );
		// Khoa cuon nen de nguoi doc khong lac khoi modal.
		document.documentElement.style.overflow = 'hidden';

		loadBook();

		// Dua tieu diem vao trong modal cho nguoi dung ban phim.
		var first = overlay.querySelector( 'input, button, a' );
		if ( first ) {
			first.focus();
		}
	}

	function shut() {
		foldBook( true );
		overlay.hidden = true;
		toggle.setAttribute( 'aria-expanded', 'false' );
		document.documentElement.style.overflow = '';
	}

	toggle.addEventListener( 'click', function () {
		if ( overlay.hidden ) {
			open();
		} else {
			shut();
		}
	} );

	if ( close ) {
		close.addEventListener( 'click', function () {
			shut();
			toggle.focus();
		} );
	}

	// Bam vao vung toi ben ngoai panel thi dong lai.
	overlay.addEventListener( 'click', function ( event ) {
		if ( panel && ! panel.contains( event.target ) ) {
			shut();
			toggle.focus();
		}
	} );

	// Phim Esc dong modal, giu tieu diem quay ve nut. Khi sach 3D dang mo
	// thi Esc dau tien gap sach lai - dong luon ca popup se lam nguoi doc
	// mat cho dang doc ma khong hieu vi sao.
	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' !== event.key || overlay.hidden ) {
			return;
		}

		if ( 'closed' !== bookState ) {
			foldBook();
			return;
		}

		shut();
		toggle.focus();
	} );

	/* --- Day anh: bam anh nho de doi anh lon --- */

	var cover  = overlay.querySelector( '.htt-print__cover' );
	var thumbs = overlay.querySelectorAll( '.htt-print__thumb' );

	// Bai co sach 3D thi khong in day anh nho nao ca - cuon sach da cho
	// xem duoc moi mat roi (xem htt_print_button). Nen doan nay chi chay
	// voi nhung bai chi co anh chup.
	if ( cover && thumbs.length ) {
		Array.prototype.forEach.call( thumbs, function ( btn ) {
			btn.addEventListener( 'click', function () {
				var full = btn.getAttribute( 'data-full' );

				if ( ! full ) {
					return;
				}

				// Anh lon dung srcset rieng cua no; bo di de src moi thang.
				cover.removeAttribute( 'srcset' );
				cover.removeAttribute( 'sizes' );
				cover.src = full;

				Array.prototype.forEach.call( thumbs, function ( other ) {
					other.classList.toggle( 'is-active', other === btn );
				} );
			} );
		} );
	}

	/* --- O so luong: nut tru / cong --- */

	var qty   = overlay.querySelector( '.htt-print__qty-input' );
	var minus = overlay.querySelector( '.htt-print__qty-btn--minus' );
	var plus  = overlay.querySelector( '.htt-print__qty-btn--plus' );

	if ( ! qty ) {
		return;
	}

	function limits() {
		return {
			min: parseInt( qty.getAttribute( 'min' ), 10 ) || 1,
			max: parseInt( qty.getAttribute( 'max' ), 10 ) || 9999
		};
	}

	// Nut tru mo di khi da cham day, de nguoi dung biet khong giam duoc nua.
	function sync() {
		var l = limits();
		var v = parseInt( qty.value, 10 );

		if ( isNaN( v ) ) {
			v = l.min;
		}

		if ( minus ) {
			minus.disabled = v <= l.min;
		}

		if ( plus ) {
			plus.disabled = v >= l.max;
		}
	}

	function step( by ) {
		var l = limits();
		var v = parseInt( qty.value, 10 );

		if ( isNaN( v ) ) {
			v = l.min;
		}

		v += by;

		if ( v < l.min ) {
			v = l.min;
		}

		if ( v > l.max ) {
			v = l.max;
		}

		qty.value = v;
		sync();
	}

	if ( minus ) {
		minus.addEventListener( 'click', function () {
			step( -1 );
		} );
	}

	if ( plus ) {
		plus.addEventListener( 'click', function () {
			step( 1 );
		} );
	}

	// Go tay vao o cung phai kiem lai, tranh gui len so am hay so le.
	qty.addEventListener( 'change', function () {
		step( 0 );
	} );

	sync();
}() );
