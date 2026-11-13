<?php
/**
 * Extensible theme registry.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discovers and validates theme manifests.
 *
 * A theme lives in its own directory and declares a theme.json manifest.
 * The manifest id must match the directory name.
 *
 * Each registered theme record exposes:
 *
 * - `preview`     Absolute path to the optional preview image, or null.
 * - `preview_url` Public URL for that preview, or an empty string.
 * - `supports`    Capability flags: `avatar`, `cover`, `accent_color`,
 *                 `header_height`, `gallery` and `videos`.
 */
final class ThemeRegistry {

	/**
	 * Manifest file name.
	 */
	const MANIFEST = 'theme.json';

	/**
	 * Base directories to scan.
	 *
	 * @var string[]
	 */
	private $directories;

	/**
	 * Addon version used for compatibility checks.
	 *
	 * @var string
	 */
	private $plugin_version;

	/**
	 * Discovered themes, memoized for the request.
	 *
	 * @var array|null
	 */
	private $themes = null;

	/**
	 * Validation errors collected during discovery.
	 *
	 * @var string[]
	 */
	private $errors = array();

	/**
	 * Constructor.
	 *
	 * @param string[] $directories    Base directories to scan.
	 * @param string   $plugin_version Addon version.
	 */
	public function __construct( array $directories, string $plugin_version ) {
		$this->directories    = $directories;
		$this->plugin_version = $plugin_version;
	}

	/**
	 * Normalized list of scannable base directories.
	 *
	 * @return string[]
	 */
	public function directories(): array {
		$directories = apply_filters( 'my_ecard_theme_directories', $this->directories );
		$valid       = array();

		foreach ( (array) $directories as $directory ) {
			if ( ! is_string( $directory ) || '' === $directory ) {
				continue;
			}

			$real = realpath( $directory );

			if ( false === $real || ! is_dir( $real ) ) {
				continue;
			}

			$valid[] = rtrim( $real, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		}

		return array_values( array_unique( $valid ) );
	}

	/**
	 * All valid themes keyed by id.
	 *
	 * @return array
	 */
	public function all(): array {
		if ( null !== $this->themes ) {
			return $this->themes;
		}

		$this->themes = array();
		$this->errors = array();

		foreach ( $this->directories() as $base ) {
			$manifests = glob( $base . '*/' . self::MANIFEST );

			if ( ! is_array( $manifests ) ) {
				continue;
			}

			sort( $manifests );

			foreach ( $manifests as $manifest_path ) {
				$theme = $this->load_manifest( $manifest_path );

				if ( null === $theme ) {
					continue;
				}

				if ( isset( $this->themes[ $theme['id'] ] ) ) {
					$this->errors[] = sprintf( 'Duplicate theme id "%s" in %s.', $theme['id'], $manifest_path );
					continue;
				}

				$this->themes[ $theme['id'] ] = $theme;
			}
		}

		$filtered = apply_filters( 'my_ecard_themes', $this->themes );

		if ( is_array( $filtered ) ) {
			$this->themes = $filtered;
		}

		return $this->themes;
	}

	/**
	 * Get one theme by id.
	 *
	 * @param string $id Theme id.
	 * @return array|null
	 */
	public function get( string $id ): ?array {
		$themes = $this->all();

		return isset( $themes[ $id ] ) ? $themes[ $id ] : null;
	}

	/**
	 * Whether a theme id exists.
	 *
	 * @param string $id Theme id.
	 * @return bool
	 */
	public function has( string $id ): bool {
		return null !== $this->get( $id );
	}

	/**
	 * Select options keyed by theme id.
	 *
	 * @return array
	 */
	public function choices(): array {
		$choices = array();

		foreach ( $this->all() as $id => $theme ) {
			$choices[ $id ] = $theme['name'];
		}

		asort( $choices );

		return $choices;
	}

	/**
	 * Validation errors from the last discovery.
	 *
	 * @return string[]
	 */
	public function errors(): array {
		$this->all();

		return $this->errors;
	}

	/**
	 * Load and validate one manifest.
	 *
	 * @param string $manifest_path Absolute manifest path.
	 * @return array|null
	 */
	private function load_manifest( string $manifest_path ): ?array {
		$raw = file_get_contents( $manifest_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local manifest read.

		if ( false === $raw ) {
			$this->errors[] = sprintf( 'Unable to read %s.', $manifest_path );
			return null;
		}

		try {
			$data = json_decode( $raw, true, 32, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $exception ) {
			$this->errors[] = sprintf( 'Invalid JSON in %s: %s', $manifest_path, $exception->getMessage() );
			return null;
		}

		if ( ! is_array( $data ) ) {
			$this->errors[] = sprintf( 'Manifest %s must contain a JSON object.', $manifest_path );
			return null;
		}

		$resolved_directory = realpath( dirname( $manifest_path ) );

		if ( false === $resolved_directory ) {
			$this->errors[] = sprintf( 'Unable to resolve the theme directory for %s.', $manifest_path );
			return null;
		}

		$directory    = $resolved_directory;
		$id           = isset( $data['id'] ) && is_string( $data['id'] ) ? strtolower( trim( $data['id'] ) ) : '';
		$directory_id = strtolower( basename( $directory ) );

		if ( '' === $id || ! preg_match( '/^[a-z0-9][a-z0-9_-]{0,63}$/', $id ) ) {
			$this->errors[] = sprintf( 'Invalid or missing theme id in %s.', $manifest_path );
			return null;
		}

		if ( $id !== $directory_id ) {
			$this->errors[] = sprintf( 'Theme id "%s" must match its directory name "%s".', $id, $directory_id );
			return null;
		}

		$name = isset( $data['name'] ) && is_string( $data['name'] ) ? trim( $data['name'] ) : '';

		if ( '' === $name ) {
			$this->errors[] = sprintf( 'Missing theme name in %s.', $manifest_path );
			return null;
		}

		$requires = isset( $data['requires_plugin'] ) && is_string( $data['requires_plugin'] ) ? trim( $data['requires_plugin'] ) : '*';

		if ( ! requirement_satisfied( $this->plugin_version, $requires ) ) {
			$this->errors[] = sprintf( 'Theme "%s" requires plugin version %s.', $id, $requires );
			return null;
		}

		$template = isset( $data['template'] ) && is_string( $data['template'] ) ? $data['template'] : 'template.php';
		$resolved = $this->resolve_file( $directory, $template, 'php' );

		if ( null === $resolved ) {
			$this->errors[] = sprintf( 'Theme "%s" has no valid template file "%s".', $id, $template );
			return null;
		}

		$styles  = $this->resolve_files( $directory, isset( $data['styles'] ) ? $data['styles'] : array(), 'css' );
		$scripts = $this->resolve_files( $directory, isset( $data['scripts'] ) ? $data['scripts'] : array(), 'js' );

		if ( null === $styles || null === $scripts ) {
			$this->errors[] = sprintf( 'Theme "%s" references a missing or invalid asset.', $id );
			return null;
		}

		$supports = array(
			'avatar'        => true,
			'cover'         => true,
			'accent_color'  => true,
			'header_height' => true,
			'gallery'       => false,
			'videos'        => false,
		);

		if ( isset( $data['supports'] ) && is_array( $data['supports'] ) ) {
			foreach ( $supports as $key => $default ) {
				if ( array_key_exists( $key, $data['supports'] ) ) {
					$supports[ $key ] = (bool) $data['supports'][ $key ];
				}
			}
		}

		$preview = null;

		if ( isset( $data['preview'] ) && is_string( $data['preview'] ) ) {
			$preview = $this->resolve_preview( $directory, $data['preview'] );
		}

		$preview_url = '';

		if ( null !== $preview ) {
			$addon_base = realpath( MY_ECARD_PATH );

			if ( false !== $addon_base ) {
				$addon_prefix = rtrim( $addon_base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

				if ( 0 === strpos( $preview, $addon_prefix ) ) {
					$preview_url = MY_ECARD_URL . str_replace( DIRECTORY_SEPARATOR, '/', substr( $preview, strlen( $addon_prefix ) ) );
				}
			}
		}

		if ( '' === $preview_url && isset( $data['preview_url'] ) && is_string( $data['preview_url'] ) ) {
			$preview_url = safe_url( trim( $data['preview_url'] ) );
		}

		return array(
			'id'              => $id,
			'name'            => $name,
			'description'     => isset( $data['description'] ) && is_string( $data['description'] ) ? trim( $data['description'] ) : '',
			'version'         => isset( $data['version'] ) && is_string( $data['version'] ) ? trim( $data['version'] ) : '',
			'requires_plugin' => $requires,
			'dir'             => $directory . DIRECTORY_SEPARATOR,
			'template'        => $resolved,
			'styles'          => $styles,
			'scripts'         => $scripts,
			'preview'         => $preview,
			'preview_url'     => $preview_url,
			'supports'        => $supports,
		);
	}

	/**
	 * Resolve a list of theme files.
	 *
	 * @param string $directory Base theme directory.
	 * @param mixed  $files     Raw manifest value.
	 * @param string $extension Expected extension.
	 * @return string[]|null Empty array when nothing is declared, null on error.
	 */
	private function resolve_files( string $directory, $files, string $extension ): ?array {
		if ( ! is_array( $files ) ) {
			return null;
		}

		$resolved = array();

		foreach ( $files as $file ) {
			if ( ! is_string( $file ) ) {
				return null;
			}

			$path = $this->resolve_file( $directory, $file, $extension );

			if ( null === $path ) {
				return null;
			}

			$resolved[] = $path;
		}

		return $resolved;
	}

	/**
	 * Resolve one file inside a theme directory, rejecting traversal.
	 *
	 * @param string $directory Base theme directory.
	 * @param string $relative  Relative path from the manifest.
	 * @param string $extension Expected extension.
	 * @return string|null
	 */
	private function resolve_file( string $directory, string $relative, string $extension ): ?string {
		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );

		if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
			return null;
		}

		$base      = realpath( $directory );
		$candidate = realpath( $directory . DIRECTORY_SEPARATOR . $relative );

		if ( false === $base || false === $candidate ) {
			return null;
		}

		if ( 0 !== strpos( $candidate, $base . DIRECTORY_SEPARATOR ) ) {
			return null;
		}

		if ( ! is_file( $candidate ) ) {
			return null;
		}

		if ( strtolower( (string) pathinfo( $candidate, PATHINFO_EXTENSION ) ) !== $extension ) {
			return null;
		}

		return $candidate;
	}

	/**
	 * Resolve an optional preview image inside a theme directory.
	 *
	 * Missing, out-of-directory or unsupported files yield null so the theme
	 * stays valid; a preview is never required.
	 *
	 * @param string $directory Base theme directory.
	 * @param string $relative  Relative path from the manifest.
	 * @return string|null Absolute preview path, or null when invalid.
	 */
	private function resolve_preview( string $directory, string $relative ): ?string {
		$relative = ltrim( str_replace( '\\', '/', $relative ), '/' );

		if ( '' === $relative || false !== strpos( $relative, '..' ) ) {
			return null;
		}

		$base      = realpath( $directory );
		$candidate = realpath( $directory . DIRECTORY_SEPARATOR . $relative );

		if ( false === $base || false === $candidate ) {
			return null;
		}

		if ( 0 !== strpos( $candidate, $base . DIRECTORY_SEPARATOR ) ) {
			return null;
		}

		if ( ! is_file( $candidate ) ) {
			return null;
		}

		$allowed = array( 'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg' );

		if ( ! in_array( strtolower( (string) pathinfo( $candidate, PATHINFO_EXTENSION ) ), $allowed, true ) ) {
			return null;
		}

		return $candidate;
	}
}
