<?php

namespace alsvanzelf\fem;

class email {

private static $config;
private static $mailer;

/**
 * connects to the database, defined by ::load_config()
 * makes sure we have a strict and unicode aware connection
 * 
 * @param  array $config optional, containing 'host', 'port', 'ssl', 'user', 'pass', 'from', 'name' values
 * @return void
 */
public static function login($config=null) {
	if (empty(self::$config) || $config) {
		self::load_config($config);
	}
	
	$transport = new \Swift_SmtpTransport(self::$config['host'], self::$config['port'], self::$config['ssl']);
	$transport->setUsername(self::$config['user']);
	$transport->setPassword(self::$config['pass']);
	
	self::$mailer = new \Swift_Mailer($transport);
}

/**
 * sends an email directly
 * 
 * @todo allow for html emails as well
 * 
 * @param  mixed  $recipient string or [email => name]
 * @param  string $subject
 * @param  string $message   plain text only for now
 * @param  array  $options   array with optional 'cc', 'bcc', 'reply_to', 'attachment' options
 *                           'attachment' is expected to be an array with 'path' and 'mime' keys
 * @return void
 */
public static function send($recipient, $subject, $body, $options=[]) {
	if (empty(self::$mailer)) {
		self::login();
	}
	
	if (ENVIRONMENT != 'production') {
		$recipient = self::protect_emailaddress($recipient);
		if (isset($options['cc'])) {
			$options['cc'] = self::protect_emailaddress($options['cc']);
		}
		if (isset($options['bcc'])) {
			$options['bcc'] = self::protect_emailaddress($options['bcc']);
		}
		if (isset($options['reply_to'])) {
			$options['reply_to'] = self::protect_emailaddress($options['reply_to']);
		}
		
		$subject = '['.ENVIRONMENT.'] '.$subject;
	}
	
	$sender = [self::$config['from'] => self::$config['name']];
	
	$message = new \Swift_Message();
	$message->setFrom($sender);
	$message->setTo($recipient);
	$message->setSubject($subject);
	$message->setBody($body);
	
	if (isset($options['cc'])) {
		$message->setCc($options['cc']);
	}
	if (isset($options['bcc'])) {
		$message->setBcc($options['bcc']);
	}
	if (isset($options['reply_to'])) {
		$message->setReplyTo($options['reply_to']);
	}
	if (isset($options['attachment'])) {
		$attachment = \Swift_Attachment::fromPath($options['attachment']['path'], $options['attachment']['mime']);
		$message->attach($attachment);
	}
	
	self::$mailer->send($message);
}

/**
 * check email addresses validity
 * 
 * @param  string $emailaddress
 * @return boolean
 */
public static function validate($emailaddress) {
	try {
		$message = new \Swift_Message();
		$message->setTo($emailaddress);
	}
	catch (\Swift_RfcComplianceException $e) {
		return false;
	}
	
	return true;
}

/**
 * protect email addresses from going to real people on non-production environments
 * 
 * it returns the website's sender address with the original one as '+alias'
 * 
 * @param  string $emailaddress     i.e. user@company.com
 * @param  string $catchall_address i.e. development@project.com
 * @return string                   i.e. development+user_company_com@project.com
 */
public static function protect_emailaddress($emailaddress, $catchall_address=null) {
	if (empty($catchall_address)) {
		if (empty(self::$config)) {
			self::load_config();
		}
		
		$catchall_address = self::$config['from'];
	}
	
	$recipient_name = null;
	if (is_array($emailaddress)) {
		$recipient_name = current($emailaddress);
		$emailaddress   = key($emailaddress);
	}
	
	if (self::validate($emailaddress) == false) {
		$exception = bootstrap::get_library('exception');
		throw new $exception('can not convert invalid email address');
	}
	
	
	$emailaddress_key = preg_replace('{[^a-zA-Z0-9_]}', '_', $emailaddress);
	$emailaddress = str_replace('@', '+'.$emailaddress_key.'@', $catchall_address);
	
	if ($recipient_name) {
		$emailaddress = array($emailaddress => $recipient_name);
	}
	
	return $emailaddress;
}

/**
 * collects the config for connecting from a ini file
 * 
 * @note the password is expected to be in a base64 encoded format
 *       to help against shoulder surfing
 * 
 * @note sets self::$config with 'host', 'port', 'ssl', 'user', 'pass', 'from', 'name' values
 */
protected static function load_config($config=null) {
	if ($config) {
		self::$config = $config;
		return;
	}
	
	$config_file = ROOT_DIR.'config/email.ini';
	if (file_exists($config_file) == false) {
		$exception = bootstrap::get_library('exception');
		throw new $exception('no email config found');
	}
	
	self::$config = parse_ini_file($config_file);
	
	// decode the password
	self::$config['pass'] = base64_decode(self::$config['pass']);
}

}
