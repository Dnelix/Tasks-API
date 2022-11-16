<?php
require_once('DBconnect.php');
require_once('../model/Task.php');
require_once('../model/Response.php');

//connect to DB
try{
    $writeDB = DBconnect::connectWriteDB();
    $readDB = DBconnect::connectReadDB();
}
catch (PDOException $e){
    error_log("Connection error - ".$e, 0);
    
    $response = new Response();

    $response -> setHttpStatusCode(500);
    $response -> setSuccess(false);
    $response -> addMessage('Database connection error');
    $response -> addMessage($e);
    $response -> send();
    exit(); 
}

// Perform operations (GET, UPDATE, DELETE) on a single task with it's id. URL eg: tasks?taskid=1
//using pretty urls we can access this by .../tasks/id
if(array_key_exists("taskid", $_GET)) {
    $taskid = $_GET['taskid'];

    //show error if taskID is invalid
    if($taskid == '' || !is_numeric($taskid)) {
        $response = new Response();
        $response -> setHttpStatusCode(400);
        $response -> setSuccess(false);
        $response -> addMessage('Task ID is invalid');
        $response -> send();
        exit();
    }

    //1. to retrieve
    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        try{
            //connect to the $readDB to perform this query since it's a read request
            $query = $readDB -> prepare ('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbl_tasks WHERE id = :taskid');            
            $query -> bindParam(':taskid', $taskid, PDO::PARAM_INT); //putting a variable into SQL. We do this because we used a dynamic value (:taskid) in the statement
            $query -> execute(); //execute query as defined

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();
                $response -> setHttpStatusCode(404);
                $response -> setSuccess(false);
                $response -> addMessage('Task not Found');
                $response -> addMessage('Row Count: '.$query->rowCount());
                $response -> send();
                exit();
            }
            //else
            while($row = $query -> fetch(PDO::FETCH_ASSOC)) {
                $task = new Task(
                    $row['id'], 
                    $row['title'], 
                    $row['description'],
                    $row['deadline'],
                    $row['completed'],
                );
                $taskArray[] = $task -> returnTaskAsArray();

            }
            //return data in an array
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            //create a success response and set the retrived array as data
            $response = new Response();
            $response -> setHttpStatusCode(200);
            $response -> setSuccess(true);
            $response -> toCache(true);
            $response -> setData($returnData);
            $response -> send();
            exit();

        }
        catch (TaskException $e){
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage($e -> getMessage());
            $response -> send();
            exit();
        }
        catch (PDOException $e){
            error_log("Database query error - ".$e, 0);
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage("Failed to get task: ".$e);
            $response -> send();
            exit();
        }
    }

    //2. to delete
    else if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        
        try{
            //connect to the $writeDB to perform this query since it's a write request
            $query = $writeDB -> prepare ('DELETE FROM tbl_tasks WHERE id = :taskid LIMIT 1');            
            $query -> bindParam(':taskid', $taskid, PDO::PARAM_INT); 
            $query -> execute(); 

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();
                $response -> setHttpStatusCode(404);
                $response -> setSuccess(false);
                $response -> addMessage('Task not Found');
                $response -> addMessage('Row Count: '.$query->rowCount());
                $response -> send();
                exit();
            }

            //else it is successful
            $response = new Response();
            $response -> setHttpStatusCode(200);
            $response -> setSuccess(true);
            $response -> addMessage("Task deleted successfully!");
            $response -> send();
            exit();

        }
        catch (PDOException $e){
            error_log("Database query error - ".$e, 0);
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage("Failed to delete task: ".$e);
            $response -> send();
            exit();
        }
    }

    //3. to update
    else if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        
        try{

            // first we throw error if the data is not coming in JSON format
            if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json'){
                $response = new Response();
                $response -> setHttpStatusCode(400);
                $response -> setSuccess(false);
                $response -> addMessage("Content type header is not set to JSON");
                $response -> send();
                exit;
            }

            // else retrieve the parsed data
            $rawPatchData = file_get_contents('php://input'); // get request body

            //we will then use json_decode to convert the body to JSON format and store in a variable.
            //generate an error if the json conversion returns false
            if(!$jsonData = json_decode($rawPatchData)){
                $response = new Response();
                $response -> setHttpStatusCode(400);
                $response -> setSuccess(false);
                $response -> addMessage("Invalid JSON data in request body");
                $response -> send();
                exit;
            }

            //keep track of all fields in the db_table that can potentially be updated
            $upd_title = false;
            $upd_description = false;
            $upd_deadline = false;
            $upd_completed = false;

            //create an empty string that will form part of your sql and dynamically hold fields that should be updated
            $queryFields = "";

            //then we write the sql statement to import only the fields that have been provided in the JSON data into the query string. Also note the comma and space that will be appended to the string. It's part of the SQL code flow. 
            if(isset($jsonData->title)){ // if title exists in the decoded json
                $upd_title = true; //change the status of the field to true
                $queryFields .= "title = :title, "; //append to queryFields string
            }
            if(isset($jsonData->description)){
                $upd_description = true;
                $queryFields .= "description = :description, ";
            }
            if(isset($jsonData->deadline)){
                $upd_deadline = true;
                $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
            }
            if(isset($jsonData->completed)){
                $upd_completed = true;
                $queryFields .= "completed = :completed, ";
            }

            //remove the last (right most) comma and space from the queryfields string (you can do this manually of course but only if you're always sure what the last field to be updated will be)
            $queryFields = rtrim($queryFields, ", "); //rightmost trim

            //check that at least one variable have been updated to true
            if($upd_title===false && $upd_description===false && $upd_deadline===false && $upd_completed===false){
                $response = new Response();
                $response -> setHttpStatusCode(400);
                $response -> setSuccess(false);
                $response -> addMessage('You have not updated any records');
                $response -> send();
                exit;
            }

            //use the parsed task ID to retrieve the record from the db
            $query = $readDB -> prepare ('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbl_tasks WHERE id = :taskid');            
            $query -> bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query -> execute();

            //confirm if data exists
            $rowCount = $query -> rowCount();
            if($rowCount === 0){
                $response = new Response();
                $response -> setHttpStatusCode(404);
                $response -> setSuccess(false);
                $response -> addMessage('Record not found. Update failed!');
                $response -> send();
                exit;
            }

            //else
            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task(
                    $row['id'], 
                    $row['title'], 
                    $row['description'],
                    $row['deadline'],
                    $row['completed'],
                );
                //$taskArray[] = $task -> returnTaskAsArray();
            }

            //write out the query string. Recall that query fields is part of this, so concatenate it
            $queryString = "UPDATE tbl_tasks SET ".$queryFields." WHERE id = :taskid";
            $query = $writeDB -> prepare($queryString);
            $query -> bindParam(':taskid', $taskid, PDO::PARAM_INT);

            // check for the ones that have been updated. Do some formatting on the data (if you wish) and bind to the query
            if($upd_title === true){
                $task -> setTitle($jsonData -> title);
                $nw_title = $task->getTitle();
                $query -> bindParam(':title', $nw_title, PDO::PARAM_STR);
            }
            if($upd_description === true){
                $task -> setDesc($jsonData -> description);
                $nw_desc = $task->getDesc();
                $query -> bindParam(':description', $nw_desc, PDO::PARAM_STR);
            }
            if($upd_deadline === true){
                $task -> setDeadline($jsonData -> deadline);
                $nw_deadline = $task->getDeadline();
                $query -> bindParam(':deadline', $nw_deadline, PDO::PARAM_STR);
            }
            if($upd_completed === true){
                $task -> setCompleted($jsonData -> completed);
                $nw_completed = $task->getCompleted();
                $query -> bindParam(':completed', $nw_completed, PDO::PARAM_STR);
            }

            //execute
            $query -> execute();

            //check
            $rowCount = $query->rowCount();
            if($rowCount === 0){
                $response = new Response();
                $response -> setHttpStatusCode(400);
                $response -> setSuccess(false);
                $response -> addMessage('Task not updated: '.$e);
                $response -> send();
                exit;
            }

            //return the newly updated record
            $query = $writeDB -> prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H/%i") as deadline, completed FROM tbl_tasks WHERE id = :taskid');
            $query -> bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query -> execute();

            $rowCount = $query -> rowCount();
            if($rowCount === 0){
                $response = new Response();
                $response -> setHttpStatusCode(404);
                $response -> setSuccess(false);
                $response -> addMessage('No task found after update');
                $response -> send();
                exit;
            }

            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task(
                    $row['id'], 
                    $row['title'], 
                    $row['description'],
                    $row['deadline'],
                    $row['completed'],
                );
                $taskArray[] = $task -> returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response -> setHttpStatusCode(200);
            $response -> setSuccess(true);
            $response -> addMessage("Task Updated");
            $response -> setData($returnData);
            $response -> send();
            exit;
        }
        catch (TaskException $e){
            $response = new Response();
            $response -> setHttpStatusCode(400);
            $response -> setSuccess(false);
            $response -> addMessage($e->getMessage());
            $response -> send();
            exit;
        }
        catch (PDOException $e){
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage('Failed to update task. Check for errors: '.$e);
            $response -> send();
            exit;
        }
    }
    //else it's an invalid request
    else {
        $response = new Response();
        $response -> setHttpStatusCode(405);
        $response -> setSuccess(false);
        $response -> addMessage('Request method is not allowed or invalid');
        $response -> send();
        exit();
    }
}

// load all completed tasks on visiting of the url .../tasks/complete
// and load all incompleted tasks on visiting of the url .../tasks/incomplete
else if(array_key_exists("completed", $_GET)){

    $cStatus = $_GET['completed'];

    if($cStatus !== "Y" && $cStatus !== "N") {
        $response = new Response();
        $response -> setHttpStatusCode(400);
        $response -> setSuccess(false);
        $response -> addMessage('Request is invalid');
        $response -> send();
        exit();
    }

    if($_SERVER['REQUEST_METHOD'] === 'GET') {

        try{

            $query = $readDB -> prepare ('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbl_tasks WHERE completed = :cStatus');            
            $query -> bindParam(':cStatus', $cStatus, PDO::PARAM_STR);
            $query -> execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();
                $response -> setHttpStatusCode(404);
                $response -> setSuccess(false);
                if ($cStatus == "Y"){
                    $response -> addMessage('No completed task found');
                } else {
                    $response -> addMessage('No incomplete tasks found');
                }
                $response -> send();
                exit();
            }
            
            //else
            $taskArray = array(); //initialize the task array that will hold the data

            while($row = $query -> fetch(PDO::FETCH_ASSOC)) {
                $task = new Task(
                    $row['id'], 
                    $row['title'], 
                    $row['description'],
                    $row['deadline'],
                    $row['completed'],
                );
                $taskArray[] = $task -> returnTaskAsArray();

            }
            //return data in an array
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            //create a success response and set the retrived array as data
            $response = new Response();
            $response -> setHttpStatusCode(200);
            $response -> setSuccess(true);
            $response -> toCache(true); // allow caching for this response to reduce load on server
            $response -> setData($returnData);
            $response -> send();
            exit();

        } 
        catch (TaskException $e){
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage($e -> getMessage());
            $response -> send();
            exit();
        }
        catch (PDOException $e){
            error_log("Database query error - ".$e, 0);
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage("Failed to get tasks: ".$e);
            $response -> send();
            exit();
        }

    } else {

        $response = new Response();
        $response -> setHttpStatusCode(405);
        $response -> setSuccess(false);
        $response -> addMessage('Request method is not allowed or invalid');
        $response -> send();
        exit();

    }

}

// Create paginations with URL eg:tasks?page=1 (pretty version will be tasks/page/1)
// We will build the program to return just 20 responses per page
else if(array_key_exists("page", $_GET)){

    if($_SERVER['REQUEST_METHOD'] === 'GET') {
        
        $page = $_GET['page'];
        if($page == '' || !is_numeric($page)) {
            $response = new Response();
            $response -> setHttpStatusCode(400);
            $response -> setSuccess(false);
            $response -> addMessage('Page number cannot be blank and must be numeric');
            $response -> send();
            exit();
        }

        // define limit of records per page
        $pageLimit = 2;
        
        try{
            
            $query = $readDB -> prepare ('SELECT count(id) AS totalTasks FROM tbl_tasks');
            $query -> execute();

            $row = $query -> fetch(PDO::FETCH_ASSOC);

            $totalTasks = intval($row['totalTasks']); //convert the count recieved from query to integer

            // to get total number of pages that the records will span, we divide total returned count by pagelimit.
            $totalPages = ceil($totalTasks/$pageLimit); //ceil() makes sure it always rounds to the next whole number.

            // determine when there's a next and previous page
            ($page < $totalPages) ? $hasNextPage = true : $hasNextPage = false;
            ($page > 1) ? $hasPrevPage = true : $hasPrevPage = false;
             

            if($totalPages === 0){
                $totalPages = 1; //a workaround to ensure that we always have at least one page.

                $response = new Response();
                $response -> setHttpStatusCode(404);
                $response -> setSuccess(false);
                $response -> addMessage('No tasks found');
                $response -> addMessage('Row Count: '.$query->rowCount());
                $response -> send();
                exit;
            }

            if($page > $totalPages){ //if requested page number is greater than the total page we have
                $response = new Response();
                $response -> setHttpStatusCode(404);
                $response -> setSuccess(false);
                $response -> addMessage('Page not Found');
                $response -> addMessage('Row Count: '.$query->rowCount());
                $response -> send();
                exit;
            }

            // create offsets to determine which number the records for a page will start from
            $offset = ($page == 1) ? 0 : ($pageLimit * ($page -1));

            // Query DB with these dynamic values
            $query = $readDB -> prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbl_tasks LIMIT :pglimit OFFSET :offset');
            // bind the dynamic variables
            $query -> bindParam(':pglimit', $pageLimit, PDO::PARAM_INT);
            $query -> bindParam(':offset', $offset, PDO::PARAM_INT);

            $query -> execute();

            $rowCount = $query->rowCount();

            $taskArray = array();

            while($row = $query -> fetch(PDO::FETCH_ASSOC)) {
                $task = new Task(
                    $row['id'], 
                    $row['title'], 
                    $row['description'],
                    $row['deadline'],
                    $row['completed'],
                );
                $taskArray[] = $task -> returnTaskAsArray();

            }
            //return data in an array with other information
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_tasks'] = $totalTasks;
            $returnData['total_pages'] = $totalPages;
            $returnData['has_next_page'] = $hasNextPage;
            $returnData['has_prev_page'] = $hasPrevPage;
            $returnData['tasks'] = $taskArray;

            //create a success response and set the retrived array as data
            $response = new Response();
            $response -> setHttpStatusCode(200);
            $response -> setSuccess(true);
            $response -> toCache(true);
            $response -> setData($returnData);
            $response -> send();
            exit();

        }
        catch (TaskException $e){
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage($e -> getMessage());
            $response -> send();
            exit();
        }
        catch (PDOException $e){
            error_log("Database query error - ".$e, 0);
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage("Failed to get task: ".$e);
            $response -> send();
            exit();
        }
    }

    else {
        $response = new Response();
        $response -> setHttpStatusCode(405);
        $response -> setSuccess(false);
        $response -> addMessage('Request method is not allowed or invalid');
        $response -> send();
        exit();
    }

}

//operate on the all-tasks table if no key is specified (retrieve all or create new)
//url endpoint will be /tasks
else if (empty($_GET)) {

    // you can retrieve all records from the table
    if($_SERVER['REQUEST_METHOD'] === 'GET') {

        try{

            $query = $readDB -> prepare ('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbl_tasks');
            $query -> execute();

            $rowCount = $query->rowCount();

            if($rowCount === 0){
                $response = new Response();
                $response -> setHttpStatusCode(404);
                $response -> setSuccess(false);
                $response -> addMessage('No tasks found');
                $response -> send();
                exit();
            }
            
            //else
            $taskArray = array(); //initialize the task array that will hold the data

            while($row = $query -> fetch(PDO::FETCH_ASSOC)) {
                $task = new Task(
                    $row['id'], 
                    $row['title'], 
                    $row['description'],
                    $row['deadline'],
                    $row['completed'],
                );
                $taskArray[] = $task -> returnTaskAsArray();

            }
            //return data in an array
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            //create a success response and set the retrived array as data
            $response = new Response();
            $response -> setHttpStatusCode(200);
            $response -> setSuccess(true);
            $response -> toCache(true); // allow caching for this response to reduce load on server
            $response -> setData($returnData);
            $response -> send();
            exit();

        } 
        catch (TaskException $e){
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage($e -> getMessage());
            $response -> send();
            exit();
        }
        catch (PDOException $e){
            error_log("Database query error - ".$e, 0);
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage("Failed to get tasks: ".$e);
            $response -> send();
            exit();
        }

    }

    // or post record(s) into the table
    else if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        try{

            // We will need to capture the request body in JSON format
            // something like { "title":"MY title", "description":"My desc"}

            // first we throw error if the data is not coming in JSON format
            if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json'){
                $response = new Response();
                $response -> setHttpStatusCode(400);
                $response -> setSuccess(false);
                $response -> addMessage("Content type header is not set to JSON");
                $response -> send();
                exit;
            }

            // else retrieve the parsed data
            $rawPostData = file_get_contents('php://input'); // get request body

            //we will then use json_decode to convert the body to JSON format and store in a variable.
            //generate an error if the json conversion returns false
            if(!$jsonData = json_decode($rawPostData)){
                $response = new Response();
                $response -> setHttpStatusCode(400);
                $response -> setSuccess(false);
                $response -> addMessage("Invalid JSON data in request body");
                $response -> send();
                exit;
            }

            // else search the converted json data to ensure that mandatory fields are provided
            if (!isset($jsonData->title) || !isset($jsonData->completed)) {
                $response = new Response();
                $response -> setHttpStatusCode(400);
                $response -> setSuccess(false);
                (!isset($jsonData->title)) ? $response->addMessage('Title must be provided') : false;
                (!isset($jsonData->completed)) ? $response->addMessage('Completion status must be defined') : false;
                $response -> send();
                exit;
            }

            //else create a new task with the data
            $newTask = new Task(
                null,
                $jsonData -> title,
                (isset($jsonData->description) ? $jsonData->description : null),
                (isset($jsonData->deadline) ? $jsonData->deadline : null),
                $jsonData -> completed
            );

            //extract and store in variables for easy usage
            $title = $newTask -> getTitle();
            $description = $newTask -> getDesc();
            $deadline = $newTask -> getDeadline();
            $completed = $newTask -> getCompleted();

            //query the write DB to store the data
            $query = $writeDB -> prepare('INSERT INTO tbl_tasks (title, description, deadline, completed) VALUES (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed)');
            //bind
            $query -> bindParam(':title', $title, PDO::PARAM_STR);
            $query -> bindParam(':description', $description, PDO::PARAM_STR);
            $query -> bindParam(':deadline', $deadline, PDO::PARAM_STR);
            $query -> bindParam(':completed', $completed, PDO::PARAM_STR);
            //execute
            $query -> execute();
            
            //check returned rows
            $rowCount = $query->rowCount();

            if($rowCount===0) {
                $response = new Response();
                $response -> setHttpStatusCode(500);
                $response -> setSuccess(false);
                $response -> addMessage("Failed to create record");
                $response -> send();
                exit;
            }

            //else return the newly created data back
            //using the id of the last inserted task
            $lastID = $writeDB->lastInsertId();

            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbl_tasks WHERE id= :taskid');
            $query -> bindParam(':taskid', $lastID, PDO::PARAM_INT);
            $query -> execute();

            $rowCount = $query->rowCount();

            if($rowCount===0) {
                $response = new Response();
                $response -> setHttpStatusCode(500);
                $response -> setSuccess(false);
                $response -> addMessage("Failed to retrieve created record");
                $response -> send();
                exit;
            }

            //else
            while($row = $query->fetch(PDO::FETCH_ASSOC)){
                $task = new Task(
                    $row['id'],
                    $row['title'],
                    $row['description'],
                    $row['deadline'],
                    $row['completed']
                );

                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage('Task created!');
            $response->setData($returnData);
            $response->send();
            exit;

        }
        catch (TaskException $e){
            $response = new Response();
            $response -> setHttpStatusCode(400);
            $response -> setSuccess(false);
            $response -> addMessage($e -> getMessage());
            $response -> send();
            exit();
        }
        catch (PDOException $e){
            error_log("Database query error - ".$e, 0);
            $response = new Response();
            $response -> setHttpStatusCode(500);
            $response -> setSuccess(false);
            $response -> addMessage("Failed to insert tasks into DB: ".$e);
            $response -> send();
            exit();
        }
    }

    else {

        $response = new Response();
        $response -> setHttpStatusCode(405);
        $response -> setSuccess(false);
        $response -> addMessage('Request method is not allowed or invalid');
        $response -> send();
        exit();

    }

} 

// Any other endpoint provided should be invalid
else {

    $response = new Response();
    $response -> setHttpStatusCode(404);
    $response -> setSuccess(false);
    $response -> addMessage('Endpoint not found');
    $response -> send();
    exit;

}
?>