<?php
require_once 'tests/testclasses.php';
spl_autoload_register(function($class) {
	$file = strtolower(str_replace('\\', DIRECTORY_SEPARATOR, $class)) . '.php';
	if (is_file($file)) require_once $file;
});