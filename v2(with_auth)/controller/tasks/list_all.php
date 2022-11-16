<?php

try{

    $query = $readDB -> prepare ('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbl_tasks');
    $query -> execute();

    $rowCount = $query->rowCount();

    if($rowCount === 0){
        responseGeneric(404, false, 'No tasks found');
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
    responseServerException($e, "Failed to get tasks: ");
}

?>