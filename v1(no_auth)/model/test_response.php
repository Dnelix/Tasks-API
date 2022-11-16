<?php

require_once('Response.php');

$response = new Response(); //initialize the class

//manually set some of the parameters
$response -> setSuccess(true);
$response -> setHttpStatusCode(200);
$response -> addMessage('This is working');
$response -> addMessage('Page will be found');
$response -> setData("data is life");

//send using the send function
$response -> send();

