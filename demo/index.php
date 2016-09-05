<?php

use KeGi\PhpErrorHandler\PhpErrorException;
use KeGi\PhpErrorHandler\PhpErrorHandler;
use KeGi\PhpErrorHandler\PhpFatalErrorException;

require_once __DIR__ . '/../vendor/autoload.php';

error_reporting(-1);
ini_set('display_errors', 'On');

/*instanciate the php error handler*/

(new PhpErrorHandler())
    ->setDebug(false) //prod = false
    ->setStrict(false) //prod = false
    ->setErrorCallback('errorHandler')
    ->setFatalErrorCallback('fatalErrorHandler')
    ->setUnrecoverableErrorCallback('unrecoverableErrorHandler');

/*******************/
/* error callbacks */
/*******************/

function errorHandler(PhpErrorException $errorException)
{
    /* In most case, you won't need this callback. The errors are already loggued */

    echo '<div style="padding:5px; margin:10px 0; background-color:#ffb88a; color:#ff6312;">';
    echo 'Callback : PHP Error catched : ' . $errorException->getMessage();
    echo '</div>';
}

function fatalErrorHandler(PhpFatalErrorException $fatalErrorException)
{

    /*here, you could call your errorController and try to generate a response*/

    echo '<div style="padding:5px; margin:10px 0; background-color:#ff6312; color:#fff;">';
    echo 'Callback : PHP Fatal Error catched : '
        . $fatalErrorException->getMessage();
    echo '</div>';

    /*any non-fatal error at that level are loggued and dismiss*/

    /* !!!!!!!!!!!!!! */
    /* !! E_NOTICE !! */
    /* !!!!!!!!!!!!!! */
    echo $thirdNonFatalError;

    /*any php fatal error or exception thrown here would result on displaying
    message on "unrecoverableErrorHandler". if your error come from the core of
    your application, there is good chances that you won't be able to generate
    an error page properly*/

    /* !!!!!!!!!!!!!! */
    /* !! E_ERROR !! */
    /* !!!!!!!!!!!!!! */
    secondFatalError();

    /*an exception thrown here will also trigger a php fatal error*/

    /* !!!!!!!!!!!!!! */
    /* !! E_ERROR !! */
    /* !!!!!!!!!!!!!! */
    #throw new Exception('secondFatalError');
}

function unrecoverableErrorHandler()
{
    /*Be aware that this function can be triggered even if not used. Don't put
    any logic here,y ou have no way to know if this will be used or not*/

    /*you could include a static html error page*/

    echo '<div style="padding:5px; margin:10px 0; background-color:#ff0f09; color:#fff;">';
    echo 'Unrecoverable error catched';
    echo '</div>';
}

/*****************************/
/* test application workflow */
/*****************************/

echo '<div style="padding:5px; margin:10px 0; background-color:#000; color:#fff;">Application start</div>';

/*generate non-fatal errors (don't stop application)*/

/* !!!!!!!!!!!!!! */
/* !! E_NOTICE !! */
/* !!!!!!!!!!!!!! */
echo $firstNonFatalError;

/* !!!!!!!!!!!!!!!!!!!! */
/* !! E_USER_WARNING !! */
/* !!!!!!!!!!!!!!!!!!!! */
trigger_error('second non fatal error', E_USER_WARNING);

/*the 4 nexts errors generate a fatal error*/

/* !!!!!!!!!!!!! */
/* !! E_ERROR !! */
/* !!!!!!!!!!!!! */
firstFatalError();

/* !!!!!!!!!!!!! */
/* !! E_PARSE !! */
/* !!!!!!!!!!!!! */
#require __DIR__.'/parseError.php';

/* !!!!!!!!!!!!!!!!!!!!! */
/* !! E_COMPILE_ERROR !! */
/* !!!!!!!!!!!!!!!!!!!!! */
#require 'missing';

/* !!!!!!!!!!!!! */
/* !! E_ERROR !! */
/* !!!!!!!!!!!!! */
#throw new Exception('secondFatalError');

echo '<div style="padding:5px; margin:10px 0; background-color:#000; color:#fff;">Application stop</div>';
