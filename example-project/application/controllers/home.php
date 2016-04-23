<?php

namespace projectname;
use alsvanzelf\fem;

$template_data = array(
	'planet' => 'World',
);

$page = new fem\page('Example project');
$page->show('home', $template_data);
