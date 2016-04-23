<?php

namespace alsvanzelf\fem;

class request {

/**
 * redirect a browser session to a new url
 * also exists flow
 * 
 * @param  string  $location       relative to the host
 * @param  boolean $stop_execution defaults to true
 * @param  int     $code           optional, status code instead of default 302
 * @return void
 */
public static function redirect($location, $stop_execution=true, $code=null) {
	if (preg_match('{^(http(s)?:)?//}', $location) == false) {
		$base_url  = 'http';
		$base_url .= !empty($_SERVER['HTTPS']) ? 's' : '';
		$base_url .= '://'.$_SERVER['SERVER_NAME'];
		
		if (strpos($location, '/') !== 0) {
			$base_url .= '/';
		}
		
		$location = $base_url.$location;
	}
	
	header('Location: '.$location, $replace=true, $code);
	
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
	if (empty($_SERVER['REQUEST_METHOD'])) {
		return 'GET';
	}
	
	$allowed_methods = array('GET', 'PUT', 'POST', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD');
	if (in_array($_SERVER['REQUEST_METHOD'], $allowed_methods) == false) {
		return false;
	}
	
	return $_SERVER['REQUEST_METHOD'];
}

/**
 * get the http ($_POST) data
 * mainly useful for data from non-POST requests like PUT/PATCH/DELETE
 * 
 * @note also converts json or xml to the array
 * 
 * @return array a like $_POST
 */
public static function get_data() {
	if (static::get_method() == 'POST' && !empty($_POST)) {
		return $_POST;
	}
	
	$data_string = file_get_contents('php://input');
	if (empty($data_string)) {
		return array();
	}
	
	// convert from json or xml
	$type = self::get_content_type();
	if ($type == 'json') {
		return json_decode($data_string, true);
	}
	if ($type == 'xml') {
		$xml_options =
			LIBXML_NOCDATA|                  // convert CDATA to strings
			LIBXML_COMPACT|                  // speed optimalization
			LIBXML_NONET|                    // disable network access
			LIBXML_NOERROR|LIBXML_NOWARNING; // turn off error reporting
		$xml_data = simplexml_load_string($data_string, $class='SimpleXMLElement', $xml_options);
		
		return json_decode(json_encode($xml_data), true);
	}
	
	parse_str($data_string, $data_array);
	
	return $data_array;
}

/**
 * get the content type of the sent body
 * 
 * @return string @see ::get_primary_mime_type()
 */
public static function get_content_type() {
	if (empty($_SERVER['CONTENT_TYPE'])) {
		return null;
	}
	
	// catch the most common formats
	if ($_SERVER['CONTENT_TYPE'] == 'application/json') {
		return 'json';
	}
	if ($_SERVER['CONTENT_TYPE'] == 'application/xml') {
		return 'xml';
	}
	
	// use a generic method
	return self::get_primary_mime_type($_SERVER['CONTENT_TYPE']);
}

/**
 * get the primary http accepted output format for the current session
 * 
 * @return string @see ::get_primary_mime_type()
 */
public static function get_primary_accept() {
	if (empty($_SERVER['HTTP_ACCEPT'])) {
		return '*';
	}
	
	// catch the most common formats
	if (strpos($_SERVER['HTTP_ACCEPT'], 'text/html,') === 0) {
		return 'html';
	}
	if (strpos($_SERVER['HTTP_ACCEPT'], 'application/json,') === 0) {
		return 'json';
	}
	
	// use a generic method
	return self::get_primary_mime_type($_SERVER['HTTP_ACCEPT']);
}

/**
 * returns basic auth credentials
 * this works around php cgi mode not passing them directly
 * 
 * @note this requires a change in the htaccess as well
 * @see  example-project/public_html/.htaccess
 * 
 * @return array|null with 'USER' and 'PW' keys
 */
public static function get_basic_auth() {
	// normally it just works
	if (!empty($_SERVER['PHP_AUTH_USER'])) {
		return array(
			'USER' => $_SERVER['PHP_AUTH_USER'],
			'PW'   => $_SERVER['PHP_AUTH_PW'],
		);
	}
	
	// php cgi mode requires a work around
	if (!empty($_SERVER['REDIRECT_REMOTE_USER']) && strpos($_SERVER['REDIRECT_REMOTE_USER'], 'Basic ') === 0) {
		$credentials = substr($_SERVER['REDIRECT_REMOTE_USER'], strlen('Basic '));
		$credentials = base64_decode($credentials);
		if (strpos($credentials, ':')) {
			$credentials = explode(':', $credentials);
			return array(
				'USER' => $credentials[0],
				'PW'   => $credentials[1],
			);
		}
	}
	
	return null;
}

/**
 * gets a friendly version of a mime type
 * 
 * @param  string $type
 * @return string the most interesting part of the mime type ..
 *                .. only the first format, and only the most determinating part ..
 *                i.e. 'text/html, ...' returns 'html'
 *                i.e. 'application/json, ...' returns 'json'
 */
private static function get_primary_mime_type($type) {
	if (strpos($type, ',')) {
		$type = substr($type, 0, strpos($type, ','));
	}
	if (strpos($type, '/')) {
		$type = substr($type, strpos($type, '/')+1);
	}
	if (strpos($type, ';')) {
		$type = substr($type, 0, strpos($type, ';'));
	}
	if (strpos($type, '+')) {
		$type = substr($type, strpos($type, '+')+1);
	}
	
	return $type;
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
