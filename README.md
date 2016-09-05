# php-error-handler
PHP7; Object oriented error handler

#Introduction

With this library, you don't have to deal with PHP errors anymore. This error handler convert php fatal errors into exceptions and you can handle them easily. If debug is set to false (eg. on PROD), absolutly no error will be displayed, only the response from your error callback, no half (broken) response will be printed. If debug is set to true (eg. on DEV) errors are displayed normally. You'll still be notified so you can implement logic with debug enabled it you want to.

This allow you to implement a complexe workflow in case of a fatal error (eg. E_PARSE, E_ERROR, uncaught exception...) You can by exemple, call an error route and emit an error response like any other response you generate.

PhpErrorHandler will be able to detect a fatal error while handling another fatal error. In that case, a static error message will be displayed.

#How to implement it on your project

1) you instanciate PhpErrorHandler at the very beggining of your project.

2) you create a method on your app to handle a fatal error (an exception if provided).

3) the uncaught exception of your project will naturally goes on your fatal error method. You don't need to handle them differently anymore.

4) you can (optionnaly) provide an errorLogger to log all php errors.

#Example

```php

<?php

namespace Your\Project;

use KeGi\PhpErrorHandler\PhpErrorHandler;
use KeGi\PhpErrorHandler\PhpErrorException;
use KeGi\PhpErrorHandler\PhpFatalErrorException;

class App
{

    public function __construct() {
        
        /*instanciate the error handler*/
        
        (new PhpErrorHandler())
        ->setDebug(false) //prod
        #->setErrorCallback([$this, 'handleError']) //most projects don't need this
        ->setFatalErrorCallback([$this, 'handleFatalError'])
        ->setUnrecoverableErrorCallback([$this, 'handleUnrecoverableError']);
    }
    
    public function run()
    {
        //run your application normally
        //uncaught exception will be handled on "handleFatalError"
    }
    
    public function handleFatalError(PhpFatalErrorException $phpFatalErrorException)
    {
        //call your error controller and render an error page normally
        //if an error occured on this method, the content of "unrecoverableError" will be displayed
    }
    
    public function handleUnrecoverableError()
    {
        //you could include a static error page
        //eg. return include '/static/server-error.html';
        
        echo 'A very bad error occured';
    }
}

```
