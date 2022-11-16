<?php

require_once('Task.php');

try{

    $task = new Task(
        1, 
        "This is the Task", 
        "I describe it here... etc", 
        "01/02/2022 12:00", 
        "N"
    );
    
    header('Content-type: application/json; charset=UTF-8');

    echo json_encode($task->returnTaskAsArray());

}
catch (TaskException $err){
    echo "ERROR: ".$err->getMessage();
}
?>