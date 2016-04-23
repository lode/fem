<?php

use alsvanzelf\fem;

require __DIR__.'/../vendor/autoload.php';
new fem\bootstrap();
fem\mysql::connect();

$dump_path = fem\ROOT_DIR.'build/dumps';
fem\build::database_dump_structure($dump_path);
