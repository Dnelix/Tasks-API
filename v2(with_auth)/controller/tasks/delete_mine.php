<?php

    try{
        //connect to the $writeDB to perform this query since it's a write request
        $query = $writeDB -> prepare ('DELETE FROM tbl_tasks WHERE id = :taskid AND userid = :userid LIMIT 1');            
        $query -> bindParam(':taskid', $taskid, PDO::PARAM_INT); 
        $query -> bindParam(':userid', $ret_userid, PDO::PARAM_INT); //from auth
        $query -> execute(); 

        $rowCount = $query->rowCount();

        if($rowCount === 0){
            responseGeneric(404, false, 'Task not Found');
        }

        //else it is successful
        responseGeneric(200, true, "Task deleted successfully!");

    }
    catch (PDOException $e){
        responseServerException($e, "Failed to delete task");
    }

?>