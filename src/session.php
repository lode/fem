<?php

namespace alsvanzelf\fem;

/**
 * improved session handling
 * 
 * use this class' methods instead of native session functions for:
 * - session_start()         => session::start($type)
 *                           => session::create($type)
 *                           => session::keep($type)
 * - session_destroy()       => session::destroy()
 * - session_regenerate_id() => session::regenerate_id()
 * - session_status()        => session::is_active()
 * 
 * $type is one of ::TYPE_TEMPORARY or ::TYPE_CONTINUOUS
 * 
 * use ::create($type) and ::keep($type) instead of ::start() ..
 * .. for more clearly defined behavior of starting sessions ..
 * .. as ::keep() only starts a session when one already exists
 * 
 * most methods can throw an exception when a session isn't started yet
 * 
 * @note the vhost must have `UseCanonicalName on` for session cookies to work
 * 
 * @todo prevent key conflicts with something like ::set(__NAMESPACE__, ...)
 */
class session {

/**
 * the place to redirect users to when forced to login
 */
protected static $login_url = 'login';

/**
 * call for extra validation for user sessions
 * if the call returns false, the session will be destroyed
 */
protected static $validation_callback = null;

/**
 * different session types
 * use continuous ones for logged in users ..
 * .. and temporary ones for anonymous visitor tracking
 * 
 * methods requesting the type as an optional argument ..
 * .. will default to continuous, except when noted otherwise
 * @todo  remove the last note here
 */
const TYPE_TEMPORARY  = 'temporary';
const TYPE_CONTINUOUS = 'continuous';

private static $type_durations = array(
	'temporary'  => 3600,    // 1 hour
	'continuous' => 2626260, // 1 month
);

/**
 * intervals at which to change the session id or dump expired sessions
 * @see ::regenerate_id()
 * @see ::is_valid()
 */
const INTERVAL_REFRESH = 1800; // half an hour
const TIMESPAN_EXPIRE  = 30;   // half a minute

/**
 * base name for cookies
 * this will be postfixed by the type (i.e. + '-temporary')
 */
const COOKIE_NAME_PREFIX = 'fem-session';

/**
 * create a new session, destroying any current session
 * use this when you want to *start* tracking a user
 * 
 * @param  string $type one of the ::TYPE_* consts
 *                      optional, defaults to ::TYPE_CONTINUOUS
 * @return void
 */
public static function create($type=null) {
	$type = self::check_type($type);
	
	self::destroy($type);
	self::start($type);
}

/**
 * keep a current session active
 * use this when you want to start using a session in the current request
 * 
 * @note this does *not* create a new session if one doesn't already exist
 * @see  ::start() if you always want to create a new one anyway
 * 
 * @param  string $type one of the ::TYPE_* consts
 *                      optional, defaults to ::TYPE_CONTINUOUS
 * @return void
 */
public static function keep($type=null) {
	$type = self::check_type($type);
	
	if (self::cookie_exists($type) == false) {
		self::start($type);
	}
}

/**
 * wrapper for session_status() which returns a boolean
 * 
 * @return boolean
 */
public static function is_active() {
	if (session_status() != PHP_SESSION_ACTIVE) {
		return false;
	}
	if (empty($_SESSION['_session_type']) || empty($_SESSION['_session_last_active'])) {
		return false;
	}
	
	return true;
}

/**
 * wrapper for the native session_start() with added security measures:
 * - sets the cookie arguments
 * - prevents session fixation
 * - validates sessions
 * - regenerates ids on an interval
 * 
 * @param  string $type one of the ::TYPE_* consts
 *                      optional, defaults to ::TYPE_CONTINUOUS
 * @return void
 */
public static function start($type=null) {
	$type = self::check_type($type);
	
	$cookie = self::get_cookie_settings($type);
	session_set_cookie_params($cookie['duration'], $cookie['domain'], $cookie['path'], $cookie['secure'], $cookie['http_only']);
	session_name($cookie['name']);
	
	session_start();
	header('Cache-Control: private; no-cache="Set-Cookie"');
	
	if (empty($_SESSION['_session_type'])) {
		// prevent session fixation
		session_regenerate_id();
		$_SESSION['_session_type'] = $type;
	}
	else {
		if (self::is_valid() == false) {
			self::destroy();
			return;
		}
		
		self::regenerate_id($interval_based=true);
	}
	
	$_SESSION['_session_fingerprint'] = \alsvanzelf\fem\request::get_fingerprint();
	$_SESSION['_session_last_active'] = time();
}

/**
 * wrapper for the native session_destroy()
 * if no active session is found, it will delete session cookies of all possible types
 * 
 * @return void
 */
public static function destroy() {
	session_destroy();
	$_SESSION = array();
	
	self::destroy_cookie();
}

/**
 * wrapper for the native session_regenerate_id()
 * 
 * when $interval_based is set to true ..
 * .. regeneration only happens at certain intervals of a sessions lifetime ..
 * .. to keep a small attack window
 * 
 * the old session is marked to expire instead of deleted at once ..
 * .. this to allow running ajax requests to continue to use the old session ..
 * @see ::TIMESPAN_EXPIRE and ::is_valid()
 * 
 * @param  boolean $interval_based
 * @return void
 */
public static function regenerate_id($interval_based=false) {
	if (self::is_active() == false) {
		throw new Exception('inactive session');
	}
	
	$fresh_enough = ($_SESSION['_session_last_active'] > (time() - self::INTERVAL_REFRESH));
	if ($fresh_enough && $interval_based) {
		return;
	}
	if (!empty($_SESSION['_session_expire_at'])) {
		return;
	}
	
	// delay deleting the old session so ajax calls can continue
	$_SESSION['_session_expire_at'] = (time() + self::TIMESPAN_EXPIRE);
	session_write_close();
	
	session_regenerate_id();
	
	// we're in the new session now, which copied all old data
	unset($_SESSION['_session_expire_at']);
}

/**
 * gets the user id connected to the session
 * 
 * @return int|boolean
 */
public static function get_user_id() {
	if (self::is_active() == false) {
		return false;
	}
	if (empty($_SESSION['_session_user_id'])) {
		return false;
	}
	
	return $_SESSION['_session_user_id'];
}

/**
 * connects a user to the current session
 * once a user is connected, ::is_valid() will validate it
 * 
 * @param int $user_id
 */
public static function set_user_id($user_id) {
	if (self::is_active() == false) {
		throw new Exception('inactive session');
	}
	
	$_SESSION['_session_user_id'] = $user_id;
}

/**
 * checks whether the current user is logged in
 * i.e. whether the session has a user attached via ::set_user_id()
 * 
 * @return boolean
 */
public static function is_loggedin() {
	if (self::is_active() == false) {
		return false;
	}
	if (empty(self::get_user_id())) {
		return false;
	}
	
	return true;
}

/**
 * redirects the user to the login page when it is not logged in
 * @see ::is_loggedin() for the check
 * @see ::$login_url for the redirection
 * 
 * @return boolean|void returns true when loggedin, redirects otherwise
 */
public static function force_loggedin() {
	if (self::is_loggedin() == false) {
		\alsvanzelf\fem\request::redirect(self::$login_url);
	}
	
	return true;
}

/**
 * wrapper for all kinds of validation methods
 * @see ::validate(), ::challenge() and user::validate_session()
 * 
 * @return boolean
 */
private static function is_valid() {
	if (self::is_active() == false) {
		throw new Exception('inactive session');
	}
	
	if (self::validate() == false) {
		return false;
	}
	if (self::challenge() == false) {
		return false;
	}
	
	if (self::$validation_callback) {
		if (self::$validation_callback() == false) {
			return false;
		}
	}
	
	return true;
}

/**
 * checks whether the session is still valid to continue on
 * this mainly checks the duration, and delayed deletions
 * 
 * @return boolean
 */
private static function validate() {
	if (self::is_active() == false) {
		throw new Exception('inactive session');
	}
	
	if (empty($_SESSION['_session_type']) || empty($_SESSION['_session_last_active'])) {
		return false;
	}
	
	// throw away expired (replaced) sessions
	if (!empty($_SESSION['_session_expire_at']) && $_SESSION['_session_expire_at'] < time()) {
		return false;
	}
	
	// last_active short enough ago
	$type_duration = self::$type_durations[ $_SESSION['_session_type'] ];
	$active_enough = ($_SESSION['_session_last_active'] > (time() - $type_duration));
	if ($active_enough == false) {
		return false;
	}
	
	return true;
}

/**
 * challenge the session's fingerprint
 * this is a balanced check for similar user-agent / ip-address data
 * a like a spam filter, the challenge only fails when the score is too high
 * 
 * @return boolean false meaning the session should not be trusted
 */
private static function challenge() {
	if (self::is_active() == false) {
		throw new Exception('inactive session');
	}
	
	if (empty($_SESSION['_session_fingerprint'])) {
		throw new Exception('cannot challenge a fresh session');
	}
	
	$old_fingerprint = $_SESSION['_session_fingerprint'];
	$new_fingerprint = \alsvanzelf\fem\request::get_fingerprint();
	
	$score = self::calculate_fingerprint_score($old_fingerprint, $new_fingerprint);
	if ($score >= 1.5) {
		return false;
	}
	
	return true;
}

/**
 * calculates a weighed difference between old and new challenge data
 * challenge data is produced by \alsvanzelf\fem\request::get_fingerprint()
 * 
 * a score somewhere between 1 and 3 should be the threshold for turning bad
 * when a score of 100 is returned, it should be treated as definitely bad
 * 
 * @param  array $previous_data from the previous request
 * @param  array $current_data  fresh from this request
 * @return float
 */
private static function calculate_fingerprint_score($old_fingerprint, $new_fingerprint) {
	$score = 0;
	foreach ($new_fingerprint as $key => $new_value) {
		if (isset($old_fingerprint[$key]) == false) {
			// tampered data
			$score = 100;
			break;
		}
		
		$old_value = $old_fingerprint[$key];
		if (empty($old_value) && empty($new_value)) {
			continue;
		}
		
		similar_text($old_value, $new_value, $similarity);
		if ($similarity < 70) {
			$score += 1.5;
		}
		if ($similarity < 80) {
			$score += 1;
		}
		if ($similarity < 90) {
			$score += 0.5;
		}
	}
	
	return $score;
}

/**
 * returns the same type as the input, except when:
 * - null, it returns the default ::TYPE_CONTINUOUS
 * - not one of the allowed types, it throws an exception
 * 
 * @param  string $type one of the ::TYPE_* consts
 * @return string|exception
 */
private static function check_type($type=null) {
	if (is_null($type)) {
		return self::TYPE_CONTINUOUS;
	}
	
	if (isset(self::$type_durations[$type]) == false) {
		throw new Exception('unknown session type');
	}
	
	return $type;
}

/**
 * generates a key for the session cookie
 * 
 * @param  string $type one of the ::TYPE_* consts
 *                      defaults to the type of the current session
 * @return string       a concatenation of the base (see ::COOKIE_NAME_PREFIX) and the type
 *                      i.e. prefix + '-temporary'
 */
private static function get_cookie_name($type=null) {
	if (is_null($type)) {
		$type = $_SESSION['_session_type'];
	}
	
	return self::COOKIE_NAME_PREFIX.'-'.$type;
}

/**
 * returns all keys needed for session cookie management
 * 
 * @param  string $type one of the ::TYPE_* consts
 * @return array        keys 'name', 'duration', 'domain', 'path', 'secure', 'http_only'
 */
private static function get_cookie_settings($type) {
	$name      = self::get_cookie_name($type);
	$duration  = (time() + self::$type_durations[$type]);
	$domain    = $_SERVER['SERVER_NAME'];
	$path      = '/';
	$secure    = (bool)$_SERVER['HTTPS'];
	$http_only = true;
	
	return array(
		'name'      => $name,
		'duration'  => $duration,
		'domain'    => $domain,
		'path'      => $path,
		'secure'    => $secure,
		'http_only' => $http_only,
	);
}

/**
 * checks whether an existing session could be started
 * 
 * @param  string $type one of the ::TYPE_* consts
 * @return boolean
 */
private static function cookie_exists($type) {
	$cookie_name = self::get_cookie_name($type);
	
	return (!empty($_COOKIE[$cookie_name]));
}

/**
 * throws away the session cookie
 * 
 * @param  string $type one of the ::TYPE_* consts
 *                      if null, defaults to removing cookies for all possible types
 * @return void
 */
private static function destroy_cookie($type=null) {
	if (is_null($type)) {
		foreach (self::$type_durations as $type => $null) {
			self::destroy_cookie($type);
		}
		return;
	}
	
	$params = self::get_cookie_settings($type);
	$params['duration'] = -(86400); // one day ago
	
	setcookie($params['name'], $params['duration'], $params['domain'], $params['path'], $params['secure'], $params['http_only']);
	
	unset($_COOKIE[ $params['name'] ]);
}

}
