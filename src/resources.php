<?php

namespace alsvanzelf\fem;

class resources {

protected static $root_dir  = \alsvanzelf\fem\ROOT_DIR;
protected static $base_path = '{{root_dir}}public_html';
protected static $base_url  = '/frontend/{{type}}/';

public static function timestamp_css($file) {
	$timestamped_path = self::get_timestamped_url($file, 'css');
	
	return '<link rel="stylesheet" type="text/css" href="'.$timestamped_path.'">';
}

public static function timestamp_js($file, $async=false) {
	$timestamped_path = self::get_timestamped_url($file, 'js');
	
	return '<script type="text/javascript" src="'.$timestamped_path.'"></script>';
}

/**
 * adds a modification timestamp to a file path
 * 
 * @param  string $file relative path from css/ or js/, excluding the extension
 *                      or absolute from the docroot when starting with a '/'
 *                      absolute is handy for loading css from a js plugin directory
 *                      i.e.: 'foo/bar'                   will become '/frontend/css/foo/bar.1234567890.css'
 *                      i.e.: '/frontend/js/plugin/style' will become '/frontend/js/plugin/style.1234567890.css'
 * @param  string $type 'css' or 'js'
 * @return string       $file with a timestamp before the extension
 */
private static function get_timestamped_url($file, $type) {
	$search  = array('{{root_dir}}', '{{type}}');
	$replace = array(self::$root_dir, $type);
	$base_path = str_replace($search, $replace, self::$base_path);
	$base_url  = str_replace($search, $replace, self::$base_url);
	
	$file      = trim($file);
	$absolute  = ($file[0] == '/');
	$file      = ($absolute) ? $file : $base_url.$file;
	$full_path = $base_path.$file.'.'.$type;
	
	if (file_exists($full_path) == false) {
		throw new \Exception('can not find '.$type.' resource '.$file);
	}
	
	$timestamp = filemtime($full_path);
	$full_url  = $file.'.'.$timestamp.'.'.$type;
	
	return $full_url;
}

}
