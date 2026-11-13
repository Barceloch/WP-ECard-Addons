<?php
/**
 * Dependency detection tests.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests\Unit;

use MyECardExtensions\Dependencies;
use MyECardExtensions\Tests\TestCase;

/**
 * Covers version range detection for the ECard dependency.
 */
final class DependenciesTest extends TestCase {

	/**
	 * Supported versions are inside the 1.6.0 - 1.99.99 range.
	 *
	 * @return void
	 */
	public function test_supports_version_inside_range(): void {
		$dependencies = new Dependencies();

		$this->assert_true( $dependencies->supports_version( '1.6.0' ) );
		$this->assert_true( $dependencies->supports_version( '1.6.0.1' ) );
		$this->assert_true( $dependencies->supports_version( '1.10.0' ) );
		$this->assert_true( $dependencies->supports_version( '1.99.99' ) );
	}

	/**
	 * Versions outside the range are rejected.
	 *
	 * @return void
	 */
	public function test_supports_version_outside_range(): void {
		$dependencies = new Dependencies();

		$this->assert_false( $dependencies->supports_version( '1.5.9' ) );
		$this->assert_false( $dependencies->supports_version( '2.0.0' ) );
		$this->assert_false( $dependencies->supports_version( '0.9.0' ) );
		$this->assert_false( $dependencies->supports_version( '' ) );
	}

	/**
	 * Without ECard loaded the addon is neither active nor satisfied.
	 *
	 * @return void
	 */
	public function test_dependency_is_inactive_without_ecard(): void {
		$dependencies = new Dependencies();

		$this->assert_false( $dependencies->is_active() );
		$this->assert_false( $dependencies->is_supported() );
		$this->assert_false( $dependencies->is_satisfied() );
		$this->assert_same( '', $dependencies->ecard_version() );
	}
}
