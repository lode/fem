<?php

namespace fem;

class bootstrap {

public function __construct() {
	// we live in a
	self::environment();
	
	// which is
	self::globalized();
	
	// and
	self::uncertain();
	
	// which we try to make
	self::secure();
}

/**
 * environmental check
 * defines environment and root dir
 */
private static function environment() {
	define('fem\ENVIRONMENT',  getenv('APP_ENV'));
	define('fem\ROOT_DIR',     realpath(__DIR__.'/../../').'/');
	define('fem\ROOT_DIR_APP', \fem\ROOT_DIR.'application/');
	
	if (empty(\fem\ENVIRONMENT)) {
		echo 'no environment set';
		exit;
	}
}

/**
 * globalization
 * sets up encoding, timezone and locale
 */
private static function globalized() {
	mb_internal_encoding('UTF-8');
	date_default_timezone_set('UTC');
	setlocale(LC_ALL, 'en_US.utf8', 'en_US', 'C.UTF-8');
}

/**
 * error handling
 * know when to say what depending on environment
 * and passes errors to exceptions
 */
private static function uncertain() {
	ini_set('display_startup_errors', 0);
	ini_set('display_errors', 0);
	error_reporting(0);
	if (\fem\ENVIRONMENT == 'development') {
		ini_set('display_startup_errors', 1);
		ini_set('display_errors', 1);
		error_reporting(-1);
	}
	
	$error_handler = function($level, $message, $file, $line, $context) {
		throw new \ErrorException($message, $code=0, $level, $file, $line);
	};
	set_error_handler($error_handler);
}

/**
 * basic security
 * stops outputting of php header
 */
private static function secure() {
	header_remove('X-Powered-By');
}

}
