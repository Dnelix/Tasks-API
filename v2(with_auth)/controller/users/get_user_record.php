<?php

try{
    //connect to the $readDB to perform this query since it's a read request
    $query = $readDB -> prepare ('SELECT id, fullname, username, password, active, DATE_FORMAT(createdon, "%d/%m/%Y %H:%i") as createdon, loginattempts, DATE_FORMAT(lastlogin, "%d/%m/%Y %H:%i") as lastlogin FROM tbl_users WHERE id = :userid LIMIT 1');            
    $query -> bindParam(':userid', $userid, PDO::PARAM_INT);
    $query -> execute();

    $rowCount = $query->rowCount();

    if($rowCount === 0){
        responseGeneric(404, false, 'User not Found');
    }
    //else
    $userinfo = [];
    while($row = $query -> fetch(PDO::FETCH_ASSOC)) {
        /*$userinfo["id"] = $row['id'];
        $userinfo["fullname"] = $row['fullname'];
        $userinfo["username"] = $row['username'];
        $userinfo["active"] = $row['active'];
        $userinfo["createdon"] = $row['createdon'];
        $userinfo["loginattempts"] = $row['loginattempts'];
        $userinfo["lastlogin"] = $row['lastlogin'];*/

        $userinfo = [
            'id' => $row['id'],
            'fullname' => $row['fullname'],
            'username' => $row['username'],
            'active' => $row['active'],
            'createdon' => $row['createdon'],
            'loginattempts' => $row['loginattempts'],
            'lastlogin' => $row['lastlogin']
        ];
    }

    //return data in an array
    $returnData = array();
    $returnData['rows_returned'] = $rowCount;
    $returnData['user_details'] = $userinfo;

    //create a success response and set the retrived array as data. Also cache the response for faster reloading within 60secs
    responseSuccessWithCaching($returnData);

}
catch (PDOException $e){
    responseServerException($e, "There was an error with fetching users");
}

?>