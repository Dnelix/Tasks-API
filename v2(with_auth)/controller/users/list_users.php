<?php

try{

    $query = $writeDB -> prepare ('SELECT id, fullname, username, active, DATE_FORMAT(createdon, "%d/%m/%Y %H:%i") as createdon, loginattempts, DATE_FORMAT(lastlogin, "%d/%m/%Y %H:%i") as lastlogin FROM tbl_users');
    $query -> execute();

    $rowCount = $query->rowCount();

    if($rowCount === 0){
        responseGeneric(404, false, 'No users found');
    }
    
    //else
    //$userArray = array(); //initialize the task array that will hold the data

    while($row = $query -> fetch(PDO::FETCH_ASSOC)) {
        $userArray[$row['username']] = [
            "id" => $row['id'], 
            "fullname" => $row['fullname'], 
            "username" => $row['username'], 
            "active" => $row['active'], 
            "createdon" => $row['createdon'],
            "loginattempts" => $row['loginattempts'],
            "lastlogin" => $row['lastlogin']
        ];
    }
    //return data in an array
    $returnData = array();
    $returnData['rows_returned'] = $rowCount;
    $returnData['users_list'] = $userArray;

    //create a success response and set the retrived array as data
    $response = new Response();
    $response -> setHttpStatusCode(200);
    $response -> setSuccess(true);
    $response -> toCache(true); // allow caching for this response to reduce load on server
    $response -> setData($returnData);
    $response -> send();
    exit();

}
catch (PDOException $e){
    responseServerException($e, 'Failed to get users');
}

?>