<?php

    //check if the content is JSON
    if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        responseGeneric(400, false, 'Content type header not set to JSON');
    }

    //else get the json data
    $rawPostData = file_get_contents('php://input');

    if(!$jsonData = json_decode($rawPostData)){
        responseGeneric(400, false, 'Invalid JSON data in request body');
    }

    //then perform basic checks on data
    //1. ensure all mandatory fields are provided
    if(!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)){
        $response = new Response();

        $response -> setHttpStatusCode(400);
        $response -> setSuccess(false);

        (!isset($jsonData->fullname) ? $response -> addMessage('Full name not provided') : false);
        (!isset($jsonData->username) ? $response -> addMessage('Username not provided') : false);
        (!isset($jsonData->password) ? $response -> addMessage('Password not provided') : false);

        $response -> send();
        exit(); 
    }

    //2. check if the strings are empty or have values above the DB limits
    if(strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname) > 255 || strlen($jsonData->username) < 1 || strlen($jsonData->username) > 50 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255){
        $response = new Response();

        $response -> setHttpStatusCode(400);
        $response -> setSuccess(false);

        (strlen($jsonData->fullname) < 1 ? $response -> addMessage('Full name cannot be blank') : false);
        (strlen($jsonData->fullname) > 255 ? $response -> addMessage('Full name cannot be greater than 255 characters') : false);
        (strlen($jsonData->username) < 1 ? $response -> addMessage('Username cannot be blank') : false);
        (strlen($jsonData->username) > 50 ? $response -> addMessage('Username cannot be greater than 50 characters') : false);
        (strlen($jsonData->password) < 1 ? $response -> addMessage('Password cannot be blank') : false);
        (strlen($jsonData->password) > 255 ? $response -> addMessage('Password cannot be greater than 255 characters') : false);

        $response -> send();
        exit(); 
    }

    //3. Collate data and strip off white spaces
    $fullname = trim($jsonData->fullname); //trim automatically removes extra leading or preceeding white space
    $username = trim($jsonData->username);
    $password = $jsonData->password; // don't trim passwords
    $createdon = date('d/m/Y H:i');

    //4. Check if user already exists
    try{

        $query = $writeDB -> prepare('SELECT id FROM tbl_users WHERE username = :username');
        $query -> bindParam(':username', $username, PDO::PARAM_STR);
        $query -> execute();

        $rowCount = $query -> rowCount();

        if($rowCount !== 0){
            responseGeneric(409, false, 'Username already exists');
        }

        // Hash Password
        $hash_pass = password_hash($password, PASSWORD_DEFAULT); //hash using the standard PHP hashing

        //Insert record into table
        $query = $writeDB -> prepare('INSERT INTO tbl_users (fullname, username, password, createdon) VALUES(:fullname, :username, :password, STR_TO_DATE(:createdon, \'%d/%m/%Y %H:%i\'))');
        $query -> bindParam(':fullname', $fullname, PDO::PARAM_STR);
        $query -> bindParam(':username', $username, PDO::PARAM_STR);
        $query -> bindParam(':password', $hash_pass, PDO::PARAM_STR);
        $query -> bindParam(':createdon', $createdon, PDO::PARAM_STR);
        $query -> execute();

        $rowCount = $query -> rowCount();

        if($rowCount === 0) {
            responseGeneric(500, false, 'Unable to create user data in DB');
        }

        //else return the newly created user details (w/o password)
        $lastID = $writeDB->lastInsertId();

        $returnData = array();
        $returnData['user_id'] = $lastID;
        $returnData['fullname'] = $fullname;
        $returnData['username'] = $username;
        $returnData['password'] = $hash_pass;
        $returnData['createdon'] = $createdon;

        responseSuccessWithData(201, $returnData, 'User Created');

    }
    catch (PDOException $e){
        responseServerException($e, 'An error occurred while creating user account. Please try again');
    }



?>