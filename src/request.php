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
public static function redirect($location, $stop_execution=true) {
	if (preg_match('{^(http(s)?:)?//}', $location) == false) {
		$base_url  = 'http';
		$base_url .= !empty($_SERVER['HTTPS']) ? 's' : '';
		$base_url .= '://'.$_SERVER['SERVER_NAME'];
		
		if (strpos($location, '/') !== 0) {
			$base_url .= '/';
		}
		
		$location = $base_url.$location;
	}
	
	header('Location: '.$location);
	
	if ($stop_execution == false) {
		return;
	}
	
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
 * get the primary http accepted output format for the current session
 * 
 * @return string the most interesting part of the accept header ..
 *                .. only the first format, and only the most determinating part ..
 *                i.e. 'text/html, ...' returns 'html' and 'application/json, ...' returns 'json'
 */
public static function get_primary_accept() {
	if (empty($_SERVER['HTTP_ACCEPT'])) {
		return 'html';
	}
	
	// catch the most common formats
	if (strpos($_SERVER['HTTP_ACCEPT'], 'text/html,')) {
		return 'html';
	}
	if (strpos($_SERVER['HTTP_ACCEPT'], 'application/json,')) {
		return 'json';
	}
	
	// use a generic method
	$accept = $_SERVER['HTTP_ACCEPT'];
	if (strpos($accept, ',')) {
		$accept = substr($accept, 0, strpos($accept, ','));
	}
	if (strpos($accept, '/')) {
		$accept = substr($accept, strpos($accept, '/')+1);
	}
	if (strpos($accept, ';')) {
		$accept = substr($accept, 0, strpos($accept, ';'));
	}
	if (strpos($accept, '+')) {
		$accept = substr($accept, 0, strpos($accept, '+'));
	}
	
	return $accept;
}

/**
 * generates a fingerprint of the current request
 * it is based on: the users ip address, the user agent, and its accept properties
 * 
 * @return array
 */
public static function get_fingerprint() {
	return array(
		'ip_address'      => !empty($_SERVER['REMOTE_ADDR']) ?          $_SERVER['REMOTE_ADDR']          : false,
		'user_agent'      => !empty($_SERVER['HTTP_USER_AGENT']) ?      $_SERVER['HTTP_USER_AGENT']      : false,
		'accept_content'  => !empty($_SERVER['HTTP_ACCEPT']) ?          $_SERVER['HTTP_ACCEPT']          : false,
		'accept_charset'  => !empty($_SERVER['HTTP_ACCEPT_CHARSET']) ?  $_SERVER['HTTP_ACCEPT_CHARSET']  : false,
		'accept_encoding' => !empty($_SERVER['HTTP_ACCEPT_ENCODING']) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : false,
		'accept_language' => !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : false,
	);
}

}
