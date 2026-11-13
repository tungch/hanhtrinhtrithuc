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
}
add_action( 'init', 'htt_register_post_meta' );

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
