<?php
/**
 * Plugin Name:       NodeThemes Image Optimizer
 * Description:       Convert JPEG and PNG images to lightweight WebP with one click from your Media Library. Designed and developed by NodeThemes.
 * Version:           1.4.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            NodeThemes
 * Author URI:        https://nodethemes.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       nodethemes-image-optimizer
 * Update URI:        https://nodethemes.com/nodethemes-image-optimizer
 *
 * @package NodeThemes_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

define( 'NTIO_VERSION', '1.4.1' );
define( 'NTIO_FILE', __FILE__ );
define( 'NTIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'NTIO_URL', plugin_dir_url( __FILE__ ) );
define( 'NTIO_BASENAME', plugin_basename( __FILE__ ) );
define( 'NTIO_PAGE_SLUG', 'nodethemes-image-optimizer' );
define( 'NTIO_OPTION', 'ntio_settings' );
define( 'NTIO_STATS_OPTION', 'ntio_stats' );
define( 'NTIO_USED_OPTION', 'ntio_free_used' );
define( 'NTIO_META', '_ntio' );
define( 'NTIO_BRAND_NAME', 'NodeThemes' );
define( 'NTIO_BRAND_URL', 'https://nodethemes.com/' );

// Where to learn about the separate Pro add-on (information link only).
if ( ! defined( 'NTIO_BUY_URL' ) ) {
	define( 'NTIO_BUY_URL', 'https://nodethemes.com/product/nodethemes-image-optimizer-pro/' );
}

// How many images the free version can optimize manually before Pro is needed.
if ( ! defined( 'NTIO_FREE_LIMIT' ) ) {
	define( 'NTIO_FREE_LIMIT', 50 );
}

/**
 * Default settings.
 *
 * @return array
 */
function ntio_defaults() {
	return apply_filters(
		'ntio_default_settings',
		array(
			'auto_optimize' => 1,
			'mode'          => 'keep', // keep (free) | replace (Pro).
			'quality'       => 80,
			'max_width'     => 0,      // Pro.
			'max_height'    => 0,      // Pro.
			'max_filesize'  => 0,      // Pro (KB).
			'convert_jpeg'  => 1,
			'convert_png'   => 1,
		)
	);
}

/**
 * Is the separate Pro add-on active? The add-on turns this on via the filter.
 *
 * @return bool
 */
function ntio_is_pro() {
	return (bool) apply_filters( 'ntio_is_pro', false );
}

/**
 * Get effective settings (Pro-only values are neutralised in the free plugin).
 *
 * @return array
 */
function ntio_get_settings() {
	$saved = get_option( NTIO_OPTION, array() );
	$s     = wp_parse_args( is_array( $saved ) ? $saved : array(), ntio_defaults() );

	if ( ! ntio_is_pro() ) {
		$s['auto_optimize'] = 0;
		$s['mode']         = 'keep';
		$s['max_width']    = 0;
		$s['max_height']   = 0;
		$s['max_filesize'] = 0;
	}
	return $s;
}

/**
 * Free-version limit (manual optimizations). 0 means unlimited.
 *
 * @return int
 */
function ntio_free_limit() {
	return max( 0, (int) NTIO_FREE_LIMIT );
}

/**
 * How many free optimizations have been used on this site.
 *
 * @return int
 */
function ntio_free_used() {
	return max( 0, (int) get_option( NTIO_USED_OPTION, 0 ) );
}

/**
 * Has the free limit been reached? Never true when Pro is active.
 *
 * @return bool
 */
function ntio_limit_reached() {
	if ( ntio_is_pro() || 0 === ntio_free_limit() ) {
		return false;
	}
	return ntio_free_used() >= ntio_free_limit();
}

/**
 * Format bytes for humans.
 *
 * @param int $bytes Bytes.
 * @return string
 */
function ntio_format_bytes( $bytes ) {
	$bytes = (int) $bytes;
	if ( $bytes <= 0 ) {
		return '0 KB';
	}
	return size_format( $bytes, $bytes >= MB_IN_BYTES ? 2 : 1 );
}

require_once NTIO_DIR . 'includes/class-ntio-converter.php';
require_once NTIO_DIR . 'includes/class-ntio-admin.php';
require_once NTIO_DIR . 'includes/class-ntio-updater.php';

// Free updates from nodethemes.com.
NTIO_Free_Updater::boot( NTIO_FILE, NTIO_VERSION, NTIO_BRAND_URL );

register_activation_hook(
	__FILE__,
	function () {
		if ( false === get_option( NTIO_OPTION, false ) ) {
			add_option( NTIO_OPTION, ntio_defaults() );
		}
		if ( false === get_option( NTIO_STATS_OPTION, false ) ) {
			add_option( NTIO_STATS_OPTION, array( 'images' => 0, 'saved' => 0 ), '', false );
		}
		if ( false === get_option( NTIO_USED_OPTION, false ) ) {
			add_option( NTIO_USED_OPTION, 0, '', false );
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		NTIO_Converter::instance();
		if ( is_admin() ) {
			NTIO_Admin::instance();
		}
	},
	10
);
