<?php

namespace alsvanzelf\fem;

class build {

/**
 * dumps create table statements per table in the specified path
 * 
 * @param  string $dump_directory directory to be filled with tablename.sql files
 * @return void
 */
public static function database_dump_structure($dump_directory) {
	if (is_dir($dump_directory) == false) {
		$exception = bootstrap::get_library('exception');
		throw new $exception('directory not found');
	}
	
	$mysql = bootstrap::get_library('mysql');
	
	$tables = $mysql::select($mysql::AS_ARRAY, "SHOW TABLES;");
	foreach ($tables as $table) {
		$table_name = current($table);
		
		$drop = "DROP TABLE IF EXISTS `".$table_name."`;";
		$dump = $mysql::select($mysql::AS_ROW, "SHOW CREATE TABLE `%s`;", $table_name);
		
		// structure should be w/o references to data
		$dump = $dump['Create Table'];
		$dump = preg_replace('{ AUTO_INCREMENT=[0-9]+}i', '', $dump);
		
		$full_dump = $drop."\n\n".$dump.";\n";
		file_put_contents($dump_directory.'/'.$table_name.'.sql', $full_dump);
	}
}

/**
 * checks composer packages for new releases since the installed version
 * 
 * @todo check if the required version is significantly off
 *       which would mean the json needs to change to be able to update
 * 
 * @return array {
 *         @var $required
 *         @var $installed
 *         @var $possible
 * }
 */
public static function check_composer_updates() {
	$composer_json  = file_get_contents(ROOT_DIR.'/composer.json');
	$composer_json = json_decode($composer_json, true);
	if (empty($composer_json['require'])) {
		$exception = bootstrap::get_library('exception');
		throw new $exception('there are no required packages to check');
	}
	
	$composer_lock = file_get_contents(ROOT_DIR.'/composer.lock');
	$composer_lock = json_decode($composer_lock, true);
	if (empty($composer_lock['packages'])) {
		$exception = bootstrap::get_library('exception');
		throw new $exception('lock file is missing its packages');
	}
	
	$composer_executable = 'composer';
	if (file_exists(ROOT_DIR.'composer.phar')) {
		$composer_executable = 'php composer.phar';
	}
	
	$required_packages  = $composer_json['require'];
	$installed_packages = $composer_lock['packages'];
	$update_packages    = array();
	
	foreach ($installed_packages as $installed_package) {
		$package_name      = $installed_package['name'];
		$installed_version = preg_replace('/v([0-9].*)/', '$1', $installed_package['version']);
		$version_regex     = '/versions\s*:.+v?([0-9]+\.[0-9]+(\.[0-9]+)?)(,|$)/U';
		
		// skip dependencies of dependencies
		if (empty($required_packages[$package_name])) {
			continue;
		}
		
		// check commit hash for dev-* versions
		if (strpos($installed_version, 'dev-') === 0) {
			$installed_version = $installed_package['source']['reference'];
			$version_regex     = '/source\s*:.+ ([a-f0-9]{40})$/m';
		}
		
		// find out the newest release
		$package_info = shell_exec($composer_executable.' show -a '.escapeshellarg($package_name));
		preg_match($version_regex, $package_info, $possible_version);
		if (empty($possible_version)) {
			$exception = bootstrap::get_library('exception');
			throw new $exception('can not find out newest release for '.$package_name);
		}
		
		if (ENVIRONMENT == 'development') {
			echo 'installed '.$package_name.' at '.$installed_version.', possible version is '.$possible_version[1].PHP_EOL;
		}
		
		if ($possible_version[1] == $installed_version) {
			continue;
		}
		
		$update_packages[$package_name] = array(
			'required'  => $required_packages[$package_name],
			'installed' => $installed_version,
			'possible'  => $possible_version[1],
		);
	}
	
	return $update_packages;
}

}
