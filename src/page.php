<?php

namespace alsvanzelf\fem;

class page {

protected $data = array();

protected static $default_error_template;

public function __construct($title=null) {
	if ($title) {
		$this->data['title'] = $title;
	}
}

public function show($template, $data=array()) {
	if (empty($data['_'])) {
		$data['_'] = $this->data;
	}
	
	$mustache = bootstrap::get_library('mustache');
	echo $mustache::render($template, $data);
}

/**
 * show an error page using an exception
 * 
 * @param  object $exception    one that extends \Exception
 * @param  string $user_message optional, human-friendly message to show to the user
 * @return void                 script execution terminates
 */
public function exception($exception, $user_message=null) {
	if ($exception instanceof \Exception == false) {
		return $this->error('unknown exception format', response::STATUS_INTERNAL_SERVER_ERROR);
	}
	
	$this->data['exception']['current'] = $exception;
	$this->data['exception']['current_trace_string'] = nl2br($exception->getTraceAsString());
	
	$previous = $exception->getPrevious();
	if ($previous) {
		$this->data['exception']['previous'] = $previous;
		$this->data['exception']['previous_trace_string'] = nl2br($previous->getTraceAsString());
	}
	
	$reason = $exception->getMessage();
	$code   = $exception->getCode();
	$this->error($reason, $code, $user_message);
}

/**
 * show an error page
 * 
 * @param  string $reason       technical description, only shown on development environments
 * @param  int    $code         http status code, @see response::STATUS_*
 * @param  string $user_message optional, human-friendly message to show to the user
 * @return void                 script execution terminates
 */
public function error($reason=null, $code=response::STATUS_INTERNAL_SERVER_ERROR, $user_message=null) {
	$response = bootstrap::get_library('response');
	
	$error_data = array(
		'status_code'    => $code,
		'status_message' => $response::get_status_message($code),
	);
	
	if ($user_message) {
		$error_data['user_message'] = $user_message;
	}
	if (ENVIRONMENT == 'development') {
		$error_data['development'] = true;
		
		if ($reason) {
			$error_data['reason'] = $reason;
		}
	}
	
	if (!empty($this->data['exception']['current'])) {
		if ($this->data['exception']['current'] instanceof \alsvanzelf\fem\exception) {
			if (empty($error_data['user_message'])) {
				$error_data['user_message'] = $this->data['exception']['current']->getUserMessage();
			}
			
			$error_data['user_action'] = $this->data['exception']['current']->getUserAction();
		}
		
		if (ENVIRONMENT != 'development') {
			unset($this->data['exception']);
		}
	}
	
	$response::send_status($code);
	
	if (empty(static::$default_error_template)) {
		$page_data = array(
			'error' => $error_data,
		);
		if (!empty($this->data['exception'])) {
			$page_data['exception'] = $this->data['exception'];
		}
		
		static::show_default_error($page_data);
	}
	else {
		$this->data['title'] = $error_data['status_message'];
		$this->data['error'] = $error_data;
		
		$this->show(static::$default_error_template);
	}
	
	die;
}

/**
 * shows a default error template in case no specific one is set
 * 
 * @param  array $error_data as build up in ::error()
 * @return void              however, mustache has echo'd the html
 */
private static function show_default_error($error_data) {
	$template_path    = \alsvanzelf\fem\ROOT_DIR.'vendor/alsvanzelf/fem/src/templates/default_error.html';
	$template_content = file_get_contents($template_path);
	
	$renderer = new \Mustache_Engine();
	echo $renderer->render($template_content, $error_data);
}

}
