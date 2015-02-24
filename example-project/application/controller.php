<?php

/**
 * login logic
 */

// login controller
$login = fem\login_password::get_by_email($_POST['email_address']);
if (empty($login) || $login->is_valid($_POST['password']) == false) {
	// error
}
fem\session::create();
fem\session::set_user_id($login->get_user_id());
fem\request::redirect('');

// login check
fem\session::keep();
fem\session::force_loggedin();
$user = new user(fem\session::get_user_id());

// logout controller
fem\session::keep();
fem\session::destroy();
fem\request::redirect('');
