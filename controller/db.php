<?php

class DB
{
    // static: we do not need to initialize the DB class to use the static variables
    private static $writeDBConnection;
    private static $readDBConnection;

    public static function connectWriteDB()
    {
        // the singleton pattern
        // if: no connection to db than connect
        //we don't want to keep creating connections
        if (self::$writeDBConnection === null) {
            self::$writeDBConnection = new PDO("mysql:host=localhost;dbname=tasksdb;charset=utf8", "qais", "123456");
            // Exception: for error mode for the database connections. we are able to catch Exeption
            self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        return self::$writeDBConnection;
    }

    public static function connectReadDB()
    {
        if (self::$readDBConnection === null) {
            self::$readDBConnection = new PDO("mysql:host=localhost;dbname=tasksdb;charset=utf8", "qais", "123456");
            // Exception: for error mode for the database connections. we are able to catch Exeption
            self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        return self::$readDBConnection;
    }

}
