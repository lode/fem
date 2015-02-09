<?php

namespace projectname;

/**
 * find composer
 */
require '../vendor/autoload.php';

/**
 * bootstraps the fem framework
 * 
 * if you want to use your own routing, remove `\alsvanzelf\fem\` from the routing line
 */
new \alsvanzelf\fem\bootstrap();
new \alsvanzelf\fem\routing();
