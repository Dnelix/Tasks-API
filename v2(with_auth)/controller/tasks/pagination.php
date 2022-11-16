<?php

if($_SERVER['REQUEST_METHOD'] !== 'GET') {
    responseGeneric(405, false, 'Request method is not allowed or invalid');
}
   
$page = $_GET['page'];
if($page == '' || !is_numeric($page)) {
    responseGeneric(400, false, 'Page number cannot be blank and must be numeric');
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

        responseGeneric(404, false, 'No tasks found');
    }

    if($page > $totalPages){ //if requested page number is greater than the total page we have
        responseGeneric(404, false, 'Page not found');
    }

    // create offsets to determine which number the records for a page will start from
    $offset = ($page == 1) ? 0 : ($pageLimit * ($page -1));

    // Query DB with these dynamic values
    $query = $readDB -> prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbl_tasks LIMIT :pglimit OFFSET :offset');
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

    //create a success response and set the retrived array as data. Cache.
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
    responseServerException($e, "Failed to get tasks");
}

?>