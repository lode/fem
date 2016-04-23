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
		$exception = bootstrap::get_library('exception');
		throw new $exception('can not generate cryptographically strong enough token');
	}
	
	return $new_token;
}

/**
 * escape data for usage in html
 * 
 * @param  string $data utf-8 encoded
 * @return string       the same data, escaped for html
 */
public static function escape($data) {
	return htmlspecialchars($data, ENT_QUOTES, 'UTF-8', $double=false);
}

/**
 * normalize non ascii characters
 * 
 * @note depends on the LC_CTYPE locale (see setlocale)
 * 
 * @param  string $data utf-8 encoded
 * @return string
 */
public static function normalize($data) {
	if (function_exists('normalizer_normalize')) {
		return normalizer_normalize($data);
	}
	
	return iconv('UTF-8', 'ASCII//TRANSLIT', $data);
}

/**
 * slugify strings for usage in urls
 * - normalizes and lowers the string
 * - replaces non-ascii chars with a dash (-)
 * - some chars (i.e. quotes) are plain removed
 * 
 * @note uses ::normalize, see notes there
 * 
 * @param  string $data utf-8 encoded
 * @return string
 */
public static function slugify($data) {
	// flatten the data
	$data = self::normalize($data);
	$data = mb_strtolower($data);
	
	// remove certain chars
	$data = str_replace(array('"', "'"), '', $data);
	
	// replace most chars
	$data = preg_replace('/[^a-z0-9]/', '-', $data);
	
	// cleanup
	$data = trim($data, '-');
	
	return $data;
}

/**
 * beautifies code words into plain language
 * - capitalizes the string
 * - changes some chars into spaces (i.e. underscore and dash)
 * 
 * @note does not create slugs and is quite dumb
 *       use own methods for something more sophisticated
 * 
 * @param  string $data
 * @return string
 */
public static function beautify($data) {
	$data = str_replace(array('_', '-'), ' ', $data);
	$data = ucfirst($data);
	
	return $data;
}

}
