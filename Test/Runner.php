<?php
namespace Clue\Test;

/**
 * Minimal test runner.
 * Scans .test.php files, discovers TestCase subclasses, runs test methods.
 */
class Runner {
    private int $passed  = 0;
    private int $failed  = 0;
    private int $skipped = 0;
    private int $errors  = 0;
    private int $total   = 0;

    /** @var array<array{file:string,class:string,method:string,type:string,message:string,line:int,sourceFile:string}> */
    private array $failures = [];
    /** @var array<array{class:string,method:string,message:string}> */
    private array $skippedTests = [];

    public function runDir(string $dir): bool {
        $files = glob(rtrim($dir, '/') . '/*.test.php');
        if (!$files) {
            echo "No test files found in $dir\n";
            return false;
        }
        sort($files);
        foreach ($files as $file) {
            $this->runFile($file);
        }
        $this->printSummary();
        return $this->failed === 0 && $this->errors === 0;
    }

    public function runFile(string $file): void {
        $before = get_declared_classes();
        require_once $file;
        $after = get_declared_classes();
        $newClasses = array_diff($after, $before);

        foreach ($newClasses as $className) {
            $ref = new \ReflectionClass($className);
            if ($ref->isAbstract()) continue;
            if ($ref->getName() === TestCase::class) continue;
            if (!$ref->isSubclassOf(TestCase::class)) continue;

            $this->runClass($className, basename($file));
        }
    }

    public function runClass(string $className, string $fileLabel = ''): void {
        $ref = new \ReflectionClass($className);

        // setUpBeforeClass / tearDownAfterClass
        $hasBefore = $ref->hasMethod('setUpBeforeClass')
            && $ref->getMethod('setUpBeforeClass')->isStatic();
        $hasAfter = $ref->hasMethod('tearDownAfterClass')
            && $ref->getMethod('tearDownAfterClass')->isStatic();

        if ($hasBefore) {
            try {
                $ref->getMethod('setUpBeforeClass')->invoke(null);
            } catch (\Throwable $e) {
                // If setUpBeforeClass fails, skip the whole class
                echo 'E';
                $this->errors++;
                $this->failures[] = [
                    'file' => $fileLabel, 'class' => $className, 'method' => 'setUpBeforeClass',
                    'type' => 'ERROR',
                    'message' => get_class($e) . ': ' . $e->getMessage(),
                ];
                return;
            }
        }

        $methods = $ref->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $name = $method->getName();
            if (!str_starts_with($name, 'test')) continue;
            if ($name === 'setUp' || $name === 'tearDown'
                || $name === 'setUpBeforeClass' || $name === 'tearDownAfterClass') continue;

            $this->runTest($className, $name, $fileLabel);
        }

        if ($hasAfter) {
            try {
                $ref->getMethod('tearDownAfterClass')->invoke(null);
            } catch (\Throwable $e) {
                echo 'E';
                $this->errors++;
                $this->failures[] = [
                    'file' => $fileLabel, 'class' => $className, 'method' => 'tearDownAfterClass',
                    'type' => 'ERROR',
                    'message' => get_class($e) . ': ' . $e->getMessage(),
                ];
            }
        }
    }

    private function runTest(string $className, string $methodName, string $fileLabel = ''): void {
        $label = $fileLabel ? "[$fileLabel] " : '';
        $label .= "$className::$methodName";
        $this->total++;

        /** @var TestCase $instance */
        $instance = new $className();
        $instance->_resetState();

        // Parse @expectedException from docblock
        $refMethod = new \ReflectionMethod($className, $methodName);
        $doc = $refMethod->getDocComment() ?: '';
        $annotationException = '';
        $annotationMessageRegex = '';

        if (preg_match('/@expectedException\s+(\S+)/', $doc, $m)) {
            $annotationException = $m[1];
        }
        if (preg_match('/@expectedExceptionMessageRegExp\s+"([^"]+)"/', $doc, $m)) {
            $annotationMessageRegex = $m[1];
        }

        try {
            if ($annotationException) {
                $this->runWithAnnotationException(
                    $instance, $methodName, $label,
                    $annotationException, $annotationMessageRegex
                );
            } else {
                $this->executeTestMethod($instance, $methodName, $label);
            }
        } catch (SkipException $e) {
            $this->skipped++;
            echo 's';
            $this->skippedTests[] = [
                'class' => $className, 'method' => $methodName,
                'message' => $e->getMessage(),
            ];
        } catch (AssertionFailedException $e) {
            $this->failed++;
            echo 'F';
            $this->failures[] = [
                'file' => $fileLabel, 'class' => $className, 'method' => $methodName,
                'type' => 'FAIL', 'message' => $e->getMessage(),
                'line' => $e->callerLine, 'sourceFile' => $e->callerFile,
            ];
        } catch (\Throwable $e) {
            $this->errors++;
            echo 'E';
            $this->failures[] = [
                'file' => $fileLabel, 'class' => $className, 'method' => $methodName,
                'type' => 'ERROR',
                'message' => get_class($e) . ': ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Run test with @expectedException annotation.
     * Uses reflection to call protected setUp/tearDown.
     */
    private function runWithAnnotationException(
        TestCase $instance,
        string $methodName,
        string $label,
        string $expectedClass,
        string $messageRegex
    ): void {
        $ref = new \ReflectionClass($instance);
        $setUp = $ref->getMethod('setUp');
        $tearDown = $ref->getMethod('tearDown');
        $setUp->setAccessible(true);
        $tearDown->setAccessible(true);

        $caught = null;

        $setUp->invoke($instance);
        try {
            $instance->$methodName();
        } catch (\Throwable $e) {
            $caught = $e;
        }
        $tearDown->invoke($instance);

        if ($caught === null) {
            throw new AssertionFailedException(
                "Expected exception $expectedClass but none was thrown"
            );
        }

        if (!($caught instanceof $expectedClass)) {
            throw new AssertionFailedException(
                "Expected exception $expectedClass but got " . get_class($caught)
                . ': ' . $caught->getMessage()
            );
        }

        if ($messageRegex) {
            $pattern = $this->ensureRegexDelimiters($messageRegex);
            if (!preg_match($pattern, $caught->getMessage())) {
                throw new AssertionFailedException(
                    "Exception message does not match regex: $messageRegex\n"
                    . 'Message was: ' . $caught->getMessage()
                );
            }
        }

        $this->passed++;
        echo '.';
    }

    /**
     * Execute a test method, handling inline expectException() calls.
     */
    private function executeTestMethod(TestCase $instance, string $methodName, string $label): void {
        $caught = null;

        try {
            $instance->runTestMethod($methodName);
        } catch (SkipException $e) {
            throw $e; // pass up
        } catch (\Throwable $e) {
            $caught = $e;
        }

        // Check inline expectException()
        $expectedClass = $instance->_expectExceptionClass;

        if ($expectedClass !== '') {
            if ($caught === null) {
                throw new AssertionFailedException(
                    "Expected exception $expectedClass but none was thrown"
                );
            }

            if (!($caught instanceof $expectedClass)) {
                throw new AssertionFailedException(
                    "Expected exception $expectedClass but got " . get_class($caught)
                    . ': ' . $caught->getMessage()
                );
            }

            $msgRegex = $instance->_expectExceptionMessageRegex;
            if ($msgRegex) {
                $pattern = $this->ensureRegexDelimiters($msgRegex);
                if (!preg_match($pattern, $caught->getMessage())) {
                    throw new AssertionFailedException(
                        "Exception message does not match regex: $msgRegex\n"
                        . 'Message was: ' . $caught->getMessage()
                    );
                }
            }

            $this->passed++;
            echo '.';
            return;
        }

        // No expected exception, rethrow if caught
        if ($caught !== null) {
            throw $caught;
        }

        $this->passed++;
        echo '.';
    }

    /**
     * Add regex delimiters if the pattern lacks them.
     */
    private function ensureRegexDelimiters(string $pattern): string {
        if ($pattern === '') return $pattern;
        $first = $pattern[0];
        if (strpbrk($first, '/~#@!%^&*') !== false) {
            return $pattern;
        }
        return '/' . str_replace('/', '\\/', $pattern) . '/';
    }

    public function hasFailures(): bool {
        return $this->failed > 0 || $this->errors > 0;
    }

    public function printSummary(): void {
        echo "\n\n";
        echo "Tests: $this->total, ";
        echo "Passed: $this->passed, ";
        echo "Failed: $this->failed, ";
        echo "Skipped: $this->skipped, ";
        echo "Errors: $this->errors\n";

        if ($this->failures) {
            echo "\n";
            foreach ($this->failures as $f) {
                $loc = $f['class'] ? "{$f['class']}::{$f['method']}" : $f['method'];
                echo "{$f['type']}: $loc\n";
                if (!empty($f['sourceFile']) && $f['line']) {
                    echo "  at {$f['sourceFile']}:{$f['line']}\n";
                }
                echo "  " . str_replace("\n", "\n  ", $f['message']) . "\n\n";
            }
        }

        if ($this->skippedTests) {
            echo "Skipped:\n";
            foreach ($this->skippedTests as $s) {
                $loc = $s['class'] ? "{$s['class']}::{$s['method']}" : $s['method'];
                echo "  $loc\n";
                if ($s['message']) {
                    echo "    " . str_replace("\n", "\n    ", $s['message']) . "\n";
                }
            }
            echo "\n";
        }
    }
}
