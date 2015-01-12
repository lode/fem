<?php

namespace projectname;

/**
 * find composer
 */
require '../vendor/autoload.php';

/**
 * bootstraps the fem framework
 * 
 * if you want to use your own bootstrapping ..
 * .. remove `\fem\` from the routing line ..
 * .. and start with the bootstrap found in example-project
 */
new \fem\bootstrap();
