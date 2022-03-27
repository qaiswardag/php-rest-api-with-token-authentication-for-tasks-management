<?php
// file for getting a single task


require_once 'db.php';
require_once '../model/Task.php';
require_once '../model/Response.php';

// 1: connect to the database
try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();

} catch (PDOException $e) {
    // 0 means that the error will be stored in the php error log file
    error_log("Connection error: " . $e, 0);
    $response = new Response();
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage('Database connection error');
    $response->send();
    exit();
}


// in order to get a single task, we need to pass in a single task ID into the url

// check if task with id exist
// we are looking for the task id in the GET super global

// check if task id is in the url e.g. /tasks/1
if (array_key_exists("taskid", $_GET)) {
    // get task id from query string
    $taskid = $_GET['taskid'];

    //check to see if task id in query string is not empty and is number, if not return json error
    if ($taskid == '' || !is_numeric($taskid)) {
        $response = new Response();
        $response->setHttpStatusCode(400);
        $response->setSuccess(false);
        $response->addMessage("Task ID cannot be blank or must be numeric");
        $response->send();
        exit;
    }


    // CORS
    // CORS
    // CORS
    // CORS
    // CORS
    // handle options request method for CORS
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Max-Age: 86400');


        $response = new Response();
        $response->setHttpStatusCode(200);
        $response->setSuccess(true);
        $response->toCache(true);
        $response->addMessage("Task IDøøøøøø cannot be blank or must be numeric");
        $response->setData($returnData);
        $response->send();
        exit();
    }
    // CORS
    // CORS
    // CORS
    // CORS
    // CORS


    // we need to check what the what the request method is
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed from tbltasks where id = :taskid');
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->execute();
            // get row count
            $rowCount = $query->rowCount();

            // if not, if there is zero row count, then we can send a standard response to say not found
            if ($rowCount === 0) {
                $response = new Response();
                // http error code: 400: means not found
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
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage($e->getMessage());
            $response->send();
            exit();
        } catch (PDOException $e) {
            // 0 means that the error will be stored in the php error log file
            error_log("Database query error: " . $e, 0);
            $response = new Response();
            $response->setHttpStatusCode(500);
            $response->setSuccess(false);
            $response->addMessage('Failed to get Task');
            $response->send();
            exit();
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

    }

    // create an error if ewquest method is not GET, DELETE or PATCH: 405
    // http error code: 405: means request method not allowed
    $response = new Response();
    $response->setHttpStatusCode(405);
    $response->setSuccess(false);
    $response->addMessage("Request method not allowed");
    $response->send();
    exit();

}
















