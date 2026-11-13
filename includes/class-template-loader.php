<?php
/**
 * Front-end template engine: intercepts ECard cards and renders addon themes.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves the addon theme for a card and swaps the template safely.
 *
 * Every failure path returns the template received from ECard, so the addon
 * never becomes the cause of a blank page or a fatal error.
 */
final class TemplateLoader {

	/**
	 * Position after ECard's own template_include filter (priority 10).
	 */
	const FILTER_PRIORITY = 20;

	/**
	 * Context mode: a theme template renders the card.
	 */
	const MODE_CARD = 'card';

	/**
	 * Context mode: the card is password protected; only the form is rendered.
	 */
	const MODE_PASSWORD = 'password';

	/**
	 * Query var used to hand the resolved context to the shell and templates.
	 */
	const QUERY_VAR = 'my_ecard_context';

	/**
	 * Theme registry.
	 *
	 * @var ThemeRegistry
	 */
	private $themes;

	/**
	 * Resolved context for the current request, if any.
	 *
	 * @var array|null
	 */
	private $context = null;

	/**
	 * Constructor.
	 *
	 * @param ThemeRegistry $themes Theme registry.
	 */
	public function __construct( ThemeRegistry $themes ) {
		$this->themes = $themes;
	}

	/**
	 * Register the template_include filter.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'template_include', array( $this, 'filter_template' ), self::FILTER_PRIORITY );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_theme_assets' ), self::FILTER_PRIORITY );
	}

	/**
	 * Enqueue the styles and scripts declared by the resolved theme manifest.
	 *
	 * The template engine owns the theme document, so the theme stylesheet must
	 * be enqueued before wp_head() runs, using the same resolution as the render.
	 *
	 * @return void
	 */
	public function enqueue_theme_assets(): void {
		try {
			if ( ! $this->is_addon_request() ) {
				return;
			}

			$theme = $this->resolve_theme( (int) get_queried_object_id() );

			if ( null === $theme ) {
				return;
			}

			$this->enqueue_files( $theme['theme'], 'styles', 'style' );
			$this->enqueue_files( $theme['theme'], 'scripts', 'script' );
		} catch ( \Throwable $exception ) {
			$this->log(
				sprintf(
					'Theme asset enqueue failed; the card keeps the ECard template. %s: %s',
					get_class( $exception ),
					$exception->getMessage()
				)
			);
		}
	}

	/**
	 * Enqueue one manifest asset list.
	 *
	 * @param array  $theme Theme record.
	 * @param string $key   Manifest key: styles or scripts.
	 * @param string $kind  style or script.
	 * @return void
	 */
	private function enqueue_files( array $theme, string $key, string $kind ): void {
		$files = isset( $theme[ $key ] ) && is_array( $theme[ $key ] ) ? $theme[ $key ] : array();

		foreach ( $files as $index => $file ) {
			$url = $this->theme_file_url( (string) $file );

			if ( '' === $url ) {
				continue;
			}

			$handle  = sprintf( 'my-ecard-theme-%s-%s-%d', $theme['id'], $kind, (int) $index );
			$version = \MyECardExtensions\my_ecard_asset_version();

			if ( 'style' === $kind ) {
				wp_enqueue_style( $handle, $url, array(), $version );
				continue;
			}

			wp_enqueue_script( $handle, $url, array(), $version, true );
		}
	}

	/**
	 * Public URL for a file inside the addon, or an empty string.
	 *
	 * Themes registered from an external directory must enqueue their own
	 * assets; the addon only serves files inside its own tree.
	 *
	 * @param string $file Absolute file path.
	 * @return string
	 */
	private function theme_file_url( string $file ): string {
		$base = realpath( MY_ECARD_PATH );
		$path = realpath( $file );

		if ( false === $base || false === $path ) {
			return '';
		}

		$prefix = rtrim( $base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

		if ( 0 !== strpos( $path, $prefix ) || ! is_file( $path ) ) {
			return '';
		}

		return MY_ECARD_URL . str_replace( DIRECTORY_SEPARATOR, '/', substr( $path, strlen( $prefix ) ) );
	}

	/**
	 * Swap the template only when the addon can render the card completely.
	 *
	 * @param string $template Template chosen by WordPress and ECard.
	 * @return string
	 */
	public function filter_template( $template ) {
		try {
			if ( ! $this->is_addon_request() ) {
				return $template;
			}

			$post_id = (int) get_queried_object_id();
			$theme   = $this->resolve_theme( $post_id );

			if ( null === $theme ) {
				return $template;
			}

			$shell = $this->shell_path();

			if ( '' === $shell ) {
				return $template;
			}

			/*
			 * WordPress access rules win. A protected card never receives card
			 * data; only the core password form is rendered.
			 */
			if ( post_password_required( $post_id ) ) {
				$this->set_context(
					array(
						'mode'     => self::MODE_PASSWORD,
						'post_id'  => $post_id,
						'theme'    => array(),
						'template' => '',
						'card'     => null,
						'shell'    => $shell,
					)
				);

				return $shell;
			}

			$card = CardData::from_post( $post_id );

			if ( null === $card ) {
				return $template;
			}

			$context = array(
				'mode'     => self::MODE_CARD,
				'post_id'  => $post_id,
				'theme'    => $theme['theme'],
				'template' => $theme['template'],
				'card'     => $card,
				'shell'    => $shell,
			);

			/**
			 * Filters the final render context before the shell is used.
			 *
			 * Returning an invalid context makes the loader fall back to the
			 * original ECard template.
			 *
			 * @param array $context Resolved context.
			 * @param int   $post_id Card post ID.
			 */
			$context = apply_filters( 'my_ecard_render_context', $context, $post_id );

			if ( ! $this->is_valid_card_context( $context ) ) {
				return $template;
			}

			$this->set_context( $context );

			return $shell;
		} catch ( \Throwable $exception ) {
			$this->log(
				sprintf(
					'Template resolution failed for request; falling back to ECard. %s: %s',
					get_class( $exception ),
					$exception->getMessage()
				)
			);

			return $template;
		}
	}

	/**
	 * Whether the current request is a single ECard card.
	 *
	 * @return bool
	 */
	public function is_addon_request(): bool {
		if ( is_admin() || wp_doing_ajax() || is_feed() || is_embed() || is_preview() ) {
			return false;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		if ( ! is_singular() ) {
			return false;
		}

		$post_id = (int) get_queried_object_id();

		return $post_id > 0 && CardData::POST_TYPE === get_post_type( $post_id );
	}

	/**
	 * Resolve the assigned, registered and readable theme for a card.
	 *
	 * @param int $post_id Card post ID.
	 * @return array|null Array with theme, template and post_id.
	 */
	public function resolve_theme( int $post_id ): ?array {
		if ( $post_id <= 0 || CardData::POST_TYPE !== get_post_type( $post_id ) ) {
			return null;
		}

		/*
		 * Drafts, pending, private and trashed cards keep ECard behaviour. Only
		 * publicly viewable cards are theme-rendered, so the addon can never
		 * expose a card WordPress would not show.
		 */
		if ( ! is_post_publicly_viewable( $post_id ) ) {
			return null;
		}

		$settings = CardSettings::read( $post_id );
		$theme_id = isset( $settings['theme'] ) && is_string( $settings['theme'] ) ? $settings['theme'] : '';

		if ( '' === $theme_id ) {
			return null;
		}

		$theme = $this->themes->get( $theme_id );

		if ( null === $theme ) {
			return null;
		}

		$template = isset( $theme['template'] ) && is_string( $theme['template'] ) ? $theme['template'] : '';

		if ( '' === $template || ! is_file( $template ) || ! is_readable( $template ) ) {
			return null;
		}

		return array(
			'theme'    => $theme,
			'template' => $template,
			'post_id'  => $post_id,
		);
	}

	/**
	 * Context resolved for the current request.
	 *
	 * @return array|null
	 */
	public function context(): ?array {
		return $this->context;
	}

	/**
	 * Absolute path to the addon shell template.
	 *
	 * @return string Empty string when the shell is missing.
	 */
	public function shell_path(): string {
		$shell = MY_ECARD_PATH . 'templates/shell.php';

		return is_file( $shell ) && is_readable( $shell ) ? $shell : '';
	}

	/**
	 * Validate a card context, including contexts altered by filters.
	 *
	 * @param mixed $context Candidate context.
	 * @return bool
	 */
	private function is_valid_card_context( $context ): bool {
		if ( ! is_array( $context ) ) {
			return false;
		}

		if ( ! isset( $context['mode'] ) || self::MODE_CARD !== $context['mode'] ) {
			return false;
		}

		if ( ! isset( $context['theme'] ) || ! is_array( $context['theme'] ) || array() === $context['theme'] ) {
			return false;
		}

		if ( ! isset( $context['card'] ) || ! $context['card'] instanceof CardData ) {
			return false;
		}

		$template = isset( $context['template'] ) ? $context['template'] : '';

		return is_string( $template ) && '' !== $template && is_file( $template ) && is_readable( $template );
	}

	/**
	 * Store the context and expose it through a query var.
	 *
	 * @param array $context Context to store.
	 * @return void
	 */
	private function set_context( array $context ): void {
		$this->context = $context;
		set_query_var( self::QUERY_VAR, $context );
	}

	/**
	 * Log a diagnostic only when WordPress debugging is enabled.
	 *
	 * @param string $message Message.
	 * @return void
	 */
	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'WP ECard Addons: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
