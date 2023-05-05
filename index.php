<?php
date_default_timezone_set('America/Mexico_City');
//session_start();
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// This file allows us to emulate Apache's "mod_rewrite" functionality from the
// built-in PHP web server. This provides a convenient way to test a Laravel
// application without having installed a "real" web server software here.
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}



require_once 'vendor/autoload.php';
// error_reporting(E_ALL);
// set_error_handler('Whis\Exceptions\Error::errorHandler');
// set_exception_handler('Whis\Exceptions\Error::exceptionHandler');
//ANCHOR - Autoload
//Redirecting to the public folder of the project
require_once __DIR__.'/public/index.php';