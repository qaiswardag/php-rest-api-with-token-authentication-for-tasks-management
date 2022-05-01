<?php
require_once 'db.php';
require_once '../model/Task.php';
require_once '../model/Response.php';

// connect to the database
try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();

} catch (PDOException $e) {
    // 0 means that the error will be stored in the php error log file
    error_log("Connection error: " . $e, 0);
    $response = new Response();
    // http code 500: server error
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage('Database connection error');
    $response->send();
    exit();
}

// handle cors
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    header('Access-Control-Max-age: 86400');
    $response = new Response();
    $response->setHttpStatusCode(200);
    $response->setSuccess(true);
    $response->addMessage('Preflight OPTIONS check');
    $response->send();
    exit;
}

// auth start
// get access token from HTTP header

// check if access token is provided
if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
    $response = new Response();
    // http code 401: unauthorized
    $response->setHttpStatusCode(401);
    $response->setSuccess(false);
    (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage('Access token is messing from the header') : false);
    (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage('Access token can not be blank') : false);
    $response->send();
    exit;
}

$accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

// make a database query based on provided access token to get usertables and sessiontables
// usertables to check that the user is active and sessions table to check if accesstokens has not expired
try {
    $query = $writeDB->prepare('select userid, accesstokenexpiry, useractive, loginattempts from tblsessions, tblusers where tblsessions.userid = tblusers.id and accesstoken = :accesstoken');
    $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        $response = new Response();
        // http code 401: unauthorized
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage('Invalid access token provided');
        $response->send();
        exit;
    }

// one row will be returned since access token is unique
    $row = $query->fetch(PDO::FETCH_ASSOC);
    $returned_userid = $row['userid'];
    $returned_accesstokenexpiry = $row['accesstokenexpiry'];
    $returned_useractive = $row['useractive'];
    $returned_loginattempts = $row['loginattempts'];

// check if user is active
    if ($returned_useractive !== 'Y') {
        $response = new Response();
        // http code 401: unauthorized
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage('User account not active');
        $response->send();
        exit;
    }

// check iflogin attempts is not greater or equal to 3
    if ($returned_loginattempts >= 3) {
        $response = new Response();
        // http code 401: unauthorized
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage('User account is currently locked out');
        $response->send();
        exit;
    }

// check if access token is not greater and not expired
// if access token is expired client have to submit a refresh token to get a new access token
    if (strtotime($returned_accesstokenexpiry) < time()) {
        $response = new Response();
        // http code 401: unauthorized
        $response->setHttpStatusCode(401);
        $response->setSuccess(false);
        $response->addMessage('Access token expired');
        $response->send();
        exit;
    }

} catch (PDOException $e) {
    $response = new Response();
    // http code 500: server error
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage('There was an issue with authenticating. Please try again');
    $response->send();
    exit;
}

// auth end
//
// in order to get a single task, we need to pass in a single task ID into the url
// check if task with id exist
// we are looking for the task id in the GET super global
if (array_key_exists("taskid", $_GET)) {
    // get task id from query string
    $taskid = $_GET['taskid'];

    //check to see if task id in query string is not empty and is number, if not return json error
    if ($taskid == '' || !is_numeric($taskid)) {
        $response = new Response();
        // http code 400: bad request
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Task ID cannot be blank or must be numeric");
        $response->send();
        exit;
    }
    // we need to check what the what the request method is
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();
            // get row count
            $rowCount = $query->rowCount();

            // if not, if there is zero row count, then we can send a standard response to say not found
            if ($rowCount === 0) {
                $response = new Response();
                // http code: 400: bad request
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("Task was not found");
                $response->send();
                exit();
            }

            // if task exist
            // for each row returned - should be just one
            // for each row returned
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new task object for each row
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

                // create task and store in array for return in json data
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();

        } catch (TaskException $e) {
            $response = new Response();
            // http code 500: server error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit();
        } catch (PDOException $e) {
            // 0 means that the error will be stored in the php error log file
            error_log("Database query error: " . $e, 0);
            $response = new Response();
            // http code 500: server error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to get Task');
            $response->send();
            exit();
        }

    }

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        try {
            $query = $writeDB->prepare('delete from tbltasks where id = :taskid and userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            // check if something have been deleted
            $rowCount = $query->rowCount();

            // if row does not exist with the given row id
            // catch will take care of errors
            if ($rowCount === 0) {
                $response = new Response();
                // http code 404: not found
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage('Task not found');
                $response->send();
                exit();
            }

            // if task is found
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage('Task deleted');
            $response->send();
            exit();

            // connection issue or query issue
        } catch (PDOException $e) {
            $response = new Response();
            // http code 500: server error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to delete task');
            $response->send();
            exit();
        }

    }

    // Update task
    // e.g. v1/tasks/1
    // handle cors
    if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        // try
        try {
            // check if data is in JSON format

            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $response = new Response();
                // http code 400: bad request
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage('Content type header not set to JSON');
                $response->send();
                exit();
            }

            // get the content of the data that have been passed in
            $rawPatchData = file_get_contents('php://input');

            // make sure to check that the data passed in is JSON
            if (!$jsonData = json_decode($rawPatchData)) {
                $response = new Response();
                // http code 400: bad request
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage('Request body is not valid JSON');
                $response->send();
                exit();
            }

            // set task field updated to false initially
            $title_updated = false;
            $description_updated = false;
            $deadline_updated = false;
            $completed_updated = false;

            // create blank query fields string to append each field to
            $queryFields = "";

            // check if title exists in PATCH
            if (isset($jsonData->title)) {
                // set title field updated to true
                $title_updated = true;
                // add title field to query field string
                $queryFields .= "title = :title, ";
            }

            // check if description exists in PATCH
            if (isset($jsonData->description)) {
                // set description field updated to true
                $description_updated = true;
                // add description field to query field string
                $queryFields .= "description = :description, ";
            }

            // check if deadline exists in PATCH
            if (isset($jsonData->deadline)) {
                // set deadline field updated to true
                $deadline_updated = true;
                // add deadline field to query field string
                $queryFields .= "deadline = STR_TO_DATE(:deadline, '%d/%m/%Y %H:%i'), ";
            }

            // check if completed exists in PATCH
            if (isset($jsonData->completed)) {
                // set completed field updated to true
                $completed_updated = true;
                // add completed field to query field string
                $queryFields .= "completed = :completed, ";
            }

            // remove the right hand comma and trailing space
            $queryFields = rtrim($queryFields, ", ");

            // make sure that not all fields are set to false
            // one of them should at least be true
            if ($title_updated === false && $deadline_updated === false && $deadline_updated === false && $completed_updated === false) {
                $response = new Response();
                // http code 400: bad request — client issue
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage('No task field provided to update the task');
                $response->send();
                exit();
            }

            // ADD AUTH TO QUERY
            // create db query to get task from database to update - use master db
            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // make sure that the task exists for a given task id
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage("No task found to update");
                $response->send();
                exit;
            }

            // if there is a row we will return it back
            // row returned - should be just one
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new task object
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
            }
            // ADD AUTH TO QUERY
            // create the query string including any query fields
            $queryString = "update tbltasks set " . $queryFields . " where id = :taskid and userid = :userid";
            // prepare the query
            $query = $writeDB->prepare($queryString);

            // if title has been provided
            if ($title_updated === true) {
                // set task object title to given value (checks for valid input)
                $task->setTitle($jsonData->title);
                // get the value back as the object could be handling the return of the value differently to
                // what was provided
                $up_title = $task->getTitle();
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':title', $up_title, PDO::PARAM_STR);
            }

            // if description has been provided
            if ($description_updated === true) {
                // set task object description to given value (checks for valid input)
                $task->setDescription($jsonData->description);
                // get the value back as the object could be handling the return of the value differently to
                // what was provided
                $up_description = $task->getDescription();
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':description', $up_description, PDO::PARAM_STR);
            }

            // if deadline has been provided
            if ($deadline_updated === true) {
                // set task object deadline to given value (checks for valid input)
                $task->setDeadline($jsonData->deadline);
                // get the value back as the object could be handling the return of the value differently to
                // what was provided
                $up_deadline = $task->getDeadline();
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':deadline', $up_deadline, PDO::PARAM_STR);
            }

            // if completed has been provided
            if ($completed_updated === true) {
                // set task object completed to given value (checks for valid input)
                $task->setCompleted($jsonData->completed);
                // get the value back as the object could be handling the return of the value differently to
                // what was provided
                $up_completed = $task->getCompleted();
                // bind the parameter of the new value from the object to the query (prevents SQL injection)
                $query->bindParam(':completed', $up_completed, PDO::PARAM_STR);
            }

            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                // http code 400: bad request
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage('Check if at least one field have been provied for update');
                $response->addMessage('Task not updated');
                $response->send();
                exit();
            }

            // return updated object back to client
            $query = $writeDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                // http code 404: not found
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage('No task found after updated');
                $response->send();
                exit();
            }

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            // send response back to client
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            // http code 404: not found
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->addMessage('Task updated');
            $response->setData($returnData);
            $response->send();
            exit();

            // catch
        } catch (TaskException $e) {
            $response = new Response();
            // http code 400: bad request
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit();
            // catch
        } catch (PDOException $e) {
            error_log("Database query error - " . $e, 0);
            $response = new Response();
            // http code 500: server error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to update task. Check your data for errors');
            $response->send();
            exit();
        }
    } else {
        $response = new Response();
        // http code 405: request method not allowed
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage('Request method not allowedøø');
        $response->send();
        exit();
    }
}
//
// check if task is completed
if (array_key_exists('completed', $_GET)) {

    // check for completed
    $completed = $_GET['completed'];

    if ($completed !== 'Y' && $completed !== 'N') {
        // send error response
        $response = new Response();

        // 400 http code since incorrect value have been passed
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage('Completed filter must be Y or N');
        $response->send();
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // get all tasks which have Y or N using the database connection
        try {
            // get tasks
            $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where completed = :completed and userid = :userid');
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_STR);
            $query->execute();

            // row count
            $rowCount = $query->rowCount();

            $taskArray = array();

            // while loop
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
                $taskArray[] = $task->returnTaskAsArray();
            }

            // return data
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData('good job');
            $response->setData($returnData);
            $response->send();
            exit();

            // catch
        } catch (TaskException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit();
        } catch (PDOException) {
            error_log("Database query error: " . $e, 0);
            $response = new Response();
            // http code 500: server error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to get tasks');
            $response->send();
            exit();
        }

    } else {
        $response = new Response();
        // http code 405: request method not allowed
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit();
    }

}

// GET tasks with pagination
if (array_key_exists("page", $_GET)) {

// get all tasks with pagination
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        // page
        $page = $_GET['page'];

        if ($page == '' || !is_numeric($page)) {
            $response = new Response();
            // http code 400: bad request
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage('Page number can not be blank and must be numeric');
            $response->send();
            exit();
        }

        // limit per page
        $limitPerPage = 4;

        // try
        try {
            // check how many row there is in the table
            $query = $readDB->prepare('select count(id) as totalNoOfTasks from tbltasks where userid = :userid');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $row = $query->fetch(PDO::FETCH_ASSOC);

            // convert to integer
            $tasksCount = intval($row['totalNoOfTasks']);

            // number of pages
            // divide amount of rows with how many rows we want to show on each page
            $numOfPages = ceil($tasksCount / $limitPerPage);

            // if we have 0 tasks, we are not able to devide with 0
            // as minimum we want 1 page to be displayed
            if ($numOfPages == 0) {
                $numOfPages = 1;
            }

            // send response saying page not found
            if ($page > $numOfPages) {
                $response = new Response();
                // 404 http code, since page do not exist
                $response->setHttpStatusCode(404);
                $response->setSuccess(false);
                $response->addMessage('Page not found');
                $response->send();
                exit();
            }

            // only get rows belonging to the page number
            $offset = ($page == 1 ? 0 : ($limitPerPage * ($page - 1)));

            // get relevant row based on page number
            $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where userid = :userid  ORDER BY created_at DESC limit :pglimit offset :offset');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT);
            $query->bindParam(':offset', $offset, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['total_rows'] = $tasksCount;
            $returnData['total_pages'] = $numOfPages;
            ($page < $numOfPages ? $returnData['has_next_page'] = true : $returnData['has_next_page'] = false);
            ($page > 1 ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false);
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();

            // catch
        } catch (TaskException $e) {
            $response = new Response();
            // http code 500: server error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit();
            // catch
        } catch (PDOException $e) {
            error_log("Databse query error - " . $e, 0);
            $response = new Response();
            // http code 500: server error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to get tasks');
            $response->addMessage('something went wrong');
            $response->send();
            exit();
        }

    } else {
        $response = new Response();
        // http code 405: request method not allowed
        $response->setHttpStatusCode(405);
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit();
    }

}

if (empty($_GET)) {
    // GET all tasks
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // try
        try {
            $query = $readDB->prepare('select id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where userid = :userid');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $taskArray = array();

            $rowCount = $query->rowCount();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // for each row
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

                $taskArray[] = $task->returnTaskAsArray();
            }

            // get all tasks
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            // new response
            $response = new Response();
            $response->setHttpStatusCode(200);
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit();

            // catch
        } catch (TaskException $e) {
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit();

            // catch
        } catch (PDOException $e) {
            error_log("Databse query error - " . $e, 0);
            $response = new Response();
            // http code 500: server error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to get tasks');
            $response->send();
            exit();

        }

    }

    // POST a task
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // try
        try {
            // request body. data that needs to be handled in JSON format
            // check request header is set to application/json
            if (isset($_SERVER['CONTENT_TYPE']) && $_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $response = new Response();
                // http code 400: incorrect data provided
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage('Content-type header is not set to JSON');
                $response->send();
                exit();
            }

            // file_get_contents reads the content of the file and stores it in the rawPOSTData variable
            $rawPostData = file_get_contents('php://input');

            // convert JSON to an object
            if (!$jsonData = json_decode($rawPostData)) {
                $response = new Response();
                // http code 400: incorrect data provided
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                $response->addMessage('Request body is not valid JSON');
                $response->send();
                exit();
            }

            // check mandatory fields
            if (!isset($jsonData->title) || !isset($jsonData->completed)) {
                $response = new Response();
                // http code 400: incorrect data provided
                $response->setHttpStatusCode(400);
                $response->setSuccess(false);
                (!isset($jsonData->title) ? $response->addMessage('Title field is mandatory and must be provided') : false);
                (!isset($jsonData->completed) ? $response->addMessage('Completed field is mandatory and must be provided') : false);
                $response->send();
                exit();
            }

            // validate
            // create new task with data, if non mandatory fields provided then set to null
            $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed);
            // get title, description, deadline, completed and store them in variables
            $title = $newTask->getTitle();
            $description = $newTask->getDescription();
            $deadline = $newTask->getDeadline();
            $completed = $newTask->getCompleted();

            // insert to tasks db table
            // create db query
            $query = $writeDB->prepare('insert into tbltasks (title, description, deadline, completed, userid) values (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed, :userid)');
            $query->bindParam(':title', $title, PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':deadline', $deadline, PDO::PARAM_STR);
            $query->bindParam(':completed', $completed, PDO::PARAM_STR);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            // make sure it has inserted the data into the database table
            // row count shhould return how many row was effected
            // get row count
            $rowCount = $query->rowCount();

            // check if row was actually inserted, PDO exception should have caught it if not
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response();
                // http code 500: server error
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage("Failed to create task");
                $response->send();
                exit;
            }

            // as part of REST api, always return same data back after successful creation
            // last insert id method. PDO method applies for current session
            // get last task id so we can return the Task in the json
            $lastTaskID = $writeDB->lastInsertId();

            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid and userid = :userid');
            $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            if ($rowCount === 0) {
                $response = new Response();
                // http code 500: server error
                $response->setHttpStatusCode(500);
                $response->setSuccess(false);
                $response->addMessage('Failed to get task after creation');
                $response->send();
                exit();
            }

            // create empty array to store tasks
            $taskArray = array();

            // for each row returned - should be just one
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                // create new task object
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

                // create task and store in array for return in json data
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            $response = new Response();
            // status code 201: something have been created
            $response->setHttpStatusCode(201);
            $response->setSuccess(true);
            $response->addMessage('New task created');
            $response->setData($returnData);
            $response->send();
            exit();

            // catch
        } catch (TaskException $e) {
            $response = new Response();
            // http code 400: incorrect data provided
            $response->setHttpStatusCode(400);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit();
            // catch
        } catch (PDOException $e) {
            error_log("Database query error - " . $e, 0);
            $response = new Response();
            // http code 500: server error
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to insert task into database. Check submitted data for errors');
            $response->send();
            exit();
        }

    }
    $response = new Response();
    // http code 405: request method not allowed
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage('Request method not allowed');
    $response->send();
    exit();
}
// if GET global variable is not empty and endpoint not defined. /test
if (!empty($_GET)) {
    $response = new Response();
    $response->setHttpStatusCode(404);
    $response->setSuccess(false);
    $response->addMessage('Endpoint not found');
    $response->send();
    exit();
}
