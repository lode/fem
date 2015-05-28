<?php

namespace alsvanzelf\fem;

class exception extends \Exception {

private $userMessage;
private $userAction;

/**
 * creates a new exception, adding a human-friendly user message to the arguments
 * 
 * @param string  $message
 * @param integer $code
 * @param object  $previous
 * @param string  $userMessage
 */
public function __construct($message='', $code=0, $previous=null, $userMessage=null) {
	parent::__construct($message, $code, $previous);
	
	$this->setUserMessage($userMessage);
}

/**
 * use fem\page to render the exception in a nice(r) way
 * 
 * @return string
 */
public function __toString() {
	try {
		$page = bootstrap::get_library('page');
		
		$page = new $page;
		$page->exception($this);
	}
	catch (\Exception $e) {
		echo $e;
	}
	
	return 'Sorry, something went wrong.';
}

/**
 * set a human-friendly user message
 * 
 * @param string $userMessage
 */
public function setUserMessage($userMessage) {
	$this->userMessage = $userMessage;
}

/**
 * get the human-friendly user message
 * 
 * @return string
 */
public function getUserMessage() {
	return $this->userMessage;
}

/**
 * set a user facing link as continue action
 * 
 * @param string $userAction
 */
public function setUserAction($userAction) {
	$this->userAction = $userAction;
}

/**
 * get the user facing link as continue action
 * 
 * @return string
 */
public function getUserAction() {
	return $this->userAction;
}

}
