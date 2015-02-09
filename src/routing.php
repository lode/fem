<?php

namespace fem;

/**
 * catching urls
 * 
 * by default, urls get mapped to files in the controller directory
 * the 'home' controller is called for empty urls
 * if a controller contains a class with the same name, it is instantiated
 * 
 * modify this behavior by extending this class and:
 * 
 * - set $default_handler to the homepage
 * - implement get_custom_routes() to define own routing
 * - set $handler_base_path to 'viewmodels' to use those instead of controllers
 *   because of that, we'll use the term 'handler' instead of controller/viewmodel
 * 
 * @note pay attention to magic properties $map_to_filesystem and $auto_instantiation
 * 
 * when handling the request, the handler (i.e. the controller) will receive these parameters:
 * - $url       the path part of the url (without host or get parameters)
 * - $method    one of GET, PUT, POST, PATCH, DELETE, OPTIONS, HEAD
 * - $arguments named subpatterns when used inside get_custom_routes()
 */
class routing {

public $url;
public $method;
public $arguments;

/**
 * the default handler (for empty urls)
 */
protected $default_handler = 'home';

/**
 * catch-all 404s and load the default handler instead
 * @note when set to true, you'll never get a 404
 */
protected $fallback_to_default = false;

/**
 * mapping urls directly to files and requiring those
 * useful for prototyping new applications
 */
protected $map_to_filesystem = true;

/**
 * the path where handlers can be found
 * set to 'viewmodels' or something else to use another kind
 * it is only used for file path finding
 */
protected $handler_base_path = 'controllers';

/**
 * instantiate a class when including a file
 * @see get_handler_type()
 */
protected $auto_instantiation = true;

/**
 * find a handler for the current url and execute it
 * 
 * @return void however, handle() will fire a handler or an error
 */
public function __construct() {
	$this->initialize();
	$handler = $this->find_handler();
	$this->handle($handler);
}

/**
 * user defined mapping url to handler
 * 
 * @return array with keys for each supported http method ..
 *               .. and inside, key-value pairs of url-regex => handler
 * 
 * for example `$routes['GET']['foo'] = 'bar';` ..
 * .. maps the GET url 'foo' to the file 'bar'
 * 
 * the url-regex doesn't need regex boundaries like '/foo/'
 * @see get_handler_type() for the different ways to define a handler
 */
protected function get_custom_routes() {
	return array();
}

/**
 * find someone else and let them handle the request
 * 
 * @param  mixed $handler @see get_handler_type()
 * @return void           however, it will fire a handler or an error
 */
protected function handle($handler) {
	$handler_type = $this->get_handler_type($handler);
	
	// unmatched requests
	if (empty($handler) || empty($handler_type)) {
		http_response_code(404);
		exit;
	}
	
	// default flow: matched requests
	$this->{'load_handler_as_'.$handler_type}($handler);
}

/**
 * finds the handler's file
 * 
 * @param  string $filename a filename, relative to the handlers directory, and w/o '.php'
 * @return string|boolean   the full path, including \fem\ROOT
 *                          or false when not found
 */
protected function find_handler_path($filename) {
	$filename = strtolower($filename);
	
	if (preg_match('{[^a-z0-9/-]}', $filename)) {
		return false;
	}
	
	$base_path = \fem\ROOT.'application/'.$this->handler_base_path.'/';
	$full_path = $base_path.$filename.'.php';
	if (file_exists($full_path) == false) {
		return false;
	}
	
	// sanity check to prevent escaping outside the base dir
	if (strpos($full_path, $base_path) !== 0) {
		return false;
	}
	
	return $full_path;
}

/**
 * makes sure $this->url and $this->method exist
 * 
 * @return void, however, it can fire an error for unknown http methods
 *               @see \fem\session::get_method() for which ones are supported
 */
protected function initialize() {
	$this->url    = \fem\session::get_url();
	$this->method = \fem\session::get_method();
	
	if (empty($this->method)) {
		http_response_code(501);
		exit;
	}
}

/**
 * find a handler for the requested url
 * 
 * checks the filesystem directly if $this->map_to_filesystem is true
 * checks user defined routes otherwise
 * 
 * gives the default handler for empty url ..
 * .. or when $this->fallback_to_default is true
 * 
 * @return mixed $handler see get_handler_type()
 */
private function find_handler() {
	$handler = false;
	
	// empty url leads to the default handler
	if (empty($this->url)) {
		return $this->default_handler;
	}
	
	// simple mapping urls to the filesystem
	if ($this->map_to_filesystem && $this->find_handler_path($this->url)) {
		// url == handler
		return $this->url;
	}
	
	// custom mapping via regex to various handler formats
	if ($this->map_to_filesystem == false) {
		$handler = $this->find_custom_handler();
		// don't return, it could be a 404
	}
	
	// fallback for unmatched requests
	if (empty($handler) && $this->fallback_to_default) {
		return $this->default_handler;
	}
	
	// 404 when file or regex is unmatched
	return $handler;
}

/**
 * matches the user defined routes
 * 
 * @return mixed $handler see get_handler_type()
 */
private function find_custom_handler() {
	// get user defined routes
	$routes = $this->get_custom_routes();
	if (empty($routes) || empty($routes[$this->method])) {
		return false;
	}
	
	// catch the non-regex routes first
	if (isset($routes[$this->method][$this->url])) {
		return $routes[$this->method][$this->url];
	}
	
	// find a matching regex
	$handler = false;
	foreach ($routes[$this->method] as $url_regex => $possible_handler) {
		if (preg_match('{'.$url_regex.'}', $this->url, $matches)) {
			$handler = $possible_handler;
			
			// save named subpatterns from the regex, to send them through to the handler
			if (strpos($url_regex, '?<') && count($matches) > 1) {
				$this->arguments = $matches;
			}
			break;
		}
	}
	
	return $handler;
}

/**
 * figure out how to run the given handler
 * 
 * there are a few different handlers formats:
 * 
 * - function: an inline function directly handling the request
 *             @note it can call handle() with a new handler of another type ..
 *             .. this might be useful for database lookups
 * 
 * - method:   a method in the format of 'class->method'
 *             the class will be instantiated before invoking the method
 * 
 * - static:   a method in the format of 'class::method'
 *             where the method will be called statically
 * 
 * - file:     a file path (relative to $handler_base_path)
 *             @see $auto_instantiation: it can instantiate a class with the same name
 * 
 * @param  mixed $handler
 * @return string|boolean the name of the handler from above description ..
 *                        .. or false when unknown
 */
private function get_handler_type($handler) {
	if ($handler instanceof Closure) {
		return 'function';
	}
	if (is_string($handler) == false) {
		return false;
	}
	
	if (strpos($handler, '->')) {
		return 'method';
	}
	if (strpos($handler, '::')) {
		return 'static';
	}
	if ($handler && $this->find_handler_path($handler)) {
		return 'file';
	}
	
	return false;
}

/**
 * inline function handler
 * 
 * @param  closure $handler
 * @return void, the closure is called
 */
private function load_handler_as_function($handler) {
	$handler($this->url, $this->method, $this->arguments);
}

/**
 * instantiated method handler
 * 
 * @param  string $handler
 * @return void, the method is called on the object
 */
private function load_handler_as_method($handler) {
	list($class, $method) = explode('->', $handler);
	
	$object = new $class;
	$object->$method($this->url, $this->method, $this->arguments);
}

/**
 * static method handler
 * 
 * @param  string $handler
 * @return void, the method is called on the class
 */
private function load_handler_as_static($handler) {
	list($class, $method) = explode('::', $handler);
	
	$class::$method($this->url, $this->method, $this->arguments);
}

/**
 * file reference handler
 * 
 * @param  string $handler
 * @return void, the file is included
 *               a inner class is instantiated if $this->auto_instantiation is true
 */
private function load_handler_as_file($handler) {
	$path = $this->find_handler_path($handler);
	require_once $path;
	
	$class_name = str_replace('/', '\\', $handler);
	if ($this->auto_instantiation && is_callable($class_name)) {
		new $class_name($this->url, $this->method, $this->arguments);
	}
}

}
