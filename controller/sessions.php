<?php
require_once 'db.php';
require_once '../model/Response.php';

// try
try {
    $writeDB = DB::connectWriteDB();

} catch (PDOException $e) {
    error_log("Connection Error: " . $e, 0);
    $response = new Response();
    // http code 500: server error. can not connect
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage('Database connection error');
    $response->send();
    exit;
}

// handle options request method for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Max-age: 86400');
    $response = new Response();
    $response->setHttpStatusCode(200);
    $response->setSuccess(true);
    $response->addMessage('Preflight OPTIONS check');
    $response->send();
    exit;
}

// if sessions id exists
// check if sessionid is in the url e.g. /sessions/1
if (array_key_exists("sessionid", $_GET)) {
    // get sessions id from query string
    $sessionid = $_GET['sessionid'];

    if ($sessionid === '' || !is_numeric($sessionid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        ($sessionid === '' ? $response->addMessage('Session ID can not be blank') : false);
        (!is_numeric($sessionid) ? $response->addMessage('Session ID must be numeric') : false);
        $response->send();
        exit;
    }

    // check to see if access token is provided in the HTTP Authorization header and that the value is longer than 0 chars
    // don't forget the Apache fix in .htaccess file
    // 401 error is for authentication failed or has not yet been provided
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $response = new Response();
        // http code 401: authentication unauthorized
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage("Access token is missing from the header") : false);
        (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage("Access token cannot be blank") : false);
        $response->send();
        exit;
    }

    // store access token in a variable
    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

        try {
            $query = $writeDB->prepare('delete from tblsessions where id = :sessionid and accesstoken = :accesstoken');
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            // the rowcount will be 0 if the rowcount is deleted or is expired
            if ($rowCount === 0) {
                $response = new Response();
                // http code 400: bad request
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage('Failed to log out of this session using provided access token. You are already logged out');
                $response->send();
                exit;
            }

            // if rowcount is 1 then user have been logged out or session have been deleted
            $returnData = array();
            $returnData['session_id'] = intval($sessionid);

            // response
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage('Logged out');
            $response->setData($returnData);
            $response->send();
            exit;

        } catch (PDOException $e) {
            $response = new Response();
            // http code 500: database error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('There was an issue logging out. Please try again');
            $response->send();
            exit;
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

        if (!isset($_SERVER['CONTENT_TYPE']) || (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json')) {
            $response = new Response();
            // http code 405: bad request
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage('Content Type header not set to JSON');
            $response->send();
            exit;
        }

        // check that the refresh token exists and not empty
        $rawPatchdata = file_get_contents('php://input');

        // check that the data provided is valid JSON
        // if the data is true (valid JSON) it will be stored in the $jsonData variable
        // get PATCH request body as the PATCHed data will be JSON format

        if (!$jsonData = json_decode($rawPatchdata)) {
            // set up response for unsuccessful request
            $response = new Response();
            // http code 400: client error
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage("Request body is not valid JSON");
            $response->send();
            exit;
        }

        // check if patch request contains access token
        if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1) {
            $response = new Response();
            // http code 400: client error
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            (!isset($jsonData->refresh_token) ? $response->addMessage("Refresh Token not supplied") : false);
            (isset($jsonData->refresh_token) && strlen($jsonData->refresh_token) < 1 ? $response->addMessage("Refresh Token cannot be blank") : false);
            $response->send();
            exit;
        }

        try {
            $refreshtoken = $jsonData->refresh_token;

            // get user record for provided session id, access AND refresh token
            // create db query to retrieve user details from provided access and refresh token
            $query = $writeDB->prepare('SELECT tblsessions.id as sessionid, tblsessions.userid as userid, accesstoken, refreshtoken, username, fullname, useractive, loginattempts, accesstokenexpiry, refreshtokenexpiry from tblsessions, tblusers where tblusers.id = tblsessions.userid and tblsessions.id = :sessionid and tblsessions.accesstoken = :accesstoken and tblsessions.refreshtoken = :refreshtoken');
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                // http code 401:  unauthorized
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage('Access token or refresh token is incorrect for this sessions id');
                $response->send();
                exit;
            }

            // no need for while loop as we are getting one row back
            // get returned row
            $row = $query->fetch(PDO::FETCH_ASSOC);

            // save returned details into variables
            $returned_fullname = $row['fullname'];
            $returned_username = $row['username'];

            $returned_sessionid = $row['sessionid'];
            $returned_userid = $row['userid'];
            $returned_accesstoken = $row['accesstoken'];
            $returned_refreshtoken = $row['refreshtoken'];
            $returned_useractive = $row['useractive'];
            $returned_loginattempts = $row['loginattempts'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];
            $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

            // check if user is active
            if ($returned_useractive !== 'Y') {
                $response = new Response();
                // http code 500: unauthorized
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage('User account is not active');
                $response->send();
                exit;
            }

            // check login attempts
            if ($returned_loginattempts >= 3) {
                $response = new Response();
                // http code 500: unauthorized
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage('User account is currently locked out');
                $response->send();
                exit;
            }

            // check if refresh token has expired
            if (strtotime($returned_refreshtokenexpiry) < time()) {
                $response = new Response();
                // http code 500: unauthorized
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage('Refresh token has expired please login again');
                $response->send();
                exit;
            }

            // regenerate a new access token and refresh token
            $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
            $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

            // expiry access token: 1200
            $access_token_expiry_seconds = 1200;
            // expiry refresh token: 1209600
            $refresh_token_expiry_seconds = 1209600;

            // only update the current session not create new session
            // create the query string to update the current session row in the sessions table and set the token and refresh token as well as their expiry dates and times
            $query = $writeDB->prepare('update tblsessions set accesstoken = :accesstoken, accesstokenexpiry = date_add(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), refreshtoken = :refreshtoken, refreshtokenexpiry = date_add(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND) where id = :sessionid and userid = :userid and accesstoken = :returnedaccesstoken and refreshtoken = :returnedrefreshtoken');
            // bind the user id
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            // bind the session id
            $query->bindParam(':sessionid', $returned_sessionid, PDO::PARAM_INT);
            // bind the access token
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            // bind the access token expiry date
            $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
            // bind the refresh token
            $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
            // bind the refresh token expiry date
            $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
            // bind the old access token for where clause as user could have multiple sessions
            $query->bindParam(':returnedaccesstoken', $returned_accesstoken, PDO::PARAM_STR);
            // bind the old refresh token for where clause as user could have multiple sessions
            $query->bindParam(':returnedrefreshtoken', $returned_refreshtoken, PDO::PARAM_STR);
            // run the query
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                // http code 401:  unauthorized
                $response->setHttpStatusCode(401);
                $response->setSuccess(false);
                $response->addMessage('Access token could not be refreshed please login again');
                $response->send();
                exit;
            }


//            $returnData = array();
//
//            $returnData['session_id'] = intval($lastSessionID);
//            $returnData['access_token'] = $accesstoken;
//            $returnData['access_token_expires_in'] = $access_token_expiry_seconds;
//            $returnData['refresh_token'] = $refreshtoken;
//            $returnData['refresh_token_expires_in'] = $refresh_token_expiry_seconds;


            $returnData = array();
            $returnData['user_id'] = $returned_userid;
            $returnData['username'] = $returned_username;
            $returnData['fullname'] = $returned_fullname;

            $returnData['session_id'] = $returned_sessionid;
            $returnData['access_token'] = $accesstoken;
            $returnData['access_token_expiry'] = $access_token_expiry_seconds;
            $returnData['refresh_token'] = $refreshtoken;
            $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage('Token refreshed');
            $response->setData($returnData);
            $response->send();
            exit;

        } catch (PDOException $e) {
            $response = new Response();
            // http code 500: database error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('There was an issues refreshing token please login again');
            $response->send();
            exit;
        }

    } else {
        $response = new Response();
        // http code 405: request method not allowed
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit;
    }

}

// if GET request is empty
// handle creating new session, e.g. log in
if (empty($_GET)) {
    // handle creating new session, e.g. logging in
    // check to make sure the request is POST only - else exit with error response
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = new Response();
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage("Request method not allowed");
        $response->send();
        exit;
    }

    // insert a delay on every login etempt for 1 second for security popurse. using function sleep
    // delay login by 1 second to slow down any potential brute force attacks
    sleep(1);

// check if content type is set to JSON
    if (!isset($_SERVER['CONTENT_TYPE']) || (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json')) {
        // set up response for unsuccessful request
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Content Type header not set to JSON");
        $response->send();
        exit;
    }

// Content-Type: application/json is the content header. the content header is just information
// make sure data provided is valid json
// get POST request body as the POSTed data will be JSON format
    $rawPostData = file_get_contents('php://input');

// decode the data provided and check if the decode method has not returned false
// if the data is true (valid JSON) it will be stored in the $jsonData variable
    if (!$jsonData = json_decode($rawPostData)) {
        $response = new Response();
        // http code 400: client error
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage('Request body is not valid JSON');
        $response->send();
        exit;
    }

// if data i valid JSON
    if (!isset($jsonData->username) || !isset($jsonData->password)) {
        $response = new Response();
        // http code 400: client error
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (!isset($jsonData->username) ? $response->addMessage('Username not supplied') : false);
        (!isset($jsonData->password) ? $response->addMessage('Password not supplied') : false);
        $response->send();
        exit;
    }

// username and password validation
    if (strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) {
        $response = new Response();
        // http code 400: client error
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        (strlen($jsonData->username) < 1 ? $response->addMessage('Username can not be blank') : false);
        (strlen($jsonData->username) > 255 ? $response->addMessage('Username can must be less than 255 characters') : false);

        (strlen($jsonData->password) < 1 ? $response->addMessage('Password can not be blank') : false);
        (strlen($jsonData->password) > 255 ? $response->addMessage('Password can must be less than 255 characters') : false);
        $response->send();
        exit;
    }

    try {
        // create a query that will return a row from database
        $username = $jsonData->username;
        $password = $jsonData->password;

        $query = $writeDB->prepare('SELECT id, fullname, username, password, useractive, loginattempts from tblusers where username = :username'); // we should max get 1 row back as username is unique
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        // if row cound is 0, means no user exists in the database with that username
        if ($rowCount === 0) {
            $response = new Response();
            // http code 401: unauthorized error
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage('Username or password is incorrect');
            $response->send();
            exit;
        }

        // if user exists
        $row = $query->fetch(PDO::FETCH_ASSOC);

        // save returned details into variables
        $returned_id = $row['id'];
        $returned_fullname = $row['fullname'];
        $returned_username = $row['username'];
        $returned_password = $row['password'];
        $returned_useractive = $row['useractive'];
        $returned_loginattempts = $row['loginattempts'];

        if ($returned_useractive !== 'Y') {
            $response = new Response();
            // http code 401: unauthorized error
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage('User account not active');
            $response->send();
            exit;
        }

        // count number of login attempts
//        if ($returned_loginattempts >= 3) {
        if ($returned_loginattempts >= 30) {
            $response = new Response();
            // http code 401: unauthorized error
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage('User account is currently locked');
            $response->send();
            exit;
        }

        // validate password
        // the password in database is hashed so we need to use the method password_verified
        // we can not convert the hashed password back to plain text password
        // check if password is the same using the hash
        if (!password_verify($password, $returned_password)) {
            // create the query to increment attempts figure
            $query = $writeDB->prepare('update tblusers set loginattempts = loginattempts+1 where id = :id');
            // bind the user id
            $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
            // run the query
            $query->execute();

            // send response
            $response = new Response();
            $response->setHttpStatusCode(401);
            $response->setSuccess(false);
            $response->addMessage("Username or password is incorrect");
            $response->send();
            exit;
        }

        // create the access and refresh token
        // the method makes sure that the value have not been used before
        // the value is bytes
        // we need to convert to bin2hex method is used
        // generate readable characters base64_encode method is used

        // to make sure no one can copy and access token from client device we suffix it with the time method
        // we add time on the top of the value which is returned
        $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

        // refresh token
        $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

        // expiry access token: 1200
        $access_token_expiry_seconds = 1200;
        // expiry refresh token: 1209600
        $refresh_token_expiry_seconds = 1209600;

    } catch (PDOException $e) {
        $response = new Response();
        // http code 500: server error
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage('There was an issue logging in');
        $response->send();
        exit;
    }

    //
    try {
        $writeDB->beginTransaction();
        $query = $writeDB->prepare('update tblusers set loginattempts = 0 where id = :id');
        $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
        $query->execute();

        $query = $writeDB->prepare('insert into tblsessions (userid, accesstoken, accesstokenexpiry, refreshtoken, refreshtokenexpiry) values (:userid, :accesstoken, date_add(NOW(), INTERVAL  :accesstokenexpiryseconds SECOND), :refreshtoken, date_add(NOW(), INTERVAL  :refreshtokenexpiryseconds SECOND))');
        $query->bindParam(':userid', $returned_id, PDO::PARAM_INT);
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->bindParam(':accesstokenexpiryseconds', $access_token_expiry_seconds, PDO::PARAM_INT);
        $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
        $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
        $query->execute();

        // get unique session id
        $lastSessionID = $writeDB->lastInsertId();

        // cause we are using beginTransaction method we need to commit or save the data
        $writeDB->commit();

        $returnData = array();
        $returnData['user_id'] = $returned_id;
        $returnData['username'] = $returned_username;
        $returnData['fullname'] = $returned_fullname;
        $returnData['session_id'] = intval($lastSessionID);
        $returnData['access_token'] = $accesstoken;
        $returnData['access_token_expires_in'] = $access_token_expiry_seconds;
        $returnData['refresh_token'] = $refreshtoken;
        $returnData['refresh_token_expires_in'] = $refresh_token_expiry_seconds;

        $response = new Response();
        // http code 201: created something - session
        $response->setHttpStatusCode(201);
        $response->setSuccess(true);
        $response->addMessage('Successfully logged in');
        $response->setData($returnData);
        $response->send();
        exit;

        // catch
    } catch (PDOException $e) {

        $writeDB->rollBack();

        $response = new Response();
        // http code 500: database error
        $response->setHttpStatusCode(500);
        $response->setSuccess(false);
        $response->addMessage('There was an issue logging in. Please try again');
        $response->send();
        exit;
    }

// check if we can find a user with the passed in username
} else {
    $response = new Response();
    // http code 404: does not exists
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage('Endpoint not found');
    $response->send();
    exit;
}
