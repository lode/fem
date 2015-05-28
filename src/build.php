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

}
