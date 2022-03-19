<?php

// need Response file before we can test
require_once 'Response.php';

$response = new Response();

// the test
$response->setSuccess(true);
$response->setHttpStatusCode(400);
$response->addMessage('test message 1');
$response->addMessage('test message 2');
//$response->setData('here is your data');

// return the response using the send method
// there is no data, but check if there is any error in the code
$response->send();


//// test 2
//$response->setHttpStatusCode(500);
//
//// return the response using the send method
//// there is no data, but check if there is any error in the code
//$response->send();
