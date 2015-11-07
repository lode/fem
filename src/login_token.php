<?php

namespace alsvanzelf\fem;

/**
 * manage user logins with token which is emailed to the user
 * 
 * instead of sending an previously generated token ..
 * .. a new token should be generated, and mailed, when a user wants to login
 * 
 * example usage:
 *   $login = login_token::get_by_token($token)
 *   if (empty($login) || $login->is_valid() == false) {
 *     // error
 *   }
 *   
 *   session::create();
 *   session::set_user_id($login->get_user_id());
 */
class login_token {

/**
 * new tokens expire when not used
 */
const EXPIRATION = 86400; // 1 day after creation

/**
 * length of login tokens
 */
const TOKEN_LENGTH = 6;

/**
 * keeper of state
 */
private $data;

public function __construct($id) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "SELECT * FROM `login_tokens` WHERE `id` = %d;";
	$login = $mysql::select('row', $sql, $id);
	if (empty($login)) {
		$exception = bootstrap::get_library('exception');
		throw new $exception('token login not found');
	}
	
	$this->data = $login;
}

/**
 * access to the info of this login
 * 
 * @param  string $key one of the database columns
 * @return string      its value
 */
public function __get($key) {
	if (empty($this->data[$key])) {
		return null;
	}
	
	return $this->data[$key];
}

/**
 * property checks, i.e. for usage in mustache
 * 
 * @param  string  $key one of the database columns
 * @return boolean
 */
public function __isset($key) {
	return isset($this->data[$key]);
}

/**
 * checks whether the given token match one on file
 * 
 * @param  string        $token
 * @return $this|boolean false when the token is not found
 */
public static function get_by_token($token) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "SELECT * FROM `login_tokens` WHERE `code` = '%s';";
	$login = $mysql::select('row', $sql, $token);
	if (empty($login)) {
		return false;
	}
	
	return new static($login['id']);
}

/**
 * check if the token is still valid
 * also marks the token as used to prevent more people getting in
 * 
 * @param  boolean $mark_as_used set to false to validate w/o user action
 * @return boolean
 */
public function is_valid($mark_as_used=true) {
	if (!empty($this->data['used'])) {
		return false;
	}
	if (time() > $this->data['expire_at']) {
		return false;
	}
	
	if ($mark_as_used) {
		$this->mark_as_used();
	}
	
	return true;
}

/**
 * get the id of the user connected to this login
 * 
 * @return int
 */
public function get_user_id() {
	return $this->data['user_id'];
}

/**
 * prevents a token to be re-used
 * is usually called directly by ::is_valid()
 * 
 * @return void
 */
public function mark_as_used() {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "UPDATE `login_tokens` SET `used` = 1, `last_used_at` = %d WHERE `id` = %d;";
	$binds = array(time(), $this->data['id']);
	$mysql::query($sql, $binds);
}

/**
 * create a new temporary login token
 * 
 * @param  int   $user_id
 * @return $this
 */
public static function create($user_id) {
	$string = bootstrap::get_library('string');
	$mysql  = bootstrap::get_library('mysql');
	
	$new_token  = $string::generate_token(self::TOKEN_LENGTH);
	$expiration = (time() + self::EXPIRATION);
	
	$sql   = "INSERT INTO `login_tokens` SET `code` = '%s', `user_id` = %d, `expire_at` = %d;";
	$binds = array($new_token, $user_id, $expiration);
	$mysql::query($sql, $binds);
	
	return new static($mysql::$insert_id);
}

}
