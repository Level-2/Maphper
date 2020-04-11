<?php
//Add support for PHPUnit 5 and 6
if (!class_exists('PHPUnit_Framework_TestCase')) {
	class PHPUnit_Framework_TestCase extends \PHPUnit\Framework\TestCase {}
}

require_once 'tests/testclasses.php';
spl_autoload_register(function($class) {

	$file = str_replace('\\', DIRECTORY_SEPARATOR, $class) . '.php';
	if (is_file($file)) require_once $file;
});