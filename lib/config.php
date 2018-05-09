<?php
session_start();
// Error reporting
error_reporting(E_ALL ^ E_NOTICE);

setlocale(LC_MONETARY, 'en_GB');

//DB Constants
/*
define('DB_HOST', 'localhost');
define('DB_USER', 'user');
define('DB_PASS', 'pass');
define('DB_NAME', 'name');
*/

require_once('db.class.php');
require_once('curl.class.php');



    ?>