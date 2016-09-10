<?php

/*default timezone*/

date_default_timezone_set('UTC');

/*composer autoloader*/

$vendorPath = realpath(__DIR__ . '/../vendor');
$autoloadFile = $vendorPath . DIRECTORY_SEPARATOR . 'autoload.php';

if (!file_exists($autoloadFile)) {
    throw new Exception('Unable to locate composer autoloader');
}

require_once $autoloadFile;

/*webserver settings (change values in phpunit.xml file)*/

if (!defined('WEB_SERVER_HOST')) {
    define('WEB_SERVER_HOST', '127.0.0.1');
}

if (!defined('WEB_SERVER_PORT')) {
    define('WEB_SERVER_PORT', 8080);
}

if (!defined('WEB_SERVER_TIMEOUT')) {
    define('WEB_SERVER_TIMEOUT', 30);
}

/*start webserver*/

$output = [];
exec(sprintf(
    'php -S %s:%d -t %s >/dev/null 2>&1 & echo $!',
    WEB_SERVER_HOST,
    WEB_SERVER_PORT,
    realpath(__DIR__ . '/root')
), $output);

$pid = (int)$output[0];

/*make sure to kill the web server at the end of the tests*/

register_shutdown_function(function () use ($pid) {

    @exec('kill ' . $pid);

    echo sprintf(
        PHP_EOL . 'Stopped Web Server (pid %1$d)' . PHP_EOL,
        $pid
    );
});

/*wait until the webserver is ready*/

$start = microtime(true);
$connected = false;

while (microtime(true) - $start <= WEB_SERVER_TIMEOUT) {

    set_error_handler(function () {
        return true;
    });
    $sp = fsockopen(WEB_SERVER_HOST, WEB_SERVER_PORT);
    restore_error_handler();

    if ($sp === false) {
        continue;
    }

    fclose($sp);
    $connected = true;
    break;
}

if (!$connected) {
    die(PHP_EOL . PHP_EOL . 'Web Server connection timeout' . PHP_EOL);
}

echo sprintf(
        PHP_EOL . 'Started Web Server %1$s:%2$d (pid %3$d)',
        WEB_SERVER_HOST,
        WEB_SERVER_PORT,
        $pid
    ) . PHP_EOL;
