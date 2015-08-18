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
}
