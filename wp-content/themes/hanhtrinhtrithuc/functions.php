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
