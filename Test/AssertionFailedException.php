<?php
namespace Clue\Test;

class AssertionFailedException extends \RuntimeException {
    public string $callerFile = '';
    public int $callerLine = 0;

    public function __construct(string $message = '') {
        parent::__construct($message);

        // Frame 0: __construct (us)
        // Frame 1: assertion method (assertEquals, assertTrue, etc.)
        //   file/line here = where the test called the assertion
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $frame = $trace[1] ?? $trace[0] ?? [];
        $this->callerFile = $frame['file'] ?? '';
        $this->callerLine = $frame['line'] ?? 0;
    }
}
