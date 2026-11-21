<?php
/**
 * Hanh Trinh Tri Thuc - child theme functions.
 *
 * @package HanhTrinhTriThuc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Sách 3D: biến nội dung bài viết thành cuốn sách lật trang trong popup
// "In theo yêu cầu". Để riêng vì phần chuyển khối Gutenberg sang chuỗi
// nội dung sách khá dài và không liên quan gì đến phần còn lại ở đây.
require_once get_stylesheet_directory() . '/inc/book3d.php';

if ( is_admin() ) {
	require_once get_stylesheet_directory() . '/inc/book3d-admin.php';
}

/**
 * Load the child theme stylesheet after the parent's.
 *
 * Block themes enqueue the parent style automatically, but the child
 * stylesheet still needs registering so CSS written here takes effect.
 */
function htt_enqueue_styles() {
	$style_path = get_stylesheet_directory() . '/style.css';

	wp_enqueue_style(
		'htt-style',
		get_stylesheet_uri(),
		array(),
		file_exists( $style_path ) ? (string) filemtime( $style_path ) : null
	);
}
add_action( 'wp_enqueue_scripts', 'htt_enqueue_styles' );

/**
 * Load the theme-switch script.
 */
function htt_enqueue_scripts() {
	$rel  = '/assets/js/theme-switch.js';
	$path = get_stylesheet_directory() . $rel;

	if ( ! file_exists( $path ) ) {
		return;
	}

	wp_enqueue_script(
		'htt-theme-switch',
		get_stylesheet_directory_uri() . $rel,
		array(),
		(string) filemtime( $path ),
		true
	);

	// Quote slider only runs on the homepage.
	if ( is_home() || is_front_page() ) {
		$q_rel  = '/assets/js/quotes.js';
		$q_path = get_stylesheet_directory() . $q_rel;

		if ( file_exists( $q_path ) ) {
			wp_enqueue_script(
				'htt-quotes',
				get_stylesheet_directory_uri() . $q_rel,
				array(),
				(string) filemtime( $q_path ),
				true
			);
		}
	}

	// Share buttons only exist on single posts.
	if ( is_singular( 'post' ) ) {
		$share_rel  = '/assets/js/share.js';
		$share_path = get_stylesheet_directory() . $share_rel;

		if ( file_exists( $share_path ) ) {
			wp_enqueue_script(
				'htt-share',
				get_stylesheet_directory_uri() . $share_rel,
				array(),
				(string) filemtime( $share_path ),
				true
			);
		}

		$print_rel  = '/assets/js/print.js';
		$print_path = get_stylesheet_directory() . $print_rel;

		if ( file_exists( $print_path ) ) {
			wp_enqueue_script(
				'htt-print',
				get_stylesheet_directory_uri() . $print_rel,
				array(),
				(string) filemtime( $print_path ),
				true
			);
		}
	}
}
add_action( 'wp_enqueue_scripts', 'htt_enqueue_scripts' );

/**
 * Apply the saved theme before first paint.
 *
 * Without this the page renders light, then flips to dark once the
 * deferred script runs - a white flash on every load for readers who
 * chose dark. This runs inline in <head>, before any pixels are drawn.
 */
function htt_theme_no_flash() {
	?>
	<script>
	(function(){try{var t=localStorage.getItem('htt-theme');
	if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}
	}catch(e){}}());
	</script>
	<?php
}
add_action( 'wp_head', 'htt_theme_no_flash', 0 );

/**
 * Channel registry.
 *
 * One place that knows which categories are YouTube channels and where
 * each one lives. Templates and shortcodes read from here, so adding a
 * channel later means editing this array and nothing else.
 *
 * Channel URLs are stored per-category under Posts > Categories > Edit,
 * so they can be changed without touching code.
 *
 * @return array<string, array{name: string, role: string}>
 */
function htt_channels() {
	return array(
		'abooks' => array(
			'name' => 'Abooks',
			'role' => 'Sách nói',
		),
		'bbooks' => array(
			'name' => 'Bbooks',
			'role' => 'Review sách',
		),
	);
}

/**
 * Read a channel's YouTube URL from its category.
 *
 * @param string $slug Category slug.
 * @return string Channel URL, or an empty string when none is set.
 */
function htt_channel_url( $slug ) {
	$term = get_term_by( 'slug', $slug, 'category' );

	if ( ! $term ) {
		return '';
	}

	return (string) get_term_meta( $term->term_id, 'htt_youtube_url', true );
}

/**
 * Add the YouTube URL field to the category edit screen.
 *
 * @param WP_Term $term Category being edited.
 */
function htt_category_field( $term ) {
	$value = get_term_meta( $term->term_id, 'htt_youtube_url', true );
	?>
	<tr class="form-field">
		<th scope="row"><label for="htt_youtube_url">Link kênh YouTube</label></th>
		<td>
			<input type="url" name="htt_youtube_url" id="htt_youtube_url"
				value="<?php echo esc_attr( $value ); ?>"
				placeholder="https://www.youtube.com/@tenkenh" style="width:100%;max-width:30rem" />
			<p class="description">
				Dán link kênh vào đây. Nút &ldquo;Xem trên YouTube&rdquo; chỉ hiện khi ô này có nội dung.
			</p>
		</td>
	</tr>
	<?php
	htt_category_image_field( $term );
}
add_action( 'category_edit_form_fields', 'htt_category_field' );

/**
 * Logo / cover image picker for a category.
 *
 * Readers arrive from a specific channel, so showing that channel's logo
 * at the top of its archive tells them immediately they are in the right
 * place.
 *
 * @param WP_Term $term Category being edited.
 */
function htt_category_image_field( $term ) {
	$image_id = (int) get_term_meta( $term->term_id, 'htt_image_id', true );
	$preview  = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
	?>
	<tr class="form-field">
		<th scope="row"><label for="htt_image_btn">Logo / ảnh danh mục</label></th>
		<td>
			<input type="hidden" name="htt_image_id" id="htt_image_id"
				value="<?php echo esc_attr( (string) $image_id ); ?>" />

			<div id="htt-image-preview" style="margin-bottom:.6rem">
				<?php if ( $preview ) : ?>
					<img src="<?php echo esc_url( $preview ); ?>" alt=""
						style="max-width:160px;height:auto;border:1px solid #dcdcde;border-radius:4px;display:block" />
				<?php endif; ?>
			</div>

			<button type="button" class="button" id="htt_image_btn">
				<?php echo $image_id ? 'Đổi ảnh' : 'Chọn ảnh'; ?>
			</button>
			<button type="button" class="button-link" id="htt_image_clear"
				style="margin-left:.6rem;color:#b32d2e<?php echo $image_id ? '' : ';display:none'; ?>">
				Gỡ ảnh
			</button>

			<p class="description">
				Ảnh hiện ở đầu trang danh mục. Nên dùng logo vuông hoặc ảnh ngang,
				tối thiểu 400px. Bỏ trống thì trang danh mục không hiện ảnh.
			</p>
		</td>
	</tr>
	<?php
}

/**
 * Load the media picker on the category edit screen.
 *
 * @param string $hook Current admin page.
 */
function htt_category_admin_assets( $hook ) {
	if ( 'term.php' !== $hook && 'edit-tags.php' !== $hook ) {
		return;
	}

	wp_enqueue_media();
	wp_add_inline_script( 'jquery-core', <<<'JS'
jQuery(function ($) {
	var frame;
	var $id      = $('#htt_image_id');
	var $preview = $('#htt-image-preview');
	var $clear   = $('#htt_image_clear');

	$('#htt_image_btn').on('click', function (e) {
		e.preventDefault();

		if (frame) { frame.open(); return; }

		frame = wp.media({
			title: 'Chọn logo cho danh mục',
			button: { text: 'Dùng ảnh này' },
			library: { type: 'image' },
			multiple: false
		});

		frame.on('select', function () {
			var img = frame.state().get('selection').first().toJSON();
			var src = (img.sizes && img.sizes.medium) ? img.sizes.medium.url : img.url;

			$id.val(img.id);
			$preview.html(
				$('<img>').attr('src', src).attr('alt', '').css({
					maxWidth: '160px', height: 'auto',
					border: '1px solid #dcdcde', borderRadius: '4px', display: 'block'
				})
			);
			$('#htt_image_btn').text('Đổi ảnh');
			$clear.show();
		});

		frame.open();
	});

	$clear.on('click', function (e) {
		e.preventDefault();
		$id.val('');
		$preview.empty();
		$('#htt_image_btn').text('Chọn ảnh');
		$(this).hide();
	});
});
JS
	);
}
add_action( 'admin_enqueue_scripts', 'htt_category_admin_assets' );

/**
 * Save the category's YouTube URL and logo.
 *
 * @param int $term_id Category being saved.
 */
function htt_save_category_field( $term_id ) {
	// Nonce is verified by WordPress before this hook fires on term save.
	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	if ( isset( $_POST['htt_youtube_url'] ) ) {
		$url = esc_url_raw( wp_unslash( $_POST['htt_youtube_url'] ) );

		if ( '' === $url ) {
			delete_term_meta( $term_id, 'htt_youtube_url' );
		} else {
			update_term_meta( $term_id, 'htt_youtube_url', $url );
		}
	}

	if ( isset( $_POST['htt_image_id'] ) ) {
		$image_id = absint( wp_unslash( $_POST['htt_image_id'] ) );

		// Only accept an id that really is an image in the library.
		if ( $image_id && wp_attachment_is_image( $image_id ) ) {
			update_term_meta( $term_id, 'htt_image_id', $image_id );
		} else {
			delete_term_meta( $term_id, 'htt_image_id' );
		}
	}
}
add_action( 'edited_category', 'htt_save_category_field' );

/**
 * Render a category's logo above its archive.
 *
 * Prints nothing when no image is set, so a category without a logo
 * simply starts with its title.
 *
 * @return string Image markup, or an empty string.
 */
function htt_category_image() {
	if ( ! is_category() ) {
		return '';
	}

	$term     = get_queried_object();
	$image_id = $term ? (int) get_term_meta( $term->term_id, 'htt_image_id', true ) : 0;

	if ( ! $image_id || ! wp_attachment_is_image( $image_id ) ) {
		return '';
	}

	$alt = (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true );

	if ( '' === $alt ) {
		$alt = sprintf( 'Logo %s', $term->name );
	}

	$img = wp_get_attachment_image(
		$image_id,
		'medium',
		false,
		array(
			'class'   => 'htt-cat-logo',
			'alt'     => $alt,
			'loading' => 'eager',
		)
	);

	return $img ? '<div class="htt-cat-logo-wrap">' . $img . '</div>' : '';
}
add_shortcode( 'htt_cat_image', 'htt_category_image' );

/**
 * Register the per-post video URL field.
 *
 * Each post mirrors one YouTube video, so the button should return the
 * reader to that exact video. Registering the meta with show_in_rest
 * puts the field in the block editor sidebar under "Bài viết".
 */
function htt_register_post_meta() {
	register_post_meta(
		'post',
		'htt_video_url',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'esc_url_raw',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_post_meta(
		'post',
		'htt_featured',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_post_meta(
		'post',
		'htt_print_show',
		array(
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_post_meta(
		'post',
		'htt_print_image',
		array(
			// Comma-separated attachment ids, first one is the cover.
			'type'              => 'string',
			'single'            => true,
			'default'           => '',
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);

	register_post_meta(
		'post',
		'htt_featured_order',
		array(
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
			'auth_callback'     => function () {
				return current_user_can( 'edit_posts' );
			},
		)
	);
}
add_action( 'init', 'htt_register_post_meta' );

/**
 * Add the "featured post" box to the post editor.
 *
 * Two fields in one box: a checkbox to put the post in the featured
 * section, and a number to order it there. Both live on the post itself
 * so picking a post is one tick while writing it - no separate screen to
 * remember.
 */
function htt_featured_meta_box() {
	add_meta_box(
		'htt-featured',
		'Bài viết nổi bật',
		'htt_featured_meta_box_html',
		'post',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'htt_featured_meta_box' );

/**
 * Render the featured-post box.
 *
 * @param WP_Post $post Post being edited.
 */
function htt_featured_meta_box_html( $post ) {
	$is_featured = (bool) get_post_meta( $post->ID, 'htt_featured', true );
	$order       = get_post_meta( $post->ID, 'htt_featured_order', true );
	$order       = '' === $order ? '' : (int) $order;

	wp_nonce_field( 'htt_featured_save', 'htt_featured_nonce' );
	?>
	<p>
		<label>
			<input type="checkbox" name="htt_featured" value="1"
				<?php checked( $is_featured ); ?> />
			Đưa bài này vào mục nổi bật
		</label>
	</p>
	<p>
		<label for="htt_featured_order">Thứ tự hiển thị</label>
		<input type="number" name="htt_featured_order" id="htt_featured_order"
			value="<?php echo esc_attr( (string) $order ); ?>"
			min="0" step="1" style="width:5em" />
	</p>
	<p class="description">
		Số nhỏ hiện trước. Để trống thì bài xếp sau cùng, theo ngày đăng
		mới nhất.
	</p>
	<?php
}

/**
 * Save the featured flag and its order.
 *
 * @param int $post_id Post being saved.
 */
function htt_save_featured( $post_id ) {
	if ( ! isset( $_POST['htt_featured_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( $_POST['htt_featured_nonce'] ), 'htt_featured_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['htt_featured'] ) ) {
		update_post_meta( $post_id, 'htt_featured', '1' );
	} else {
		delete_post_meta( $post_id, 'htt_featured' );
	}

	$order = isset( $_POST['htt_featured_order'] )
		? trim( (string) wp_unslash( $_POST['htt_featured_order'] ) )
		: '';

	if ( '' === $order ) {
		delete_post_meta( $post_id, 'htt_featured_order' );
	} else {
		update_post_meta( $post_id, 'htt_featured_order', max( 0, (int) $order ) );
	}
}
add_action( 'save_post_post', 'htt_save_featured' );

/**
 * ==========================================================================
 * In theo yêu cầu
 *
 * One WooCommerce product serves every article: the reader orders "a
 * printed copy", and which book that means comes from the post they are
 * reading. The product id is stored once in Settings; the cover image,
 * title and author come from the post, so adding a book is just writing
 * the article - no product to create each time.
 * ==========================================================================
 */

/**
 * Settings for the print-on-demand button.
 */
function htt_print_settings() {
	add_settings_section(
		'htt_print',
		'In theo yêu cầu',
		function () {
			echo '<p>Nút "In theo yêu cầu" hiện ở cuối mỗi bài viết. '
				. 'Ảnh và tên sách lấy theo từng bài, nên chỉ cần khai báo '
				. 'một sản phẩm dùng chung.</p>';
		},
		'general'
	);

	$fields = array(
		'htt_print_product_id' => array(
			'label' => 'ID sản phẩm WooCommerce',
			'type'  => 'number',
			'desc'  => 'Mở sản phẩm trong trang quản trị, lấy số trong địa chỉ '
				. '(post=<strong>123</strong>). Để trống nếu chưa có WooCommerce.',
		),
		'htt_print_price'      => array(
			'label' => 'Giá hiển thị',
			'type'  => 'text',
			'desc'  => 'Chỉ dùng khi chưa cài WooCommerce. Ví dụ: 180.000đ',
		),
		'htt_print_note'       => array(
			'label' => 'Mô tả ngắn',
			'type'  => 'text',
			'desc'  => 'Một câu về dịch vụ in. Ví dụ: Bìa mềm, in màu, giao 3-5 ngày.',
		),
		'htt_print_fallback'   => array(
			'label' => 'Link đặt hàng dự phòng',
			'type'  => 'url',
			'desc'  => 'Dùng khi chưa có WooCommerce — link Shopee, Tiki hoặc Zalo.',
		),
	);

	foreach ( $fields as $key => $field ) {
		register_setting( 'general', $key, array( 'sanitize_callback' => 'sanitize_text_field' ) );

		add_settings_field(
			$key,
			$field['label'],
			function () use ( $key, $field ) {
				printf(
					'<input type="%1$s" name="%2$s" id="%2$s" value="%3$s" class="regular-text" />'
					. '<p class="description">%4$s</p>',
					esc_attr( $field['type'] ),
					esc_attr( $key ),
					esc_attr( (string) get_option( $key, '' ) ),
					wp_kses( $field['desc'], array( 'strong' => array() ) )
				);
			},
			'general',
			'htt_print'
		);
	}
}
add_action( 'admin_init', 'htt_print_settings' );

/**
 * Let a single post opt out of the print button.
 */
function htt_print_meta_box() {
	add_meta_box(
		'htt-print',
		'In theo yêu cầu',
		'htt_print_meta_box_html',
		'post',
		'side'
	);
}
add_action( 'add_meta_boxes', 'htt_print_meta_box' );

/**
 * Render the opt-out box.
 *
 * @param WP_Post $post Post being edited.
 */
function htt_print_meta_box_html( $post ) {
	$show = (bool) get_post_meta( $post->ID, 'htt_print_show', true );
	wp_nonce_field( 'htt_print_save', 'htt_print_nonce' );
	?>
	<p>
		<label>
			<input type="checkbox" name="htt_print_show" value="1" <?php checked( $show ); ?> />
			Hiện nút In theo yêu cầu ở bài này
		</label>
	</p>
	<p class="description">
		Mặc định nút không hiện. Tích vào đây với những bài gắn với một
		cuốn sách có thể đặt in.
	</p>

	<hr />

	<?php
	$image_ids = htt_print_image_ids( $post->ID );
	?>
	<p><strong>Ảnh sản phẩm</strong></p>

	<div id="htt-print-image-list" class="htt-print-admin-list">
		<?php foreach ( $image_ids as $id ) : ?>
			<span class="htt-print-admin-item" data-id="<?php echo esc_attr( (string) $id ); ?>">
				<?php echo wp_get_attachment_image( $id, 'thumbnail' ); ?>
				<button type="button" class="htt-print-admin-remove" aria-label="Bỏ ảnh này">&times;</button>
			</span>
		<?php endforeach; ?>
	</div>

	<input type="hidden" name="htt_print_image" id="htt_print_image"
		value="<?php echo esc_attr( implode( ',', $image_ids ) ); ?>" />

	<p>
		<button type="button" class="button" id="htt_print_image_btn">Thêm ảnh</button>
		<button type="button" class="button-link delete" id="htt_print_image_clear"
			<?php echo $image_ids ? '' : 'style="display:none"'; ?>>Xoá hết</button>
	</p>

	<p class="description">
		Ảnh đầu tiên là ảnh đại diện — dùng trong giỏ hàng, thanh toán và
		đơn hàng. Kéo thả để đổi thứ tự. Để trống thì lấy ảnh đại diện của
		bài viết.
	</p>

	<style>
		.htt-print-admin-list {
			display: flex;
			flex-wrap: wrap;
			gap: 6px;
			margin-bottom: 8px;
		}
		.htt-print-admin-item {
			position: relative;
			display: block;
			width: 72px;
			cursor: move;
		}
		.htt-print-admin-item img {
			display: block;
			width: 72px;
			height: 72px;
			object-fit: cover;
			border: 1px solid #dcdcde;
			border-radius: 4px;
		}
		/* Vien dam hon de biet ngay anh nao se dung lam dai dien. */
		.htt-print-admin-item:first-child img {
			border-color: #2271b1;
			box-shadow: 0 0 0 1px #2271b1;
		}
		.htt-print-admin-item.is-dragging {
			opacity: .4;
		}
		.htt-print-admin-remove {
			position: absolute;
			top: -6px;
			right: -6px;
			width: 20px;
			height: 20px;
			padding: 0;
			border: 0;
			border-radius: 50%;
			background: #d63638;
			color: #fff;
			font-size: 14px;
			line-height: 1;
			cursor: pointer;
		}
	</style>
	<?php
}

/**
 * Load the media picker on the post editor.
 *
 * @param string $hook Current admin page.
 * @return void
 */
function htt_print_admin_assets( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}

	if ( 'post' !== get_current_screen()->post_type ) {
		return;
	}

	wp_enqueue_media();
	wp_add_inline_script( 'jquery-core', <<<'JS'
jQuery(function ($) {
	var frame;
	var $id    = $('#htt_print_image');
	var $list  = $('#htt-print-image-list');
	var $clear = $('#htt_print_image_clear');

	if (!$list.length) { return; }

	// O an giu thu tu that; luoi anh chi la hinh chieu cua no.
	function sync() {
		var ids = $list.find('.htt-print-admin-item').map(function () {
			return $(this).data('id');
		}).get();

		$id.val(ids.join(','));
		$clear.toggle(ids.length > 0);
	}

	function addItem(img) {
		if ($list.find('[data-id="' + img.id + '"]').length) { return; }

		var src = (img.sizes && img.sizes.thumbnail) ? img.sizes.thumbnail.url : img.url;

		$list.append(
			$('<span class="htt-print-admin-item" draggable="true">')
				.attr('data-id', img.id)
				.append($('<img>').attr('src', src).attr('alt', ''))
				.append('<button type="button" class="htt-print-admin-remove" aria-label="Bo anh nay">&times;</button>')
		);
	}

	$('#htt_print_image_btn').on('click', function (e) {
		e.preventDefault();

		if (frame) { frame.open(); return; }

		frame = wp.media({
			title: 'Chọn ảnh cho sản phẩm in',
			button: { text: 'Dùng ảnh này' },
			library: { type: 'image' },
			multiple: 'add'
		});

		frame.on('select', function () {
			frame.state().get('selection').each(function (m) {
				addItem(m.toJSON());
			});
			sync();
		});

		frame.open();
	});

	$list.on('click', '.htt-print-admin-remove', function (e) {
		e.preventDefault();
		$(this).closest('.htt-print-admin-item').remove();
		sync();
	});

	$clear.on('click', function (e) {
		e.preventDefault();
		$list.empty();
		sync();
	});

	// Keo tha doi thu tu - anh dau tien la anh dai dien.
	var dragged = null;

	$list.on('dragstart', '.htt-print-admin-item', function () {
		dragged = this;
		$(this).addClass('is-dragging');
	});

	$list.on('dragend', '.htt-print-admin-item', function () {
		$(this).removeClass('is-dragging');
		dragged = null;
		sync();
	});

	$list.on('dragover', '.htt-print-admin-item', function (e) {
		e.preventDefault();

		if (!dragged || dragged === this) { return; }

		var items = $list.find('.htt-print-admin-item').get();

		if (items.indexOf(dragged) < items.indexOf(this)) {
			$(this).after(dragged);
		} else {
			$(this).before(dragged);
		}
	});

	$list.find('.htt-print-admin-item').attr('draggable', 'true');
	sync();
});
JS
	);
}
add_action( 'admin_enqueue_scripts', 'htt_print_admin_assets' );

/**
 * Save the opt-out flag.
 *
 * @param int $post_id Post being saved.
 */
function htt_save_print( $post_id ) {
	if ( ! isset( $_POST['htt_print_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( $_POST['htt_print_nonce'] ), 'htt_print_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( isset( $_POST['htt_print_show'] ) ) {
		update_post_meta( $post_id, 'htt_print_show', '1' );
	} else {
		delete_post_meta( $post_id, 'htt_print_show' );
	}

	$raw = isset( $_POST['htt_print_image'] )
		? sanitize_text_field( wp_unslash( $_POST['htt_print_image'] ) )
		: '';

	$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );

	$ids = array_filter(
		$ids,
		function ( $id ) {
			return 'attachment' === get_post_type( $id );
		}
	);

	// Keep the editor's order, but never the same photo twice.
	$ids = array_values( array_unique( $ids ) );

	if ( $ids ) {
		update_post_meta( $post_id, 'htt_print_image', implode( ',', $ids ) );
	} else {
		delete_post_meta( $post_id, 'htt_print_image' );
	}
}
add_action( 'save_post_post', 'htt_save_print' );

/**
 * Is WooCommerce available to take the order?
 *
 * @return bool
 */
function htt_has_woo() {
	return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_product' );
}

/**
 * Sticky "print on demand" button plus its product panel.
 *
 * The panel is rendered with the page rather than fetched on click, so
 * the reader sees the cover and price the moment the button is pressed -
 * and it still works with JavaScript switched off, where the panel is
 * simply always open.
 *
 * @return string
 */
function htt_print_button() {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}

	$post_id = get_the_ID();

	// Opt-in: the button stays out of the way unless the editor ticked
	// the box, since most articles are not tied to a printable book.
	if ( ! get_post_meta( $post_id, 'htt_print_show', true ) ) {
		return '';
	}

	$product_id = (int) get_option( 'htt_print_product_id', 0 );
	$fallback   = (string) get_option( 'htt_print_fallback', '' );

	// Nothing to sell yet - stay out of the reader's way.
	if ( ! $product_id && '' === $fallback ) {
		return '';
	}

	$note = (string) get_option( 'htt_print_note', '' );

	// Price and order link come from WooCommerce when it is running, and
	// from the settings above when it is not.
	$price = (string) get_option( 'htt_print_price', '' );
	$order = $fallback;
	$label = 'Đặt in cuốn này';

	$woo = false;

	if ( $product_id && htt_has_woo() ) {
		$product = wc_get_product( $product_id );

		if ( $product && $product->is_purchasable() && $product->is_in_stock() ) {
			$price = wp_strip_all_tags( $product->get_price_html() );
			// Carry the post id so the order knows which book was meant.
			$order = add_query_arg(
				array(
					'add-to-cart' => $product_id,
					'htt_post'    => $post_id,
				),
				wc_get_cart_url()
			);
			$label = 'Thêm vào giỏ';
			$woo   = true;
		}
	}

	if ( '' === $order ) {
		return '';
	}

	$title = get_the_title( $post_id );

	// Uploaded product photos if there are any, else the article cover.
	$gallery = htt_print_image_ids( $post_id );

	if ( ! $gallery ) {
		$fallback = (int) get_post_thumbnail_id( $post_id );
		$gallery  = $fallback ? array( $fallback ) : array();
	}

	$cover = '';
	$strip = '';

	if ( $gallery ) {
		// 'large' is resized, not cropped, so a portrait book cover keeps
		// its shape - 'htt-card' would square it off to 16:9.
		$cover = wp_get_attachment_image(
			$gallery[0],
			'large',
			false,
			array(
				'class'   => 'htt-print__cover',
				'loading' => 'lazy',
				'alt'     => '',
			)
		);

		// Cuốn sách 3D dựng từ bìa và nội dung bài này. Khung iframe in ra
		// cùng trang nhưng chưa có src: cả bộ Three.js nặng hơn nửa megabyte,
		// nên nó chỉ được tải khi người đọc thật sự mở popup (xem print.js).
		$book3d = '';

		if ( htt_book3d_enabled( $post_id ) ) {
			// Màu nền khai trong Cài đặt dùng cho cả popup lẫn sách mở
			// toàn trang. Để trống thì khung này mang màu của giao diện,
			// nên nó tự đi theo chế độ sáng / tối.
			$book3d_bg = htt_book3d_defaults()['bg'];

			$book3d = sprintf(
				'<div class="htt-print__book3d" data-spread="%4$s"%3$s hidden>'
				. '<iframe data-src="%1$s" title="Xem thử sách 3D: %2$s"'
				. ' loading="lazy" allow="fullscreen" referrerpolicy="same-origin"></iframe>'
				. '</div>',
				esc_url( htt_book3d_iframe_url( $post_id ) ),
				esc_attr( $title ),
				'' === $book3d_bg
					? ''
					: ' style="--htt-book3d-bg:' . esc_attr( $book3d_bg ) . '"',
				esc_attr( htt_book3d_defaults()['spread'] )
			);
		}

		// More than one photo earns a row of thumbnails under the main
		// image; a single one would just be the same picture twice.
		//
		// Có sách 3D thì không có dãy nào cả: cuốn sách xoay được, lật
		// được, cho xem cả bìa trước, bìa sau lẫn ruột - một dãy ảnh tĩnh
		// bên dưới chỉ lặp lại thứ người mua đang cầm trên tay.
		if ( '' === $book3d && count( $gallery ) > 1 ) {
			$thumbs = '';

			foreach ( $gallery as $i => $id ) {
				$full = wp_get_attachment_image_url( $id, 'large' );

				if ( ! $full ) {
					continue;
				}

				$thumbs .= sprintf(
					'<button type="button" class="htt-print__thumb%1$s" data-full="%2$s"'
					. ' aria-label="Xem ảnh %3$d">%4$s</button>',
					0 === $i ? ' is-active' : '',
					esc_url( $full ),
					$i + 1,
					wp_get_attachment_image( $id, 'thumbnail', false, array( 'alt' => '' ) )
				);
			}

			$strip = '<div class="htt-print__thumbs">' . $thumbs . '</div>';
		}

		// Ảnh tĩnh nằm dưới sách 3D, không bị bỏ đi: nó hiện ngay trong khi
		// sách đang tải, và là thứ duy nhất còn lại khi máy không có WebGL
		// hay người đọc tắt JavaScript.
		$cover .= $book3d;
	}

	$channel = htt_post_channel( $post_id );

	// With WooCommerce the quantity has to reach the cart, so the action
	// is a real form post. Without it there is nothing to submit to, and
	// a plain link to the shop/Zalo page is all we can offer.
	if ( $woo ) {
		$action = sprintf(
			'<form class="htt-print__form" method="post" action="%1$s">'
			. '<input type="hidden" name="add-to-cart" value="%2$d" />'
			. '<input type="hidden" name="htt_post" value="%3$d" />'
			// Same three-part control the block cart uses: minus, number,
			// plus - so the reader meets one quantity field, not two.
			. '<div class="htt-print__qty">'
			. '<label for="htt-print-qty">Số lượng</label>'
			. '<div class="htt-print__qty-selector">'
			. '<button type="button" class="htt-print__qty-btn htt-print__qty-btn--minus"'
			. ' aria-label="Giảm số lượng">&minus;</button>'
			. '<input type="number" id="htt-print-qty" class="htt-print__qty-input"'
			. ' name="quantity" value="1" min="1" max="9999" step="1" inputmode="numeric" />'
			. '<button type="button" class="htt-print__qty-btn htt-print__qty-btn--plus"'
			. ' aria-label="Tăng số lượng">&plus;</button>'
			. '</div>'
			. '</div>'
			. '<button type="submit" class="htt-print__order">%4$s</button>'
			. '</form>',
			esc_url( wc_get_cart_url() ),
			$product_id,
			$post_id,
			esc_html( $label )
		);
	} else {
		$action = sprintf(
			'<a class="htt-print__order" href="%1$s">%2$s</a>',
			esc_url( $order ),
			esc_html( $label )
		);
	}

	return sprintf(
		'<div class="htt-print" data-htt-print>'
		. '<button type="button" class="htt-print__toggle" aria-expanded="false" aria-controls="htt-print-panel">'
		. '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="20" height="20">'
		. '<path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"'
		. ' d="M6 9V3.8h12V9M6 17.5H4.2v-6h15.6v6H18M6 14.5h12v5.7H6z"/></svg>'
		. '<span>In theo yêu cầu</span></button>'
		// Khong co JavaScript thi khong ai go duoc thuoc tinh hidden, nen
		// panel mo san ngay trong trang - van dat hang duoc nhu thuong.
		// Sach 3D van an: no can WebGL va JavaScript moi chay, con anh
		// tinh ben duoi thi khong.
		. '<noscript><style>'
		. '.htt-print__overlay[hidden]{display:flex}'
		. '.htt-print__book3d{display:none}'
		. '.htt-print__media.is-3d .htt-print__cover{opacity:1}'
		. '</style></noscript>'
		. '<div class="htt-print__overlay" id="htt-print-panel" role="dialog" aria-modal="true"'
		. ' aria-label="In theo yêu cầu" hidden>'
		. '<div class="htt-print__panel">'
		. '<button type="button" class="htt-print__close" aria-label="Đóng">&times;</button>'
		. '%1$s'
		. '<div class="htt-print__body">'
		. '%2$s'
		. '<p class="htt-print__name">%3$s</p>'
		. '%4$s%5$s'
		. '%6$s'
		. '</div></div></div></div>',
		$cover ? '<div class="htt-print__media">' . $cover . '</div>' . $strip : '',
		$channel
			? sprintf(
				'<span class="htt-print__channel htt-print__channel--%1$s">%2$s</span>',
				esc_attr( $channel['slug'] ),
				esc_html( $channel['name'] )
			)
			: '',
		esc_html( htt_print_product_name( $title ) ),
		'' !== $price ? '<p class="htt-print__price">' . esc_html( $price ) . '</p>' : '',
		'' !== $note ? '<p class="htt-print__note">' . esc_html( $note ) . '</p>' : '',
		$action
	);
}
add_shortcode( 'htt_print', 'htt_print_button' );

/**
 * Remember which article an order came from.
 *
 * One product covers every book, so without this the order would only
 * say "in theo yêu cầu" and leave you guessing which title to print.
 *
 * @param array $data Cart item data.
 * @return array
 */
function htt_print_cart_data( $data ) {
	$post_id = isset( $_REQUEST['htt_post'] ) ? absint( $_REQUEST['htt_post'] ) : 0;

	if ( $post_id && 'post' === get_post_type( $post_id ) ) {
		$data['htt_print_title'] = get_the_title( $post_id );
		$data['htt_print_post']  = $post_id;
	}

	return $data;
}
add_filter( 'woocommerce_add_cart_item_data', 'htt_print_cart_data' );

/**
 * Name each cart line after the article it prints.
 *
 * The Store API - which feeds the block cart, the checkout and the
 * mini-cart - reads the title straight off the product object, with no
 * filter of its own. Every cart line carries its own copy of that
 * object, so renaming it here reaches all three without touching the
 * product in the catalogue.
 *
 * Doing it per line also keeps a cart holding two different books
 * correct, which a filter on `woocommerce_product_title` could not:
 * that one only receives the product, never the line.
 *
 * @param array $item Cart item.
 * @return array
 */
function htt_print_cart_item_name( $item ) {
	if ( empty( $item['htt_print_title'] ) || empty( $item['data'] ) ) {
		return $item;
	}

	$item['data']->set_name( htt_print_product_name( $item['htt_print_title'] ) );

	return $item;
}
add_filter( 'woocommerce_add_cart_item', 'htt_print_cart_item_name' );

/**
 * Keep that name after the page is reloaded.
 *
 * The cart is rebuilt from the session on every request, with a fresh
 * product object each time - without this the line would go back to
 * reading "In theo yêu cầu".
 *
 * @param array $item Cart item.
 * @return array
 */
function htt_print_session_item_name( $item ) {
	return htt_print_cart_item_name( $item );
}
add_filter( 'woocommerce_get_cart_item_from_session', 'htt_print_session_item_name' );

/**
 * Name the article in the "added to your cart" notice too.
 *
 * That message is built from the product id alone, before the cart line
 * exists, so it would otherwise read "In theo yêu cầu" while the cart
 * right below names the book.
 *
 * @param string $quoted     Product name wrapped in quotes.
 * @param int    $product_id Product added.
 * @return string
 */
function htt_print_added_notice( $quoted, $product_id ) {
	if ( (int) $product_id !== (int) get_option( 'htt_print_product_id', 0 ) ) {
		return $quoted;
	}

	$post_id = isset( $_REQUEST['htt_post'] ) ? absint( $_REQUEST['htt_post'] ) : 0;

	if ( ! $post_id || 'post' !== get_post_type( $post_id ) ) {
		return $quoted;
	}

	return sprintf(
		'&ldquo;%s&rdquo;',
		wp_strip_all_tags( htt_print_product_name( get_the_title( $post_id ) ) )
	);
}
add_filter( 'woocommerce_add_to_cart_item_name_in_quotes', 'htt_print_added_notice', 10, 2 );

/**
 * Which image represents the printed copy of this article?
 *
 * Thứ tự: bìa trước của cuốn sách 3D, rồi ảnh sản phẩm đã tải lên, rồi
 * ảnh đại diện của bài. Mọi nơi hiện sản phẩm - popup, giỏ hàng, thanh
 * toán, đơn hàng - đều hỏi hàm này, nên người mua không gặp hai cái bìa
 * khác nhau cho cùng một thứ.
 *
 * Bìa 3D đứng đầu vì nó là cuốn sách người mua vừa xem trong popup. Ảnh
 * đại diện của bài thường là ảnh ngang (hình nền video, ảnh minh hoạ),
 * không phải bìa sách - để nó lên trước thì giỏ hàng hiện một thứ khác
 * hẳn với cuốn sách vừa đặt.
 *
 * @param int $post_id Article being ordered.
 * @return int Attachment id, or 0 when the article has no image either.
 */
function htt_print_image_id( $post_id ) {
	$post_id = (int) $post_id;

	if ( function_exists( 'htt_book3d_enabled' ) && htt_book3d_enabled( $post_id ) ) {
		$front = htt_book3d_front_id( $post_id );

		if ( $front ) {
			return (int) $front;
		}
	}

	$ids = htt_print_image_ids( $post_id );

	return $ids ? (int) $ids[0] : (int) get_post_thumbnail_id( $post_id );
}

/**
 * Every photo uploaded for the printed copy, in the editor's order.
 *
 * Stored as a comma-separated list of attachment ids. The first one is
 * the cover: it is what the cart, the checkout and the order show,
 * since those only have room for a single image.
 *
 * Falls back to nothing - callers decide whether to use the article's
 * featured image instead.
 *
 * @param int $post_id Article being ordered.
 * @return int[] Attachment ids that still exist.
 */
function htt_print_image_ids( $post_id ) {
	$post_id = (int) $post_id;

	if ( ! $post_id ) {
		return array();
	}

	$raw = (string) get_post_meta( $post_id, 'htt_print_image', true );

	if ( '' === $raw || '0' === $raw ) {
		return array();
	}

	$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );

	// Drop anything deleted from the media library since it was picked.
	$ids = array_filter(
		$ids,
		function ( $id ) {
			return 'attachment' === get_post_type( $id );
		}
	);

	return array_values( $ids );
}

/**
 * What the printed copy is called once an article is attached.
 *
 * One product serves every title, so "In theo yêu cầu" on its own tells
 * the buyer nothing. Naming the line after the article means the cart,
 * the checkout and the order all say which book is being printed.
 *
 * @param string $title Article title.
 * @return string
 */
function htt_print_product_name( $title ) {
	$title = trim( (string) $title );

	if ( '' === $title ) {
		return 'In theo yêu cầu';
	}

	return sprintf( 'In nội dung [%s] theo yêu cầu', $title );
}

/**
 * Which post does this cart item belong to?
 *
 * @param array $item Cart item.
 * @return int
 */
function htt_print_item_post( $item ) {
	return isset( $item['htt_print_post'] ) ? (int) $item['htt_print_post'] : 0;
}

/**
 * Swap the product placeholder for the book's own cover.
 *
 * One product stands in for every title, so it carries no image of its
 * own - the cart would show a grey placeholder. The article's featured
 * image is the cover being ordered, so use that instead.
 *
 * This hook feeds the block cart, the checkout and the mini-cart, which
 * all read from the Store API.
 *
 * @param array $images Image objects.
 * @param array $item   Cart item.
 * @return array
 */
function htt_print_api_image( $images, $item ) {
	$post_id = htt_print_item_post( $item );

	if ( ! $post_id ) {
		return $images;
	}

	$id = htt_print_image_id( $post_id );

	if ( ! $id ) {
		return $images;
	}

	$full  = wp_get_attachment_image_src( $id, 'full' );
	$thumb = wp_get_attachment_image_src( $id, htt_print_thumb_size() );

	if ( ! $full ) {
		return $images;
	}

	return array(
		(object) array(
			'id'        => (int) $id,
			'src'       => $full[0],
			'thumbnail' => $thumb ? $thumb[0] : $full[0],
			'srcset'    => (string) wp_get_attachment_image_srcset( $id, 'full' ),
			'sizes'     => (string) wp_get_attachment_image_sizes( $id, 'full' ),
			'name'      => get_the_title( $id ),
			'alt'       => get_the_title( $post_id ),
		),
	);
}
add_filter( 'woocommerce_store_api_cart_item_images', 'htt_print_api_image', 10, 2 );

/**
 * Same swap for the classic cart templates.
 *
 * @param string $html Thumbnail markup.
 * @param array  $item Cart item.
 * @return string
 */
function htt_print_cart_thumb( $html, $item ) {
	$post_id = htt_print_item_post( $item );
	$image   = htt_print_image_id( $post_id );

	if ( ! $image ) {
		return $html;
	}

	return wp_get_attachment_image(
		$image,
		htt_print_thumb_size(),
		false,
		array(
			'alt'   => get_the_title( $post_id ),
			'class' => 'attachment-woocommerce_thumbnail htt-print-thumb',
		)
	);
}
add_filter( 'woocommerce_cart_item_thumbnail', 'htt_print_cart_thumb', 10, 2 );

/**
 * Keep the cover on the finished order too.
 *
 * The cart item is gone by then, so the post id is read back from the
 * line item meta saved at checkout.
 *
 * @param string                $html Thumbnail markup.
 * @param WC_Order_Item_Product $line Order line.
 * @return string
 */
function htt_print_order_thumb( $html, $line ) {
	if ( ! is_a( $line, 'WC_Order_Item_Product' ) ) {
		return $html;
	}

	$post_id = (int) $line->get_meta( '_htt_print_post' );
	$image   = htt_print_image_id( $post_id );

	if ( ! $image ) {
		return $html;
	}

	$src = wp_get_attachment_image_src( $image, htt_print_thumb_size() );

	if ( ! $src ) {
		return $html;
	}

	// Email đọc ảnh theo thuộc tính width/height trong thẻ, không theo
	// CSS. Ảnh gốc cao hơn rộng, nên nếu để nguyên 600×924 thì một dòng
	// hàng sẽ chiếm gần hết màn hình điện thoại. Thu về đúng bề ngang
	// WooCommerce yêu cầu, chiều cao tính theo tỉ lệ thật - vẫn không cắt.
	$box = (int) apply_filters( 'woocommerce_email_item_thumbnail_width', 64 );
	list( , $w, $h ) = $src;
	$ratio  = $w > 0 ? $h / $w : 1;
	$height = (int) round( $box * $ratio );

	return sprintf(
		'<img src="%1$s" alt="%2$s" width="%3$d" height="%4$d"'
		. ' class="htt-print-thumb" style="width:%3$dpx;height:auto;max-width:100%%" />',
		esc_url( $src[0] ),
		esc_attr( get_the_title( $post_id ) ),
		$box,
		$height
	);
}
add_filter( 'woocommerce_order_item_thumbnail', 'htt_print_order_thumb', 10, 2 );

/**
 * Thêm ảnh bìa vào bảng sản phẩm của trang xác nhận và trang xem đơn.
 *
 * Hai trang này không hiện ảnh sản phẩm nào - cả bản khối mới lẫn bản
 * template cũ đều chỉ in tên và giá. Người mua mở lại đơn cũ sau vài
 * tuần thì chỉ còn một dòng chữ, không nhận ra mình đã đặt cuốn nào.
 *
 * Chèn qua tên sản phẩm thay vì ghi đè template: khối mới dựng markup
 * thẳng trong PHP, không đọc template nào cả, nhưng cả hai đều chạy
 * tên sản phẩm qua đúng filter này.
 *
 * @param string        $name Tên sản phẩm, có thể đã bọc trong thẻ <a>.
 * @param WC_Order_Item $item Dòng hàng.
 * @return string
 */
function htt_print_order_name_image( $name, $item ) {
	// Email và trang quản trị cũng chạy qua filter này, mà cả hai đã có
	// cột ảnh riêng - thêm ở đây nữa thì một dòng hàng có hai cái bìa.
	// Email: cờ do htt_print_email_items() bật/tắt quanh lúc dựng.
	// Quản trị: bảng sản phẩm có sẵn cột thumb, xem htt_print_admin_thumb().
	if ( ! is_a( $item, 'WC_Order_Item_Product' ) || is_admin()
		|| ! empty( $GLOBALS['htt_print_in_email'] ) ) {
		return $name;
	}

	$post_id = (int) $item->get_meta( '_htt_print_post' );
	$image   = htt_print_image_id( $post_id );

	if ( ! $image ) {
		return $name;
	}

	return sprintf(
		'<span class="htt-order-item">%1$s<span class="htt-order-item__name">%2$s</span></span>',
		wp_get_attachment_image(
			$image,
			htt_print_thumb_size(),
			false,
			array(
				'class'   => 'htt-order-item__thumb',
				'alt'     => '',
				'loading' => 'lazy',
			)
		),
		$name
	);
}
add_filter( 'woocommerce_order_item_name', 'htt_print_order_name_image', 10, 2 );

/**
 * Bìa sách cho cột ảnh của bảng sản phẩm trong trang quản trị đơn hàng.
 *
 * Bảng này đã có sẵn một cột ảnh, nhưng nó rỗng: một sản phẩm dùng chung
 * cho mọi đầu sách nên bản thân sản phẩm không mang ảnh nào.
 *
 * @param string $html    Thẻ ảnh của sản phẩm, thường là rỗng.
 * @param int    $item_id Mã dòng hàng.
 * @param object $item    Dòng hàng.
 * @return string
 */
function htt_print_admin_thumb( $html, $item_id, $item = null ) {
	if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) {
		return $html;
	}

	$post_id = (int) $item->get_meta( '_htt_print_post' );
	$image   = htt_print_image_id( $post_id );

	if ( ! $image ) {
		return $html;
	}

	// Cột ảnh trong trang quản trị rộng đúng 38px và không có CSS nào của
	// giao diện với tới được - style.css chỉ tải ở ngoài trang. Vì vậy
	// chiều cao đặt thẳng vào thẻ; bề rộng để auto nên bìa dọc không bị
	// bóp thành vuông.
	//
	// Cỡ 'medium' thay vì cỡ dùng ngoài trang: một ô 38px không cần ảnh
	// 600px. WordPress chỉ cắt khi ảnh gốc lớn hơn khung theo cả hai
	// chiều, mà bìa sách thì cao hơn rộng nên nó được thu nhỏ, không cắt.
	return wp_get_attachment_image(
		$image,
		'medium',
		false,
		array(
			'alt'   => '',
			'style' => 'width:auto;height:38px;max-width:none;display:block;border-radius:2px',
		)
	);
}
add_filter( 'woocommerce_admin_order_item_thumbnail', 'htt_print_admin_thumb', 10, 3 );

/**
 * Đánh dấu khoảng thời gian đang dựng bảng sản phẩm trong email.
 *
 * Email có cột ảnh riêng, nên trong khoảng này tên sản phẩm phải để
 * trơn. Bật trước mọi hàm nối vào hook (ưu tiên 1) và tắt sau tất cả
 * (ưu tiên 999), vì WooCommerce nối hai hàm vào đây ở mức 10.
 *
 * @param bool $start Vào hay ra khỏi khoảng.
 * @return void
 */
function htt_print_email_items( $start ) {
	$GLOBALS['htt_print_in_email'] = (bool) $start;
}
add_action(
	'woocommerce_email_order_details',
	function () {
		htt_print_email_items( true );
	},
	1
);
add_action(
	'woocommerce_email_order_details',
	function () {
		htt_print_email_items( false );
	},
	999
);

/**
 * Cỡ ảnh dùng cho giỏ hàng, thanh toán và đơn hàng.
 *
 * KHÔNG dùng woocommerce_thumbnail: cỡ đó cắt vuông 300×300, nên một bìa
 * sách dọc sẽ mất cả phần trên lẫn phần dưới - đúng chỗ thường in tên
 * sách và tên tác giả. woocommerce_single rộng 600px, cao tuỳ ảnh, giữ
 * nguyên tỉ lệ thật.
 *
 * Cỡ này chỉ tồn tại khi WooCommerce đang chạy. Không có thì dùng ảnh
 * gốc: các cỡ có sẵn của WordPress trên site này đều đang đặt cắt vuông,
 * nên ảnh gốc là thứ duy nhất chắc chắn còn nguyên tỉ lệ.
 *
 * @return string
 */
function htt_print_thumb_size() {
	return function_exists( 'wc_get_image_size' ) && wc_get_image_size( 'woocommerce_single' )
		? 'woocommerce_single'
		: 'full';
}

/**
 * Copy the title onto the saved order line.
 *
 * @param WC_Order_Item_Product $line   Order line.
 * @param string                $key    Cart item key.
 * @param array                 $values Cart item.
 * @return void
 */
function htt_print_order_meta( $line, $key, $values ) {
	// The line is already named after the article, so a separate "Sách"
	// row would only repeat it.

	// Underscore keeps it out of the customer-facing meta list; it is
	// only here so the order can find the cover image later.
	if ( ! empty( $values['htt_print_post'] ) ) {
		$line->add_meta_data( '_htt_print_post', (int) $values['htt_print_post'], true );
	}
}
add_action( 'woocommerce_checkout_create_order_line_item', 'htt_print_order_meta', 10, 3 );

/**
 * Add the video URL box to the post editor.
 */
function htt_video_meta_box() {
	add_meta_box(
		'htt-video-url',
		'Link video YouTube',
		'htt_video_meta_box_html',
		'post',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'htt_video_meta_box' );

/**
 * Render the video URL box.
 *
 * @param WP_Post $post Post being edited.
 */
function htt_video_meta_box_html( $post ) {
	$value = get_post_meta( $post->ID, 'htt_video_url', true );
	wp_nonce_field( 'htt_video_url_save', 'htt_video_url_nonce' );
	?>
	<p>
		<label for="htt_video_url" class="screen-reader-text">Link video YouTube</label>
		<input type="url" name="htt_video_url" id="htt_video_url"
			value="<?php echo esc_attr( $value ); ?>"
			placeholder="https://www.youtube.com/watch?v=..."
			style="width:100%" />
	</p>
	<p class="description">
		Dán link video tương ứng với bài viết này. Nút &ldquo;Xem video&rdquo;
		sẽ dẫn người đọc về đúng video đó.
	</p>
	<?php
}

/**
 * Save the video URL.
 *
 * @param int $post_id Post being saved.
 */
function htt_save_video_url( $post_id ) {
	if ( ! isset( $_POST['htt_video_url_nonce'] )
		|| ! wp_verify_nonce( sanitize_key( $_POST['htt_video_url_nonce'] ), 'htt_video_url_save' ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( ! isset( $_POST['htt_video_url'] ) ) {
		return;
	}

	$url = esc_url_raw( wp_unslash( $_POST['htt_video_url'] ) );

	if ( '' === $url ) {
		delete_post_meta( $post_id, 'htt_video_url' );
	} else {
		update_post_meta( $post_id, 'htt_video_url', $url );
	}
}
add_action( 'save_post_post', 'htt_save_video_url' );

/**
 * Which channel does this post belong to?
 *
 * @param int $post_id Post to inspect.
 * @return array{slug: string, name: string, role: string}|null
 */
function htt_post_channel( $post_id ) {
	foreach ( htt_channels() as $slug => $channel ) {
		if ( has_category( $slug, $post_id ) ) {
			return array_merge( array( 'slug' => $slug ), $channel );
		}
	}

	return null;
}

/**
 * Render the "watch on YouTube" button.
 *
 * Prefers the post's own video URL so the reader lands on the exact
 * video the article covers. Falls back to the channel page when a post
 * has no video set, and renders nothing when neither exists - better an
 * absent button than a dead link.
 *
 * @return string Button markup, or an empty string.
 */
function htt_youtube_button() {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}

	$post_id = get_the_ID();
	$channel = htt_post_channel( $post_id );

	if ( ! $channel ) {
		return '';
	}

	$video = (string) get_post_meta( $post_id, 'htt_video_url', true );

	if ( '' !== $video ) {
		$url   = $video;
		$label = 'Xem video trên YouTube';
	} else {
		$url   = htt_channel_url( $channel['slug'] );
		$label = sprintf( 'Xem trên kênh %s', $channel['name'] );
	}

	if ( '' === $url ) {
		return '';
	}

	return sprintf(
		'<p class="htt-yt-wrap"><a class="htt-yt htt-yt--%1$s" href="%2$s" target="_blank" rel="noopener">'
		. '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="16" height="16">'
		. '<path fill="currentColor" d="M8 5.5v13l11-6.5z"/></svg>'
		. '%3$s</a></p>',
		esc_attr( $channel['slug'] ),
		esc_url( $url ),
		esc_html( $label )
	);
}
add_shortcode( 'htt_youtube', 'htt_youtube_button' );

/**
 * Output description and Open Graph tags.
 *
 * Readers arrive from YouTube and share links onward in Zalo, Facebook
 * and Messenger. Without these tags a shared link shows no preview image
 * and no summary, which costs clicks on exactly the path that matters.
 */
/**
 * Rebuild menu links from their term/post id instead of the stored URL.
 *
 * The navigation-link block renders whatever URL was saved when the item
 * was created, so a menu built on one domain keeps pointing at it. The
 * items do carry the term or post id, so the real address can be looked
 * up at render time and the stored URL ignored.
 *
 * Only items WordPress resolved to real content are touched; custom
 * links stay exactly as written, apart from the home link.
 *
 * @param array $attributes Block attributes.
 * @return array
 */
function htt_navigation_link_url( $attributes ) {
	if ( empty( $attributes['kind'] ) || empty( $attributes['id'] ) ) {
		return $attributes;
	}

	if ( 'taxonomy' === $attributes['kind'] ) {
		$link = get_term_link( (int) $attributes['id'] );

		if ( ! is_wp_error( $link ) ) {
			$attributes['url'] = $link;
		}
	} elseif ( 'post-type' === $attributes['kind'] ) {
		$link = get_permalink( (int) $attributes['id'] );

		if ( $link ) {
			$attributes['url'] = $link;
		}
	}

	return $attributes;
}

/**
 * Keep the "Trang chủ" custom link pointing at the current domain.
 *
 * It is stored as a custom link with the full URL baked in, so it needs
 * handling separately from the id-based items above.
 *
 * @param array $attributes Block attributes.
 * @return array
 */
function htt_navigation_home_url( $attributes ) {
	if ( empty( $attributes['url'] ) || 'custom' !== ( $attributes['kind'] ?? '' ) ) {
		return $attributes;
	}

	$url = (string) $attributes['url'];

	// Leave anything pointing at another site alone.
	$host = (string) wp_parse_url( $url, PHP_URL_HOST );

	if ( '' !== $host && $host !== (string) wp_parse_url( home_url(), PHP_URL_HOST )
		&& ! in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
		return $attributes;
	}

	// A home link carries no query, no fragment, and a path that is
	// either empty or just the folder WordPress is installed in.
	if ( wp_parse_url( $url, PHP_URL_QUERY ) || wp_parse_url( $url, PHP_URL_FRAGMENT ) ) {
		return $attributes;
	}

	$path    = trim( (string) wp_parse_url( $url, PHP_URL_PATH ), '/' );
	$install = trim( (string) wp_parse_url( home_url(), PHP_URL_PATH ), '/' );

	if ( '' === $path || $path === $install || 'hanhtrinhtrithuc' === $path ) {
		$attributes['url'] = home_url( '/' );
	}

	return $attributes;
}

/**
 * Apply the URL rewrite to every link inside the navigation block.
 *
 * Navigation renders its children itself, so the usual per-block filter
 * never sees them; this hook hands over the inner blocks instead.
 *
 * @param WP_Block_List $inner_blocks Navigation children.
 * @return WP_Block_List
 */
function htt_navigation_inner_blocks( $inner_blocks ) {
	foreach ( $inner_blocks as $block ) {
		if ( 'core/navigation-link' !== $block->name ) {
			continue;
		}

		$attrs = htt_navigation_link_url( $block->attributes );
		$attrs = htt_navigation_home_url( $attrs );

		$block->attributes = $attrs;
	}

	return $inner_blocks;
}
add_filter( 'block_core_navigation_render_inner_blocks', 'htt_navigation_inner_blocks' );

/**
 * How many images stay eagerly loaded before lazy-loading kicks in.
 *
 * Core keeps the first three eager on the assumption they sit above the
 * fold. That holds for the two-column grid on the homepage and archives,
 * but not for a single post: there the only image near the top is the
 * featured one, and everything after it belongs to the "Nội dung khác"
 * grid at the very bottom.
 *
 * Note this threshold is also what decides which image gets
 * fetchpriority="high", so dropping it to zero would cost the LCP hint.
 *
 * @param int $threshold Default number of images to skip.
 * @return int
 */
function htt_loading_threshold( $threshold ) {
	if ( is_singular( 'post' ) ) {
		return 1;
	}

	return $threshold;
}
add_filter( 'wp_omit_loading_attr_threshold', 'htt_loading_threshold' );

/**
 * Render the comment form heading as an h2.
 *
 * Core wraps "Để lại bình luận" in an h3. The comments title above it is
 * an h2, but that only prints once a post actually has comments - so on a
 * post with none the h3 follows the h1 directly and the outline skips a
 * level.
 *
 * @param array $defaults Comment form arguments.
 * @return array
 */
function htt_comment_form_heading( $defaults ) {
	$defaults['title_reply_before'] = '<h2 id="reply-title" class="comment-reply-title">';
	$defaults['title_reply_after']  = '</h2>';

	return $defaults;
}
add_filter( 'comment_form_defaults', 'htt_comment_form_heading' );

/**
 * Canonical URL for archive views.
 *
 * WordPress only prints a canonical tag on singular views, so category,
 * tag, author and date archives have none - search engines then have to
 * guess which of several URLs listing the same posts is the real one.
 *
 * Paged archives point at themselves, not page one: /page/2/ lists
 * different posts, so collapsing it onto page one would hide them.
 *
 * Search results and 404s are deliberately skipped - they are noindex
 * territory and have no canonical form worth advertising.
 *
 * @return void
 */
function htt_canonical_tag() {
	// Singular views already get one from core's rel_canonical().
	if ( is_singular() || is_search() || is_404() ) {
		return;
	}

	if ( is_front_page() ) {
		$url = home_url( '/' );
	} elseif ( is_home() ) {
		$url = get_permalink( (int) get_option( 'page_for_posts' ) );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		$link = $term ? get_term_link( $term ) : '';
		$url  = is_wp_error( $link ) ? '' : $link;
	} elseif ( is_author() ) {
		$url = get_author_posts_url( (int) get_query_var( 'author' ) );
	} elseif ( is_year() ) {
		$url = get_year_link( (int) get_query_var( 'year' ) );
	} elseif ( is_month() ) {
		$url = get_month_link( (int) get_query_var( 'year' ), (int) get_query_var( 'monthnum' ) );
	} elseif ( is_day() ) {
		$url = get_day_link(
			(int) get_query_var( 'year' ),
			(int) get_query_var( 'monthnum' ),
			(int) get_query_var( 'day' )
		);
	} else {
		return;
	}

	if ( ! $url ) {
		return;
	}

	$paged = (int) get_query_var( 'paged' );

	if ( $paged > 1 ) {
		$url = trailingslashit( $url ) . 'page/' . $paged . '/';
	}

	printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $url ) );
}

function htt_meta_tags() {
	if ( is_singular( 'post' ) ) {
		$post_id = get_the_ID();
		$title   = get_the_title( $post_id );
		$desc    = get_the_excerpt( $post_id );
		$url     = get_permalink( $post_id );
		$image   = get_the_post_thumbnail_url( $post_id, 'htt-hero' )
			?: get_the_post_thumbnail_url( $post_id, 'full' );
		$type    = 'article';
	} elseif ( is_category() ) {
		$term  = get_queried_object();
		$title = $term->name;
		$desc  = $term->description;
		$url   = get_term_link( $term );
		$image = '';
		$type  = 'website';
	} else {
		$title = get_bloginfo( 'name' );
		$desc  = get_bloginfo( 'description' );
		$url   = home_url( '/' );
		$image = '';
		$type  = 'website';
	}

	$desc = wp_strip_all_tags( (string) $desc );
	$desc = trim( preg_replace( '/\s+/', ' ', $desc ) );

	if ( mb_strlen( $desc ) > 160 ) {
		$desc = mb_substr( $desc, 0, 157 ) . '…';
	}

	if ( '' !== $desc ) {
		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $desc ) );
		printf( '<meta property="og:description" content="%s" />' . "\n", esc_attr( $desc ) );
		printf( '<meta name="twitter:description" content="%s" />' . "\n", esc_attr( $desc ) );
	}

	htt_canonical_tag();

	printf( '<meta property="og:title" content="%s" />' . "\n", esc_attr( $title ) );
	printf( '<meta property="og:type" content="%s" />' . "\n", esc_attr( $type ) );
	printf( '<meta property="og:url" content="%s" />' . "\n", esc_url( $url ) );
	printf( '<meta property="og:site_name" content="%s" />' . "\n", esc_attr( get_bloginfo( 'name' ) ) );
	printf( '<meta property="og:locale" content="vi_VN" />' . "\n" );
	printf( '<meta name="twitter:title" content="%s" />' . "\n", esc_attr( $title ) );

	if ( $image ) {
		printf( '<meta property="og:image" content="%s" />' . "\n", esc_url( $image ) );
		printf( '<meta name="twitter:image" content="%s" />' . "\n", esc_url( $image ) );
		printf( '<meta name="twitter:card" content="summary_large_image" />' . "\n" );
	} else {
		printf( '<meta name="twitter:card" content="summary" />' . "\n" );
	}
}
add_action( 'wp_head', 'htt_meta_tags', 5 );

/**
 * "Xem tất cả bài viết" link under the homepage grid.
 *
 * The homepage shows only the newest six posts, so readers need a way
 * through to the rest. Prints nothing when there is no rest to see.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function htt_all_posts_link( $atts = array() ) {
	$atts = shortcode_atts( array( 'shown' => 6 ), $atts, 'htt_all_posts' );

	$total = (int) wp_count_posts( 'post' )->publish;

	if ( $total <= (int) $atts['shown'] ) {
		return '';
	}

	// A dedicated posts page is the only archive that lists everything.
	// Without one the "archive" is the homepage itself, which this link
	// sits on - so fall back to the channel archives instead of pointing
	// the reader back where they already are.
	$posts_page = (int) get_option( 'page_for_posts' );

	if ( $posts_page ) {
		return sprintf(
			'<p class="htt-all-posts"><a class="htt-all-posts__link" href="%s">Xem tất cả bài viết'
			. '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="16" height="16">'
			. '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
			. ' stroke-linejoin="round" d="M5 12h13M12.5 6l6 6-6 6"/></svg></a></p>',
			esc_url( get_permalink( $posts_page ) )
		);
	}

	$links = array();

	foreach ( htt_channels() as $slug => $channel ) {
		$term = get_term_by( 'slug', $slug, 'category' );

		if ( ! $term || ! $term->count ) {
			continue;
		}

		$links[] = sprintf(
			'<a class="htt-all-posts__link htt-all-posts__link--%1$s" href="%2$s">Xem tất cả %3$s'
			. '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false" width="16" height="16">'
			. '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"'
			. ' stroke-linejoin="round" d="M5 12h13M12.5 6l6 6-6 6"/></svg></a>',
			esc_attr( $slug ),
			esc_url( get_term_link( $term ) ),
			esc_html( $channel['name'] )
		);
	}

	if ( ! $links ) {
		return '';
	}

	return '<p class="htt-all-posts">' . implode( '', $links ) . '</p>';
}
add_shortcode( 'htt_all_posts', 'htt_all_posts_link' );

/**
 * Channel filter chips for the homepage.
 *
 * Block template files are static HTML, so the links have to come from a
 * shortcode - otherwise the site URL ends up hardcoded and breaks the day
 * the site moves to a real domain.
 *
 * @return string
 */
function htt_filter_chips() {
	$chips = array(
		sprintf(
			'<a class="htt-chip htt-chip--all" href="%s" aria-current="page">Tất cả</a>',
			esc_url( home_url( '/' ) )
		),
	);

	foreach ( htt_channels() as $slug => $channel ) {
		$term = get_term_by( 'slug', $slug, 'category' );

		if ( ! $term ) {
			continue;
		}

		$chips[] = sprintf(
			'<a class="htt-chip htt-chip--%1$s" href="%2$s">%3$s</a>',
			esc_attr( $slug ),
			esc_url( get_term_link( $term ) ),
			esc_html( $channel['name'] )
		);
	}

	return '<nav class="htt-filters" aria-label="Lọc theo kênh">'
		. implode( '', $chips )
		. '</nav>';
}
add_shortcode( 'htt_filters', 'htt_filter_chips' );

/**
 * Channel logos in the footer.
 *
 * The logo itself is a CSS background image; the anchor only carries the
 * channel name for screen readers.
 *
 * @return string
 */
function htt_footer_channels() {
	$links = array();

	foreach ( htt_channels() as $slug => $channel ) {
		$term = get_term_by( 'slug', $slug, 'category' );

		if ( ! $term ) {
			continue;
		}

		$links[] = sprintf(
			'<a class="htt-footer-channel htt-footer-channel--%1$s" href="%2$s">'
			. '<span class="screen-reader-text">%3$s — %4$s</span></a>',
			esc_attr( $slug ),
			esc_url( get_term_link( $term ) ),
			esc_html( $channel['name'] ),
			esc_html( $channel['role'] )
		);
	}

	if ( ! $links ) {
		return '';
	}

	return '<div class="htt-footer-channels">' . implode( '', $links ) . '</div>';
}
add_shortcode( 'htt_footer_channels', 'htt_footer_channels' );

/**
 * Register the "Câu nói" post type.
 *
 * Quotes live in their own list rather than inside the homepage layout,
 * so adding or removing one never risks breaking the page, and the same
 * quotes can be reused elsewhere later.
 */
function htt_register_quote_type() {
	register_post_type(
		'htt_quote',
		array(
			'labels'          => array(
				'name'               => 'Câu nói',
				'singular_name'      => 'Câu nói',
				'add_new'            => 'Thêm câu nói',
				'add_new_item'       => 'Thêm câu nói mới',
				'edit_item'          => 'Sửa câu nói',
				'new_item'           => 'Câu nói mới',
				'view_item'          => 'Xem câu nói',
				'search_items'       => 'Tìm câu nói',
				'not_found'          => 'Chưa có câu nói nào',
				'not_found_in_trash' => 'Thùng rác trống',
				'all_items'          => 'Tất cả câu nói',
				'menu_name'          => 'Câu nói',
			),
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'show_in_rest'    => true,
			'menu_position'   => 21,
			'menu_icon'       => 'dashicons-format-quote',
			'supports'        => array( 'title', 'editor', 'thumbnail', 'page-attributes' ),
			'has_archive'     => false,
			'rewrite'         => false,
			'capability_type' => 'post',
		)
	);
}
add_action( 'init', 'htt_register_quote_type' );

/**
 * Rename the editor labels so the fields read as what they hold.
 *
 * @param array $labels Default post labels.
 * @return array
 */
function htt_quote_enter_title( $title ) {
	if ( 'htt_quote' === get_post_type() ) {
		return 'Tên vĩ nhân';
	}

	return $title;
}
add_filter( 'enter_title_here', 'htt_quote_enter_title' );

/**
 * Rename the featured-image box for quotes.
 */
function htt_quote_thumbnail_label() {
	remove_meta_box( 'postimagediv', 'htt_quote', 'side' );
	add_meta_box(
		'postimagediv',
		'Ảnh chân dung',
		'post_thumbnail_meta_box',
		'htt_quote',
		'side',
		'high'
	);
}
add_action( 'do_meta_boxes', 'htt_quote_thumbnail_label' );

/**
 * Columns for the quote list screen.
 *
 * @param array $cols Existing columns.
 * @return array
 */
function htt_quote_columns( $cols ) {
	return array(
		'cb'         => $cols['cb'] ?? '',
		'htt_thumb'  => 'Ảnh',
		'title'      => 'Tên vĩ nhân',
		'htt_quote'  => 'Câu nói',
		'menu_order' => 'Thứ tự',
		'date'       => $cols['date'] ?? 'Ngày',
	);
}
add_filter( 'manage_htt_quote_posts_columns', 'htt_quote_columns' );

/**
 * Fill the custom columns.
 *
 * @param string $col Column key.
 * @param int    $post_id Post being listed.
 */
function htt_quote_column_content( $col, $post_id ) {
	if ( 'htt_thumb' === $col ) {
		$thumb = get_the_post_thumbnail( $post_id, array( 48, 48 ), array( 'style' => 'border-radius:50%' ) );
		echo $thumb ? wp_kses_post( $thumb ) : '—';
		return;
	}

	if ( 'htt_quote' === $col ) {
		$text = wp_strip_all_tags( get_post_field( 'post_content', $post_id ) );
		echo esc_html( mb_strimwidth( trim( $text ), 0, 90, '…' ) );
		return;
	}

	if ( 'menu_order' === $col ) {
		echo (int) get_post_field( 'menu_order', $post_id );
	}
}
add_action( 'manage_htt_quote_posts_custom_column', 'htt_quote_column_content', 10, 2 );

/**
 * Order the quote list by the manual order field.
 *
 * @param WP_Query $query Current admin query.
 */
function htt_quote_admin_order( $query ) {
	if ( ! is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( 'htt_quote' !== $query->get( 'post_type' ) ) {
		return;
	}

	if ( ! $query->get( 'orderby' ) ) {
		$query->set( 'orderby', 'menu_order title' );
		$query->set( 'order', 'ASC' );
	}
}
add_action( 'pre_get_posts', 'htt_quote_admin_order' );

/**
 * Render the auto-advancing quote slider.
 *
 * Prints nothing when no quotes exist, so the homepage simply skips the
 * section until the first quote is added.
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function htt_quotes_slider( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'interval' => 3000,
			'limit'    => 12,
		),
		$atts,
		'htt_quotes'
	);

	$quotes = get_posts(
		array(
			'post_type'      => 'htt_quote',
			'post_status'    => 'publish',
			'posts_per_page' => (int) $atts['limit'],
			'orderby'        => array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			),
		)
	);

	if ( ! $quotes ) {
		return '';
	}

	$interval = max( 1500, (int) $atts['interval'] );

	ob_start();
	?>
	<section class="htt-quotes" aria-roledescription="carousel" aria-label="Câu nói của các vĩ nhân"
		data-htt-quotes data-interval="<?php echo esc_attr( (string) $interval ); ?>">

		<div class="htt-quotes__viewport">
			<?php foreach ( $quotes as $index => $quote ) : ?>
				<?php
				$text  = wp_strip_all_tags( $quote->post_content );
				$text  = trim( preg_replace( '/\s+/', ' ', $text ) );
				$photo = get_the_post_thumbnail(
					$quote->ID,
					array( 96, 96 ),
					array(
						'class'   => 'htt-quotes__photo',
						'alt'     => sprintf( 'Ảnh %s', $quote->post_title ),
						'loading' => 0 === $index ? 'eager' : 'lazy',
					)
				);
				?>
				<figure class="htt-quotes__slide<?php echo 0 === $index ? ' is-active' : ''; ?>"
					role="group" aria-roledescription="slide"
					aria-label="<?php echo esc_attr( sprintf( '%d trong %d', $index + 1, count( $quotes ) ) ); ?>"
					<?php echo 0 === $index ? '' : 'aria-hidden="true"'; ?>>

					<blockquote class="htt-quotes__text"><p><?php echo esc_html( $text ); ?></p></blockquote>

					<?php if ( $photo ) : ?>
						<div class="htt-quotes__photo-wrap"><?php echo wp_kses_post( $photo ); ?></div>
					<?php endif; ?>

					<figcaption class="htt-quotes__name"><?php echo esc_html( $quote->post_title ); ?></figcaption>
				</figure>
			<?php endforeach; ?>
		</div>

		<?php if ( count( $quotes ) > 1 ) : ?>
			<div class="htt-quotes__dots" role="tablist" aria-label="Chọn câu nói">
				<?php foreach ( $quotes as $index => $quote ) : ?>
					<button type="button" class="htt-quotes__dot<?php echo 0 === $index ? ' is-active' : ''; ?>"
						role="tab" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>"
						aria-label="<?php echo esc_attr( sprintf( 'Câu nói của %s', $quote->post_title ) ); ?>"
						data-index="<?php echo esc_attr( (string) $index ); ?>"></button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'htt_quotes', 'htt_quotes_slider' );

/**
 * Section of hand-picked featured posts.
 *
 * Which posts appear, and in what order, is set per post in the editor
 * (box "Bài viết nổi bật"). Posts without an order fall to the end,
 * newest first, so ticking the box is enough to get a sensible result.
 *
 * Usage: [htt_featured] or [htt_featured title="Đọc nhiều" limit="4"]
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function htt_featured_posts( $atts = array() ) {
	$atts = shortcode_atts(
		array(
			'title' => 'Bài viết nổi bật',
			'limit' => 4,
		),
		$atts,
		'htt_featured'
	);

	$posts = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, (int) $atts['limit'] ),
			'meta_key'       => 'htt_featured_order',
			'orderby'        => array(
				'meta_value_num' => 'ASC',
				'date'           => 'DESC',
			),
			'meta_query'     => array(
				'relation' => 'AND',
				array(
					'key'     => 'htt_featured',
					'value'   => '1',
					'compare' => '=',
				),
			),
		)
	);

	// Nothing picked yet - print nothing rather than an empty heading.
	if ( ! $posts ) {
		return '';
	}

	// Build the markup as one unbroken string. WordPress runs wpautop on
	// shortcode output, and every blank line or line break in the HTML
	// would come back as a stray <p> or <br> - which is what pushes the
	// cards apart and breaks the anchor.
	$items = '';

	foreach ( $posts as $post ) {
		$thumb = get_the_post_thumbnail(
			$post->ID,
			'htt-card',
			array(
				'class'   => 'htt-featured__image',
				'loading' => 'lazy',
			)
		);

		$media = $thumb
			? '<div class="htt-featured__media">' . $thumb . '</div>'
			: '';

		$channel = htt_post_channel( $post->ID );
		$label   = $channel
			? sprintf(
				'<span class="htt-featured__channel htt-featured__channel--%1$s">%2$s</span>',
				esc_attr( $channel['slug'] ),
				esc_html( $channel['name'] )
			)
			: '';

		$items .= sprintf(
			'<li class="htt-featured__item"><a class="htt-featured__link" href="%1$s">%2$s%3$s'
			. '<h3 class="htt-featured__heading">%4$s</h3>'
			. '<time class="htt-featured__date" datetime="%5$s">%6$s</time></a></li>',
			esc_url( get_permalink( $post->ID ) ),
			$media,
			$label,
			esc_html( get_the_title( $post->ID ) ),
			esc_attr( get_the_date( 'c', $post->ID ) ),
			esc_html( get_the_date( '', $post->ID ) )
		);
	}

	$heading = '' !== trim( (string) $atts['title'] )
		? sprintf(
			'<h2 class="htt-featured__title" id="htt-featured-title">%s</h2>',
			esc_html( $atts['title'] )
		)
		: '';

	return sprintf(
		'<section class="htt-featured" aria-labelledby="htt-featured-title">%s<ul class="htt-featured__list">%s</ul></section>',
		$heading,
		$items
	);
}
add_shortcode( 'htt_featured', 'htt_featured_posts' );

/**
 * Keep wpautop away from the featured section.
 *
 * The shortcode returns a finished block of HTML. Left alone, wpautop
 * wraps parts of it in <p> and adds <br> - stray tags that put uneven
 * margins between the image, channel label and title, so the cards no
 * longer line up with the post grid above.
 *
 * @param string $content Rendered block content.
 * @param array  $block   Block being rendered.
 * @return string
 */
function htt_unautop_featured( $content, $block ) {
	if ( 'core/shortcode' !== ( $block['blockName'] ?? '' ) ) {
		return $content;
	}

	if ( false === strpos( $content, 'htt-featured' )
		&& false === strpos( $content, 'htt-print' ) ) {
		return $content;
	}

	// Drop the <p>/<br> wrappers wpautop added around our own markup.
	$tags    = 'section|ul|li|div|h[23]|span|time|a|button|svg|p|form|label|input';
	$content = preg_replace( '#<p>\s*(<(?:' . $tags . ')\b)#i', '$1', $content );
	$content = preg_replace( '#(</(?:' . $tags . ')>)\s*</p>#i', '$1', $content );
	$content = preg_replace( '#<p>\s*</p>#i', '', $content );
	$content = str_replace( '<br />', '', $content );

	return $content;
}
add_filter( 'render_block', 'htt_unautop_featured', 10, 2 );

/**
 * Output Schema.org structured data for single posts.
 *
 * Tells search engines this is an article, when it was published and
 * which image belongs to it - the data Google uses to decide how a
 * result is displayed.
 */
function htt_schema() {
	if ( ! is_singular( 'post' ) ) {
		return;
	}

	$post_id = get_the_ID();
	$image   = get_the_post_thumbnail_url( $post_id, 'htt-hero' )
		?: get_the_post_thumbnail_url( $post_id, 'full' );

	$desc = wp_strip_all_tags( (string) get_the_excerpt( $post_id ) );
	$desc = trim( preg_replace( '/\s+/', ' ', $desc ) );

	$data = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'Article',
		'headline'         => get_the_title( $post_id ),
		'datePublished'    => get_the_date( 'c', $post_id ),
		'dateModified'     => get_the_modified_date( 'c', $post_id ),
		'inLanguage'       => 'vi',
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => get_permalink( $post_id ),
		),
		'publisher'        => array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
		),
	);

	if ( '' !== $desc ) {
		$data['description'] = $desc;
	}

	if ( $image ) {
		$data['image'] = $image;
	}

	$channel = htt_post_channel( $post_id );

	if ( $channel ) {
		$data['articleSection'] = $channel['name'];
	}

	printf(
		'<script type="application/ld+json">%s</script>' . "\n",
		wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
	);

	// Facebook and Zalo read these when a link is shared.
	printf(
		'<meta property="article:published_time" content="%s" />' . "\n",
		esc_attr( get_the_date( 'c', $post_id ) )
	);
	printf(
		'<meta property="article:modified_time" content="%s" />' . "\n",
		esc_attr( get_the_modified_date( 'c', $post_id ) )
	);

	if ( $channel ) {
		printf( '<meta property="article:section" content="%s" />' . "\n", esc_attr( $channel['name'] ) );
	}
}
add_action( 'wp_head', 'htt_schema', 6 );

/**
 * Share buttons.
 *
 * Readers pass links on in Facebook and Zalo, so those two get real
 * buttons; everything else is covered by copying the link.
 *
 * @return string
 */
function htt_share_buttons() {
	if ( ! is_singular( 'post' ) ) {
		return '';
	}

	$url   = get_permalink();
	$title = get_the_title();

	$fb = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url );
	$zl = 'https://zalo.me/share?u=' . rawurlencode( $url ) . '&t=' . rawurlencode( $title );

	ob_start();
	?>
	<div class="htt-share">
		<span class="htt-share__label">Chia sẻ</span>

		<a class="htt-share__btn" href="<?php echo esc_url( $fb ); ?>"
			target="_blank" rel="noopener" aria-label="Chia sẻ lên Facebook">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M22 12a10 10 0 1 0-11.56 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.1 0 2.24.2 2.24.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.45 2.89h-2.33v6.99A10 10 0 0 0 22 12z"/></svg>
			<span>Facebook</span>
		</a>

		<a class="htt-share__btn" href="<?php echo esc_url( $zl ); ?>"
			target="_blank" rel="noopener" aria-label="Chia sẻ lên Zalo">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2C6.2 2 1.5 6 1.5 11c0 2.8 1.5 5.3 3.8 7-.2 1.3-.8 2.6-1.7 3.6-.2.2 0 .5.3.4 2-.5 3.6-1.4 4.6-2.1 1.1.3 2.3.5 3.5.5 5.8 0 10.5-4 10.5-9S17.8 2 12 2z"/></svg>
			<span>Zalo</span>
		</a>

		<button type="button" class="htt-share__btn htt-share__copy"
			data-url="<?php echo esc_attr( $url ); ?>" aria-label="Sao chép liên kết">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" d="M10.5 13.5a4 4 0 0 0 5.7 0l2.8-2.8a4 4 0 0 0-5.7-5.7l-1.3 1.3M13.5 10.5a4 4 0 0 0-5.7 0l-2.8 2.8a4 4 0 1 0 5.7 5.7l1.3-1.3"/></svg>
			<span>Sao chép link</span>
		</button>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'htt_share', 'htt_share_buttons' );

/**
 * Declare the site language as Vietnamese on the front end.
 *
 * The content is Vietnamese even though the admin runs in English, and
 * <html lang> is what screen readers use to pick a pronunciation and
 * what search engines use to classify the page.
 *
 * @param string $output Existing language attributes.
 * @param string $doctype Context the attributes are for.
 * @return string
 */
function htt_language_attributes( $output, $doctype = 'html' ) {
	if ( is_admin() || 'html' !== $doctype ) {
		return $output;
	}

	// Replace whatever locale WordPress inferred with the content language.
	$output = preg_replace( '/lang="[^"]*"/', 'lang="vi"', $output );

	if ( false === strpos( $output, 'lang=' ) ) {
		$output = trim( $output . ' lang="vi"' );
	}

	return $output;
}
add_filter( 'language_attributes', 'htt_language_attributes', 10, 2 );

/**
 * Vietnamese wording for the comment blocks.
 *
 * The site runs the English WordPress build, so these blocks render
 * "One response to", "Reply" and English dates. Readers here are
 * Vietnamese, and the comment area is the one place they are asked
 * to write something.
 *
 * @param string $content Rendered block markup.
 * @return string
 */
function htt_translate_comment_blocks( $content ) {
	return str_replace(
		array( '>Reply<', '>Leave a Reply<', '>Leave a Comment<', 'Cancel reply' ),
		array( '>Trả lời<', '>Để lại bình luận<', '>Để lại bình luận<', 'Huỷ' ),
		$content
	);
}
add_filter( 'render_block_core/comment-reply-link', 'htt_translate_comment_blocks' );
add_filter( 'render_block_core/post-comments-form', 'htt_translate_comment_blocks' );

/**
 * Vietnamese comments-title, including the plural forms.
 *
 * @param string $content Rendered block markup.
 * @return string
 */
function htt_comments_title( $content ) {
	$count = (int) get_comments_number();

	if ( ! $count ) {
		return $content;
	}

	$text = 1 === $count ? 'Một bình luận' : sprintf( '%d bình luận', $count );

	// Replace whatever English title WordPress produced.
	return preg_replace(
		'/(<h[1-6][^>]*>).*?(<\/h[1-6]>)/s',
		// Braces matter: with "2 bình luận" a bare $1 would be read as $12.
		'${1}' . esc_html( $text ) . '${2}',
		$content
	);
}
add_filter( 'render_block_core/comments-title', 'htt_comments_title' );

/**
 * Vietnamese date format for comments.
 *
 * @param string $content Rendered block markup.
 * @return string
 */
function htt_comment_date( $content ) {
	// The date text sits inside an <a> nested in <time>, so match the
	// whole <time> element and rewrite only its innermost text.
	return preg_replace_callback(
		'/<time[^>]*datetime="([^"]+)"[^>]*>.*?<\/time>/s',
		static function ( $m ) {
			$ts = strtotime( $m[1] );

			if ( ! $ts ) {
				return $m[0];
			}

			$date = esc_html( wp_date( 'j/n/Y', $ts ) );

			// Keep the permalink wrapper, swap the label. Braces are
			// required: a bare $1 followed by "13/11" would be read as
			// backreference $113 and swallow the opening <a> tag.
			return preg_replace( '/(<a[^>]*>).*?(<\/a>)/s', '${1}' . $date . '${2}', $m[0], 1 )
				?: $m[0];
		},
		$content
	);
}
add_filter( 'render_block_core/comment-date', 'htt_comment_date' );

/**
 * Vietnamese label for the "remember me" cookie checkbox.
 *
 * @param array $defaults Form arguments.
 * @return array
 */
function htt_comment_cookie_label( $defaults ) {
	if ( ! empty( $defaults['fields']['cookies'] ) ) {
		$defaults['fields']['cookies'] = preg_replace(
			'/(<label[^>]*>).*?(<\/label>)/s',
			'$1Lưu tên và email của tôi cho lần bình luận sau.$2',
			$defaults['fields']['cookies']
		);
	}

	return $defaults;
}
add_filter( 'comment_form_defaults', 'htt_comment_cookie_label', 20 );

/**
 * Vietnamese labels inside the comment form.
 *
 * @param array $fields Form field markup, keyed by name.
 * @return array
 */
function htt_comment_form_fields( $fields ) {
	// The website field attracts spam and readers here rarely have one.
	unset( $fields['url'] );

	$fields['author'] = str_replace(
		array( '>Name', 'Name <span', 'placeholder="Name"' ),
		array( '>Tên', 'Tên <span', 'placeholder="Tên"' ),
		$fields['author'] ?? ''
	);

	$fields['email'] = str_replace(
		array( '>Email', 'Email <span', 'placeholder="Email"' ),
		array( '>Email (không hiển thị công khai)', 'Email <span', 'placeholder="Email"' ),
		$fields['email'] ?? ''
	);

	return array_filter( $fields );
}
add_filter( 'comment_form_default_fields', 'htt_comment_form_fields' );

/**
 * Vietnamese defaults for the comment form itself.
 *
 * @param array $defaults Form arguments.
 * @return array
 */
function htt_comment_form_defaults( $defaults ) {
	$defaults['title_reply']        = 'Để lại bình luận';
	$defaults['title_reply_to']     = 'Trả lời %s';
	$defaults['cancel_reply_link']  = 'Huỷ';
	$defaults['label_submit']       = 'Gửi bình luận';
	$defaults['comment_notes_before'] = '';
	$defaults['comment_field']      = str_replace(
		'>Comment',
		'>Nội dung bình luận',
		$defaults['comment_field'] ?? ''
	);

	return $defaults;
}
add_filter( 'comment_form_defaults', 'htt_comment_form_defaults' );

/**
 * Translate the navigation block's built-in labels.
 *
 * The site runs the English WordPress build, so the mobile menu button
 * announces "Open menu" to screen readers. Readers here are Vietnamese.
 *
 * @param string $content Rendered navigation markup.
 * @return string
 */
function htt_translate_nav_labels( $content ) {
	return str_replace(
		array( 'aria-label="Open menu"', 'aria-label="Close menu"' ),
		array( 'aria-label="Mở menu"', 'aria-label="Đóng menu"' ),
		$content
	);
}
add_filter( 'render_block_core/navigation', 'htt_translate_nav_labels' );

/**
 * Keep the current post out of the "Nội dung khác" list.
 *
 * The Query Loop block has no exclude-current option. On a single post
 * every loop on the page is a "more posts" list, so excluding the post
 * being read is always what we want there.
 *
 * @param array $query Query args the block will run.
 * @return array
 */
function htt_exclude_current_post( $query ) {
	if ( ! is_singular( 'post' ) ) {
		return $query;
	}

	$current = get_queried_object_id();

	if ( ! $current ) {
		return $query;
	}

	$query['post__not_in'] = array_merge(
		$query['post__not_in'] ?? array(),
		array( $current )
	);

	return $query;
}
add_filter( 'query_loop_block_query_vars', 'htt_exclude_current_post' );

/**
 * Register 16:9 image sizes.
 *
 * Every post image here is a YouTube thumbnail, which is always 16:9 and
 * usually carries text. WordPress' default sizes crop to a square, which
 * would cut both sides off and destroy that text, so the theme adds its
 * own widescreen sizes and uses them instead.
 */
function htt_image_sizes() {
	add_theme_support( 'post-thumbnails' );

	// 16:9 crops at the three widths the layouts actually request.
	add_image_size( 'htt-card', 480, 270, true );   // feed card thumbnail
	add_image_size( 'htt-wide', 960, 540, true );   // single post header
	add_image_size( 'htt-hero', 1280, 720, true );  // full-width / retina
}
add_action( 'after_setup_theme', 'htt_image_sizes' );

/**
 * Offer the 16:9 sizes in the editor's image-size dropdown.
 *
 * @param array $sizes Existing size choices, keyed by slug.
 * @return array
 */
function htt_image_size_names( $sizes ) {
	return array_merge(
		$sizes,
		array(
			'htt-card' => __( 'The 16:9 (480px)', 'hanhtrinhtrithuc' ),
			'htt-wide' => __( 'Rong 16:9 (960px)', 'hanhtrinhtrithuc' ),
			'htt-hero' => __( 'Lon 16:9 (1280px)', 'hanhtrinhtrithuc' ),
		)
	);
}
add_filter( 'image_size_names_choose', 'htt_image_size_names' );

/**
 * Stop WordPress generating sizes this theme never renders.
 *
 * Each unused size is another file written per upload; on a library of
 * YouTube thumbnails that adds up fast, and disk space is tight.
 *
 * @param array $sizes Intermediate image sizes to generate.
 * @return array
 */
function htt_trim_default_sizes( $sizes ) {
	foreach ( array( 'medium_large', '1536x1536', '2048x2048' ) as $drop ) {
		unset( $sizes[ $drop ] );
	}

	return $sizes;
}
add_filter( 'intermediate_image_sizes_advanced', 'htt_trim_default_sizes' );
