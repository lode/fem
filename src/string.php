<?php

namespace alsvanzelf\fem;

class string {

/**
 * generates cryptographically strong tokens
 * 
 * @param  int $length of the returned string
 * @return string
 */
public static function generate_token($length) {
	$byte_length = ($length / 2);
	$new_token = bin2hex(openssl_random_pseudo_bytes($byte_length, $strong_enough));
	if ($strong_enough == false || empty($new_token)) {
		throw new \Exception('can not generate cryptographically strong enough token');
	}
	
	return $new_token;
}

}
