<?php
require_once('DBconnect.php');
require_once('../model/response.php');
require_once('_functions.php');

//connect to DB
require_once('db/connect_write_read_db.php'); // we only use the master (write) DB for authentication checks

//neccessary endpoints:
// /users -> to create a user (POST) && to list all users (GET)
// /users/id -> to list a user detail (GET) && delete a user (DELETE) && update a user detail (PATCH)

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once('users/create_user.php');
}

else if($_SERVER['REQUEST_METHOD'] === 'GET') {
    //check for userid and utilize for more specific actions
    if(array_key_exists('userid', $_GET)){
        $userid = $_GET['userid'];

        //validations
        if($userid == '' || !is_numeric($userid)){
            responseGeneric(400, false, 'User ID not allowed');
        }

        //Get a user detail given id
        require_once('users/get_user_record.php');

        //delete a user given id

        //update a user detail given id
    }

    //else get all users
    if (empty($_GET)){
        require_once("users/list_users.php");
    }

    responseGeneric(401, false, 'Your request cannot be understood');
}

else {
    responseGeneric(405, false, 'Invalid Request Method!');
}

?>