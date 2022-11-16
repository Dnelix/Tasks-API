<?php
require_once('DBconnect.php');
require_once('../model/Response.php');
require_once('_functions.php');
require_once('_constants.php');

//connect to DB
require_once('db/connect_write_db.php'); // we only use the master (write) DB for authentication checks

// necessary endpoints:
// /sessions -> To create a session or login (POST)
// /sessions/id -> To delete a session or logout (DELETE) given id
// /sessions/id -> To update or refresh a session (PATCH) given id

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    require_once('sessions/create_session.php');
}

// else if there's a specified ID, go ahead and check if it is a PATCH or DELETE request and perform operations accordingly
else if(array_key_exists('sessionid', $_GET)){

    $sessionid = $_GET['sessionid'];

    //ensure id is valid
    if($sessionid === '' || !is_numeric($sessionid)){
        $response = new Response();
        $response -> setHttpStatusCode(400); //Invalid
        $response -> setSuccess(false);
        (($sessionid == '') ? $response -> addMessage('Session ID cannot be empty'): false);
        (!is_numeric($sessionid) ? $response -> addMessage('Session ID must be an integer'): false);
        $response -> send();
        exit(); 
    }
    
    //validate authorization
    if(!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1){
        $response = new Response();
        $response -> setHttpStatusCode(401); //unauthorized
        $response -> setSuccess(false);
        (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response -> addMessage('Access token is missing from the header'): false);
        ((strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) ? $response -> addMessage('Access token cannot be blank'): false);
        $response -> send();
        exit(); 
    }
        
    //get and store the access token
    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    // if delete    
    if($_SERVER['REQUEST_METHOD'] === 'DELETE'){
        require_once('sessions/delete_session.php');
    }

    // if update
    else if($_SERVER['REQUEST_METHOD'] === 'PATCH'){
        require_once('sessions/update_session.php');
    }

    //else invalid
    else{
        responseGeneric(405, false, 'Invalid session request');
    }
}

//else invalid request
else{
    responseGeneric(405, false, 'Invalid request method');
}