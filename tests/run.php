<?php
/**
 * Dependency-free test runner.
 *
 * Usage: php tests/run.php
 *
 * @package MyECardExtensions
 */

require_once __DIR__ . '/bootstrap.php';

$files = glob( __DIR__ . '/Unit/*Test.php' );

if ( ! is_array( $files ) || array() === $files ) {
	fwrite( STDERR, "No test files found in tests/Unit.\n" );
	exit( 1 );
}

sort( $files );

foreach ( $files as $file ) {
	require_once $file;
}

$classes = array();

foreach ( get_declared_classes() as $class_name ) {
	if ( ! is_subclass_of( $class_name, 'MyECardExtensions\\Tests\\TestCase' ) ) {
		continue;
	}

	$reflection = new ReflectionClass( $class_name );

	if ( $reflection->isAbstract() ) {
		continue;
	}

	$classes[] = $class_name;
}

sort( $classes );

$tests   = 0;
$failed  = 0;
$pending = array();

foreach ( $classes as $class_name ) {
	$short = substr( $class_name, (int) strrpos( $class_name, '\\' ) + 1 );

	echo "\n" . $short . "\n";

	$instance = new $class_name();
	$results  = $instance->run_all();

	foreach ( $results as $method => $failures ) {
		$tests++;

		if ( array() === $failures ) {
			echo "  PASS  " . $method . "\n";
			continue;
		}

		$failed++;
		echo "  FAIL  " . $method . "\n";

		foreach ( $failures as $failure ) {
			echo "        - " . $failure . "\n";
			$pending[] = $short . '::' . $method . ' -> ' . $failure;
		}
	}
}

echo "\n" . str_repeat( '-', 72 ) . "\n";

printf(
	"%d tests, %d passed, %d failed\n",
	$tests,
	$tests - $failed,
	$failed
);

if ( $failed > 0 ) {
	echo "\nFailures:\n";

	foreach ( $pending as $line ) {
		echo ' - ' . $line . "\n";
	}

	exit( 1 );
}

echo "OK\n";
exit( 0 );
