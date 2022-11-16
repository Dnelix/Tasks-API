<?php

try{
    //connect to the $readDB to perform this query since it's a read request
    $query = $readDB -> prepare ('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbl_tasks WHERE id = :taskid AND userid = :userid');            
    $query -> bindParam(':taskid', $taskid, PDO::PARAM_INT); //putting a variable into SQL. We do this because we used a dynamic value (:taskid) in the statement
    $query -> bindParam(':userid', $ret_userid, PDO::PARAM_INT); // the user ID obtained from the authentication query (to ensure users can only see their own tasks)
    $query -> execute(); //execute query as defined

    $rowCount = $query->rowCount();

    if($rowCount === 0){
        responseGeneric(404, false, 'Task not Found');
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

    //create a success response and set the retrived array as data. Also cache the response for faster reloading within 60secs
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
    responseServerException($e, "Failed to get task");
}

?>