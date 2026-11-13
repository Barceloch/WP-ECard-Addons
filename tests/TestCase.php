<?php
/**
 * Dependency-free test case base class.
 *
 * The addon cannot rely on Composer dev dependencies in this environment, so
 * the test harness provides the small subset of assertions the suite needs.
 *
 * @package MyECardExtensions
 */

namespace MyECardExtensions\Tests;

/**
 * Base class with assertions and a per-method runner.
 */
abstract class TestCase {

	/**
	 * Failures collected for the current test method.
	 *
	 * @var array<int, string>
	 */
	private $failures = array();

	/**
	 * Name of the test method being executed.
	 *
	 * @var string
	 */
	private $current = '';

	/**
	 * Run every public test* method.
	 *
	 * @return array<string, array<int, string>> Method name => failure messages.
	 */
	public function run_all(): array {
		$results = array();
		$class   = new \ReflectionClass( $this );

		foreach ( $class->getMethods( \ReflectionMethod::IS_PUBLIC ) as $method ) {
			if ( 0 !== strpos( $method->name, 'test' ) ) {
				continue;
			}

			if ( $method->getNumberOfRequiredParameters() > 0 ) {
				continue;
			}

			$this->current  = $method->name;
			$this->failures = array();

			$this->set_up();

			try {
				$this->{$method->name}();
			} catch ( \Throwable $exception ) {
				$this->failures[] = sprintf(
					'Uncaught %s: %s (%s:%d)',
					get_class( $exception ),
					$exception->getMessage(),
					$exception->getFile(),
					$exception->getLine()
				);
			}

			$this->tear_down();

			$results[ $method->name ] = $this->failures;
		}

		return $results;
	}

	/**
	 * Per-test setup hook.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		$this->reset_wp_state();
	}

	/**
	 * Per-test teardown hook.
	 *
	 * @return void
	 */
	protected function tear_down(): void {
		unset( $_POST );
	}

	/**
	 * Clear the stubbed WordPress state between tests.
	 *
	 * @return void
	 */
	protected function reset_wp_state(): void {
		$GLOBALS['my_ecard_test_posts']       = array();
		$GLOBALS['my_ecard_test_meta']        = array();
		$GLOBALS['my_ecard_test_attachments'] = array();
		$GLOBALS['my_ecard_test_styles']      = array();
		$GLOBALS['my_ecard_test_scripts']     = array();
		$GLOBALS['my_ecard_test_filters']     = array();
		$GLOBALS['my_ecard_test_query_vars']  = array();
		$GLOBALS['my_ecard_test_transients']  = array();
		$GLOBALS['my_ecard_test_status']      = 0;
		$GLOBALS['my_ecard_test_headers']     = array();
		$GLOBALS['my_ecard_test_singular']    = false;
		$GLOBALS['my_ecard_test_queried_id']  = 0;
		$GLOBALS['my_ecard_test_is_admin']    = false;
		$GLOBALS['my_ecard_test_doing_ajax']  = false;
		$GLOBALS['my_ecard_test_is_feed']     = false;
		$GLOBALS['my_ecard_test_is_embed']    = false;
		$GLOBALS['my_ecard_test_is_preview']  = false;
		$GLOBALS['my_ecard_test_nonce_valid'] = true;
		$GLOBALS['my_ecard_test_can_edit']    = true;
	}

	/**
	 * Record a failure.
	 *
	 * @param string $message Failure description.
	 * @return void
	 */
	protected function fail( string $message ): void {
		$this->failures[] = $message;
	}

	/**
	 * Assert strict equality.
	 *
	 * @param mixed  $expected Expected value.
	 * @param mixed  $actual   Actual value.
	 * @param string $message  Failure description.
	 * @return void
	 */
	protected function assert_same( $expected, $actual, string $message = '' ): void {
		if ( $expected !== $actual ) {
			$this->fail(
				sprintf(
					'%sExpected %s, got %s.',
					'' === $message ? '' : $message . ' ',
					$this->describe( $expected ),
					$this->describe( $actual )
				)
			);
		}
	}

	/**
	 * Assert truthiness.
	 *
	 * @param mixed  $actual  Actual value.
	 * @param string $message Failure description.
	 * @return void
	 */
	protected function assert_true( $actual, string $message = '' ): void {
		if ( true !== $actual && ! $actual ) {
			$this->fail( ( '' === $message ? 'Expected a truthy value.' : $message ) );
		}
	}

	/**
	 * Assert falsiness.
	 *
	 * @param mixed  $actual  Actual value.
	 * @param string $message Failure description.
	 * @return void
	 */
	protected function assert_false( $actual, string $message = '' ): void {
		if ( true === $actual || $actual ) {
			$this->fail( ( '' === $message ? 'Expected a falsy value.' : $message ) );
		}
	}

	/**
	 * Assert NULL.
	 *
	 * @param mixed  $actual  Actual value.
	 * @param string $message Failure description.
	 * @return void
	 */
	protected function assert_null( $actual, string $message = '' ): void {
		$this->assert_same( null, $actual, $message );
	}

	/**
	 * Assert a non-NULL value.
	 *
	 * @param mixed  $actual  Actual value.
	 * @param string $message Failure description.
	 * @return void
	 */
	protected function assert_not_null( $actual, string $message = '' ): void {
		if ( null === $actual ) {
			$this->fail( ( '' === $message ? 'Expected a non-NULL value, got NULL.' : $message ) );
		}
	}

	/**
	 * Assert an array count.
	 *
	 * @param int    $expected Expected count.
	 * @param array  $actual   Actual array.
	 * @param string $message  Failure description.
	 * @return void
	 */
	protected function assert_count( int $expected, array $actual, string $message = '' ): void {
		$this->assert_same( $expected, count( $actual ), $message );
	}

	/**
	 * Assert a key exists in an array.
	 *
	 * @param string $key     Key.
	 * @param array  $actual  Array.
	 * @param string $message Failure description.
	 * @return void
	 */
	protected function assert_key( string $key, array $actual, string $message = '' ): void {
		if ( ! array_key_exists( $key, $actual ) ) {
			$this->fail(
				sprintf(
					'%sKey "%s" is missing. Available keys: %s.',
					'' === $message ? '' : $message . ' ',
					$key,
					implode( ', ', array_keys( $actual ) )
				)
			);
		}
	}

	/**
	 * Assert a string contains a needle.
	 *
	 * @param string $needle   Needle.
	 * @param string $haystack Haystack.
	 * @param string $message  Failure description.
	 * @return void
	 */
	protected function assert_contains( string $needle, string $haystack, string $message = '' ): void {
		if ( false === strpos( $haystack, $needle ) ) {
			$this->fail(
				sprintf(
					'%s"%s" was not found in "%s".',
					'' === $message ? '' : $message . ' ',
					$needle,
					$haystack
				)
			);
		}
	}

	/**
	 * Build a readable representation for failure messages.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function describe( $value ): string {
		if ( is_string( $value ) ) {
			return '"' . $value . '"';
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( null === $value ) {
			return 'NULL';
		}

		if ( is_array( $value ) ) {
			return 'array(' . count( $value ) . ')';
		}

		if ( is_object( $value ) ) {
			return 'object(' . get_class( $value ) . ')';
		}

		return (string) $value;
	}
}
