<?php

namespace alsvanzelf\fem;

class page {

protected static $renderer = '\alsvanzelf\fem\mustache';

protected $data = array();

public function __construct($title) {
	$this->data['title'] = $title;
}

public function show($template, $data=array()) {
	if (empty($data['_'])) {
		$data['_'] = $this->data;
	}
	
	$renderer = static::$renderer;
	echo $renderer::render($template, $data);
}

}
