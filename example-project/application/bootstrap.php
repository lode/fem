<?php

namespace projectname;
use alsvanzelf\fem;

class bootstrap extends fem\bootstrap {

public function __construct() {
	parent::__construct();
	
	// own bootstrap logic
	
	// let fem call your own page library when needed
	fem\bootstrap::set_custom_library('page', '\\projectname\\page');
}

}
