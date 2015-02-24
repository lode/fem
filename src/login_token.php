<?php

namespace alsvanzelf\fem;

/**
 * manage user logins with token which is emailed to the user
 * 
 * instead of sending an previously generated token ..
 * .. a new token should be generated, and mailed, when a user wants to login
 * 
 * @todo create a generic re-usable token helper
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
	$sql   = "SELECT * FROM `login_tokens` WHERE `id` = %d;";
	$login = mysql::select('row', $sql, $id);
	if (empty($login)) {
		return;
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
 * checks whether the given token match one on file
 * 
 * @param  string        $token
 * @return $this|boolean false when the token is not found
 */
public static function get_by_token($token) {
	$sql   = "SELECT * FROM `login_tokens` WHERE `code` = '%s';";
	$login = mysql::select('row', $sql, $token);
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
	$sql = "UPDATE `login_tokens` SET `used` = 1 WHERE `id` = %d;";
	mysql::query($sql, $this->data['id']);
}

/**
 * create a new temporary login token
 * 
 * @param  int   $user_id
 * @return $this
 */
public static function create($user_id) {
	$byte_length = (self::TOKEN_LENGTH / 2);
	$new_token = bin2hex(openssl_random_pseudo_bytes($byte_length, $strong_enough));
	if ($strong_enough == false || empty($new_token)) {
		throw new \Exception('can not generate a new token');
	}
	
	$sql   = "INSERT INTO `login_tokens` SET `code` = '%s', `user_id` = %d, `expire_at` = %d;";
	$binds = array($new_token, $user_id, (time()+self::EXPIRATION));
	mysql::query($sql, $binds);
	
	return new static(mysql::$insert_id);
}

}
