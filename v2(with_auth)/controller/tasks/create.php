<?php

try{

    // We will need to capture the request body in JSON format
    // something like { "title":"MY title", "description":"My desc"}

    // first we throw error if the data is not coming in JSON format
    if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json'){
        responseGeneric(400, false, "Content type header is not set to JSON");
    }

    // else retrieve the parsed data
    $rawPostData = file_get_contents('php://input'); // get request body

    //we will then use json_decode to convert the body to JSON format and store in a variable.
    //generate an error if the json conversion returns false
    if(!$jsonData = json_decode($rawPostData)){
        responseGeneric(400, false, 'Invalid JSON data in request body');
    }

    // else search the converted json data to ensure that mandatory fields are provided
    if (!isset($jsonData->title) || !isset($jsonData->completed) || !isset($jsonData->userid)) {
        $response = new Response();
        $response -> setHttpStatusCode(400);
        $response -> setSuccess(false);
        (!isset($jsonData->title)) ? $response->addMessage('Title must be provided') : false;
        (!isset($jsonData->completed)) ? $response->addMessage('Completion status must be defined') : false;
        (!isset($jsonData->userid)) ? $response->addMessage('Every task must be assigned to a user. Specify user id') : false;
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

    $userid = $jsonData -> userid;

    //extract and store in variables for easy usage
    $title = $newTask -> getTitle();
    $description = $newTask -> getDesc();
    $deadline = $newTask -> getDeadline();
    $completed = $newTask -> getCompleted();

    //query the write DB to store the data
    $query = $writeDB -> prepare('INSERT INTO tbl_tasks (userid, title, description, deadline, completed) VALUES (:userid, :title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed)');
    //bind
    $query -> bindParam(':userid', $userid, PDO::PARAM_INT);
    $query -> bindParam(':title', $title, PDO::PARAM_STR);
    $query -> bindParam(':description', $description, PDO::PARAM_STR);
    $query -> bindParam(':deadline', $deadline, PDO::PARAM_STR);
    $query -> bindParam(':completed', $completed, PDO::PARAM_STR);
    //execute
    $query -> execute();
    
    //check returned rows
    $rowCount = $query->rowCount();

    if($rowCount===0) {
        responseGeneric(500, false, "Failed to create record");
    }

    //else return the newly created data back
    //using the id of the last inserted task
    $lastID = $writeDB->lastInsertId();

    $query = $writeDB->prepare('SELECT id, userid, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbl_tasks WHERE id= :taskid');
    $query -> bindParam(':taskid', $lastID, PDO::PARAM_INT);
    $query -> execute();

    $rowCount = $query->rowCount();

    if($rowCount===0) {
        responseGeneric(500, false, "Failed to retrieve created record");
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
    $returnData['userid'] = $userid;
    $returnData['tasks'] = $taskArray;

    responseSuccessWithData(201, $returnData, 'Task created!');

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
    responseServerException($e, "Failed to insert tasks into DB");
}

?>