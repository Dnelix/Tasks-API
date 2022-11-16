<?php

//RESPONSES
    // 500 - server error
    // 400 - invalid data
    // 401 - unauthorized
    // 404 - not found
    // 405 - invalid request
    // 409 - data conflict
    // 200 - success
    // 201 - record created


function responseGeneric($code, $state, $message){
    $response = new Response();
    $response -> setHttpStatusCode($code);
    $response -> setSuccess($state);
    (isset($message) ? $response -> addMessage($message) : false);
    $response -> send();
    exit(); 
}

function responseServerException($e, $message){
    error_log("Connection error - ".$e, 0);

    $response = new Response();
    $response -> setHttpStatusCode(500);
    $response -> setSuccess(false);
    $response -> addMessage($message);
    $response -> addMessage($e); //optional. Remove for prod.
    $response -> send();
    exit(); 
}

function responseSuccessWithData($code, $returnData, $message){
    $response = new Response();
    $response -> setHttpStatusCode($code);
    $response -> setSuccess(true);
    $response -> addMessage($message);
    $response -> setData($returnData);
    $response -> send();
    exit(); 
}

function responseWithData($code, $state, $returnData, $message){
    $response = new Response();
    $response -> setHttpStatusCode($code);
    (isset($state) ? $response -> setSuccess(true) : $response -> setSuccess(false));
    (isset($message) ? $response -> addMessage($message) : false);
    (isset($returnData) ? $response -> setData($returnData) : false);
    $response -> send();
    exit(); 
}

function responseSuccessWithCaching($returnData){
    $response = new Response();
    $response -> setHttpStatusCode(200);
    $response -> setSuccess(true);
    $response -> toCache(true);
    $response -> setData($returnData);
    $response -> send();
    exit();
}

?>