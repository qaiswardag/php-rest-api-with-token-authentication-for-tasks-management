<?php

require_once 'Task.php';


// 1: Test: successfull
try {
    $task = new Task(1, "Title here", "Description here", "01/01/2019 12:00", "N");
    header('Content-type: application/json;charset=UTF-8');
    echo json_encode($task->returnTaskAsArray());

} catch (TaskException $e) {
    echo "Error: " . $e->getMessage();
}


//// 2: Test: not successfull
//try {
//    $task = new Task(1, "Title here", "Description here", "01/01/2019 12:00", "O");
//    header('Content-type: application/json;charset=UTF-8');
//    echo json_encode($task->returnTaskAsArray());
//
//} catch (TaskException $e) {
//    echo "Error: " . $e->getMessage();
//}
