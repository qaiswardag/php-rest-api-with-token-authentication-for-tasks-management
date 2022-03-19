<?php
require_once 'db.php';
require_once '../model/Response.php';

// because we are using exeptions we have to try and run
try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
}
//
// catch
catch (PDOException $e) {
    echo "Database connection error: ", $e->getMessage();
    $response = new Response();
    // http code 500: is a server error. if we can not connect to a database it is a server error
    $response->setHttpStatusCode(500);
    $response->setSuccess(false);
    $response->addMessage('Database connection error');

    // return the response using the send method
    $response->send();
    // exit the scrip
    exit;
}
