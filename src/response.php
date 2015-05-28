<?php

namespace alsvanzelf\fem;

class response {

/**
 * an adviced set of http status codes
 */
const STATUS_OK                    = 200;
const STATUS_CREATED               = 201;
const STATUS_NO_CONTENT            = 204;
const STATUS_BAD_REQUEST           = 400;
const STATUS_UNAUTHORIZED          = 401;
const STATUS_FORBIDDEN             = 403;
const STATUS_NOT_FOUND             = 404;
const STATUS_METHOD_NOT_ALLOWED    = 405;
const STATUS_UNPROCESSABLE_ENTITY  = 422;
const STATUS_INTERNAL_SERVER_ERROR = 500;
const STATUS_SERVICE_UNAVAILABLE   = 503;

/**
 * human facing strings briefly describing each error
 */
protected static $status_messages = array(
	200 => 'Ok',
	201 => 'Created',
	204 => 'No content',
	400 => 'Bad request',
	401 => 'Unauthorized',
	403 => 'Forbidden',
	404 => 'Not found',
	405 => 'Method not allowed',
	422 => 'Unprocessable entity',
	500 => 'Internal server error',
	503 => 'Service unavailable',
);

/**
 * send a status code to the browser
 * 
 * @param  int  $code
 * @return void
 */
public static function send_status($code) {
	if (!isset(static::$status_messages[$code])) {
		$code = self::STATUS_INTERNAL_SERVER_ERROR;
	}
	
	http_response_code($code);
}

/**
 * get the describing message of the status code
 * @param  int    $code i.e. 404
 * @return string       i.e. 'Not found'
 */
public static function get_status_message($code) {
	if (!isset(static::$status_messages[$code])) {
		$code = self::STATUS_INTERNAL_SERVER_ERROR;
	}
	
	$message = static::$status_messages[$code];
	return $message;
}

}
