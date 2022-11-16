<?php

$cStatus = $_GET['completed'];

if($cStatus !== "Y" && $cStatus !== "N") {
    responseGeneric(400, false, 'Request is invalid');
}

if($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responseGeneric(405, false, 'Request method is not allowed or invalid');
}

try{

    $query = $readDB -> prepare ('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbl_tasks WHERE completed = :cStatus AND userid = :userid');            
    $query -> bindParam(':cStatus', $cStatus, PDO::PARAM_STR);
    $query -> bindParam(':userid', $ret_userid, PDO::PARAM_STR);
    $query -> execute();

    $rowCount = $query->rowCount();

    if($rowCount === 0){
        $response = new Response();
        $response -> setHttpStatusCode(404);
        $response -> setSuccess(false);
        (($cStatus == "Y") ? $response -> addMessage('No completed task found') : $response -> addMessage('No incomplete tasks found'));
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

    //create a success response and set the retrived array as data. Allow caching for this response to reduce load on server
    responseSuccessWithCaching($returnData);

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
    responseServerException($e, "Failed to retrieve tasks");
}


?>