<?php

namespace alsvanzelf\fem;

class request {

/**
 * redirect a browser session to a new url
 * also exists flow
 * 
 * @param  string $location relative to the host
 * @return void
 */
public static function redirect($location) {
	$base_url  = 'http';
	$base_url .= ($_SERVER['HTTPS']) ? 's' : '';
	$base_url .= '//'.$_SERVER['SERVER_NAME'];
	
	if (strpos($location, '/') !== 0) {
		$base_url .= '/';
	}
	
	header('Location: '.$base_url.$location);
	exit;
}

/**
 * get the request url from the current session
 * 
 * @return string
 */
public static function get_url() {
	if (empty($_SERVER['PATH_INFO'])) {
		return '';
	}
	
	return ltrim($_SERVER['PATH_INFO'], '/');
}

/**
 * get the http method used for the current session
 * 
 * @return string|boolean one of GET|PUT|POST|PATCH|DELETE|OPTIONS|HEAD
 *                        or false for unknown types
 */
public static function get_method() {
	if (empty($_SERVER['HTTP_METHOD'])) {
		return 'GET';
	}
	
	$allowed_methods = array('GET', 'PUT', 'POST', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD');
	if (in_array($_SERVER['HTTP_METHOD'], $allowed_methods) == false) {
		return false;
	}
	
	return $_SERVER['HTTP_METHOD'];
}

/**
 * generates a fingerprint of the current request
 * it is based on: the users ip address, the user agent, and its accept properties
 * 
 * @return array
 */
public static function get_fingerprint() {
	return array(
		'ip_address'      => isset($_SERVER['REMOTE_ADDR']) ?          $_SERVER['REMOTE_ADDR']          : null,
		'user_agent'      => isset($_SERVER['HTTP_USER_AGENT']) ?      $_SERVER['HTTP_USER_AGENT']      : null,
		'accept_content'  => isset($_SERVER['HTTP_ACCEPT']) ?          $_SERVER['HTTP_ACCEPT']          : null,
		'accept_charset'  => isset($_SERVER['HTTP_ACCEPT_CHARSET']) ?  $_SERVER['HTTP_ACCEPT_CHARSET']  : null,
		'accept_encoding' => isset($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : null,
		'accept_language' => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null,
	);
}

}
