<?php

namespace projectname;
use fem;

class routing extends fem\routing {

protected $default_handler = 'home';
protected $map_to_filesystem = false;

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
 * @see \fem\routing::get_handler_type() for the different ways to define a handler
 */
protected function get_custom_routes() {
	$routes = array();
	
	// map to a file
	$routes['GET']['foo'] = 'bar';
	
	// map to a method
	$routes['GET']['foo'] = 'bar->baz';
	$routes['GET']['foo'] = 'bar::baz';
	
	// map to an inline function
	$routes['GET']['foo'] = function($url, $method){};
	$routes['GET']['foo'] = function($url, $method, $arguments){ echo 'baz'; };
	
	return $routes;
}

}
