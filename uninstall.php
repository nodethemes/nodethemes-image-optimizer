<?php
/**
 * Cleanup on uninstall. Converted images and WebP files are left untouched.
 *
 * @package NodeThemes_Image_Optimizer
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ntio_settings' );
delete_option( 'ntio_stats' );
delete_post_meta_by_key( '_ntio' );
// ntio_free_used is kept on purpose so reinstalling does not reset the free-version limit.
