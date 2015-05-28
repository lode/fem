<?php

namespace alsvanzelf\fem;

/**
 * manage user logins with an email address - password combination
 * 
 * requires ircmaxell/password-compat for php < 5.5
 * 
 * example usage:
 *   $login = login_password::get_by_email($email_address);
 *   if (empty($login) || $login->is_valid($password) == false) {
 *     // error
 *   }
 *   
 *   session::create();
 *   session::set_user_id($login->get_user_id());
 */
class login_password {

/**
 * minimum length of a password to be allowed to create a hash for it
 */
const MINIMUM_LENGTH = 8;

/**
 * keeper of state
 */
private $data;

public function __construct($id) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "SELECT * FROM `login_passwords` WHERE `id` = %d;";
	$login = $mysql::select('row', $sql, $id);
	if (empty($login)) {
		throw new \Exception('password login not found');
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
 * checks whether the given email address match one on file
 * 
 * @param  string        $email_address
 * @return $this|boolean false when the email address is not found
 */
public static function get_by_email($email_address) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "SELECT * FROM `login_passwords` WHERE `email_address` = '%s';";
	$login = $mysql::select('row', $sql, $email_address);
	if (empty($login)) {
		return false;
	}
	
	return new static($login['id']);
}

/**
 * check if the password gives access to the login
 * also re-hashes the password hash if the algorithm is out of date
 * 
 * @param  string  $password     in plain text
 * @param  boolean $check_rehash set to false to skip re-hashing
 * @return boolean
 */
public function is_valid($password, $check_rehash=true) {
	if (password_verify($password, $this->data['hash']) == false) {
		return false;
	}
	
	if ($check_rehash && password_needs_rehash($this->data['hash'], PASSWORD_DEFAULT)) {
		$new_hash = self::hash_password($password);
		$this->set_new_hash($this->data['user_id'], $this->data['email_address'], $new_hash);
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
 * adds a login
 * 
 * @param int    $user_id
 * @param string $email_address
 * @param string $password      in plain text
 */
public function add($user_id, $email_address, $password) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "INSERT INTO `login_passwords` SET `user_id` = %d, `email_address` = '%s';";
	$binds = array($user_id, $email_address);
	$mysql::query($sql, $binds);
	
	$login = new static($mysql::$insert_id);
	
	$hash = self::hash_password($password);
	$login->set_new_hash($hash);
}

/**
 * stores a new hash for the current login
 * 
 * @param  string $new_hash
 * @return void
 */
public function set_new_hash($new_hash) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "UPDATE `login_passwords` SET `hash` = '%s' WHERE `id` = %d;";
	$binds = array($new_hash, $this->data['id']);
	$mysql::query($sql, $binds);
	
	$this->data['hash'] = $new_hash;
}

/**
 * generates a new hash for the given password
 * we wrap the native method to ensure a successful hash
 * 
 * also enforces a minimum length for passwords
 * @see ::MINIMUM_LENGTH
 * 
 * @param  string $password
 * @return string
 */
public static function hash_password($password) {
	if (mb_strlen($password) < self::MINIMUM_LENGTH) {
		throw new \Exception('passwords need a minimum length of '.self::MINIMUM_LENGTH);
	}
	
	$hash = password_hash($password, PASSWORD_DEFAULT);
	if (empty($hash)) {
		throw new \Exception('unable to hash password');
	}
	
	return $hash;
}

}
