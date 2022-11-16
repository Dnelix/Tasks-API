<?php
require_once('DBconnect.php');
require_once('../model/Task.php');
require_once('../model/Response.php');
require_once('_functions.php');

//connect to DB
require_once('db/connect_write_read_db.php'); // we only use the master (write) DB for authentication checks

//check authorization
require_once('sessions/authenticate.php'); //script to authenticate user access token before performing the action

// Perform operations (GET, UPDATE, DELETE) on a single task with it's id. URL eg: tasks?taskid=1
//using pretty urls we can access this by .../tasks/id

if(array_key_exists("taskid", $_GET)) {
    $taskid = $_GET['taskid'];

    //show error if taskID is invalid
    if($taskid == '' || !is_numeric($taskid)) {
        responseGeneric(400, false, 'Task ID is invalid');
    }

    //1. to retrieve (updated to use authentication. Only see tasks associated with logged in user)
    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        require_once('tasks/retrieve_record.php');
    }

    //2. to delete
    else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        //require_once('tasks/delete.php');
        require_once('tasks/delete_mine.php'); //uses auth
    }

    //3. to update
    else if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        //require_once('tasks/update.php');
        require_once('tasks/update_mine.php'); //uses auth
    }

    //else it's an invalid request
    else {
        responseGeneric(405, false, 'Request method is not allowed or invalid');
    }
}

// load all completed tasks on visiting of the url .../tasks/complete
// and load all incompleted tasks on visiting of the url .../tasks/incomplete
else if(array_key_exists("completed", $_GET)){
    //require_once('tasks/get_by_status.php');
    require_once('tasks/get_mine_by_status.php');
}

// Create paginations with URL eg:tasks?page=1 (pretty version will be tasks/page/1)
// We will build the program to return just 20 responses per page
else if(array_key_exists("page", $_GET)){
    require_once('tasks/pagination.php');
}

//operate on the all-tasks table if no key is specified (retrieve all or create new)
//url endpoint will be /tasks
else if (empty($_GET)) {

    // you can retrieve all records from the table
    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        //require_once('tasks/list_all.php');
        require_once('tasks/list_all_mine.php');
    }

    // or post record(s) into the table
    else if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        //require_once('tasks/create.php');
        require_once('tasks/create_mine.php');
    }

    else {
        responseServerException($e, 'Request method is not allowed or invalid');
    }

}

// Any other endpoint provided should be invalid
else {
    responseGeneric(404, false, 'Endpoint not found');
}
?>