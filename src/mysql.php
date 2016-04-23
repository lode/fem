<?php

namespace alsvanzelf\fem;

class mysql {

/**
 * query results
 */
public static $num_rows      = null;
public static $insert_id     = null;
public static $affected_rows = null;
public static $error_number  = null;
public static $error_message = null;

/**
 * possible types for ::select() calls
 */
const AS_FIELD = 'field';
const AS_ROW   = 'row';
const AS_ARRAY = 'array';

private static $types = array(self::AS_FIELD, self::AS_ROW, self::AS_ARRAY);

/**
 * internal keepers of state
 */
private static $connection  = null;

/**
 * connects to the database, defined by ::get_config()
 * makes sure we have a strict and unicode aware connection
 * 
 * @see http://stackoverflow.com/questions/5741187/sql-injection-that-gets-around-mysql-real-escape-string
 * 
 * @param  array $config optional, containing 'host', 'user', 'pass', 'name', and 'port' values
 * @return void
 */
public static function connect($config=null) {
	if (empty($config)) {
		$config = self::get_config();
	}
	
	self::$connection = new \mysqli($config['host'], $config['user'], $config['pass'], $config['name'], $config['port']);
	
	self::$connection->set_charset('utf8');
	
	$sql_modes = array(
		// force correct column types
		'STRICT_ALL_TABLES',
		// extra's later included in strict mode
		'ERROR_FOR_DIVISION_BY_ZERO',
		'NO_ZERO_DATE',
		'NO_ZERO_IN_DATE',
		// block the usage of double quotes to quote values
		// as this is unsafe in some versions of mysql
		// double quotes are now allowed to quote identifiers (next to backtick)
		'ANSI_QUOTES',
	);
	$sql_modes_string = implode(',', $sql_modes);
	
	self::raw("SET SQL_MODE='".$sql_modes_string."';");
}

/**
 * allows to get the current database handler
 * 
 * @return mysqli
 */
public static function get_connection_object() {
	return self::$connection;
}

/**
 * allows to set the current database handler
 * 
 * @param mysqli $connection
 */
public static function set_connection_object(\mysqli $connection) {
	self::$connection = $connection;
}

/**
 * executes a SELECT statement, and returns the result as array, row, or single field
 * 
 * @param  string $type  one of the ::AS_* consts
 * @param  string $sql   the base sql statement
 * @param  array  $binds bind values for the given sql, @see ::merge()
 * @return array         result set
 */
public static function select($type, $sql, $binds=null) {
	if (in_array($type, self::$types) == false) {
		$exception = bootstrap::get_library('exception');
		throw new $exception('unknown select type');
	}
	
	$results = self::query($sql, $binds);
	return self::{'as_'.$type}($results);
}

/**
 * executes any query on the database
 * 
 * protects against unsafe UPDATE or DELETE statements
 * blocks when they don't contain a WHERE or LIMIT clause
 * 
 * @param  string $sql   the base sql statement
 * @param  array  $binds bind values for the given sql, @see ::merge()
 * @return mysqli_result
 */
public static function query($sql, $binds=null) {
	if (!empty($binds)) {
		$sql = self::merge($sql, $binds);
	}
	
	// secure against wild update/delete statements
	if (preg_match('{^UPDATE|DELETE\s}', $sql) && preg_match('{\sWHERE|LIMIT\s}', $sql) == false) {
		$exception = bootstrap::get_library('exception');
		throw new $exception('unsafe UPDATE/DELETE statement, use a WHERE/LIMIT clause');
	}
	
	return self::raw($sql);
}

/**
 * executes a query on the database
 * similar to ::query(), except nothing is modified or checked anymore
 * 
 * @param  string $sql
 * @return mysqli_result
 */
public static function raw($sql) {
	$exception = bootstrap::get_library('exception');
	$response  = bootstrap::get_library('response');
	
	if (is_null(self::$connection)) {
		throw new $exception('no db connection', $response::STATUS_SERVICE_UNAVAILABLE);
	}
	
	$result = self::$connection->query($sql);
	
	self::$error_number  = self::$connection->errno;
	self::$error_message = self::$connection->error;
	if (self::$error_number) {
		throw new $exception(self::$error_message, self::$error_number);
	}
	
	if ($result instanceof \mysqli_result) {
		self::$num_rows = $result->num_rows;
	}
	self::$insert_id     = self::$connection->insert_id;
	self::$affected_rows = self::$connection->affected_rows;
	
	return $result;
}

/**
 * collects the config for connecting from:
 * - an environment variable `APP_MYSQL`
 * - a `config/mysql.ini` file
 * 
 * @note in the ini file, the password is expected to be in a base64 encoded format
 *       to help against shoulder surfing
 * 
 * @return array with 'host', 'user', 'pass', 'name', 'port' values
 */
protected static function get_config() {
	if (getenv('APP_MYSQL')) {
		$config = parse_url(getenv('APP_MYSQL'));
		
		// strip of the leading slash
		$config['name'] = substr($config['path'], 1);
		
		// cleanup
		unset($config['scheme']);
		unset($config['path']);
	}
	else {
		$config_file = \alsvanzelf\fem\ROOT_DIR.'config/mysql.ini';
		if (file_exists($config_file) == false) {
			$exception = bootstrap::get_library('exception');
			throw new $exception('no db config found');
		}
		
		$config = parse_ini_file($config_file);
		
		// decode the password
		$config['pass'] = base64_decode($config['pass']);
	}
	
	// default the port number
	if (empty($config['port'])) {
		$config['port'] = 3306;
	}
	elseif (is_int($config['port']) === false) {
		$config['port'] = (int) $config['port'];
	}
	
	return $config;
}

/**
 * merges bind values while escaping them
 * 
 * $sql can contain printf conversion specifications, i.e.:
 * - SELECT * WHERE `foo` = '%s';
 * - SELECT * WHERE `foo` > %d;
 * 
 * @param  string $sql   the base sql statement
 * @param  array  $binds bind values for the given sql
 * @return string        input sql merged with bind values
 */
private static function merge($sql, $binds) {
	if (is_array($binds) == false) {
		$binds = (array)$binds;
	}
	if (is_null(self::$connection)) {
		$exception = bootstrap::get_library('exception');
		$response  = bootstrap::get_library('response');
		throw new $exception('no db connection', $response::STATUS_SERVICE_UNAVAILABLE);
	}
	
	foreach ($binds as &$argument) {
		$argument = self::$connection->real_escape_string($argument);
	}
	
	return vsprintf($sql, $binds);
}

/**
 * converts a SELECT result set to an array
 * 
 * @param  mysqli_result $results
 * @return array
 */
private static function as_array(\mysqli_result $results) {
	$array = array();
	while ($row = $results->fetch_assoc()) {
		$array[] = $row;
	}
	
	return $array;
}

/**
 * like ::as_array() but only containing the first row
 * 
 * @param  mysqli_result $results
 * @return array
 */
private static function as_row(\mysqli_result $results) {
	return $results->fetch_assoc();
}

/**
 * like ::as_row() but only containing the first value
 * 
 * @param  mysqli_result $results
 * @return string
 */
private static function as_field(\mysqli_result $results) {
	$row = self::as_row($results);
	return current($row);
}

}
