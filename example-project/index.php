<?php

namespace projectname;

/**
 * find composer
 */
require '../vendor/autoload.php';

/**
 * bootstraps the fem framework
 * 
 * if you want to use your own routing, remove `\fem\` from the routing line
 */
new \fem\bootstrap();
new \fem\routing();
