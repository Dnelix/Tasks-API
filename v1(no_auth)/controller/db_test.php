<?php

require_once('DBconnect.php');
require_once('../model/Response.php');

try {
    $writeDB = DBconnect::connectWriteDB();
    $readDB = DBconnect::connectReadDB();
} 
catch (PDOException $err) {
    $response = new Response();

    $response -> setHttpStatusCode(500);
    $response -> setSuccess(false);
    $response -> addMessage('Database connection error');
    $response -> addMessage($err);
    $response -> send();
    exit(); //don't continue the script if there is an error with connection
}