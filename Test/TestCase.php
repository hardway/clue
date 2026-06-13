<?php
namespace Clue\Test;

/**
 * Minimal TestCase for Clue framework tests.
 * No external dependencies, no PHPUnit.
 */
class TestCase {

    /**
     * Run a test method with setUp/tearDown.
     * Called by Runner.
     */
    final public function runTestMethod(string $methodName): void {
        $this->setUp();
        $this->$methodName();
        $this->tearDown();
    }

    protected function setUp(): void {}
    protected function tearDown(): void {}

    // ========== Assertions ==========

    protected function assertEquals($expected, $actual, string $message = ''): void {
        // Use loose comparison (==) for PHPUnit compatibility
        // Use assertSame() for strict comparison
        if ($expected != $actual) {
            $msg = $message ? "$message: " : '';
            $msg .= "Expected " . $this->export($expected)
                  . " but got " . $this->export($actual);
            throw new AssertionFailedException($msg);
        }
    }

    protected function assertNotEquals($expected, $actual, string $message = ''): void {
        if ($expected === $actual) {
            $msg = $message ? "$message: " : '';
            $msg .= "Did not expect " . $this->export($expected);
            throw new AssertionFailedException($msg);
        }
    }

    protected function assertTrue($condition, string $message = ''): void {
        if (!$condition) {
            throw new AssertionFailedException($message ?: 'Expected true, got false');
        }
    }

    protected function assertFalse($condition, string $message = ''): void {
        if ($condition) {
            throw new AssertionFailedException($message ?: 'Expected false, got true');
        }
    }

    protected function assertNull($actual, string $message = ''): void {
        if ($actual !== null) {
            $msg = $message ? "$message: " : '';
            $msg .= "Expected null, got " . $this->export($actual);
            throw new AssertionFailedException($msg);
        }
    }

    protected function assertNotNull($actual, string $message = ''): void {
        if ($actual === null) {
            throw new AssertionFailedException($message ?: 'Expected non-null');
        }
    }

    protected function assertInstanceOf(string $expected, $actual, string $message = ''): void {
        if (!($actual instanceof $expected)) {
            $msg = $message ? "$message: " : '';
            $msg .= 'Expected instance of ' . $expected
                  . ' but got ' . (is_object($actual) ? get_class($actual) : $this->export($actual));
            throw new AssertionFailedException($msg);
        }
    }

    protected function assertEmpty($actual, string $message = ''): void {
        if (!empty($actual)) {
            $msg = $message ? "$message: " : '';
            $msg .= "Expected empty, got " . $this->export($actual);
            throw new AssertionFailedException($msg);
        }
    }

    protected function assertNotEmpty($actual, string $message = ''): void {
        if (empty($actual)) {
            throw new AssertionFailedException($message ?: 'Expected non-empty value');
        }
    }

    protected function assertContains($needle, $haystack, string $message = ''): void {
        $found = is_string($haystack)
            ? strpos($haystack, $needle) !== false
            : in_array($needle, $haystack, true);
        if (!$found) {
            $msg = $message ? "$message: " : '';
            $msg .= "Expected to find " . $this->export($needle);
            throw new AssertionFailedException($msg);
        }
    }

    /** @deprecated Use assertMatchesRegularExpression */
    protected function assertRegexp(string $pattern, string $string, string $message = ''): void {
        $this->assertMatchesRegularExpression($pattern, $string, $message);
    }

    protected function assertMatchesRegularExpression(string $pattern, string $string, string $message = ''): void {
        if (!preg_match($pattern, $string)) {
            $msg = $message ? "$message: " : '';
            $msg .= "String '$string' does not match pattern '$pattern'";
            throw new AssertionFailedException($msg);
        }
    }

    // ========== Exception expectations ==========

    /**
     * Declare that the current test expects an exception.
     * The Runner will verify after the test method completes.
     */
    protected function expectException(string $exceptionClass): void {
        $this->_expectExceptionClass = $exceptionClass;
    }

    protected function expectExceptionMessage(string $message): void {
        $this->_expectExceptionMessage = $message;
    }

    protected function expectExceptionMessageMatches(string $regex): void {
        $this->_expectExceptionMessageRegex = $regex;
    }

    // ========== Skip ==========

    protected function markTestSkipped(string $reason = ''): void {
        throw new SkipException($reason);
    }

    // ========== Internal state (used by Runner) ==========

    /** @internal */
    public string $_expectExceptionClass = '';
    /** @internal */
    public string $_expectExceptionMessage = '';
    /** @internal */
    public string $_expectExceptionMessageRegex = '';

    /** @internal Reset per-test state before next run */
    public function _resetState(): void {
        $this->_expectExceptionClass = '';
        $this->_expectExceptionMessage = '';
        $this->_expectExceptionMessageRegex = '';
    }

    // ========== 临时抑制 PHP 警告 ==========

    private int $_warningLevel = 0;

    /** 保存当前 error_reporting 并关闭警告 */
    protected function suppressWarnings(): void {
        $this->_warningLevel = error_reporting();
        error_reporting(0);
    }

    /** 恢复之前保存的 error_reporting */
    protected function restoreWarnings(): void {
        error_reporting($this->_warningLevel);
    }

    // ========== Helpers ==========

    private function export($value): string {
        if ($value === null) return 'null';
        if (is_bool($value)) return $value ? 'true' : 'false';
        if (is_string($value)) return "'" . addslashes($value) . "'";
        if (is_int($value) || is_float($value)) return (string)$value;
        if (is_array($value)) return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_object($value)) return get_class($value);
        return (string)$value;
    }
}
