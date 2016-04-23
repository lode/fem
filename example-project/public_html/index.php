<?php

/**
 * find composer
 */
require __DIR__.'/../vendor/autoload.php';

/**
 * bootstraps the fem framework
 */
new \alsvanzelf\fem\bootstrap();

/**
 * ignite
 * 
 * to use your own routing replace `\alsvanzelf\fem\` with `\projectname\`
 */
new \alsvanzelf\fem\routing();
