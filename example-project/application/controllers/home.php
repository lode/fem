<?php

namespace projectname;
use alsvanzelf\fem;

$template_data = [
	'planet' => 'World',
];

$page = new fem\page('Example project');
$page->show('home', $template_data);
