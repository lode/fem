<?php

namespace alsvanzelf\fem;

class page {

protected $data = array();

public function __construct($title) {
	$this->data['title'] = $title;
}

public function show($template, $data=array()) {
	if (empty($data['_'])) {
		$data['_'] = $this->data;
	}
	
	$mustache = bootstrap::get_library('mustache');
	echo $mustache::render($template, $data);
}

}
