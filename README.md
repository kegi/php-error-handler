# php-error-handler
PHP7; Object oriented error handler

#Introduction

With this library, you don't have to deal with PHP errors anymore. This error handler convert php fatal errors into exceptions so you can handle them easily. If debug is set to false (eg. on PROD), absolutly no error will be displayed, only the response from your error callback, no half (broken) response will be printed. If debug is set to true (eg. on DEV) errors are displayed normally and you'll still be notified.

This allow you to implement a complexe workflow in case of a fatal error (eg. E_PARSE, E_ERROR, uncaught exception...) For instance, you could call an error route and emit a response.

PhpErrorHandler is able to detect a fatal error while handling another fatal error. In that case, a static error message will be displayed.

#How to implement it on your project

1) you instanciate PhpErrorHandler at the very beggining of your project.

2) you create a method on your app to handle a fatal error (an exception is provided as an argument in your method).

3) the uncaught exception of your project will naturally go on your fatal error method. You don't need to handle them differently anymore.

4) you can provide an errorLogger to log all php errors (optional).

#Example

```php

<?php

namespace Your\Project\Bootstrap;

use KeGi\PhpErrorHandler\PhpErrorHandler;
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
        //run your application...
    }
    
    public function handleFatalError(PhpFatalErrorException $phpFatalErrorException)
    {
        //php fatal error detected (such as uncaugh exception...)
        
        //call your error controller and render an error page
        //if an error occures while rendering the error page, the content of
        //"handleUnrecoverableError" will be displayed
    }
    
    public function handleUnrecoverableError()
    {
        //you could include a static error page
        //eg. return include '/static/server-error.html';
        
        echo 'A very bad error occured';
    }
}

```

#Parameters
##Debug mode
Set/unset debug mode, *see workflow below*. (default: **false**)
```
setDebug(bool $debug)
hasDebug() : bool
```

##Strict mode
Set/unset strict mode. with strict mode enabled, non-fatal php error (such as E_NOTICE) generate a fatal error. (default: **false**)
```
setStrict(bool $strict)
isStrict() : bool
```

#errorCallback
Set/unset error callback. This will be call for every single php non-fatal error. **Most project don't need this.**. Any input returned will be printed.
```
setErrorCallback([mixed $callable])
getErrorCallback() : mixed
```

#fatalErrorCallback
Set/unset fatal error callback. This will be call in case of a fatal error. You can print or return your input.
```
setFatalErrorCallback([mixed $callable])
getFatalErrorCallback() : mixed
```

#unrecoverableErrorCallback
Set/unset fatal error callback. This will be call in case of a fatal error from "**fatalErrorCallback**". You can print or return your input.
```
setUnrecoverableErrorCallback([mixed $callable])
getUnrecoverableErrorCallback() : mixed
```

#Error Logger
Set/unset error logger. ErrorLogger need to implement PSR Logger interface. Note: Logs are enabled with or without debug mode enabled.
```
setErrorLogger([LoggerInterface $errorLogger])
getErrorLogger() : mixed
```

#workflow
![alt tag](https://raw.githubusercontent.com/kegi/php-error-handler/master/docs/workflow.png)
