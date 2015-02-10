<?php

namespace alsvanzelf\fem;

class request {

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

}
