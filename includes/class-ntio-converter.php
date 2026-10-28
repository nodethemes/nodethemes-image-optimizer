<?php
/**
 * Image conversion engine (free features). Pro features hook in through filters.
 *
 * @package NodeThemes_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles WebP conversion for new uploads and front-end delivery.
 */
class NTIO_Converter {

	/**
	 * Singleton.
	 *
	 * @var NTIO_Converter|null
	 */
	private static $instance = null;

	/**
	 * Files converted during the current upload request (Pro replace mode).
	 *
	 * @var array
	 */
	private $pending = array();

	/**
	 * Get instance.
	 *
	 * @return NTIO_Converter
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_filter( 'wp_editor_set_quality', array( $this, 'editor_quality' ), 20, 2 );
		add_filter( 'wp_delete_file', array( $this, 'delete_sibling' ) );
		add_action( 'template_redirect', array( $this, 'maybe_start_buffer' ), 1 );
	}

	/* ---------------------------------------------------------------------
	 * Helpers (some are used by the Pro module)
	 * ------------------------------------------------------------------ */

	/**
	 * Can this server write WebP?
	 *
	 * @return bool
	 */
	public static function supports_webp() {
		return (bool) wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
	}

	/**
	 * Which engine is available?
	 *
	 * @return string
	 */
	public static function engine_name() {
		if ( extension_loaded( 'imagick' ) && class_exists( 'Imagick' ) ) {
			$formats = array();
			try {
				$formats = Imagick::queryFormats( 'WEBP' );
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement
				$formats = array();
			}
			if ( ! empty( $formats ) ) {
				return 'Imagick';
			}
		}
		if ( function_exists( 'imagewebp' ) ) {
			return 'GD';
		}
		return '';
	}

	/**
	 * Is this mime type enabled for conversion?
	 *
	 * @param string $mime     Mime type.
	 * @param array  $settings Settings.
	 * @return bool
	 */
	public function is_convertible_mime( $mime, $settings ) {
		if ( 'image/jpeg' === $mime ) {
			return ! empty( $settings['convert_jpeg'] );
		}
		if ( 'image/png' === $mime ) {
			return ! empty( $settings['convert_png'] );
		}
		return false;
	}

	/**
	 * Enabled mime types.
	 *
	 * @return array
	 */
	public static function enabled_mimes() {
		$s     = ntio_get_settings();
		$mimes = array();
		if ( ! empty( $s['convert_jpeg'] ) ) {
			$mimes[] = 'image/jpeg';
		}
		if ( ! empty( $s['convert_png'] ) ) {
			$mimes[] = 'image/png';
		}
		return $mimes;
	}

	/**
	 * Settings -> converter arguments.
	 *
	 * @param array $s Settings.
	 * @return array
	 */
	public function args_from_settings( $s ) {
		return array(
			'quality'      => max( 10, min( 100, (int) $s['quality'] ) ),
			'max_width'    => max( 0, (int) $s['max_width'] ),
			'max_height'   => max( 0, (int) $s['max_height'] ),
			'max_filesize' => max( 0, (int) $s['max_filesize'] ) * 1024,
		);
	}

	/**
	 * Remember a converted upload until its metadata is generated.
	 *
	 * @param string $path File path.
	 * @param array  $info { orig, new }.
	 */
	public function register_pending( $path, $info ) {
		$this->pending[ $path ] = $info;
	}

	/**
	 * Add to global statistics.
	 *
	 * @param int $orig Original bytes.
	 * @param int $new  New bytes.
	 */
	private function add_stats( $orig, $new ) {
		$stats           = get_option( NTIO_STATS_OPTION, array() );
		$stats           = wp_parse_args( is_array( $stats ) ? $stats : array(), array( 'images' => 0, 'saved' => 0 ) );
		$stats['images'] = (int) $stats['images'] + 1;
		$stats['saved']  = (int) $stats['saved'] + max( 0, (int) $orig - (int) $new );
		update_option( NTIO_STATS_OPTION, $stats, false );
	}

	/**
	 * Store the per-attachment result and update global stats once.
	 *
	 * @param int   $id   Attachment ID.
	 * @param array $data Result data.
	 */
	public function record( $id, $data ) {
		$previous = get_post_meta( $id, NTIO_META, true );
		$data     = array_merge( array( 'status' => 'done', 'time' => time() ), $data );
		update_post_meta( $id, NTIO_META, $data );
		if ( 'done' === $data['status'] && ( ! is_array( $previous ) || empty( $previous['status'] ) || 'done' !== $previous['status'] ) ) {
			$this->add_stats( isset( $data['orig'] ) ? $data['orig'] : 0, isset( $data['new'] ) ? $data['new'] : 0 );
		}
	}

	/* ---------------------------------------------------------------------
	 * Core conversion
	 * ------------------------------------------------------------------ */

	/**
	 * Convert an image to WebP. Pro can take over via the ntio_pre_convert filter
	 * (dimension limits, file-size targeting).
	 *
	 * @param string $src  Source path.
	 * @param string $dest Destination (.webp).
	 * @param array  $args Arguments.
	 * @return array|WP_Error { path, bytes, quality }
	 */
	public function convert( $src, $dest, $args = array() ) {
		$pre = apply_filters( 'ntio_pre_convert', null, $src, $dest, $args );
		if ( null !== $pre ) {
			return $pre;
		}

		$a = wp_parse_args( $args, array( 'quality' => 80 ) );

		if ( ! self::supports_webp() ) {
			return new WP_Error( 'ntio_no_webp', __( 'This server cannot create WebP images (GD with WebP support or Imagick is required).', 'nodethemes-image-optimizer' ) );
		}
		if ( ! file_exists( $src ) ) {
			return new WP_Error( 'ntio_missing', __( 'Source file not found.', 'nodethemes-image-optimizer' ) );
		}

		wp_raise_memory_limit( 'image' );

		$editor = wp_get_image_editor( $src );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}
		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$editor->maybe_exif_rotate();
		}

		$quality = max( 10, min( 100, (int) $a['quality'] ) );
		$editor->set_quality( $quality );
		$saved = $editor->save( $dest, 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$bytes = (int) filesize( $saved['path'] );
		if ( $bytes <= 0 ) {
			return new WP_Error( 'ntio_empty', __( 'The converted file is empty.', 'nodethemes-image-optimizer' ) );
		}

		return array(
			'path'    => $saved['path'],
			'bytes'   => $bytes,
			'quality' => $quality,
		);
	}

	/* ---------------------------------------------------------------------
	 * Upload pipeline
	 * ------------------------------------------------------------------ */

	/**
	 * Quality for WebP sub-sizes generated by WordPress.
	 *
	 * @param int    $quality Quality.
	 * @param string $mime    Mime.
	 * @return int
	 */
	public function editor_quality( $quality, $mime ) {
		if ( 'image/webp' === $mime ) {
			$s = ntio_get_settings();
			return max( 10, min( 100, (int) $s['quality'] ) );
		}
		return $quality;
	}

	/**
	 * Fresh upload (hooked by the Pro add-on for automatic optimization,
	 * replace mode and dimension limits).
	 *
	 * @param array $upload { file, url, type }.
	 * @return array
	 */
	public function handle_upload( $upload ) {
		$s = ntio_get_settings();

		if ( empty( $s['auto_optimize'] ) || ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) || empty( $upload['type'] ) ) {
			return $upload;
		}
		if ( ! $this->is_convertible_mime( $upload['type'], $s ) ) {
			return $upload;
		}

		return apply_filters( 'ntio_handle_upload', $upload, $s );
	}

	/**
	 * After WordPress built the sub-sizes.
	 *
	 * @param array $metadata Metadata.
	 * @param int   $id       Attachment ID.
	 * @return array
	 */
	public function after_metadata( $metadata, $id ) {
		if ( ! is_array( $metadata ) || ! wp_attachment_is_image( $id ) ) {
			return $metadata;
		}

		$s    = ntio_get_settings();
		$file = get_attached_file( $id );

		// Pro replace mode: the upload was already converted.
		if ( $file && isset( $this->pending[ $file ] ) ) {
			$info = $this->pending[ $file ];
			unset( $this->pending[ $file ] );
			$this->record(
				$id,
				array(
					'mode' => 'replace',
					'orig' => $info['orig'],
					'new'  => $info['new'],
				)
			);
			return $metadata;
		}

		// Keep mode: WebP copies for the original and every size.
		if ( 'keep' === $s['mode'] && ! empty( $s['auto_optimize'] ) && self::supports_webp() ) {
			$mime = get_post_mime_type( $id );
			if ( $this->is_convertible_mime( $mime, $s ) ) {
				$result = $this->create_siblings( $id, $metadata );
				if ( ! is_wp_error( $result ) && $result['count'] > 0 ) {
					$this->record(
						$id,
						array(
							'mode'  => 'keep',
							'orig'  => $result['orig'],
							'new'   => $result['new'],
							'files' => $result['count'],
						)
					);
				}
			}
		}

		return $metadata;
	}

	/* ---------------------------------------------------------------------
	 * Keep-original mode (WebP siblings)
	 * ------------------------------------------------------------------ */

	/**
	 * Create image.ext.webp next to each file of an attachment.
	 *
	 * @param int        $id       Attachment ID.
	 * @param array|null $metadata Metadata.
	 * @return array|WP_Error { count, orig, new }
	 */
	public function create_siblings( $id, $metadata = null ) {
		$s    = ntio_get_settings();
		$file = get_attached_file( $id );
		if ( ! $file || ! file_exists( $file ) ) {
			return new WP_Error( 'ntio_missing', __( 'Attachment file not found.', 'nodethemes-image-optimizer' ) );
		}
		if ( null === $metadata ) {
			$metadata = wp_get_attachment_metadata( $id );
		}

		$dir   = dirname( $file );
		$files = array( $file );
		if ( is_array( $metadata ) ) {
			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size ) {
					if ( ! empty( $size['file'] ) ) {
						$files[] = $dir . '/' . $size['file'];
					}
				}
			}
			if ( ! empty( $metadata['original_image'] ) ) {
				$files[] = $dir . '/' . $metadata['original_image'];
			}
		}
		$files = array_unique( $files );

		// Siblings must keep the same pixel size as the files they mirror.
		$args               = $this->args_from_settings( $s );
		$args['max_width']  = 0;
		$args['max_height'] = 0;

		$count      = 0;
		$orig_total = 0;
		$new_total  = 0;

		foreach ( $files as $f ) {
			if ( ! file_exists( $f ) || ! preg_match( '/\.(jpe?g|png)$/i', $f ) ) {
				continue;
			}
			$dest   = $f . '.webp';
			$result = $this->convert( $f, $dest, $args );
			if ( is_wp_error( $result ) ) {
				if ( file_exists( $dest ) ) {
					wp_delete_file( $dest );
				}
				continue;
			}
			$orig_bytes = (int) filesize( $f );
			if ( $result['bytes'] >= $orig_bytes ) {
				wp_delete_file( $result['path'] );
				continue;
			}
			$count++;
			$orig_total += $orig_bytes;
			$new_total  += $result['bytes'];
		}

		return array(
			'count' => $count,
			'orig'  => $orig_total,
			'new'   => $new_total,
		);
	}

	/**
	 * Remove a WebP sibling when WordPress deletes the original file.
	 *
	 * @param string $file File path.
	 * @return string
	 */
	public function delete_sibling( $file ) {
		if ( is_string( $file ) && preg_match( '/\.(jpe?g|png)$/i', $file ) && file_exists( $file . '.webp' ) ) {
			wp_delete_file( $file . '.webp' );
		}
		return $file;
	}

	/* ---------------------------------------------------------------------
	 * Front-end delivery for keep mode
	 * ------------------------------------------------------------------ */

	/**
	 * Buffer public pages in keep mode.
	 */
	public function maybe_start_buffer() {
		$s = ntio_get_settings();
		if ( 'keep' !== $s['mode'] ) {
			return;
		}
		if ( is_admin() || is_feed() || is_customize_preview() || wp_doing_ajax() || is_embed() ) {
			return;
		}
		if ( ! apply_filters( 'ntio_enable_frontend_rewrite', true ) ) {
			return;
		}
		ob_start( array( $this, 'rewrite_html' ) );
	}

	/**
	 * Swap JPEG/PNG upload URLs for their WebP sibling when one exists.
	 *
	 * @param string $html Page HTML.
	 * @return string
	 */
	public function rewrite_html( $html ) {
		if ( ! is_string( $html ) || '' === $html || false === stripos( $html, '<html' ) ) {
			return $html;
		}

		$uploads = wp_get_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return $html;
		}

		$base_url = preg_replace( '#^https?:#i', '', $uploads['baseurl'] );
		$base_dir = $uploads['basedir'];
		$pattern  = '#((?:https?:)?' . preg_quote( $base_url, '#' ) . '/[^\s"\'()<>,]+?\.(?:jpe?g|png))(?=[\s"\'(),<>?\#]|$)#i';

		$cache = array();

		$out = preg_replace_callback(
			$pattern,
			function ( $m ) use ( $base_url, $base_dir, &$cache ) {
				$url = $m[1];
				if ( isset( $cache[ $url ] ) ) {
					return $cache[ $url ];
				}
				$rel = substr( preg_replace( '#^https?:#i', '', $url ), strlen( $base_url ) );
				if ( false !== strpos( $rel, '..' ) ) {
					$cache[ $url ] = $url;
					return $url;
				}
				$path          = $base_dir . rawurldecode( $rel );
				$cache[ $url ] = file_exists( $path . '.webp' ) ? $url . '.webp' : $url;
				return $cache[ $url ];
			},
			$html
		);

		return ( null === $out ) ? $html : $out;
	}
}
