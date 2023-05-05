<?php
    //In this file, we will try to have the least amount of code possible
    //We will only have the code that is necessary to start the application
    //date_default_timezone_set("America/Mexico_City");
    //require_once __DIR__ . "/vendor/autoload.php";
    // var_dump(dirname(__DIR__));
    // exit;
    Whis\App::bootstrap(dirname(__DIR__))->run();