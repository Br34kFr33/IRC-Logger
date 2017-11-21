<?php

ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);

set_time_limit(0);

define('IRCBOT_ROOT', dirname(__FILE__) . '/');
define('IRCBOT_LIB', IRCBOT_ROOT . 'lib/');

require_once IRCBOT_LIB . 'constants.php';
require_once IRCBOT_LIB . 'functions.php';

$rs = ircbot_main();
