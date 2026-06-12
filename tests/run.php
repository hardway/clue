<?php
/**
 * Clue Test Runner
 *
 * Usage:
 *   php tests/run.php                    # run all .test.php in tests/
 *   php tests/run.php mail               # run tests/mail.test.php
 *   php tests/run.php mail,activerecord  # run multiple
 */

// Bootstrap the Clue framework
require_once __DIR__ . '/../stub.php';

// Backwards compat: old tests extend PHPUnit_Framework_TestCase
if (!class_exists('PHPUnit_Framework_TestCase', false)) {
    class_alias('Clue\\Test\\TestCase', 'PHPUnit_Framework_TestCase');
}

$runner = new \Clue\Test\Runner();

$target = $argv[1] ?? '';

if (!$target) {
    // Run all tests in directory
    exit($runner->runDir(__DIR__) ? 0 : 1);
}

// One or more comma-separated test names (without .test.php extension)
$ok = true;
foreach (explode(',', $target) as $name) {
    $file = __DIR__ . '/' . trim($name) . '.test.php';
    if (!file_exists($file)) {
        echo "Test file not found: $file\n";
        exit(1);
    }
    $runner->runFile($file);
}

$runner->printSummary();
exit($runner->hasFailures() ? 1 : 0);
