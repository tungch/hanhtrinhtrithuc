<?php
/**
 * Hanh Trinh Tri Thuc - child theme functions.
 *
 * @package HanhTrinhTriThuc
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
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

	if ( false === strpos( $content, 'htt-featured' ) ) {
		return $content;
	}

	// Drop the <p>/<br> wrappers wpautop added around our own markup.
	$content = preg_replace( '#<p>\s*(<(?:section|ul|li|div|h[23]|span|time|a)\b)#i', '$1', $content );
	$content = preg_replace( '#(</(?:section|ul|li|div|h[23]|span|time|a)>)\s*</p>#i', '$1', $content );
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
