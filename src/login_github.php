<?php

namespace alsvanzelf\fem;

/**
 * manage user logins via github oauth
 * 
 * requires the config of a github application, @see ::get_config()
 * 
 * to help the complex oauth process ..
 * .. this class uses a bit more magic than other login helpers ..
 * .. everything is switchable via parameters and can be called separate ..
 * .. see every methods docblock for explanation on manual execution
 * 
 * @todo detect when a user revokes the token
 * 
 * example usage:
 *   // let the user authorize
 *   login_github::request_authorization($scope);
 *   
 *   // validate when they come back
 *   $info = login_github::is_valid($callback_data);
 *   if (empty($info)) {
 *     // redirect to login
 *   }
 *   $login = login_github::get_by_info($info);
 *   if (empty($login)) {
 *     // create a new user
 *     $login = login_github::signup($new_user->id, $info);
 *   }
 *   
 *   session::create();
 *   session::set_user_id($login->get_user_id());
 *   $login->persist_in_session();
 */
class login_github {

public static $callback_url = null;

/**
 * keeper of state
 */
private $data;

public function __construct($id) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "SELECT * FROM `login_github` WHERE `id` = %d;";
	$login = $mysql::select('row', $sql, $id);
	if (empty($login)) {
		throw new exception('github login not found');
	}
	
	$this->data = $login;
}

/**
 * collects a config containing the github app codes from a ini file
 * 
 * @return array with 'client_id', 'client_secret', 'callback_url' values
 */
protected static function get_config() {
	$config_file = \alsvanzelf\fem\ROOT_DIR.'config/github.ini';
	if (file_exists($config_file) == false) {
		throw new exception('no github application config found');
	}
	
	return parse_ini_file($config_file);
}

/**
 * checks whether the login match one on file
 * and returns the found login
 * 
 * $info needs to contain both 'oauth_token' and 'github_username'
 * use ::get_by_oauth_token() if only the 'oauth_token' is known
 * 
 * magic alert: also update the token on file if the token is different but the username is found
 * to do manually, set $update_oauth_token to false and call $login->update_oauth_token()
 * 
 * @param  array         $info               as returned by ::is_valid()
 * @param  boolean       $update_oauth_token defaults to true
 * @return $this|boolean false               when the token is not found
 */
public static function get_by_info($info, $update_oauth_token=true) {
	$mysql = bootstrap::get_library('mysql');
	
	// find via the oauth token
	$sql   = "SELECT * FROM `login_github` WHERE `oauth_token` = '%s';";
	$login = $mysql::select('row', $sql, $info['oauth_token']);
	if (!empty($login)) {
		return new static($login['id']);
	}
	
	// find via the github username
	$sql   = "SELECT * FROM `login_github` WHERE `github_username` = '%s';";
	$login = $mysql::select('row', $sql, $info['github_username']);
	if (empty($login)) {
		return false;
	}
	
	$object = new static($login['id']);
	
	// keep token up to date
	if ($update_oauth_token && $info['oauth_token'] != $login['oauth_token']) {
		$object->update_oauth_token($info['oauth_token']);
	}
	
	return $object;
}

/**
 * checks whether the given user id match a login on file
 * and returns the found login
 * 
 * @param  int           $user_id
 * @return $this|boolean false when the user id is not found
 */
public static function get_by_user_id($user_id) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "SELECT * FROM `login_github` WHERE `user_id` = %d;";
	$login = $mysql::select('row', $sql, $user_id);
	if (empty($login)) {
		return false;
	}
	
	return new static($login['id']);
}

/**
 * checks whether the given github username match one on file
 * and returns the found login
 * 
 * @param  string        $github_username
 * @return $this|boolean false when the username is not found
 */
public static function get_by_github_username($github_username) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "SELECT * FROM `login_github` WHERE `github_username` = '%s';";
	$login = $mysql::select('row', $sql, $github_username);
	if (empty($login)) {
		return false;
	}
	
	return new static($login['id']);
}

/**
 * checks whether the given oauth token match one on file
 * and returns the found login
 * 
 * @param  string        $oauth_token
 * @return $this|boolean false when the token is not found
 */
public static function get_by_oauth_token($oauth_token) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "SELECT * FROM `login_github` WHERE `oauth_token` = '%s';";
	$login = $mysql::select('row', $sql, $oauth_token);
	if (empty($login)) {
		return false;
	}
	
	return new static($login['id']);
}

/**
 * adds a new login connection between a local user and a github account
 * 
 * @param  int     $user_id
 * @param  array   $info          as returned by ::is_valid()
 * @return $this
 */
public static function signup($user_id, $info) {
	if (empty($info['github_username']) || empty($info['oauth_token']) || empty($info['scope'])) {
		throw new exception('all info from ::is_valid() is needed for signup');
	}
	
	$mysql = bootstrap::get_library('mysql');
	
	$sql = "INSERT INTO `login_github` SET
		`user_id` = %d,
		`github_username` = '%s',
		`oauth_token` = '%s',
		`scope` = '%s'
	;";
	$binds = array($user_id, $info['github_username'], $info['oauth_token'], $info['scope']);
	$mysql::query($sql, $binds);
	
	return new static($mysql::$insert_id);
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
 * get the id of the user connected to this login
 * 
 * @return int
 */
public function get_user_id() {
	return $this->data['user_id'];
}

/**
 * updates the oauth token for the login
 * 
 * @param  string $new_oauth_token
 * @return void
 */
public function update_oauth_token($new_oauth_token) {
	$mysql = bootstrap::get_library('mysql');
	
	$sql   = "UPDATE `login_github` SET `oauth_token` = '%s' WHERE `id` = %d;";
	$binds = array($new_oauth_token, $this->id);
	$mysql::query($sql, $binds);
}

/**
 * send a user to github to authorize our application
 * 
 * @param  string $scope        defaults to the scope of the previous authorization
 *                              or no scopes at all if authorization is never done yet
 *                              see https://developer.github.com/v3/oauth/#parameters
 * @param  string $callback_url defaults to what is set in the config
 * @return void                 even more so, the user is gone
 */
public static function request_authorization($scope=null, $callback_url=null) {
	$config = static::get_config();
	
	if (!empty($callback_url) && strpos($callback_url, $config['callback_url']) !== 0) {
		throw new exception('custom callback url needs to start with defined callback url');
	}
	if (empty($callback_url)) {
		$callback_url = $config['callback_url'];
	}
	
	$string  = bootstrap::get_library('string');
	$session = bootstrap::get_library('session');
	$request = bootstrap::get_library('request');
	
	// state is a shared secret to prevent people faking the callback
	$state = $string::generate_token($length=40);
	
	$session::start(session::TYPE_TEMPORARY);
	$_SESSION['fem/login_github/state'] = $state;
	$_SESSION['fem/login_github/scope'] = $scope;
	
	// let the user authorize at github
	$url       = 'https://github.com/login/oauth/authorize';
	$arguments = array(
		'client_id' => $config['client_id'],
		'scope'     => $scope,
		'state'     => $state,
	);
	$request::redirect($url.'?'.http_build_query($arguments));
}

/**
 * checks whether an oauth callback contains a valid state and code
 * 
 * magic alert: this also does a lot more validation and info gathering
 * to do manually, set $extended to false and call ::is_valid_extended() or ..
 * .. call ::exchange_for_oauth_token(), ::verify_scope(), ::get_user_info() and ::verify_user()
 * 
 * can return an info array containing 'oauth_token', 'github_username', etc.
 * @see ::is_valid_extended()
 * 
 * @param  array         $callback_data as received from github, containing 'state', 'code' and 'scope'
 * @param  boolean       $extended      defaults to true
 * @return boolean|array                returns array on successful validation and $extended set to true
 */
public static function is_valid($callback_data, $extended=true) {
	if (empty($callback_data['state'])) {
		throw new exception('state expected in oauth callback');
	}
	
	$session = bootstrap::get_library('session');
	$session::start($session::TYPE_TEMPORARY);
	if ($callback_data['state'] != $_SESSION['fem/login_github/state']) {
		throw new exception('state is different, someone tries to fake the callback?');
	}
	
	if (empty($callback_data['code'])) {
		return false;
	}
	
	if ($extended) {
		return static::is_valid_extended($callback_data);
	}
	return true;
}

/**
 * does extended validation by checking:
 * - the code can be exchanged for an oauth token, @see ::exchange_for_oauth_token()
 * - the scope meets the minimul user:email, @see ::verify_scope()
 * - the github account is known for the received oauth_token, @see ::verify_user()
 * 
 * returns info array with:
 * - oauth_token
 * - scope
 * - github_username
 * - github_userinfo
 * 
 * @param  array         $callback_data as received from github, containing 'state', 'code' and 'scope'
 * @return array|boolean                returns false if a validaiton check fails
 * @throws exception                    if code can not be exchanged for a token
 */
public static function is_valid_extended($callback_data) {
	// get oauth token
	$info = static::exchange_for_oauth_token($callback_data['code']);
	
	// check scope
	if (static::verify_scope($info['scope']) == false) {
		return false;
	}
	
	// get user
	$github_userinfo = static::get_user_info($info['oauth_token']);
	$info['github_username'] = $github_userinfo['login'];
	$info['github_userinfo'] = $github_userinfo;
	
	// check github account
	if (static::verify_user($info['oauth_token'], $info['github_username']) == false) {
		return false;
	}
	
	return $info;
}

/**
 * exchange the temporary code for a lasting oauth token
 * 
 * @param  string    $code
 * @return string
 * @throws exception       if the exchange fails
 */
public static function exchange_for_oauth_token($code) {
	$config = static::get_config();
	
	$url       = 'https://github.com/login/oauth/access_token';
	$options   = array(
		'body' => array( // 'json' || 'body' || 'query'
			'client_id'     => $config['client_id'],
			'client_secret' => $config['client_secret'],
			'code'          => $code,
		),
		'headers' => array(
			'Accept' => 'application/json',
		),
	);
	
	$http = new \GuzzleHttp\Client();
	$response = $http->post($url, $options)->json();
	
	if (empty($response['access_token'])) {
		throw new exception('can not get oauth token for temporary code');
	}
	
	return array(
		'oauth_token' => $response['access_token'],
		'scope'       => $response['scope'],
	);
}

/**
 * checks whether the received scope meets the requested scope
 * 
 * @param  string  $received_scope
 * @param  string  $requested_scope defaults to 'user:email'
 * @return boolean
 */
public static function verify_scope($received_scope, $requested_scope='user:email') {
	if (strpos($received_scope, $requested_scope) !== false) {
		return true;
	}
	
	if (strpos($requested_scope, ':')) {
		$parent_requested_scope = substr($requested_scope, 0, strpos($requested_scope, ':'));
		if (strpos($received_scope, $parent_requested_scope) !== false) {
			return true;
		}
	}
	
	return false;
}

/**
 * gets a users github account details for a given oauth token
 * 
 * @param  string $oauth_token
 * @return array               can be expected to contain a 'login' key with the github username
 */
public static function get_user_info($oauth_token) {
	$url     = 'https://api.github.com/user';
	$options = array(
		'headers' => array(
			'Accept' => 'application/json',
			'Authorization' => 'token '.$oauth_token,
		),
	);
	
	$http = new \GuzzleHttp\Client();
	return $http->get($url, $options)->json();
}

/**
 * checks whether the username belongs to the user identified by the oauth token
 * 
 * @param  string    $oauth_token     as received by the oauth process
 * @param  strign    $github_username as received by ::get_user_info()
 * @return boolean                    returns true if there is no login for this token ..
 *                                    .. or the usernames match
 *                                    never returns false
 * @throws exception                  if the usernames don't match
 */
public static function verify_user($oauth_token, $github_username) {
	$known_login = static::get_by_oauth_token($oauth_token);
	if (empty($known_login)) {
		return true;
	}
	
	if ($known_login->github_username != $github_username) {
		throw new exception('oauth token switched user');
	}
	
	return true;
}

}
