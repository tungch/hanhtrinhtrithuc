<?php
/**
 * Sách 3D - phần khai báo trong trang quản trị.
 *
 * Ảnh bìa, khổ sách và giới hạn đọc thử của mỗi cuốn khai ở đây. Ruột
 * sách thì không: nó lấy từ nội dung bài viết (xem inc/book3d.php).
 *
 * @package HanhTrinhTriThuc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mục "Sách 3D" trong Cài đặt > Tổng quan.
 *
 * Khuôn sách trước, đường dẫn bộ sách sau: khổ sách và cỡ chữ là thứ
 * thỉnh thoảng còn chỉnh, còn đường dẫn thì khai một lần rồi thôi.
 */
function htt_book3d_settings() {
	htt_book3d_default_settings();

	register_setting( 'general', 'htt_book3d_base', array( 'sanitize_callback' => 'sanitize_text_field' ) );

	add_settings_field(
		'htt_book3d_base',
		'Địa chỉ bộ sách',
		function () {
			$value   = (string) get_option( 'htt_book3d_base', '' );
			$default = get_stylesheet_directory_uri() . HTT_BOOK3D_DIR;
			$ok      = file_exists( get_stylesheet_directory() . HTT_BOOK3D_DIR . '/index.html' );

			printf(
				'<input type="url" name="htt_book3d_base" id="htt_book3d_base" value="%1$s"'
				. ' class="regular-text" placeholder="%2$s" />'
				. '<p class="description">Để trống thì dùng bộ sách kèm theo giao diện:<br /><code>%2$s</code>'
				. ' &mdash; %3$s</p>',
				esc_attr( $value ),
				esc_url( $default ),
				$ok
					? '<strong style="color:#008a20">đã có</strong>'
					: '<strong style="color:#d63638">không tìm thấy index.html</strong>'
			);
		},
		'general',
		'htt_book3d'
	);
}
add_action( 'admin_init', 'htt_book3d_settings' );

/**
 * Khuôn sách dùng chung cho mọi bài.
 *
 * Khổ sách, cỡ chữ và loại giấy thường giống nhau ở mọi cuốn - cùng một
 * nhà in. Khai ở đây một lần; bài nào khác khuôn thì vẫn đè riêng trong
 * hộp "Sách 3D" của bài đó.
 */
function htt_book3d_default_settings() {
	add_settings_section(
		'htt_book3d',
		'Sách 3D',
		function () {
			echo '<p>Khuôn chung cho mọi cuốn sách 3D. Từng bài viết vẫn đặt '
				. 'riêng được trong hộp <strong>Sách 3D</strong> ở trang sửa bài; '
				. 'bài nào để trống thì theo những giá trị dưới đây.</p>';
		},
		'general'
	);

	// Đổi bất kỳ giá trị nào ở đây là mọi cuốn sách phải dựng lại.
	foreach ( array( 'w', 'h', 'font', 'paper', 'limit', 'bg', 'spread' ) as $key ) {
		$option = 'htt_book3d_default_' . $key;

		register_setting(
			'general',
			$option,
			array( 'sanitize_callback' => 'htt_book3d_sanitize_default' )
		);

		add_action( 'update_option_' . $option, 'htt_book3d_defaults_changed' );
		add_action( 'add_option_' . $option, 'htt_book3d_defaults_changed' );
	}

	add_settings_field(
		'htt_book3d_default_spread',
		'Cách giở sách',
		'htt_book3d_field_spread',
		'general',
		'htt_book3d'
	);

	add_settings_field(
		'htt_book3d_default_size',
		'Khổ sách',
		'htt_book3d_field_size',
		'general',
		'htt_book3d'
	);

	add_settings_field(
		'htt_book3d_default_font',
		'Cỡ chữ',
		'htt_book3d_field_font',
		'general',
		'htt_book3d'
	);

	add_settings_field(
		'htt_book3d_default_paper',
		'Độ dày giấy',
		'htt_book3d_field_paper',
		'general',
		'htt_book3d'
	);

	add_settings_field(
		'htt_book3d_default_limit',
		'Giới hạn đọc thử',
		'htt_book3d_field_limit',
		'general',
		'htt_book3d'
	);

	add_settings_field(
		'htt_book3d_default_bg',
		'Màu nền',
		'htt_book3d_field_bg',
		'general',
		'htt_book3d'
	);
}

/**
 * Làm sạch một giá trị khuôn sách.
 *
 * Chỉ cắt khoảng trắng và bỏ thẻ; việc kẹp số vào khoảng dùng được để
 * cho htt_book3d_defaults() lo, vì nó là nơi duy nhất biết khoảng hợp lệ
 * của từng trường. Nhờ vậy một giá trị gõ nhầm vẫn nằm nguyên trong ô
 * cho người viết thấy mà sửa, thay vì bị âm thầm nắn lại.
 *
 * @param mixed $value Giá trị thô từ form.
 * @return string
 */
function htt_book3d_sanitize_default( $value ) {
	return trim( sanitize_text_field( (string) $value ) );
}

/**
 * Ô chọn cách giở sách.
 */
function htt_book3d_field_spread() {
	$value = (string) get_option( 'htt_book3d_default_spread', 'single' );

	$modes = array(
		'single' => array(
			'Từng trang một',
			'Sách mở ra chỉ hiện một trang, chữ to gần gấp đôi. Vuốt ngang '
				. 'hoặc chạm nửa màn hình để sang trang.',
		),
		'double' => array(
			'Hai trang đối nhau',
			'Giữ đúng dáng cuốn sách đang mở, nhưng mỗi trang chỉ còn một '
				. 'nửa bề ngang nên chữ nhỏ hơn.',
		),
		'auto'   => array(
			'Tự chọn theo màn hình',
			'Màn hình rộng thì hai trang, màn hình hẹp thì một trang.',
		),
	);

	$html = '';

	foreach ( $modes as $mode => $info ) {
		list( $label, $note ) = $info;

		$html .= sprintf(
			'<label style="display:block;margin-bottom:6px">'
			. '<input type="radio" name="htt_book3d_default_spread" value="%1$s" %2$s /> '
			. '<strong>%3$s</strong>'
			. '<span class="description" style="display:block;margin-left:24px">%4$s</span>'
			. '</label>',
			esc_attr( $mode ),
			checked( $mode, '' === $value ? 'single' : $value, false ),
			esc_html( $label ),
			esc_html( $note )
		);
	}

	echo '<fieldset>' . $html . '</fieldset>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- dựng bằng sprintf ở trên.
}

/**
 * Ô khổ sách: rộng × cao.
 */
function htt_book3d_field_size() {
	// type="text" chứ không phải "number": ô number coi "13,5" là không
	// hợp lệ rồi gửi lên chuỗi rỗng, nên người quen gõ dấu phẩy thập phân
	// sẽ mất giá trị vừa nhập mà không được báo gì.
	printf(
		'<input type="text" name="htt_book3d_default_w" class="small-text" inputmode="decimal"'
		. ' value="%1$s" placeholder="%3$s" /> &times; '
		. '<input type="text" name="htt_book3d_default_h" class="small-text" inputmode="decimal"'
		. ' value="%2$s" placeholder="%4$s" /> cm'
		. '<p class="description">Rộng &times; cao. Để trống thì dùng %3$s &times; %4$s.</p>',
		esc_attr( (string) get_option( 'htt_book3d_default_w', '' ) ),
		esc_attr( (string) get_option( 'htt_book3d_default_h', '' ) ),
		esc_attr( number_format_i18n( HTT_BOOK3D_WIDTH, 1 ) ),
		esc_attr( number_format_i18n( HTT_BOOK3D_HEIGHT, 1 ) )
	);
}

/**
 * Ô cỡ chữ.
 */
function htt_book3d_field_font() {
	$value   = (string) get_option( 'htt_book3d_default_font', '' );
	$options = '';

	foreach ( array(
		'0.85' => 'Nhỏ',
		'1'    => 'Vừa',
		'1.2'  => 'Lớn',
	) as $size => $label ) {
		$options .= sprintf(
			'<option value="%1$s" %2$s>%3$s</option>',
			esc_attr( $size ),
			selected( (float) $size === (float) ( '' === $value ? 1 : $value ), true, false ),
			esc_html( $label )
		);
	}

	printf(
		'<select name="htt_book3d_default_font" id="htt_book3d_default_font">%s</select>'
		. '<p class="description">Chữ càng lớn thì sách càng nhiều trang, và gáy càng dày.</p>',
		$options // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- dựng bằng sprintf ở trên.
	);
}

/**
 * Ô độ dày giấy.
 */
function htt_book3d_field_paper() {
	printf(
		'<input type="text" name="htt_book3d_default_paper" id="htt_book3d_default_paper"'
		. ' class="small-text" inputmode="decimal" value="%1$s" placeholder="%2$s" /> mm/tờ'
		. '<p class="description">0,08 giấy mỏng &middot; 0,1 thường &middot; 0,13 dày. '
		. 'Quyết định độ dày gáy sách.</p>',
		esc_attr( (string) get_option( 'htt_book3d_default_paper', '' ) ),
		esc_attr( number_format_i18n( HTT_BOOK3D_PAPER, 2 ) )
	);
}

/**
 * Ô giới hạn đọc thử.
 */
function htt_book3d_field_limit() {
	printf(
		'<input type="number" name="htt_book3d_default_limit" id="htt_book3d_default_limit"'
		. ' class="small-text" min="0" max="2000" step="1" value="%1$s" /> trang'
		. '<p class="description">Sách chỉ gồm số trang đầu này, rồi đóng lại bằng một trang '
		. '&ldquo;Hết phần đọc thử&rdquo;. Để <code>0</code> nếu cho xem trọn bài. '
		. 'Gáy sách vẫn dày đúng theo toàn bộ nội dung.</p>',
		esc_attr( (string) get_option( 'htt_book3d_default_limit', '' ) )
	);
}

/**
 * Ô màu nền.
 */
function htt_book3d_field_bg() {
	$value = htt_book3d_hex( (string) get_option( 'htt_book3d_default_bg', '' ) );

	// Hai ô cùng một giá trị: bảng màu để chọn nhanh, ô chữ để dán mã
	// hex và - quan trọng hơn - để xoá được về trống, việc mà type="color"
	// không làm được vì nó luôn phải có một màu.
	printf(
		'<span class="htt-book3d-color">'
		. '<input type="color" id="htt_book3d_default_bg_picker" value="%1$s"'
		. ' aria-label="Chọn màu nền" />'
		. '<input type="text" name="htt_book3d_default_bg" id="htt_book3d_default_bg"'
		. ' class="regular-text code" value="%2$s" placeholder="theo nền của giao diện"'
		. ' spellcheck="false" />'
		. '<button type="button" class="button button-small htt-book3d-color-clear"%3$s>Bỏ màu</button>'
		. '</span>'
		. '<p class="description">Màu phía sau cuốn sách, dùng cho cả chế độ sáng lẫn tối. '
		. 'Để trống thì nền đi theo giao diện: trắng ngà khi sáng, xám đậm khi tối.</p>'
		. '<style>'
		. '.htt-book3d-color{display:flex;align-items:center;gap:8px}'
		. '.htt-book3d-color input[type=color]{width:40px;height:30px;padding:2px;cursor:pointer}'
		. '</style>',
		esc_attr( '' === $value ? '#f6f7f9' : $value ),
		esc_attr( $value ),
		$value ? '' : ' style="display:none"'
	);
}


/**
 * Hộp khai báo sách 3D trong trang sửa bài.
 */
function htt_book3d_meta_box() {
	add_meta_box( 'htt-book3d', 'Sách 3D', 'htt_book3d_meta_box_html', 'post', 'side' );
}
add_action( 'add_meta_boxes', 'htt_book3d_meta_box' );

/**
 * Một ô chọn ảnh bìa.
 *
 * @param string $field Tên trường: front | back | spine.
 * @param string $label Nhãn hiển thị.
 * @param int    $id    Attachment đang chọn.
 * @param string $hint  Giải thích khi để trống.
 */
function htt_book3d_image_field( $field, $label, $id, $hint ) {
	$name = 'htt_book3d_' . $field;
	?>
	<div class="htt-book3d-pick">
		<strong><?php echo esc_html( $label ); ?></strong>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>"
			id="<?php echo esc_attr( $name ); ?>"
			value="<?php echo esc_attr( (string) $id ); ?>" />
		<div class="htt-book3d-thumb">
			<?php
			if ( $id ) {
				echo wp_get_attachment_image( $id, 'thumbnail' );
			}
			?>
		</div>
		<p>
			<button type="button" class="button button-small htt-book3d-choose">Chọn ảnh</button>
			<button type="button" class="button-link delete htt-book3d-drop"
				<?php echo $id ? '' : 'style="display:none"'; ?>>Bỏ</button>
		</p>
		<p class="description"><?php echo esc_html( $hint ); ?></p>
	</div>
	<?php
}

/**
 * Nội dung hộp khai báo.
 *
 * @param WP_Post $post Bài đang sửa.
 */
function htt_book3d_meta_box_html( $post ) {
	$m    = htt_book3d_meta( $post->ID );
	$base = htt_book3d_base_url();
	wp_nonce_field( 'htt_book3d_save', 'htt_book3d_nonce' );
	?>
	<p>
		<label>
			<input type="checkbox" name="htt_book3d_show" value="1" <?php checked( $m['show'] ); ?> />
			Hiện sách 3D trong popup đặt in
		</label>
	</p>
	<p class="description">
		Ruột sách lấy từ chính nội dung bài này, không phải soạn riêng.
		Sửa bài là sách đổi theo.
	</p>

	<?php if ( '' === $base ) : ?>
		<p class="notice notice-warning" style="padding:6px 10px;margin:8px 0">
			Chưa tìm thấy bộ sách 3D. Khai báo địa chỉ ở
			<a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">Cài đặt &rsaquo; Tổng quan</a>.
		</p>
	<?php endif; ?>

	<hr />

	<?php
	htt_book3d_image_field(
		'front',
		'Bìa trước',
		$m['front'],
		'Để trống thì lấy ảnh sản phẩm đầu tiên, rồi đến ảnh đại diện của bài.'
	);
	htt_book3d_image_field(
		'back',
		'Bìa sau',
		$m['back'],
		'Để trống thì sách tự vẽ một bìa sau trơn.'
	);
	htt_book3d_image_field(
		'spine',
		'Gáy sách',
		$m['spine'],
		'Ảnh dọc, hẹp. Để trống thì gáy lấy màu từ mép bìa trước.'
	);
	?>

	<hr />

	<p><strong>Tiêu đề chạy</strong></p>
	<p>
		<label for="htt_book3d_head_l">Trang bên trái</label><br />
		<input type="text" name="htt_book3d_head_l" id="htt_book3d_head_l"
			class="widefat" maxlength="120"
			value="<?php echo esc_attr( $m['head_l'] ); ?>" />
	</p>
	<p>
		<label for="htt_book3d_head_r">Trang bên phải</label><br />
		<input type="text" name="htt_book3d_head_r" id="htt_book3d_head_r"
			class="widefat" maxlength="120"
			value="<?php echo esc_attr( $m['head_r'] ); ?>" />
	</p>
	<p class="description">
		Dòng chữ nhỏ in ở đầu mỗi trang, như sách giấy &mdash; thường là
		tên tác giả bên trái, tên sách bên phải. Để trống thì đầu trang bỏ
		trắng và số trang chuyển xuống chân trang. Trang mở chương không
		có dòng này.
	</p>

	<hr />

	<?php
	// Ô bỏ trống nghĩa là "theo khuôn chung", nên chỉ điền sẵn khi bài
	// này thật sự có giá trị riêng. Con số của khuôn chung nằm ở
	// placeholder - thấy được mà không bị lưu thành giá trị riêng.
	$raw  = function ( $key ) use ( $post ) {
		return (string) get_post_meta( $post->ID, 'htt_book3d_' . $key, true );
	};
	$hint = htt_book3d_defaults();

	// Viết số như người ta đọc: 14 chứ không phải 14,0; 0,1 chứ không
	// phải 0,10. number_format luôn giữ đủ số lẻ nên phải tự cắt đuôi.
	$tidy = function ( $value ) {
		return rtrim( rtrim( number_format( (float) $value, 2, ',', '' ), '0' ), ',' );
	};
	$link = sprintf(
		'<a href="%s#htt_book3d">cài đặt chung</a>',
		esc_url( admin_url( 'options-general.php' ) )
	);
	?>

	<p>
		<label for="htt_book3d_limit"><strong>Giới hạn đọc thử</strong></label><br />
		<input type="number" name="htt_book3d_limit" id="htt_book3d_limit" class="small-text"
			min="0" max="2000" step="1"
			placeholder="<?php echo esc_attr( (string) $hint['limit'] ); ?>"
			value="<?php echo esc_attr( $raw( 'limit' ) ); ?>" /> trang
	</p>
	<p class="description">
		Sách chỉ gồm số trang đầu này, rồi đóng lại bằng một trang
		&ldquo;Hết phần đọc thử&rdquo;. Đặt <code>0</code> nếu bài này cho xem
		trọn vẹn. Gáy sách vẫn dày đúng theo toàn bộ nội dung.
	</p>

	<?php
	// type="text" chu khong phai "number": o number tu coi "13,5" la khong
	// hop le roi gui len chuoi rong, nen nguoi quen go dau phay thap phan
	// se mat gia tri vua nhap ma khong duoc bao gi. Chu so van go duoc
	// thoai mai, va ban phim dien thoai van hien ban phim so nho inputmode.
	?>
	<p>
		<strong>Khổ sách</strong><br />
		<input type="text" name="htt_book3d_w" class="small-text" inputmode="decimal"
			placeholder="<?php echo esc_attr( $tidy( $hint['w'] ) ); ?>"
			value="<?php echo esc_attr( $raw( 'w' ) ); ?>" /> &times;
		<input type="text" name="htt_book3d_h" class="small-text" inputmode="decimal"
			placeholder="<?php echo esc_attr( $tidy( $hint['h'] ) ); ?>"
			value="<?php echo esc_attr( $raw( 'h' ) ); ?>" /> cm
	</p>
	<p class="description">Rộng &times; cao, tính bằng xăng-ti-mét.</p>

	<p>
		<label for="htt_book3d_font"><strong>Cỡ chữ</strong></label><br />
		<select name="htt_book3d_font" id="htt_book3d_font">
			<?php
			$font  = $raw( 'font' );
			$sizes = array(
				'0.85' => 'Nhỏ',
				'1'    => 'Vừa',
				'1.2'  => 'Lớn',
			);

			printf(
				'<option value="" %s>Theo cài đặt chung (%s)</option>',
				selected( '', $font, false ),
				esc_html( $sizes[ rtrim( rtrim( number_format( $hint['font'], 2, '.', '' ), '0' ), '.' ) ] ?? 'Vừa' )
			);

			foreach ( $sizes as $value => $text ) {
				printf(
					'<option value="%1$s" %2$s>%3$s</option>',
					esc_attr( $value ),
					selected( '' !== $font && (float) $value === (float) $font, true, false ),
					esc_html( $text )
				);
			}
			?>
		</select>
	</p>

	<p>
		<label for="htt_book3d_paper"><strong>Độ dày giấy</strong></label><br />
		<input type="text" name="htt_book3d_paper" id="htt_book3d_paper" class="small-text"
			inputmode="decimal"
			placeholder="<?php echo esc_attr( $tidy( $hint['paper'] ) ); ?>"
			value="<?php echo esc_attr( $raw( 'paper' ) ); ?>" /> mm/tờ
	</p>
	<p class="description">
		0,08 giấy mỏng &middot; 0,1 thường &middot; 0,13 dày. Quyết định độ dày gáy.
	</p>

	<p class="description">
		Mọi ô ở trên để trống thì theo <?php echo wp_kses_post( $link ); ?>.
	</p>

	<?php if ( '' !== $base ) : ?>
		<hr />
		<p>
			<a class="button button-secondary" target="_blank" rel="noopener"
				href="<?php echo esc_url( htt_book3d_iframe_url( $post->ID ) ); ?>">Xem trước sách</a>
		</p>
		<p class="description">Mở ở tab mới. Lưu bài trước khi xem để thấy bản mới nhất.</p>
	<?php endif; ?>

	<style>
		.htt-book3d-pick { margin-bottom: 12px; }
		.htt-book3d-pick p { margin: 4px 0; }
		.htt-book3d-thumb img {
			display: block;
			max-width: 80px;
			height: auto;
			margin: 4px 0;
			border: 1px solid #dcdcde;
			border-radius: 4px;
		}
	</style>
	<?php
}

/**
 * Trình chọn ảnh cho ba ô bìa.
 *
 * @param string $hook Trang admin hiện tại.
 */
function htt_book3d_admin_assets( $hook ) {
	// Trang Cài đặt > Tổng quan: chỉ cần nối hai ô màu với nhau.
	if ( 'options-general.php' === $hook ) {
		wp_add_inline_script( 'jquery-core', htt_book3d_color_script() );

		return;
	}

	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	$screen = get_current_screen();

	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}

	wp_enqueue_media();
	wp_add_inline_script( 'jquery-core', htt_book3d_picker_script() );
}
add_action( 'admin_enqueue_scripts', 'htt_book3d_admin_assets' );

/**
 * Nối bảng màu với ô chữ đựng mã hex.
 *
 * Ô chữ mới là cái được gửi đi; bảng màu chỉ là cách chọn cho nhanh.
 *
 * @return string
 */
function htt_book3d_color_script() {
	return <<<'JS'
jQuery(function ($) {
	var $text   = $('#htt_book3d_default_bg');
	var $picker = $('#htt_book3d_default_bg_picker');
	var $clear  = $('.htt-book3d-color-clear');

	if (!$text.length) { return; }

	$picker.on('input change', function () {
		$text.val(this.value);
		$clear.show();
	});

	$text.on('input', function () {
		var v = $.trim(this.value);

		// Bang mau chi nhan dang #aabbcc; dang viet tat #abc phai gian ra.
		if (/^#?[0-9a-fA-F]{3}$/.test(v)) {
			v = v.replace('#', '');
			v = '#' + v[0] + v[0] + v[1] + v[1] + v[2] + v[2];
		}

		if (/^#[0-9a-fA-F]{6}$/.test(v)) {
			$picker.val(v.toLowerCase());
		}

		$clear.toggle(v !== '');
	});

	$clear.on('click', function (e) {
		e.preventDefault();
		$text.val('').trigger('input');
		$text.focus();
	});
});
JS;
}

/**
 * Script cho ba ô chọn ảnh bìa.
 *
 * Mỗi ô giữ khung chọn ảnh riêng, nếu không thì chọn bìa sau sẽ ghi
 * đè lên ô bìa trước.
 *
 * @return string
 */
function htt_book3d_picker_script() {
	return <<<'JS'
jQuery(function ($) {
	$('.htt-book3d-pick').each(function () {
		var $box   = $(this);
		var $input = $box.find('input[type=hidden]');
		var $thumb = $box.find('.htt-book3d-thumb');
		var $drop  = $box.find('.htt-book3d-drop');
		var frame;

		$box.find('.htt-book3d-choose').on('click', function (e) {
			e.preventDefault();

			if (!frame) {
				frame = wp.media({
					title: 'Chon anh bia',
					button: { text: 'Dung anh nay' },
					library: { type: 'image' },
					multiple: false
				});

				frame.on('select', function () {
					var img = frame.state().get('selection').first().toJSON();
					var src = (img.sizes && img.sizes.thumbnail) ? img.sizes.thumbnail.url : img.url;

					$input.val(img.id);
					$thumb.html($('<img>').attr('src', src).attr('alt', ''));
					$drop.show();
				});
			}

			frame.open();
		});

		$drop.on('click', function (e) {
			e.preventDefault();
			$input.val('');
			$thumb.empty();
			$drop.hide();
		});
	});
});
JS;
}

/**
 * Lưu khai báo sách 3D.
 *
 * @param int $post_id Bài được lưu.
 */
function htt_book3d_save( $post_id ) {
	if ( ! isset( $_POST['htt_book3d_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( $_POST['htt_book3d_nonce'] ), 'htt_book3d_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['htt_book3d_show'] ) ) {
		update_post_meta( $post_id, 'htt_book3d_show', '1' );
	} else {
		delete_post_meta( $post_id, 'htt_book3d_show' );
	}

	// Ảnh bìa: chỉ nhận id của một ảnh thật còn trong thư viện.
	foreach ( array( 'front', 'back', 'spine' ) as $field ) {
		$key = 'htt_book3d_' . $field;
		$id  = isset( $_POST[ $key ] ) ? absint( $_POST[ $key ] ) : 0;

		if ( $id && 'attachment' === get_post_type( $id ) ) {
			update_post_meta( $post_id, $key, (string) $id );
		} else {
			delete_post_meta( $post_id, $key );
		}
	}

	// Tiêu đề chạy: chữ thuần, không thẻ - trang sách vẽ bằng canvas nên
	// không dựng được HTML. Ô trống thì xoá hẳn dòng meta.
	foreach ( array( 'head_l', 'head_r' ) as $field ) {
		$key  = 'htt_book3d_' . $field;
		$text = isset( $_POST[ $key ] )
			? sanitize_text_field( wp_unslash( $_POST[ $key ] ) )
			: '';

		// Cắt bớt cho vừa một dòng đầu trang; dài hơn thì chữ phải thu
		// nhỏ đến mức không đọc được nữa.
		$text = trim( mb_substr( $text, 0, 120 ) );

		if ( '' === $text ) {
			delete_post_meta( $post_id, $key );
		} else {
			update_post_meta( $post_id, $key, $text );
		}
	}

	// Các số: kẹp vào khoảng hợp lý trước khi lưu, vì một khổ sách 0cm
	// hay giấy dày 5mm sẽ dựng ra khối hình không còn giống cuốn sách.
	$numbers = array(
		'htt_book3d_w'     => array( 5, 40 ),
		'htt_book3d_h'     => array( 5, 40 ),
		'htt_book3d_font'  => array( 0.6, 2 ),
		'htt_book3d_paper' => array( 0.05, 0.3 ),
	);

	foreach ( $numbers as $key => $range ) {
		list( $min, $max ) = $range;

		// Dấu phẩy thập phân là cách viết quen tay ở đây, nhưng (float)
		// sẽ đọc "20,5" thành 20.
		$raw = isset( $_POST[ $key ] )
			? str_replace( ',', '.', trim( (string) wp_unslash( $_POST[ $key ] ) ) )
			: '';

		// Ô trống nghĩa là "theo cài đặt chung", nên xoá hẳn dòng meta:
		// bài sẽ đi theo khuôn chung, kể cả khi khuôn đó đổi sau này.
		if ( '' === $raw ) {
			delete_post_meta( $post_id, $key );
			continue;
		}

		// Chữ nghĩa là gõ nhầm: giữ nguyên giá trị cũ thay vì âm thầm trả
		// về mặc định, để lần lưu sau người viết vẫn thấy số mình đã đặt.
		if ( ! is_numeric( $raw ) ) {
			continue;
		}

		update_post_meta( $post_id, $key, (string) min( $max, max( $min, (float) $raw ) ) );
	}

	// Giới hạn đọc thử phân biệt ba trạng thái, nên không dùng absint():
	// ô trống = theo cài đặt chung, 0 = bài này cho xem trọn vẹn (kể cả
	// khi cài đặt chung đang cắt), số dương = cắt tại đó.
	$limit = isset( $_POST['htt_book3d_limit'] )
		? trim( (string) wp_unslash( $_POST['htt_book3d_limit'] ) )
		: '';

	if ( '' === $limit || ! is_numeric( $limit ) ) {
		delete_post_meta( $post_id, 'htt_book3d_limit' );
	} else {
		update_post_meta( $post_id, 'htt_book3d_limit', (string) min( 2000, absint( $limit ) ) );
	}
}
add_action( 'save_post_post', 'htt_book3d_save' );
