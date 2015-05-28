<?php

namespace alsvanzelf\fem;

class bootstrap {

/**
 * an array of custom libraries extending fem
 */
private static $custom_libraries;

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
 * set a custom library for usage by other fem libraries
 * use this when extending a fem library
 * without this, fem will call the non-extended library in its own calls
 * 
 * @param  string $name         the name of the fem class, i.e. 'page'
 * @param  string $custom_class the (fully qualified) name of the extending class
 * @return void
 */
public static function set_custom_library($name, $custom_class) {
	if (class_exists('\\alsvanzelf\\fem\\'.$name) == false) {
		throw new \Exception('library does not exist in fem');
	}
	
	self::$custom_libraries[$name] = $custom_class;
}

/**
 * get the class name which should be used for a certain fem library
 * used internally to determine whether a custom library has been set
 * returns the fem class name if no custom library is set
 * 
 * @param  string $name the name of the fem class, i.e. 'page'
 * @return string       the (fully qualified) name of the class which should be used
 */
public static function get_library($name) {
	if (class_exists('\\alsvanzelf\\fem\\'.$name) == false) {
		throw new \Exception('library does not exist in fem');
	}
	
	if (isset(self::$custom_libraries[$name])) {
		return self::$custom_libraries[$name];
	}
	
	return '\\alsvanzelf\\fem\\'.$name;
}

/**
 * environmental check
 * defines environment and root dir
 */
private static function environment() {
	define('alsvanzelf\fem\ENVIRONMENT',  getenv('APP_ENV'));
	define('alsvanzelf\fem\ROOT_DIR',     realpath(__DIR__.'/../../../../').'/');
	define('alsvanzelf\fem\ROOT_DIR_APP', \alsvanzelf\fem\ROOT_DIR.'application/');
	
	if (constant('\alsvanzelf\fem\ENVIRONMENT') == false) {
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
	if (\alsvanzelf\fem\ENVIRONMENT == 'development') {
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
 * and help against session fixation
 */
private static function secure() {
	header_remove('X-Powered-By');
	
	ini_set('session.use_trans_sid',    0);
	ini_set('session.use_only_cookies', 1);
	ini_set('session.cookie_httponly',  1);
	ini_set('session.use_strict_mode',  1); // @note this is only effective from 5.5.2
	ini_set('session.entropy_file',     '/dev/urandom');
}

}
