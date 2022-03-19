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
            self::$writeDBConnection = new PDO("mysql:host=159.89.106.210;dbname=vbtvppsykw;charset=utf8", "vbtvppsykw", "ew8Uy5V7pZ");
            // Exception: for error mode for the database connections. we are able to catch Exeption
            self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        return self::$writeDBConnection;
    }

    public static function connectReadDB()
    {
        if (self::$readDBConnection === null) {
            self::$writeDBConnection = new PDO("mysql:host=159.89.106.210;dbname=vbtvppsykw;charset=utf8", "vbtvppsykw", "ew8Uy5V7pZ");
            // Exception: for error mode for the database connections. we are able to catch Exeption
            self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        }
        return self::$readDBConnection;
    }

}
