<?php
/**
 * Theme registry tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\Tests\TestCase;
use MyECardExtensions\ThemeRegistry;

/**
 * Covers manifest discovery and validation.
 */
final class ThemeRegistryTest extends TestCase {

	/**
	 * Build a registry pointed at the fixture themes directory.
	 *
	 * @param string $version Addon version used for compatibility checks.
	 * @return ThemeRegistry
	 */
	private function registry( string $version = '0.1.0' ): ThemeRegistry {
		return new ThemeRegistry( array( dirname( __DIR__ ) . '/fixtures/themes' ), $version );
	}

	/**
	 * A valid manifest exposes its resolved metadata.
	 *
	 * @return void
	 */
	public function test_valid_manifest_is_discovered(): void {
		$theme = $this->registry()->get( 'valid' );

		$this->assert_not_null( $theme );
		$this->assert_same( 'valid', $theme['id'] );
		$this->assert_same( 'Valid Fixture', $theme['name'] );
		$this->assert_same( '1.0.0', $theme['version'] );
		$this->assert_same( '>=0.1.0', $theme['requires_plugin'] );
		$this->assert_contains( 'valid/template.php', $theme['template'] );
		$this->assert_count( 1, $theme['styles'] );
		$this->assert_contains( 'valid/style.css', $theme['styles'][0] );
		$this->assert_count( 1, $theme['scripts'] );
		$this->assert_contains( 'valid/script.js', $theme['scripts'][0] );
		$this->assert_false( $theme['supports']['cover'] );
		$this->assert_false( $theme['supports']['header_height'] );
		$this->assert_true( $theme['supports']['avatar'] );
		$this->assert_true( $theme['supports']['gallery'] );
		$this->assert_true( $theme['supports']['videos'] );
		$this->assert_not_null( $theme['preview'] );
		$this->assert_false( '' === $theme['preview_url'] );
		$this->assert_contains( 'tests/fixtures/themes/valid/preview.png', $theme['preview_url'] );
	}

	/**
	 * Only the valid fixture is registered.
	 *
	 * @return void
	 */
	public function test_only_valid_themes_are_registered(): void {
		$themes = $this->registry()->all();

		$this->assert_count( 1, $themes );
		$this->assert_key( 'valid', $themes );
	}

	/**
	 * A preview outside the addon falls back to the manifest URL.
	 *
	 * @return void
	 */
	public function test_preview_outside_the_addon_uses_the_manifest_url(): void {
		$base = $this->create_temp_theme(
			'external',
			array(
				'id'          => 'external',
				'name'        => 'External Fixture',
				'template'    => 'template.php',
				'preview_url' => 'https://cdn.example.test/preview.png',
			)
		);

		try {
			$registry = new ThemeRegistry( array( $base ), MY_ECARD_VERSION );
			$theme    = $registry->get( 'external' );

			$this->assert_not_null( $theme );
			$this->assert_null( $theme['preview'] );
			$this->assert_same( 'https://cdn.example.test/preview.png', $theme['preview_url'] );
		} finally {
			$this->remove_directory( $base );
		}
	}

	/**
	 * A missing preview file never invalidates the theme.
	 *
	 * @return void
	 */
	public function test_missing_preview_does_not_invalidate_the_theme(): void {
		$base = $this->create_temp_theme(
			'nopreview',
			array(
				'id'       => 'nopreview',
				'name'     => 'No Preview Fixture',
				'template' => 'template.php',
				'preview'  => 'missing.png',
			)
		);

		try {
			$registry = new ThemeRegistry( array( $base ), MY_ECARD_VERSION );
			$theme    = $registry->get( 'nopreview' );

			$this->assert_true( $registry->has( 'nopreview' ) );
			$this->assert_not_null( $theme );
			$this->assert_null( $theme['preview'] );
			$this->assert_same( '', $theme['preview_url'] );
		} finally {
			$this->remove_directory( $base );
		}
	}

	/**
	 * Options are keyed by id and sorted by name.
	 *
	 * @return void
	 */
	public function test_choices_are_keyed_by_id(): void {
		$this->assert_same( array( 'valid' => 'Valid Fixture' ), $this->registry()->choices() );
	}

	/**
	 * A manifest whose id does not match its directory is rejected.
	 *
	 * @return void
	 */
	public function test_id_directory_mismatch_is_rejected(): void {
		$registry = $this->registry();

		$this->assert_false( $registry->has( 'mismatch' ) );
		$this->assert_false( $registry->has( 'not-the-directory' ) );
		$this->assert_contains( 'must match its directory name', implode( "\n", $registry->errors() ) );
	}

	/**
	 * A manifest pointing at a missing template is rejected.
	 *
	 * @return void
	 */
	public function test_missing_template_is_rejected(): void {
		$registry = $this->registry();

		$this->assert_false( $registry->has( 'no-template' ) );
		$this->assert_contains( 'no valid template file', implode( "\n", $registry->errors() ) );
	}

	/**
	 * Path traversal in the template is rejected.
	 *
	 * @return void
	 */
	public function test_path_traversal_is_rejected(): void {
		$registry = $this->registry();

		$this->assert_false( $registry->has( 'traversal' ) );
		$this->assert_contains( 'no valid template file', implode( "\n", $registry->errors() ) );
	}

	/**
	 * An unmet plugin requirement is rejected.
	 *
	 * @return void
	 */
	public function test_unmet_plugin_requirement_is_rejected(): void {
		$registry = $this->registry();

		$this->assert_false( $registry->has( 'requires' ) );
		$this->assert_contains( 'requires plugin version', implode( "\n", $registry->errors() ) );
	}

	/**
	 * Invalid JSON is rejected with a readable error.
	 *
	 * @return void
	 */
	public function test_invalid_json_is_rejected(): void {
		$registry = $this->registry();

		$this->assert_false( $registry->has( 'broken-json' ) );
		$this->assert_contains( 'Invalid JSON', implode( "\n", $registry->errors() ) );
	}

	/**
	 * Unknown ids return NULL and false.
	 *
	 * @return void
	 */
	public function test_unknown_theme_returns_null(): void {
		$registry = $this->registry();

		$this->assert_null( $registry->get( 'does-not-exist' ) );
		$this->assert_false( $registry->has( '' ) );
	}

	/**
	 * Non-existent base directories are ignored.
	 *
	 * @return void
	 */
	public function test_missing_base_directory_is_ignored(): void {
		$registry = new ThemeRegistry( array( '/nonexistent/my-ecard-themes' ), '0.1.0' );

		$this->assert_count( 0, $registry->directories() );
		$this->assert_count( 0, $registry->all() );
	}

	/**
	 * Create a temporary base directory holding one theme.
	 *
	 * @param string $id       Theme id, also used as the directory name.
	 * @param array  $manifest Manifest data written to theme.json.
	 * @return string Absolute path to the base directory.
	 */
	private function create_temp_theme( string $id, array $manifest ): string {
		$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'my-ecard-theme-' . $id . '-' . uniqid( '', true );

		if ( ! mkdir( $base . DIRECTORY_SEPARATOR . $id, 0777, true ) ) {
			$this->fail( 'Unable to create the temporary theme directory.' );
			return $base;
		}

		$template = $base . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'template.php';

		if ( false === file_put_contents( $template, "<?php\n// Temporary fixture template.\n" ) ) {
			$this->fail( 'Unable to write the temporary theme template.' );
		}

		$json = json_encode( $manifest, JSON_PRETTY_PRINT );

		if ( false === $json || false === file_put_contents( $base . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR . 'theme.json', $json ) ) {
			$this->fail( 'Unable to write the temporary theme manifest.' );
		}

		return $base;
	}

	/**
	 * Recursively delete a directory created by the tests.
	 *
	 * @param string $directory Absolute directory path.
	 * @return void
	 */
	private function remove_directory( string $directory ): void {
		if ( '' === $directory || ! is_dir( $directory ) ) {
			return;
		}

		$entries = scandir( $directory );

		if ( ! is_array( $entries ) ) {
			return;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}

			$path = $directory . DIRECTORY_SEPARATOR . $entry;

			if ( is_dir( $path ) && ! is_link( $path ) ) {
				$this->remove_directory( $path );
				continue;
			}

			unlink( $path );
		}

		rmdir( $directory );
	}
}
