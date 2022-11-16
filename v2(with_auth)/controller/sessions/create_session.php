<?php
    // a safe action is to delay a session request by 1 sec to reduce hacking attempts
    sleep(1); //delay by 1 sec

    //check if the content is JSON
    if($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        responseGeneric(400, false, 'Content type header not set to JSON');
    }

    //else get the json data
    $rawPostData = file_get_contents('php://input');

    if(!$jsonData = json_decode($rawPostData)){
        responseGeneric(400, false, 'Invalid JSON data in request body');
    }

    //data validation checks. We are only sending username and password to create a session
    if(!isset($jsonData->username) || !isset($jsonData->password)){
        
        $response = new Response();
        $response -> setHttpStatusCode(400);
        $response -> setSuccess(false);
        (!isset($jsonData->username) ? $response -> addMessage('Username not set') : false);
        (!isset($jsonData->password) ? $response -> addMessage('Password not set') : false);
        $response -> send();
        exit(); 
    }

    //2. check if the strings are empty or have values above the DB limits
    if(strlen($jsonData->username) < 1 || strlen($jsonData->username) > 50 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255){
        
        $response = new Response();
        $response -> setHttpStatusCode(400);
        $response -> setSuccess(false);
        (strlen($jsonData->username) < 1 ? $response -> addMessage('Username cannot be blank') : false);
        (strlen($jsonData->username) > 50 ? $response -> addMessage('Username cannot be greater than 50 characters') : false);
        (strlen($jsonData->password) < 1 ? $response -> addMessage('Password cannot be blank') : false);
        (strlen($jsonData->password) > 255 ? $response -> addMessage('Password cannot be greater than 255 characters') : false);
        $response -> send();
        exit(); 
    }

    //3. Check if user exists
    try{

        $username = $jsonData->username;
        $password = $jsonData->password;
        $all = 'id,fullname,username,password,active,createdon,loginattempts,lastlogin';

        $query = $writeDB -> prepare('SELECT '. $all .' FROM tbl_users WHERE username = :username');
        $query -> bindParam(':username', $username, PDO::PARAM_STR);
        $query -> execute();

        $rowCount = $query -> rowCount();

        if($rowCount === 0 || $rowCount > 1){
            responseGeneric(401, false, 'User not found or invalid!');
        }
        
        //no need for while statement since it's only going to be a single record
        $row = $query -> fetch(PDO::FETCH_ASSOC);

        $ret_id = $row['id'];
        $ret_fullname = $row['fullname'];
        $ret_username = $row['username'];
        $ret_password = $row['password'];
        $ret_active = $row['active'];
        $ret_createdon = $row['createdon'];
        $ret_loginattempts = $row['loginattempts'];
        $ret_lastlogin = $row['lastlogin'];

        // Hash Password
        $hash_pass = password_hash($password, PASSWORD_DEFAULT); //hash using the standard PHP hashing

        // Data Validations - check if user is still active
        if($ret_active != 'Y'){    
            responseGeneric(401, false, 'User account is not active!');
        }

        // if login attempts have exceeded 3...
        if($ret_loginattempts >= $max_loginattempts){
            responseGeneric(401, false, 'Number of attempts exceeded! User account have been locked out.');
        }

        // validate the password (using the password_verify() functn)
        if(!password_verify($password, $ret_password)) { 
            // increment login attempts
            $query = $writeDB->prepare('UPDATE tbl_users set loginattempts = loginattempts+1 WHERE id = :id');
            $query -> bindParam(':id', $ret_id, PDO::PARAM_INT);
            $query -> execute();

            // send response
            responseGeneric(401, false, 'Username or password is incorrect!');
        }

        //else login is successful
        // accesstoken and all is generated in constants

        //end of checks

    }
    catch (PDOException $e){
        responseServerException($e, 'Problem with logging in. Please try again');
    }

    //use a separate try and catch for the login updates so that we can perform a rollback in case of failure.
    // Three db functions are involved in the rollback: beginTransaction(); commit(); rollback();
    try{

        $writeDB -> beginTransaction(); //rollback to this point if an error is found

        $lastlogin = date('d/m/Y H:i'); //current date&time

        $query = $writeDB -> prepare('UPDATE tbl_users SET loginattempts = 0, lastlogin = STR_TO_DATE(:lastlogin, '. $system_dateformat .') WHERE id = :id');
        $query -> bindParam(':id', $ret_id, PDO::PARAM_INT);
        $query -> bindParam(':lastlogin', $lastlogin, PDO::PARAM_STR);
        $query -> execute();

        //Insert login record into sessions table
        //we use the date_add() SQL functn to get the current time and add to it the number of seconds before expiry. I.e.: date_add(currentTime, INTERVAL xxx SECOND)
        $query = $writeDB -> prepare('INSERT INTO tbl_sessions (userid, accesstoken, a_tokenexpiry, refreshtoken, r_tokenexpiry) 
            VALUES (:id, :accesstoken, date_add(NOW(), INTERVAL :a_tokenexpiry SECOND), :refreshtoken, date_add(NOW(), INTERVAL :r_tokenexpiry SECOND))');
        $query -> bindParam(':id', $ret_id, PDO::PARAM_INT);
        $query -> bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query -> bindParam(':a_tokenexpiry', $accesstoken_expiry, PDO::PARAM_INT);
        $query -> bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
        $query -> bindParam(':r_tokenexpiry', $refreshtoken_expiry, PDO::PARAM_INT);
        $query -> execute();

        $lastSessionID = $writeDB -> lastInsertId();

        $writeDB -> commit(); //if everything is fine so far, commit to database.

        $rowCount = $query -> rowCount();

        if($rowCount === 0){
            responseGeneric(401, false, 'Some error occurred with login');
        }

        $returnData = array();
        $returnData['session_id'] = intval($lastSessionID); //cast as integer
        $returnData['accesstoken'] = $accesstoken;
        $returnData['access_token_expires_in'] = $accesstoken_expiry;
        $returnData['refreshtoken'] = $refreshtoken;
        $returnData['refresh_token_expires_in'] = $refreshtoken_expiry;

        responseSuccessWithData(201, $returnData, 'User successfully logged in');
    }
    catch(PDOException $e){
        
        $writeDB -> rollBack(); // rollback to beginning and return the db to former values if any error is caught in the processing

        responseServerException($e, 'There was an issue with logging in. Please try again');
    
    }

?>