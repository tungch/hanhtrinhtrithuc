<?php
/**
 * Sách 3D - biến nội dung bài viết thành một cuốn sách lật trang.
 *
 * Cuốn sách trong popup "In theo yêu cầu" không có nội dung riêng: nó
 * đọc chính bài viết. Người viết chỉ cần chọn ảnh bìa, còn ruột sách
 * sinh ra từ các khối Gutenberg của bài - sửa bài là sách đổi theo,
 * không có bước xuất file nào ở giữa.
 *
 * Việc chia trang KHÔNG làm ở đây. Sách chia trang bằng cách đo chiều
 * rộng chữ thật (canvas.measureText), nên chỉ trình duyệt làm được.
 * PHP chỉ lo chuyển khối thành chuỗi `content` theo cú pháp mà
 * js/pages.js hiểu, rồi giao phần còn lại cho js/book3d.js.
 *
 * @package HanhTrinhTriThuc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Khổ sách mặc định (cm) và độ dày một tờ giấy (mm).
 */
const HTT_BOOK3D_WIDTH  = 14;
const HTT_BOOK3D_HEIGHT = 20.5;
const HTT_BOOK3D_PAPER  = 0.1;

/**
 * Nơi đặt bộ sách 3D, tính từ thư mục theme con.
 */
const HTT_BOOK3D_DIR = '/assets/book3d';

/**
 * ==========================================================================
 * Chuyển khối bài viết thành chuỗi `content` của sách
 * ==========================================================================
 */

/**
 * Làm sạch một đoạn HTML thành đúng một dòng văn bản.
 *
 * Ba việc, theo thứ tự bắt buộc:
 *
 * 1. Bỏ thẻ. `<em>`, `<strong>`, `<a>` không in được lên trang giấy vẽ
 *    bằng canvas, nên chỉ giữ lại chữ.
 * 2. Giải mã thực thể. wp_strip_all_tags() không làm việc này, nên một
 *    đoạn chứa &ldquo; sẽ hiện nguyên văn "&ldquo;" trên trang sách.
 * 3. Gộp mọi khoảng trắng - kể cả xuống dòng - về một dấu cách. pages.js
 *    tách đoạn theo ký tự \n, nên một dòng xuống dòng giữa đoạn sẽ bị
 *    đọc thành hai đoạn riêng.
 *
 * @param string $html Nội dung thô của một khối.
 * @return string Một dòng, không có ký tự xuống dòng.
 */
function htt_book3d_line( $html ) {
	$text = wp_strip_all_tags( (string) $html );
	$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

	// \x{A0} = khoảng trắng không ngắt, thường lọt vào từ trình soạn.
	$text = preg_replace( '/[\s\x{A0}]+/u', ' ', $text );

	return trim( (string) $text );
}

/**
 * Che những ký tự mà pages.js đọc thành lệnh.
 *
 * parseContent() hiểu một dòng mở đầu bằng '#' là tiêu đề chương, một
 * dòng chỉ có '---' là lệnh ngắt trang, và '[ảnh: key]' là trang ảnh.
 * Văn viết dùng cả ba thứ đó một cách tự nhiên, mà pages.js không có
 * cú pháp escape nào - nên phải chặn từ phía này.
 *
 * Cách chặn là chèn U+2060 (word joiner): một ký tự rộng 0px, không
 * ngắt dòng, nên trang sách trông không đổi, nhưng dòng không còn
 * "bắt đầu bằng #" nữa.
 *
 * @param string $line Một dòng đã làm sạch.
 * @return string
 */
function htt_book3d_escape( $line ) {
	if ( '' === $line ) {
		return '';
	}

	// Dòng chỉ gồm 3 dấu gạch trở lên = lệnh ngắt trang. Đổi sang gạch
	// ngang en dash: người đọc thấy y như cũ, pages.js không nhận ra lệnh.
	if ( preg_match( '/^-{3,}$/', $line ) ) {
		return str_repeat( "\u{2013}", strlen( $line ) );
	}

	// '#' hoặc '[' ở đầu dòng: đẩy một ký tự vô hình lên trước.
	if ( '#' === $line[0] || '[' === $line[0] ) {
		return "\u{2060}" . $line;
	}

	return $line;
}

/**
 * Chuyển một danh sách khối thành các dòng của sách.
 *
 * Gọi đệ quy, vì khối trích dẫn bọc khối đoạn văn và khối danh sách bọc
 * khối mục danh sách - nội dung thật nằm ở tầng trong.
 *
 * @param array $blocks Khối từ parse_blocks().
 * @param array $media  Ảnh gom được, truyền theo tham chiếu.
 * @param int   $depth  Độ sâu đệ quy, chặn khối lồng nhau vô hạn.
 * @return string[] Các dòng, chưa ghép.
 */
function htt_book3d_blocks_to_lines( $blocks, &$media, $depth = 0 ) {
	if ( $depth > 6 ) {
		return array();
	}

	$lines = array();

	foreach ( (array) $blocks as $block ) {
		$name  = isset( $block['blockName'] ) ? (string) $block['blockName'] : '';
		$inner = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
		$attrs = isset( $block['attrs'] ) ? (array) $block['attrs'] : array();
		$kids  = isset( $block['innerBlocks'] ) ? (array) $block['innerBlocks'] : array();

		switch ( $name ) {

			// Khối không tên là một trong hai thứ: khoảng trắng giữa hai
			// khối thật (bỏ qua), hoặc toàn bộ bài viết soạn bằng trình
			// soạn cũ, chưa chia khối (giữ lại). Bài cũ không có thẻ <p>
			// nào, chỉ ngăn đoạn bằng dòng trống, nên phải tách theo đó
			// thay vì gộp hết thành một đoạn dài.
			case '':
				foreach ( preg_split( '/\R{2,}/', $inner ) as $chunk ) {
					$text = htt_book3d_line( $chunk );

					if ( '' !== $text ) {
						$lines[] = htt_book3d_escape( $text );
					}
				}
				break;

			case 'core/paragraph':
				$text = htt_book3d_line( $inner );

				// Bài viết hay mở đầu bằng một <p></p> rỗng do trình soạn
				// để lại; nó không nên thành một đoạn trong sách.
				if ( '' !== $text ) {
					$lines[] = htt_book3d_escape( $text );
				}
				break;

			case 'core/heading':
				$text  = htt_book3d_line( $inner );
				$level = isset( $attrs['level'] ) ? (int) $attrs['level'] : 2;

				if ( '' === $text ) {
					break;
				}

				if ( 2 === $level ) {
					// h2 = chương mới, mở ở trang mới.
					$lines[] = '# ' . $text;
				} else {
					// h3 trở xuống ở lại trong dòng chảy. Một h3 ngay dưới
					// h2 là chuyện thường, và nếu nó cũng sang trang mới
					// thì chương sẽ mở bằng một trang chỉ có mỗi tiêu đề.
					$lines[] = htt_book3d_escape( $text );
				}
				break;

			case 'core/list':
				$lines = array_merge( $lines, htt_book3d_blocks_to_lines( $kids, $media, $depth + 1 ) );
				break;

			case 'core/list-item':
				$text = htt_book3d_line( $inner );

				if ( '' !== $text ) {
					// Dấu đầu dòng thật, vì canvas không vẽ được <ul>.
					$lines[] = "\u{2022} " . $text;
				}
				break;

			case 'core/quote':
			case 'core/pullquote':
				$quoted = htt_book3d_blocks_to_lines( $kids, $media, $depth + 1 );

				// Trích dẫn kiểu cũ giữ chữ ngay trong innerHTML, không
				// có khối con nào để đệ quy vào. Phải bỏ <cite> ra trước,
				// nếu không tên người nói sẽ dính liền vào câu trích rồi
				// lại hiện thêm một lần nữa ở dòng dưới.
				if ( ! $quoted ) {
					$body = preg_replace( '#<cite[^>]*>.*?</cite>#is', '', $inner );
					$text = htt_book3d_line( (string) $body );

					if ( '' !== $text ) {
						$quoted = array( $text );
					}
				}

				foreach ( $quoted as $line ) {
					$lines[] = "\u{2014} " . $line;
				}

				// <cite> nằm ngoài khối con, phải lấy riêng.
				if ( preg_match( '#<cite[^>]*>(.*?)</cite>#is', $inner, $m ) ) {
					$cite = htt_book3d_line( $m[1] );

					if ( '' !== $cite ) {
						$lines[] = "\u{2014} " . $cite;
					}
				}
				break;

			case 'core/separator':
				$lines[] = '---';
				break;

			case 'core/image':
				$line = htt_book3d_image_line( $block, $media );

				if ( '' !== $line ) {
					$lines[] = $line;
				}
				break;

			case 'core/table':
				$lines = array_merge( $lines, htt_book3d_table_lines( $inner ) );
				break;

			// Những khối không in được lên giấy.
			case 'core/embed':
			case 'core/audio':
			case 'core/video':
			case 'core/html':
			case 'core/buttons':
			case 'core/button':
			case 'core/shortcode':
			case 'core/spacer':
			case 'core/more':
			case 'core/nextpage':
				break;

			default:
				// Khối bọc (group, columns, cover...) có nội dung ở tầng
				// trong; khối lạ thì đọc lấy chữ của nó.
				if ( $kids ) {
					$lines = array_merge( $lines, htt_book3d_blocks_to_lines( $kids, $media, $depth + 1 ) );
					break;
				}

				$text = htt_book3d_line( $inner );

				if ( '' !== $text ) {
					$lines[] = htt_book3d_escape( $text );
				}
				break;
		}
	}

	return $lines;
}

/**
 * Một khối ảnh thành lệnh `[ảnh: key]`, và ghi ảnh vào danh sách media.
 *
 * @param array $block Khối core/image.
 * @param array $media Danh sách ảnh, theo tham chiếu.
 * @return string Dòng lệnh, hoặc rỗng nếu không tìm được ảnh.
 */
function htt_book3d_image_line( $block, &$media ) {
	$attrs = isset( $block['attrs'] ) ? (array) $block['attrs'] : array();
	$inner = isset( $block['innerHTML'] ) ? (string) $block['innerHTML'] : '';
	$src   = '';

	// Ảnh trong thư viện: lấy bản 'full' thay vì bản đã co trong bài, vì
	// một trang sách rộng tới 1400px.
	if ( ! empty( $attrs['id'] ) ) {
		$src = (string) wp_get_attachment_image_url( (int) $attrs['id'], 'full' );
	}

	// Ảnh dán từ ngoài không có id, chỉ có src trong HTML.
	if ( '' === $src && preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $inner, $m ) ) {
		$src = $m[1];
	}

	if ( '' === $src ) {
		return '';
	}

	$caption = '';

	if ( preg_match( '#<figcaption[^>]*>(.*?)</figcaption>#is', $inner, $m ) ) {
		$caption = htt_book3d_line( $m[1] );
	}

	$key           = 'anh' . ( count( $media ) + 1 );
	$media[ $key ] = array(
		'src'     => $src,
		'caption' => $caption,
		// 'framed' = ảnh minh hoạ trên nền giấy, giữ được chú thích;
		// 'full' sẽ phủ kín trang và cắt mất chú thích.
		'fit'     => $caption ? 'framed' : 'full',
	);

	return '[ảnh: ' . $key . ']';
}

/**
 * Một bảng thành các dòng văn bản.
 *
 * Trang sách vẽ bằng canvas không dựng được bảng, nên mỗi hàng thành
 * một dòng với các ô nối bằng gạch ngang - mất dáng bảng nhưng giữ
 * được thông tin.
 *
 * @param string $html HTML của khối bảng.
 * @return string[]
 */
function htt_book3d_table_lines( $html ) {
	$lines = array();

	if ( preg_match_all( '#<tr[^>]*>(.*?)</tr>#is', $html, $rows ) ) {
		foreach ( $rows[1] as $row ) {
			if ( ! preg_match_all( '#<t[hd][^>]*>(.*?)</t[hd]>#is', $row, $cells ) ) {
				continue;
			}

			$parts = array_filter( array_map( 'htt_book3d_line', $cells[1] ), 'strlen' );

			if ( $parts ) {
				$lines[] = htt_book3d_escape( implode( " \u{2014} ", $parts ) );
			}
		}
	}

	if ( preg_match( '#<figcaption[^>]*>(.*?)</figcaption>#is', $html, $m ) ) {
		$caption = htt_book3d_line( $m[1] );

		if ( '' !== $caption ) {
			$lines[] = htt_book3d_escape( $caption );
		}
	}

	return $lines;
}

/**
 * Ruột sách của một bài viết.
 *
 * Tiêu đề bài KHÔNG vào đây: nó đã nằm trên bìa, và trang đầu của sách
 * nên mở ra là vào thẳng nội dung.
 *
 * @param int $post_id Bài viết.
 * @return array{content:string,media:array} Chuỗi nội dung và các ảnh.
 */
function htt_book3d_content( $post_id ) {
	$post = get_post( (int) $post_id );

	if ( ! $post ) {
		return array(
			'content' => '',
			'media'   => array(),
		);
	}

	$media = array();
	$lines = htt_book3d_blocks_to_lines( parse_blocks( $post->post_content ), $media );

	// Hai lệnh ngắt trang liền nhau tạo một trang trắng giữa sách, và
	// một lệnh ngắt ở đầu hay cuối thì không ngắt gì cả.
	$clean = array();

	foreach ( $lines as $line ) {
		if ( '---' === $line && ( ! $clean || '---' === end( $clean ) ) ) {
			continue;
		}

		$clean[] = $line;
	}

	while ( $clean && '---' === end( $clean ) ) {
		array_pop( $clean );
	}

	return array(
		'content' => implode( "\n", $clean ),
		'media'   => $media,
	);
}

/**
 * ==========================================================================
 * Nơi đặt bộ sách và khai báo của từng bài
 * ==========================================================================
 */

/**
 * Đường dẫn web tới thư mục chứa index.html của sách.
 *
 * Mặc định là thư mục trong theme, nên tải giao diện lên là có sách
 * luôn. Option cho phép trỏ đi nơi khác khi muốn thay bộ sách mà không
 * chờ lần deploy theme tiếp theo.
 *
 * @return string Địa chỉ không có dấu / ở cuối, rỗng khi không tìm thấy.
 */
function htt_book3d_base_url() {
	$custom = trim( (string) get_option( 'htt_book3d_base', '' ) );

	if ( '' !== $custom ) {
		return untrailingslashit( $custom );
	}

	// Chỉ dùng thư mục trong theme khi nó thật sự có ở đó: thiếu file thì
	// thà không hiện sách 3D, còn hơn nhúng một iframe trắng.
	if ( ! file_exists( get_stylesheet_directory() . HTT_BOOK3D_DIR . '/index.html' ) ) {
		return '';
	}

	return get_stylesheet_directory_uri() . HTT_BOOK3D_DIR;
}

/**
 * Cài đặt chung cho mọi cuốn sách 3D.
 *
 * Khổ sách, cỡ chữ và loại giấy thường giống nhau ở mọi cuốn - cùng một
 * nhà in, cùng một khuôn. Khai ở đây một lần thay vì lặp lại trong từng
 * bài; bài nào khác khuôn thì vẫn đè riêng được.
 *
 * Ba tầng, rơi dần xuống: trường trong bài → cài đặt này → hằng số trong
 * code. Tầng cuối để một option bị xoá hay chứa rác cũng không làm sách
 * dựng ra sai khuôn.
 *
 * @return array
 */
function htt_book3d_defaults( $reload = false ) {
	static $cache = null;

	if ( null !== $cache && ! $reload ) {
		return $cache;
	}

	// Đọc một số từ option, kẹp vào khoảng dùng được. Chuỗi rỗng hay chữ
	// thì coi như chưa khai, lấy hằng số.
	$num = function ( $key, $default, $min, $max ) {
		$raw = str_replace( ',', '.', trim( (string) get_option( 'htt_book3d_default_' . $key, '' ) ) );

		if ( '' === $raw || ! is_numeric( $raw ) ) {
			return $default;
		}

		return min( $max, max( $min, (float) $raw ) );
	};

	// Cách giở sách: single = từng trang một, double = hai trang đối nhau,
	// auto = tuỳ khung rộng hay hẹp. Giá trị lạ thì coi như auto.
	$spread = (string) get_option( 'htt_book3d_default_spread', 'single' );

	if ( ! in_array( $spread, array( 'single', 'double', 'auto' ), true ) ) {
		$spread = 'single';
	}

	$cache = array(
		'w'      => $num( 'w', HTT_BOOK3D_WIDTH, 5, 40 ),
		'h'      => $num( 'h', HTT_BOOK3D_HEIGHT, 5, 40 ),
		'font'   => $num( 'font', 1, 0.6, 2 ),
		'paper'  => $num( 'paper', HTT_BOOK3D_PAPER, 0.05, 0.3 ),
		'limit'  => min( 2000, absint( get_option( 'htt_book3d_default_limit', 0 ) ) ),
		'bg'     => htt_book3d_hex( (string) get_option( 'htt_book3d_default_bg', '' ) ),
		'spread' => $spread,
	);

	return $cache;
}

/**
 * Một mã màu hex hợp lệ, hoặc chuỗi rỗng.
 *
 * Nhận cả #abc lẫn #aabbcc, có hoặc không có dấu thăng. Trả về dạng
 * #aabbcc viết thường để mọi nơi dùng một kiểu.
 *
 * @param string $value Giá trị thô.
 * @return string
 */
function htt_book3d_hex( $value ) {
	$value = ltrim( strtolower( trim( (string) $value ) ), '#' );

	if ( ! preg_match( '/^([0-9a-f]{3}|[0-9a-f]{6})$/', $value ) ) {
		return '';
	}

	// #abc là cách viết tắt của #aabbcc; tự giãn ra cho đồng nhất.
	if ( 3 === strlen( $value ) ) {
		$value = $value[0] . $value[0] . $value[1] . $value[1] . $value[2] . $value[2];
	}

	return '#' . $value;
}

/**
 * Đổi cài đặt chung thì mọi cuốn sách phải dựng lại.
 *
 * Hai việc: bỏ cấu hình đã lưu sẵn của từng bài, và đọc lại option trong
 * chính request này - trang Cài đặt ghi nhiều option trong một lần bấm
 * Lưu, nên giá trị cũ đã nằm trong bộ nhớ tĩnh từ trước đó.
 */
function htt_book3d_defaults_changed() {
	htt_book3d_defaults( true );
	htt_book3d_flush_all();

	// Địa chỉ iframe mang theo dấu thời gian để trình duyệt biết khi nào
	// cần tải lại cấu hình. Sửa bài thì post_modified đổi, còn đổi cài đặt
	// chung thì không - nên phải tự ghi lại mốc thời gian ở đây.
	update_option( 'htt_book3d_defaults_time', (string) time(), false );
}

/**
 * Khai báo sách 3D của một bài, đã điền sẵn giá trị mặc định.
 *
 * Không có "số trang": sách tự tính độ dày gáy theo số trang mà trình
 * duyệt chia ra được, nên không ai phải đếm.
 *
 * @param int $post_id Bài viết.
 * @return array
 */
function htt_book3d_meta( $post_id ) {
	$post_id  = (int) $post_id;
	$defaults = htt_book3d_defaults();

	// Trường để trống trong bài nghĩa là "theo cài đặt chung", nên giá
	// trị mặc định lấy từ đó chứ không phải một con số cố định.
	$num = function ( $key, $default ) use ( $post_id ) {
		$raw = get_post_meta( $post_id, 'htt_book3d_' . $key, true );

		return '' === $raw || null === $raw ? $default : (float) $raw;
	};

	// Giới hạn đọc thử cũng vậy, nhưng nó là số nguyên và giá trị 0 có
	// nghĩa riêng ("cho xem trọn bài"), nên không gộp chung với $num:
	// một bài cố ý đặt 0 phải thắng được cài đặt chung đang là 12.
	$limit = get_post_meta( $post_id, 'htt_book3d_limit', true );

	return array(
		'show'   => (bool) get_post_meta( $post_id, 'htt_book3d_show', true ),
		'front'  => (int) get_post_meta( $post_id, 'htt_book3d_front', true ),
		'back'   => (int) get_post_meta( $post_id, 'htt_book3d_back', true ),
		'spine'  => (int) get_post_meta( $post_id, 'htt_book3d_spine', true ),
		// Tiêu đề chạy ở đầu trang. Khai riêng từng bài, không có cài đặt
		// chung: mỗi cuốn một tên sách, một tên tác giả.
		'head_l' => trim( (string) get_post_meta( $post_id, 'htt_book3d_head_l', true ) ),
		'head_r' => trim( (string) get_post_meta( $post_id, 'htt_book3d_head_r', true ) ),
		'w'      => $num( 'w', $defaults['w'] ),
		'h'      => $num( 'h', $defaults['h'] ),
		'font'   => $num( 'font', $defaults['font'] ),
		'limit'  => '' === $limit || null === $limit ? $defaults['limit'] : (int) $limit,
		'paper'  => $num( 'paper', $defaults['paper'] ),
		// Hai thứ này chỉ khai ở cài đặt chung: chúng nói về cách trình
		// bày cái popup, không phải về riêng cuốn sách nào.
		'bg'     => $defaults['bg'],
		'spread' => $defaults['spread'],
	);
}

/**
 * Bài này có hiện được sách 3D không?
 *
 * @param int $post_id Bài viết.
 * @return bool
 */
function htt_book3d_enabled( $post_id ) {
	$m = htt_book3d_meta( $post_id );

	if ( ! $m['show'] || '' === htt_book3d_base_url() ) {
		return false;
	}

	// Không có ảnh nào làm bìa trước thì cuốn sách sẽ là một khối trơn
	// không ai nhận ra là sách gì - thà giữ ảnh sản phẩm phẳng.
	return (bool) htt_book3d_front_id( $post_id );
}

/**
 * Ảnh dùng làm bìa trước.
 *
 * Thứ tự: bìa đã khai riêng, rồi ảnh sản phẩm đầu tiên của popup đặt
 * in, rồi ảnh đại diện của bài. Nhờ vậy một bài đã có ảnh bìa sách
 * trong popup thì bật sách 3D lên là chạy, không phải chọn lại ảnh.
 *
 * @param int $post_id Bài viết.
 * @return int Attachment id, 0 nếu không có ảnh nào.
 */
function htt_book3d_front_id( $post_id ) {
	$post_id = (int) $post_id;
	$front   = (int) get_post_meta( $post_id, 'htt_book3d_front', true );

	if ( $front ) {
		return $front;
	}

	if ( function_exists( 'htt_print_image_ids' ) ) {
		$ids = htt_print_image_ids( $post_id );

		if ( $ids ) {
			return (int) $ids[0];
		}
	}

	return (int) get_post_thumbnail_id( $post_id );
}

/**
 * ==========================================================================
 * Cấu hình sách gửi cho trình duyệt
 * ==========================================================================
 */

/**
 * Toàn bộ cấu hình của một cuốn sách, đúng dạng book.json.
 *
 * Hai khoá thêm so với book.json gốc:
 *
 * - previewPages: số trang được giữ lại. Việc cắt phải làm ở trình
 *   duyệt, vì chỉ nó biết trang thứ mấy bắt đầu ở đâu.
 * - texts.endPage: dòng chữ đóng lại phần đọc thử.
 *
 * Khoá `cta` thì cố ý để trống: popup đã có nút "Thêm vào giỏ" thật
 * ngay dưới sách, và cta của book3d mở link bằng target _top nên sẽ
 * điều hướng cả trang chứa iframe.
 *
 * @param int $post_id Bài viết.
 * @return array|null Mảng cấu hình, hoặc null nếu bài không dùng sách 3D.
 */
function htt_book3d_config( $post_id ) {
	$post = get_post( (int) $post_id );

	if ( ! $post ) {
		return null;
	}

	$post_id = (int) $post->ID;
	$m       = htt_book3d_meta( $post_id );
	$front   = htt_book3d_front_id( $post_id );
	$body    = htt_book3d_content( $post_id );

	$url = function ( $id ) {
		return $id ? (string) wp_get_attachment_image_url( (int) $id, 'full' ) : '';
	};

	return array(
		'title'          => get_the_title( $post_id ),
		'author'         => get_the_author_meta( 'display_name', (int) $post->post_author ),
		'size'           => array(
			'width'  => $m['w'],
			'height' => $m['h'],
		),
		// Độ dày gáy tính theo số trang thật của bài, kể cả khi phần đọc
		// thử chỉ còn vài trang: người mua cần thấy cuốn sách dày bao
		// nhiêu, không phải bản đọc thử mỏng bao nhiêu.
		'autoThickness'  => true,
		'paperThickness' => $m['paper'],
		'cover'          => array(
			'front' => $url( $front ),
			'back'  => $url( $m['back'] ),
			'spine' => $url( $m['spine'] ),
		),
		'fontScale'      => $m['font'],
		'content'        => $body['content'],
		'media'          => $body['media'],
		'previewPages'   => $m['limit'],
		'spread'         => $m['spread'],
		// Tiêu đề chạy ở đầu mỗi trang. Để trống thì đầu trang bỏ trắng,
		// số trang tự xuống chân trang.
		'runningHead'    => array(
			'left'  => $m['head_l'],
			'right' => $m['head_r'],
		),
		// Nền phía sau cuốn sách. Ảnh nền của bộ sách gốc bị tắt hẳn:
		// nó là một dải màu chuyển, vẽ đè lên màu phẳng nên đặt màu mà
		// không tắt ảnh thì chẳng thấy màu đâu.
		'background'     => array(
			'color' => $m['bg'],
			'image' => '',
		),
		'texts'          => array(
			'hint'        => 'Bấm vào sách để đọc thử',
			'loading'     => 'Đang tải sách…',
			'insideCover' => $m['limit'] > 0 ? 'Bản đọc thử' : '',
			'endPage'     => $m['limit'] > 0 ? 'Hết phần đọc thử' : 'Hết',
		),
		'cta'            => null,
	);
}

/**
 * Địa chỉ iframe cho sách của một bài.
 *
 * @param int $post_id Bài viết.
 * @return string Rỗng nếu chưa khai báo được bộ sách.
 */
function htt_book3d_iframe_url( $post_id ) {
	$base = htt_book3d_base_url();

	if ( '' === $base ) {
		return '';
	}

	// Dấu thời gian đi kèm địa chỉ: trình duyệt được phép giữ cấu hình
	// trong bộ nhớ đệm (xem htt_book3d_rest_config), nên nếu địa chỉ
	// không đổi thì sửa xong vẫn thấy sách cũ hàng phút. Hai mốc, vì
	// sách đổi theo cả nội dung bài lẫn cài đặt chung.
	$config = add_query_arg(
		'v',
		get_post_modified_time( 'U', true, $post_id ) . '-' . (int) get_option( 'htt_book3d_defaults_time', 0 ),
		rest_url( 'htt/v1/book3d/' . (int) $post_id )
	);

	// Bài chưa đăng chỉ người có quyền sửa mới xem được, nên phải mang
	// theo nonce để REST nhận ra người đang đăng nhập.
	if ( 'publish' !== get_post_status( $post_id ) ) {
		$config = add_query_arg( '_wpnonce', wp_create_nonce( 'wp_rest' ), $config );
	}

	return add_query_arg(
		array(
			'config' => rawurlencode( $config ),
			'embed'  => '1',
			// Phiên bản mã nguồn của bộ sách. Không có nó thì trình duyệt
			// giữ book3d.js trong bộ nhớ đệm rất lâu, và một lần sửa bộ
			// sách có thể mất nhiều ngày mới tới được người đọc.
			'ver'    => htt_book3d_version(),
		),
		$base . '/index.html'
	);
}

/**
 * Dấu phiên bản của mã nguồn bộ sách.
 *
 * Lấy theo lần sửa gần nhất của book3d.js - file đổi thường xuyên nhất.
 * Bộ sách để ngoài theme (khai trong Cài đặt) thì không đọc được thời
 * gian sửa, lúc đó lấy theo phiên bản giao diện.
 *
 * @return string
 */
function htt_book3d_version() {
	$path = get_stylesheet_directory() . HTT_BOOK3D_DIR . '/js/book3d.js';

	if ( '' === (string) get_option( 'htt_book3d_base', '' ) && file_exists( $path ) ) {
		return (string) filemtime( $path );
	}

	$theme = wp_get_theme();

	return (string) ( $theme->get( 'Version' ) ?: '1' );
}

/**
 * ==========================================================================
 * REST: cấu hình sách của một bài
 * ==========================================================================
 */

/**
 * Đăng ký đường dẫn đọc cấu hình sách.
 */
function htt_book3d_rest_routes() {
	register_rest_route(
		'htt/v1',
		'/book3d/(?P<id>\d+)',
		array(
			'methods'             => 'GET',
			'callback'            => 'htt_book3d_rest_config',
			'args'                => array(
				'id' => array(
					'validate_callback' => function ( $value ) {
						return (bool) absint( $value );
					},
				),
			),
			'permission_callback' => 'htt_book3d_rest_allowed',
		)
	);
}
add_action( 'rest_api_init', 'htt_book3d_rest_routes' );

/**
 * Ai được đọc cấu hình sách của bài này?
 *
 * Bài đã đăng thì ai cũng được - nội dung vốn đã công khai trên trang.
 * Bài nháp, bài riêng tư hay bài hẹn giờ thì chỉ người sửa được bài
 * mới xem, nếu không đường dẫn này thành cửa sau đọc bài chưa đăng.
 *
 * @param WP_REST_Request $request Yêu cầu.
 * @return bool|WP_Error
 */
function htt_book3d_rest_allowed( $request ) {
	$post_id = absint( $request['id'] );
	$post    = get_post( $post_id );

	if ( ! $post || 'post' !== $post->post_type ) {
		return new WP_Error( 'htt_book3d_missing', 'Không có bài viết này.', array( 'status' => 404 ) );
	}

	if ( 'publish' === $post->post_status ) {
		return true;
	}

	if ( current_user_can( 'edit_post', $post_id ) ) {
		return true;
	}

	return new WP_Error(
		'htt_book3d_forbidden',
		'Bài này chưa đăng.',
		array( 'status' => is_user_logged_in() ? 403 : 401 )
	);
}

/**
 * Trả cấu hình sách.
 *
 * Bài dài vài chục nghìn chữ thì việc tách khối và dọn chữ không nên
 * làm lại mỗi lần có người mở popup, nên kết quả được giữ lại và chỉ
 * dựng lại khi bài được sửa (xem htt_book3d_flush_cache).
 *
 * @param WP_REST_Request $request Yêu cầu.
 * @return WP_REST_Response|WP_Error
 */
function htt_book3d_rest_config( $request ) {
	$post_id = absint( $request['id'] );
	$key     = 'htt_book3d_' . $post_id;
	$cached  = get_transient( $key );
	$config  = false === $cached ? htt_book3d_config( $post_id ) : $cached;

	if ( ! $config ) {
		return new WP_Error( 'htt_book3d_missing', 'Không có bài viết này.', array( 'status' => 404 ) );
	}

	if ( false === $cached ) {
		set_transient( $key, $config, DAY_IN_SECONDS );
	}

	$response = rest_ensure_response( $config );

	// Nội dung chỉ đổi khi bài được sửa, nên để trình duyệt dùng lại bản
	// đã tải: mở lại popup không phải chờ tải sách lần nữa.
	$response->header( 'Cache-Control', 'public, max-age=300' );
	$response->header( 'Last-Modified', gmdate( 'D, d M Y H:i:s', (int) get_post_modified_time( 'U', true, $post_id ) ) . ' GMT' );

	return $response;
}

/**
 * Dựng lại cấu hình khi bài hoặc ảnh thay đổi.
 *
 * @param int $post_id Bài vừa lưu.
 */
function htt_book3d_flush_cache( $post_id ) {
	delete_transient( 'htt_book3d_' . (int) $post_id );
}
add_action( 'save_post_post', 'htt_book3d_flush_cache' );
add_action( 'deleted_post', 'htt_book3d_flush_cache' );

/**
 * Xoá cấu hình của mọi bài khi một ảnh bị sửa hoặc xoá.
 *
 * Một ảnh có thể là bìa của nhiều cuốn, mà truy ngược từ ảnh về bài
 * thì tốn hơn là dựng lại cấu hình, nên cứ xoá hết.
 */
function htt_book3d_flush_all() {
	global $wpdb;

	// Transient không có API liệt kê theo tiền tố, nên phải hỏi thẳng.
	// Lấy tên trước rồi xoá từng cái qua delete_transient, thay vì một
	// câu DELETE: xoá thẳng trong bảng thì bản sao trong object cache
	// (Redis, Memcached) vẫn còn, và site sẽ đọc lại đúng cấu hình cũ.
	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( '_transient_htt_book3d_' ) . '%'
		)
	);

	foreach ( (array) $names as $name ) {
		delete_transient( substr( $name, strlen( '_transient_' ) ) );
	}
}
add_action( 'delete_attachment', 'htt_book3d_flush_all' );
add_action( 'edit_attachment', 'htt_book3d_flush_all' );
