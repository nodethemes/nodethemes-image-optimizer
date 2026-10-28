<?php
/**
 * Admin UI: settings and branding.
 *
 * @package NodeThemes_Image_Optimizer
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin interface.
 */
class NTIO_Admin {

	/**
	 * Singleton.
	 *
	 * @var NTIO_Admin|null
	 */
	private static $instance = null;

	/**
	 * Page hook suffix.
	 *
	 * @var string
	 */
	private $hook = '';

	/**
	 * Get instance.
	 *
	 * @return NTIO_Admin
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
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'plugin_action_links_' . NTIO_BASENAME, array( $this, 'action_links' ) );
		add_filter( 'plugin_row_meta', array( $this, 'row_meta' ), 10, 2 );
		add_filter( 'manage_media_columns', array( $this, 'media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'media_column_content' ), 10, 2 );
		add_filter( 'attachment_fields_to_edit', array( $this, 'attachment_field' ), 10, 2 );
		add_action( 'admin_post_ntio_optimize_one', array( $this, 'handle_optimize_one' ) );
		add_action( 'admin_notices', array( $this, 'optimize_notice' ) );
	}

	/* ---------------------------------------------------------------------
	 * Menu, settings, assets
	 * ------------------------------------------------------------------ */

	/**
	 * Register the page under Media.
	 */
	public function menu() {
		$this->hook = add_media_page(
			__( 'NodeThemes Image Optimizer', 'nodethemes-image-optimizer' ),
			__( 'NodeThemes Optimizer', 'nodethemes-image-optimizer' ),
			'manage_options',
			NTIO_PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register the option.
	 */
	public function register_settings() {
		register_setting(
			'ntio_group',
			NTIO_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => ntio_defaults(),
			)
		);
	}

	/**
	 * Sanitize settings. Pro-only fields keep their old value without a license.
	 *
	 * @param mixed $in Raw input.
	 * @return array
	 */
	public function sanitize( $in ) {
		$d   = ntio_defaults();
		$in  = is_array( $in ) ? $in : array();
		$old = get_option( NTIO_OPTION, array() );
		$old = is_array( $old ) ? $old : array();
		$pro = ntio_is_pro();

		$out                  = array();
		$out['auto_optimize'] = $pro
			? ( empty( $in['auto_optimize'] ) ? 0 : 1 )
			: ( isset( $old['auto_optimize'] ) ? (int) (bool) $old['auto_optimize'] : (int) $d['auto_optimize'] );
		$out['quality']       = max( 10, min( 100, isset( $in['quality'] ) ? absint( $in['quality'] ) : $d['quality'] ) );
		$out['convert_jpeg']  = empty( $in['convert_jpeg'] ) ? 0 : 1;
		$out['convert_png']   = empty( $in['convert_png'] ) ? 0 : 1;

		if ( $pro ) {
			$out['mode']         = ( isset( $in['mode'] ) && 'replace' === $in['mode'] ) ? 'replace' : 'keep';
			$out['max_width']    = min( 10000, isset( $in['max_width'] ) ? absint( $in['max_width'] ) : 0 );
			$out['max_height']   = min( 10000, isset( $in['max_height'] ) ? absint( $in['max_height'] ) : 0 );
			$out['max_filesize'] = min( 20000, isset( $in['max_filesize'] ) ? absint( $in['max_filesize'] ) : 0 );
		} else {
			$out['mode']         = isset( $old['mode'] ) && 'replace' === $old['mode'] ? 'replace' : 'keep';
			$out['max_width']    = isset( $old['max_width'] ) ? absint( $old['max_width'] ) : 0;
			$out['max_height']   = isset( $old['max_height'] ) ? absint( $old['max_height'] ) : 0;
			$out['max_filesize'] = isset( $old['max_filesize'] ) ? absint( $old['max_filesize'] ) : 0;
		}

		return $out;
	}

	/**
	 * Enqueue assets on our page only.
	 *
	 * @param string $hook Hook suffix.
	 */
	public function assets( $hook ) {
		if ( $hook !== $this->hook ) {
			return;
		}
		wp_enqueue_style( 'ntio-admin', NTIO_URL . 'assets/admin.css', array(), NTIO_VERSION );
		wp_enqueue_script( 'ntio-admin', NTIO_URL . 'assets/admin.js', array( 'jquery' ), NTIO_VERSION, true );
	}

	/* ---------------------------------------------------------------------
	 * Plugins list branding
	 * ------------------------------------------------------------------ */

	/**
	 * Settings link.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'upload.php?page=' . NTIO_PAGE_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'nodethemes-image-optimizer' ) . '</a>' );
		if ( ! ntio_is_pro() ) {
			$links[] = '<a href="' . esc_url( NTIO_BUY_URL ) . '" target="_blank" rel="noopener" style="color:#6366f1;font-weight:600">' . esc_html__( 'Go Pro', 'nodethemes-image-optimizer' ) . '</a>';
		}
		return $links;
	}

	/**
	 * Brand links.
	 *
	 * @param array  $links Links.
	 * @param string $file  Plugin file.
	 * @return array
	 */
	public function row_meta( $links, $file ) {
		if ( NTIO_BASENAME === $file ) {
			$links[] = '<a href="' . esc_url( NTIO_BRAND_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Visit NodeThemes', 'nodethemes-image-optimizer' ) . '</a>';
			$links[] = '<a href="' . esc_url( NTIO_BRAND_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'More Themes & Plugins', 'nodethemes-image-optimizer' ) . '</a>';
		}
		return $links;
	}

	/* ---------------------------------------------------------------------
	 * Media Library column
	 * ------------------------------------------------------------------ */

	/**
	 * Add a column.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public function media_column( $cols ) {
		$cols['ntio'] = __( 'WebP Optimization', 'nodethemes-image-optimizer' );
		return $cols;
	}

	/**
	 * Column output.
	 *
	 * @param string $column Column.
	 * @param int    $id     Attachment ID.
	 */
	public function media_column_content( $column, $id ) {
		if ( 'ntio' !== $column ) {
			return;
		}
		$meta = get_post_meta( $id, NTIO_META, true );
		if ( ! is_array( $meta ) || empty( $meta['status'] ) ) {
			$this->optimize_button( $id );
			return;
		}
		if ( 'done' === $meta['status'] && ! empty( $meta['orig'] ) ) {
			$saved = max( 0, (int) $meta['orig'] - (int) $meta['new'] );
			$pct   = (int) round( $saved / max( 1, (int) $meta['orig'] ) * 100 );
			printf(
				'<strong style="color:#059669">%s</strong><br><small>%s &rarr; %s</small>',
				/* translators: %d: percent saved */
				esc_html( sprintf( __( 'Saved %d%%', 'nodethemes-image-optimizer' ), $pct ) ),
				esc_html( ntio_format_bytes( $meta['orig'] ) ),
				esc_html( ntio_format_bytes( $meta['new'] ) )
			);
			return;
		}
		echo '<span class="ntio-muted" title="' . esc_attr( isset( $meta['reason'] ) ? $meta['reason'] : '' ) . '">' . esc_html__( 'Skipped', 'nodethemes-image-optimizer' ) . '</span>';
		$this->optimize_button( $id, true );
	}

	/**
	 * Show the same status / "Optimize" button in the attachment details
	 * (media modal and attachment edit screen).
	 *
	 * @param array   $fields Fields.
	 * @param WP_Post $post   Attachment.
	 * @return array
	 */
	public function attachment_field( $fields, $post ) {
		if ( ! $post || ! wp_attachment_is_image( $post->ID ) ) {
			return $fields;
		}
		ob_start();
		$this->media_column_content( 'ntio', $post->ID );
		$html = trim( ob_get_clean() );

		$fields['ntio'] = array(
			'label' => __( 'WebP Optimization', 'nodethemes-image-optimizer' ),
			'input' => 'html',
			'html'  => $html,
		);
		return $fields;
	}

	/**
	 * "Optimize" link for one image (manual optimization).
	 *
	 * @param int  $id    Attachment ID.
	 * @param bool $retry Is this a retry?
	 */
	private function optimize_button( $id, $retry = false ) {
		$mime = get_post_mime_type( $id );
		if ( ! NTIO_Converter::supports_webp() || ! NTIO_Converter::instance()->is_convertible_mime( $mime, ntio_get_settings() ) || ! current_user_can( 'edit_post', $id ) ) {
			if ( ! $retry ) {
				echo '<span class="ntio-muted">&mdash;</span>';
			}
			return;
		}
		if ( ntio_limit_reached() ) {
			echo ' <a class="button button-small" style="color:#6366f1;font-weight:600" href="' . esc_url( NTIO_BUY_URL ) . '" target="_blank" rel="noopener">' . esc_html__( 'Limit reached: Go Pro', 'nodethemes-image-optimizer' ) . '</a>';
			return;
		}
		$url = wp_nonce_url( add_query_arg( array( 'action' => 'ntio_optimize_one', 'id' => (int) $id ), admin_url( 'admin-post.php' ) ), 'ntio_optimize_one' );
		echo ' <a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html( $retry ? __( 'Retry', 'nodethemes-image-optimizer' ) : __( 'Optimize', 'nodethemes-image-optimizer' ) ) . '</a>';
	}

	/**
	 * Handle the manual "Optimize" click.
	 */
	public function handle_optimize_one() {
		check_admin_referer( 'ntio_optimize_one' );
		$id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $id || ! current_user_can( 'edit_post', $id ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'nodethemes-image-optimizer' ), 403 );
		}

		$engine = NTIO_Converter::instance();
		$mime   = get_post_mime_type( $id );
		$s      = ntio_get_settings();

		// Free version: manual optimization is limited. Pro has no limit.
		if ( ntio_limit_reached() ) {
			$this->set_notice(
				'warning',
				sprintf(
					/* translators: 1: free limit, 2: Pro page URL */
					__( 'Free limit reached: you have optimized %1$d images. Get the Pro version to keep optimizing without limits: %2$s', 'nodethemes-image-optimizer' ),
					ntio_free_limit(),
					NTIO_BUY_URL
				)
			);
			$back = wp_get_referer();
			wp_safe_redirect( $back ? $back : admin_url( 'upload.php' ) );
			exit;
		}

		if ( ! NTIO_Converter::supports_webp() ) {
			$result = new WP_Error( 'ntio_no_webp', __( 'WebP is not supported on this server.', 'nodethemes-image-optimizer' ) );
		} elseif ( ! $engine->is_convertible_mime( $mime, $s ) ) {
			$result = new WP_Error( 'ntio_type', __( 'This file type is not enabled for optimization.', 'nodethemes-image-optimizer' ) );
		} else {
			$result = $engine->create_siblings( $id );
			if ( ! is_wp_error( $result ) && $result['count'] < 1 ) {
				$result = new WP_Error( 'ntio_larger', __( 'WebP versions were not smaller than the originals.', 'nodethemes-image-optimizer' ) );
			}
		}

		if ( is_wp_error( $result ) ) {
			update_post_meta( $id, NTIO_META, array( 'status' => 'skipped', 'reason' => $result->get_error_message(), 'time' => time() ) );
			$this->set_notice( 'warning', $result->get_error_message() );
		} else {
			$previous = get_post_meta( $id, NTIO_META, true );
			$engine->record( $id, array( 'mode' => 'keep', 'orig' => $result['orig'], 'new' => $result['new'], 'files' => $result['count'] ) );
			// Count each image once (re-optimizing a finished image is free).
			if ( ! ntio_is_pro() && ( ! is_array( $previous ) || empty( $previous['status'] ) || 'done' !== $previous['status'] ) ) {
				update_option( NTIO_USED_OPTION, ntio_free_used() + 1, false );
			}
			$this->set_notice( 'success', __( 'Image optimized: WebP copies were created.', 'nodethemes-image-optimizer' ) );
		}

		$back = wp_get_referer();
		wp_safe_redirect( $back ? $back : admin_url( 'upload.php' ) );
		exit;
	}

	/**
	 * Store a one-time notice for the current user.
	 *
	 * @param string $type    success|warning.
	 * @param string $message Message.
	 */
	private function set_notice( $type, $message ) {
		set_transient( 'ntio_notice_' . get_current_user_id(), array( $type, $message ), 60 );
	}

	/**
	 * Show (and clear) the one-time notice.
	 */
	public function optimize_notice() {
		$key = 'ntio_notice_' . get_current_user_id();
		$n   = get_transient( $key );
		if ( ! is_array( $n ) ) {
			return;
		}
		delete_transient( $key );
		printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $n[0] ), esc_html( $n[1] ) );
	}

	/* ---------------------------------------------------------------------
	 * Page
	 * ------------------------------------------------------------------ */

	/**
	 * Brand mark.
	 *
	 * @return string
	 */
	private function logo() {
		return '<svg class="ntio-logo" viewBox="0 0 48 48" width="44" height="44" aria-hidden="true" focusable="false"><defs><linearGradient id="ntioG" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6366f1"/><stop offset="1" stop-color="#8b5cf6"/></linearGradient></defs><rect width="48" height="48" rx="12" fill="url(#ntioG)"/><path d="M14 35V13h4.2l11.6 16.2V13H34v22h-4.1L18.2 18.8V35z" fill="#fff"/></svg>';
	}

	/**
	 * Render the page.
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$webp_ok = NTIO_Converter::supports_webp();
		$pro     = ntio_is_pro();
		?>
		<div class="wrap ntio-wrap">
			<h1 class="screen-reader-text"><?php esc_html_e( 'NodeThemes Image Optimizer', 'nodethemes-image-optimizer' ); ?></h1>

			<header class="ntio-header">
				<div class="ntio-brand">
					<?php echo $this->logo(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					<div>
						<h2>
							<?php esc_html_e( 'NodeThemes Image Optimizer', 'nodethemes-image-optimizer' ); ?>
							<?php if ( $pro ) : ?>
								<span class="ntio-pro-badge ntio-pro-badge--on">PRO</span>
							<?php endif; ?>
						</h2>
						<p>
							<?php
							printf(
								/* translators: %s: brand link */
								esc_html__( 'Designed & developed by %s', 'nodethemes-image-optimizer' ),
								'<a href="' . esc_url( NTIO_BRAND_URL ) . '" target="_blank" rel="noopener">NodeThemes.com</a>'
							);
							?>
						</p>
					</div>
				</div>
				<span class="ntio-version">v<?php echo esc_html( NTIO_VERSION ); ?></span>
			</header>

			<?php if ( ! $webp_ok ) : ?>
				<div class="notice notice-error"><p>
					<strong><?php esc_html_e( 'WebP is not available on your server.', 'nodethemes-image-optimizer' ); ?></strong>
					<?php esc_html_e( 'Ask your host to enable the PHP GD extension with WebP support, or the Imagick extension with WebP support.', 'nodethemes-image-optimizer' ); ?>
				</p></div>
			<?php endif; ?>

			<?php settings_errors(); ?>

			<?php $this->render_settings( $pro, $webp_ok ); ?>

			<footer class="ntio-footer">
				<?php
				printf(
					/* translators: %s: brand link */
					esc_html__( 'NodeThemes Image Optimizer is designed and developed by %s — premium WordPress themes & plugins.', 'nodethemes-image-optimizer' ),
					'<a href="' . esc_url( NTIO_BRAND_URL ) . '" target="_blank" rel="noopener"><strong>NodeThemes</strong></a>'
				);
				?>
			</footer>
		</div>
		<?php
	}

	/**
	 * Settings tab.
	 *
	 * @param bool $pro     Pro active.
	 * @param bool $webp_ok WebP support.
	 */
	private function render_settings( $pro, $webp_ok ) {
		$s      = ntio_get_settings();
		$raw    = get_option( NTIO_OPTION, array() );
		$raw    = wp_parse_args( is_array( $raw ) ? $raw : array(), ntio_defaults() );
		$engine = NTIO_Converter::engine_name();
		$stats  = get_option( NTIO_STATS_OPTION, array() );
		$stats  = wp_parse_args( is_array( $stats ) ? $stats : array(), array( 'images' => 0, 'saved' => 0 ) );
		$opt    = NTIO_OPTION;
		$lock   = $pro ? '' : 'disabled';
		$badge  = $pro ? '' : '<span class="ntio-pro-badge">' . $this->lock_svg( 11 ) . 'PRO</span>';
		// Values shown for Pro fields: saved ones when Pro, defaults otherwise.
		$v_mode   = $pro ? $s['mode'] : 'keep';
		$v_width  = $pro ? (int) $s['max_width'] : (int) $raw['max_width'];
		$v_height = $pro ? (int) $s['max_height'] : (int) $raw['max_height'];
		$v_size   = $pro ? (int) $s['max_filesize'] : (int) $raw['max_filesize'];
		?>
		<section class="ntio-cards">
			<div class="ntio-card ntio-stat">
				<span class="ntio-stat-label"><?php esc_html_e( 'Images optimized', 'nodethemes-image-optimizer' ); ?></span>
				<strong id="ntio-stat-images"><?php echo esc_html( number_format_i18n( (int) $stats['images'] ) ); ?></strong>
			</div>
			<div class="ntio-card ntio-stat">
				<span class="ntio-stat-label"><?php esc_html_e( 'Disk space saved', 'nodethemes-image-optimizer' ); ?></span>
				<strong id="ntio-stat-saved"><?php echo esc_html( ntio_format_bytes( $stats['saved'] ) ); ?></strong>
			</div>
			<div class="ntio-card ntio-stat">
				<span class="ntio-stat-label"><?php esc_html_e( 'Image engine', 'nodethemes-image-optimizer' ); ?></span>
				<strong class="<?php echo $webp_ok ? 'ntio-ok' : 'ntio-bad'; ?>">
					<?php echo $webp_ok ? esc_html( $engine . ' · WebP ✓' ) : esc_html__( 'No WebP support', 'nodethemes-image-optimizer' ); ?>
				</strong>
			</div>
		</section>

		<?php if ( ! $pro ) : ?>
			<?php
			$limit     = ntio_free_limit();
			$used      = min( ntio_free_used(), $limit );
			$remaining = max( 0, $limit - $used );
			$pct       = $limit > 0 ? (int) round( $used / $limit * 100 ) : 0;
			?>
			<section class="ntio-card ntio-usage">
				<h3><?php esc_html_e( 'Free version usage', 'nodethemes-image-optimizer' ); ?></h3>
				<p>
					<?php
					printf(
						/* translators: 1: used, 2: limit */
						esc_html__( 'You have optimized %1$s of %2$s free images.', 'nodethemes-image-optimizer' ),
						'<strong>' . esc_html( number_format_i18n( $used ) ) . '</strong>',
						'<strong>' . esc_html( number_format_i18n( $limit ) ) . '</strong>'
					);
					?>
				</p>
				<div class="ntio-progress"><div class="ntio-progress-bar" style="width:<?php echo esc_attr( $pct ); ?>%"></div></div>
				<?php if ( $remaining > 0 ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: %s: remaining images */
							esc_html__( '%s free optimizations left. Upgrade to Pro for unlimited optimization, bulk mode and automatic optimization of new uploads.', 'nodethemes-image-optimizer' ),
							esc_html( number_format_i18n( $remaining ) )
						);
						?>
					</p>
				<?php else : ?>
					<p class="ntio-limit-msg"><strong><?php esc_html_e( 'You have reached the free limit.', 'nodethemes-image-optimizer' ); ?></strong> <?php esc_html_e( 'Your existing WebP images keep working. To optimize more images, get the Pro version.', 'nodethemes-image-optimizer' ); ?></p>
				<?php endif; ?>
				<a class="button button-primary" href="<?php echo esc_url( NTIO_BUY_URL ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Get Pro', 'nodethemes-image-optimizer' ); ?></a>
			</section>
		<?php endif; ?>

		<?php if ( ! $pro ) : ?>
			<section class="ntio-card ntio-howto">
				<h3><?php esc_html_e( 'How to optimize your images', 'nodethemes-image-optimizer' ); ?></h3>
				<ol>
					<li>
						<?php
						printf(
							/* translators: %s: link to the Media Library */
							esc_html__( 'Open the %s (list view).', 'nodethemes-image-optimizer' ),
							'<a href="' . esc_url( admin_url( 'upload.php?mode=list' ) ) . '">' . esc_html__( 'Media Library', 'nodethemes-image-optimizer' ) . '</a>'
						);
						?>
					</li>
					<li><?php esc_html_e( 'Click "Optimize" in the "WebP Optimization" column next to an image (or in the image details).', 'nodethemes-image-optimizer' ); ?></li>
					<li><?php esc_html_e( 'Done. The WebP copy is created and shown to your visitors automatically. The free version includes a limited number of optimizations.', 'nodethemes-image-optimizer' ); ?></li>
				</ol>
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'upload.php?mode=list' ) ); ?>"><?php esc_html_e( 'Go to Media Library', 'nodethemes-image-optimizer' ); ?></a>
			</section>
		<?php endif; ?>

		<form method="post" action="options.php" class="ntio-card ntio-form">
			<?php settings_fields( 'ntio_group' ); ?>
			<h3><?php esc_html_e( 'Optimization Settings', 'nodethemes-image-optimizer' ); ?></h3>

			<table class="form-table" role="presentation">
				<?php if ( $pro ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-optimize new uploads', 'nodethemes-image-optimizer' ); ?></th>
					<td>
						<label class="ntio-switch">
							<input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[auto_optimize]" value="1" <?php checked( 1, (int) $s['auto_optimize'] ); ?>>
							<span><?php esc_html_e( 'Create WebP versions the moment images are uploaded', 'nodethemes-image-optimizer' ); ?></span>
						</label>
					</td>
				</tr>
				<?php else : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Auto-optimize new uploads', 'nodethemes-image-optimizer' ); ?> <?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
					<td>
						<div class="ntio-lockwrap">
							<div class="ntio-mock" aria-hidden="true">
								<span class="ntio-switch"><span class="ntio-fake-check"></span><span><?php esc_html_e( 'Create WebP versions the moment images are uploaded', 'nodethemes-image-optimizer' ); ?></span></span>
							</div>
							<?php $this->lock_cta(); ?>
						</div>
						<p class="description"><?php esc_html_e( 'In the free version, optimize images one by one with the "Optimize" button in Media > Library (WebP Optimization column).', 'nodethemes-image-optimizer' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>

				<?php if ( $pro ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Original files', 'nodethemes-image-optimizer' ); ?> <?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
					<td>
						<label class="ntio-radio">
							<input type="radio" name="<?php echo esc_attr( $opt ); ?>[mode]" value="keep" <?php checked( 'keep', $v_mode ); ?>>
							<span>
								<strong><?php esc_html_e( 'Keep originals and serve WebP copies', 'nodethemes-image-optimizer' ); ?></strong>
								<em><?php esc_html_e( 'Non-destructive. WebP copies are created next to your files and swapped in on your public pages automatically. Best for page builders.', 'nodethemes-image-optimizer' ); ?></em>
							</span>
						</label>
						<label class="ntio-radio <?php echo $pro ? '' : 'ntio-locked'; ?>">
							<input type="radio" name="<?php echo esc_attr( $opt ); ?>[mode]" value="replace" <?php checked( 'replace', $v_mode ); ?> <?php echo esc_attr( $lock ); ?>>
							<span>
								<strong><?php esc_html_e( 'Replace with WebP (smallest disk usage)', 'nodethemes-image-optimizer' ); ?></strong>
								<em><?php esc_html_e( 'The WebP becomes the image in your Media Library and the original file is removed. If the WebP would not be smaller, the original is kept.', 'nodethemes-image-optimizer' ); ?></em>
							</span>
						</label>
					</td>
				</tr>
				<?php else : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Original files', 'nodethemes-image-optimizer' ); ?> <?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
					<td>
						<div class="ntio-radio ntio-radio--static is-selected">
							<span class="ntio-fake-radio is-on" aria-hidden="true"></span>
							<span>
								<strong><?php esc_html_e( 'Keep originals and serve WebP copies', 'nodethemes-image-optimizer' ); ?></strong>
								<em><?php esc_html_e( 'Non-destructive. WebP copies are created next to your files and swapped in on your public pages automatically. Best for page builders.', 'nodethemes-image-optimizer' ); ?></em>
							</span>
						</div>
						<div class="ntio-lockwrap">
							<div class="ntio-mock" aria-hidden="true">
								<div class="ntio-radio ntio-radio--static">
									<span class="ntio-fake-radio"></span>
									<span>
										<strong><?php esc_html_e( 'Replace with WebP (smallest disk usage)', 'nodethemes-image-optimizer' ); ?></strong>
										<em><?php esc_html_e( 'The WebP becomes the image in your Media Library and the original file is removed. If the WebP would not be smaller, the original is kept.', 'nodethemes-image-optimizer' ); ?></em>
									</span>
								</div>
							</div>
							<?php $this->lock_cta(); ?>
						</div>
					</td>
				</tr>
				<?php endif; ?>

				<tr>
					<th scope="row"><?php esc_html_e( 'Image types', 'nodethemes-image-optimizer' ); ?></th>
					<td>
						<label class="ntio-inline"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[convert_jpeg]" value="1" <?php checked( 1, (int) $s['convert_jpeg'] ); ?>> JPEG / JPG</label>
						<label class="ntio-inline"><input type="checkbox" name="<?php echo esc_attr( $opt ); ?>[convert_png]" value="1" <?php checked( 1, (int) $s['convert_png'] ); ?>> PNG</label>
						<p class="description"><?php esc_html_e( 'Animated GIFs and SVG files are never touched.', 'nodethemes-image-optimizer' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ntio-quality"><?php esc_html_e( 'WebP quality', 'nodethemes-image-optimizer' ); ?></label></th>
					<td>
						<div class="ntio-range">
							<input type="range" id="ntio-quality" min="10" max="100" step="1" value="<?php echo esc_attr( (int) $s['quality'] ); ?>" name="<?php echo esc_attr( $opt ); ?>[quality]">
							<output id="ntio-quality-out"><?php echo esc_html( (int) $s['quality'] ); ?></output>
						</div>
						<p class="description"><?php esc_html_e( '75–85 gives an excellent balance of quality and size. Lower = smaller files.', 'nodethemes-image-optimizer' ); ?></p>
					</td>
				</tr>

				<?php if ( $pro ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Maximum dimensions', 'nodethemes-image-optimizer' ); ?> <?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
					<td>
						<label class="ntio-num">
							<span><?php esc_html_e( 'Width', 'nodethemes-image-optimizer' ); ?></span>
							<input type="number" min="0" max="10000" step="10" name="<?php echo esc_attr( $opt ); ?>[max_width]" value="<?php echo esc_attr( $v_width ); ?>" <?php echo esc_attr( $lock ); ?>> px
						</label>
						<label class="ntio-num">
							<span><?php esc_html_e( 'Height', 'nodethemes-image-optimizer' ); ?></span>
							<input type="number" min="0" max="10000" step="10" name="<?php echo esc_attr( $opt ); ?>[max_height]" value="<?php echo esc_attr( $v_height ); ?>" <?php echo esc_attr( $lock ); ?>> px
						</label>
						<p class="description"><?php esc_html_e( 'Larger images are scaled down proportionally (never enlarged). Use 0 for no limit.', 'nodethemes-image-optimizer' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row"><label for="ntio-filesize"><?php esc_html_e( 'Maximum file size', 'nodethemes-image-optimizer' ); ?></label> <?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
					<td>
						<label class="ntio-num">
							<input id="ntio-filesize" type="number" min="0" max="20000" step="10" name="<?php echo esc_attr( $opt ); ?>[max_filesize]" value="<?php echo esc_attr( $v_size ); ?>" <?php echo esc_attr( $lock ); ?>> KB
						</label>
						<p class="description"><?php esc_html_e( 'Optional target for each converted image. Quality is lowered step by step (never below 40) until the file fits. Use 0 to disable.', 'nodethemes-image-optimizer' ); ?></p>
					</td>
				</tr>
				<?php else : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Maximum dimensions', 'nodethemes-image-optimizer' ); ?> <?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
					<td>
						<div class="ntio-lockwrap">
							<div class="ntio-mock" aria-hidden="true">
								<span class="ntio-num"><span><?php esc_html_e( 'Width', 'nodethemes-image-optimizer' ); ?></span><span class="ntio-fake-input">1920</span> px</span>
								<span class="ntio-num"><span><?php esc_html_e( 'Height', 'nodethemes-image-optimizer' ); ?></span><span class="ntio-fake-input">1080</span> px</span>
								<p class="description"><?php esc_html_e( 'Larger images are scaled down proportionally (never enlarged). Use 0 for no limit.', 'nodethemes-image-optimizer' ); ?></p>
							</div>
							<?php $this->lock_cta(); ?>
						</div>
					</td>
				</tr>

				<tr>
					<th scope="row"><?php esc_html_e( 'Maximum file size', 'nodethemes-image-optimizer' ); ?> <?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput ?></th>
					<td>
						<div class="ntio-lockwrap">
							<div class="ntio-mock" aria-hidden="true">
								<span class="ntio-num"><span class="ntio-fake-input">300</span> KB</span>
								<p class="description"><?php esc_html_e( 'Optional target for each converted image. Quality is lowered step by step (never below 40) until the file fits. Use 0 to disable.', 'nodethemes-image-optimizer' ); ?></p>
							</div>
							<?php $this->lock_cta(); ?>
						</div>
					</td>
				</tr>
				<?php endif; ?>
			</table>

			<?php submit_button( __( 'Save Settings', 'nodethemes-image-optimizer' ) ); ?>
		</form>

		<?php if ( $pro ) : ?>
			<?php do_action( 'ntio_render_bulk_card' ); ?>
		<?php else : ?>
			<?php $this->render_locked_bulk(); ?>
		<?php endif; ?>
		<?php
	}

	/**
	 * Small padlock icon.
	 *
	 * @param int $size Pixel size.
	 * @return string
	 */
	private function lock_svg( $size = 16 ) {
		$size = (int) $size;
		return '<svg class="ntio-lock-icon" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" aria-hidden="true" focusable="false"><path fill="currentColor" d="M17 9h-1V7a4 4 0 0 0-8 0v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2zm-7-2a2 2 0 0 1 4 0v2h-4V7zm3 9.7V18h-2v-1.3a2 2 0 1 1 2 0z"/></svg>';
	}

	/**
	 * Clickable lock overlay that sends the visitor to the Pro page.
	 * Purely presentational: there is no setting behind it.
	 */
	private function lock_cta() {
		?>
		<a class="ntio-lock-cta" href="<?php echo esc_url( NTIO_BUY_URL ); ?>" target="_blank" rel="noopener">
			<span class="ntio-lock-chip">
				<?php echo $this->lock_svg( 14 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php esc_html_e( 'Unlock with Pro', 'nodethemes-image-optimizer' ); ?>
			</span>
		</a>
		<?php
	}

	/**
	 * Locked look-alike of the Pro bulk optimizer (static, non-functional).
	 */
	private function render_locked_bulk() {
		?>
		<section class="ntio-card ntio-bulk">
			<h3>
				<?php esc_html_e( 'Bulk Optimize Existing Images', 'nodethemes-image-optimizer' ); ?>
				<span class="ntio-pro-badge"><?php echo $this->lock_svg( 11 ); // phpcs:ignore WordPress.Security.EscapeOutput ?>PRO</span>
			</h3>
			<div class="ntio-lockwrap ntio-lockwrap--wide">
				<div class="ntio-mock" aria-hidden="true">
					<p><?php esc_html_e( 'Optimize every image already in your Media Library with live progress, in one click.', 'nodethemes-image-optimizer' ); ?></p>
					<div class="ntio-progress"><div class="ntio-progress-bar" style="width:62%"></div></div>
					<span class="ntio-fake-button"><?php esc_html_e( 'Start Bulk Optimization', 'nodethemes-image-optimizer' ); ?></span>
				</div>
				<?php $this->lock_cta(); ?>
			</div>
		</section>
		<?php
	}
}
