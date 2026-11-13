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
}
add_action( 'category_edit_form_fields', 'htt_category_field' );

/**
 * Save the YouTube URL field.
 *
 * @param int $term_id Category being saved.
 */
function htt_save_category_field( $term_id ) {
	// Nonce is verified by WordPress before this hook fires on term save.
	if ( ! isset( $_POST['htt_youtube_url'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_categories' ) ) {
		return;
	}

	$url = esc_url_raw( wp_unslash( $_POST['htt_youtube_url'] ) );

	if ( '' === $url ) {
		delete_term_meta( $term_id, 'htt_youtube_url' );
	} else {
		update_term_meta( $term_id, 'htt_youtube_url', $url );
	}
}
add_action( 'edited_category', 'htt_save_category_field' );

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
