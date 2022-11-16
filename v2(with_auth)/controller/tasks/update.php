<?php

try{

    // first we throw error if the data is not coming in JSON format
    if(isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json'){
        responseGeneric(400, false, "Content type header is not set to JSON");
    }

    // else retrieve the parsed data
    $rawPatchData = file_get_contents('php://input'); // get request body

    //we will then use json_decode to convert the body to JSON format and store in a variable.
    //generate an error if the json conversion returns false
    if(!$jsonData = json_decode($rawPatchData)){
        responseGeneric(400, false, "Invalid JSON data in request body");
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
        responseGeneric(400, false, 'You have not updated any records');
    }

    //use the parsed task ID to retrieve the record from the db
    $query = $readDB -> prepare ('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbl_tasks WHERE id = :taskid');            
    $query -> bindParam(':taskid', $taskid, PDO::PARAM_INT);
    $query -> execute();

    //confirm if data exists
    $rowCount = $query -> rowCount();
    if($rowCount === 0){
        responseGeneric(404, false, 'Record not found. Update failed!');
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
        responseGeneric(400, false, 'Task not updated');
    }

    //return the newly updated record
    $query = $writeDB -> prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H/%i") as deadline, completed FROM tbl_tasks WHERE id = :taskid');
    $query -> bindParam(':taskid', $taskid, PDO::PARAM_INT);
    $query -> execute();

    $rowCount = $query -> rowCount();
    if($rowCount === 0){
        responseGeneric(404, false, 'No task found after update');
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

    responseSuccessWithData(200, $returnData, "Task Updated");
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
    responseServerException($e, 'Failed to update task. Check for errors');
}

?>