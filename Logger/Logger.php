<?php
namespace Clue\Logger;

Interface Logger{
	/**
	 * 必须写入的字段
	 * level, message, timestamp
	 *
	 * 可选写入字段
	 * caller
	 */
    function write($data);

    function format($data);
    function format_backtrace($trace);
    function format_var($var);
}
