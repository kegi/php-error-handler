<?php

namespace KeGi\PhpErrorHandler;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * This handler catch all PHP errors
 *
 * You can provide 3 callback functions to catch php errors :
 *
 *  - errorCallback :               will be call for every non-fatal errors
 *                                  (notice, strict, deprecated, warning...)
 *
 *  - fatalErrorCallback :          will be call if a fatal error occured. (eg.
 *                                  E_PARSE)
 *
 *  - unrecoverableErrorCallback :  will be call if a fatal error or exception
 *                                  occured while handling a fatal error.
 *
 * If an errorLogger is provided, all PHP Errors will be loggued with the
 * correct level or error.
 *
 * if debug is set to true :
 *
 *  - error are displayed normally
 *  - unrecoverableErrorCallback will never be called
 *  - other error callbacks are called the same way
 *
 * if debug is set to false :
 *
 *  - no error is displayed
 *  - if a fatal error occured, the previously generated content is dismissed and
 *    only the response of the callback id displayed
 *  - in case of a second fatal error, the previously generated content is
 *    dismissed and a final error message is display
 *
 * if strict is set to true :
 *
 *  - all non-fatal errors trigger an exception
 *
 */
class PhpErrorHandler
{

    /**
     * Default message to display if an error occured while handling a fatal error
     */
    const DEFAULT_UNRECOVERABLE_ERROR_MESSAGE = 'An error occured, please try again later.';

    /**
     * @var callable|null
     */
    private $errorCallback;

    /**
     * @var callable|null
     */
    private $fatalErrorCallback;

    /**
     * @var callable|null
     */
    private $unrecoverableErrorCallback;

    /**
     * @var bool
     */
    private $debug;

    /**
     * @var bool
     */
    private $strict;

    /**
     * @var LoggerInterface|null
     */
    private $errorLogger;

    /**
     * @var bool
     */
    private $isShuttingDown = false;

    /**
     * @param bool                 $debug
     * @param bool                 $strict
     * @param callable|null        $errorCallback
     * @param callable|null        $fatalErrorCallback
     * @param callable|null        $unrecoverableErrorCallback
     * @param LoggerInterface|null $errorLogger
     * @param bool                 $setDisplayError
     */
    public function __construct(
        bool $debug = false,
        bool $strict = false,
        $errorCallback = null,
        $fatalErrorCallback = null,
        $unrecoverableErrorCallback = null,
        $errorLogger = null,
        bool $setDisplayError = true
    ) {

        ob_start();

        $this->setDebug($debug);
        $this->setErrorCallback($errorCallback);
        $this->setFatalErrorCallback($fatalErrorCallback);
        $this->setUnrecoverableErrorCallback($unrecoverableErrorCallback);
        $this->setErrorLogger($errorLogger);

        if ($setDisplayError) {
            if ($debug === true) {
                ini_set('display_errors', 'On');
                error_reporting(-1);
            } else {
                ini_set('display_errors', 'Off');
            }
        }

        /*set errors handlers with closure to keep methods private*/

        set_error_handler(function (
            $errorType,
            $errorMessage,
            $errorFile,
            $errorLine
        ) {
            return $this->handleError($errorType, $errorMessage, $errorFile,
                $errorLine);
        });

        register_shutdown_function(function () {
            $this->onExecutionEnd();
        });
    }

    /**
     * @return bool
     */
    public function hasDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param bool $debug
     *
     * @return $this
     */
    public function setDebug(bool $debug)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @return bool
     */
    public function isStrict(): bool
    {
        return $this->strict;
    }

    /**
     * @param bool $strict
     *
     * @return $this
     */
    public function setStrict(bool $strict)
    {
        $this->strict = $strict;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getErrorCallback()
    {
        return $this->errorCallback;
    }

    /**
     * @param callable|null $errorCallback
     *
     * @return $this
     */
    public function setErrorCallback($errorCallback)
    {
        $this->errorCallback = $errorCallback;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getFatalErrorCallback()
    {
        return $this->fatalErrorCallback;
    }

    /**
     * @param callable|null $fatalErrorCallback
     *
     * @return $this
     */
    public function setFatalErrorCallback($fatalErrorCallback)
    {
        $this->fatalErrorCallback = $fatalErrorCallback;

        return $this;
    }

    /**
     * @return callable|null
     */
    public function getUnrecoverableErrorCallback()
    {
        return $this->unrecoverableErrorCallback;
    }

    /**
     * @param callable|null $unrecoverableErrorCallback
     *
     * @return $this
     */
    public function setUnrecoverableErrorCallback($unrecoverableErrorCallback)
    {
        $this->unrecoverableErrorCallback = $unrecoverableErrorCallback;

        return $this;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getErrorLogger()
    {
        return $this->errorLogger;
    }

    /**
     * @param LoggerInterface|null $errorLogger
     *
     * @return $this
     */
    public function setErrorLogger($errorLogger)
    {
        $this->errorLogger = $errorLogger;

        return $this;
    }

    /**
     * Handle a PHP non-fatal error
     *
     * Error is loggued if an errorLogger is available
     * Error callback is called if available
     * This handler return false to give a chance to others error handlers
     * except if the error callback returned true.
     *
     * note: Errors triggered while handling a fatal error are loggued and
     * dismiss
     *
     * @param int    $errorType
     * @param string $errorMessage
     * @param string $errorFile
     * @param int    $errorLine
     *
     * @return bool
     * @throws Throwable
     */
    private function handleError(
        int $errorType,
        string $errorMessage,
        string $errorFile,
        int $errorLine
    ) : bool
    {

        /*if strict mode is enabled, non-fatal error are converted in fatal error*/

        if ($this->isStrict()) {

            $errorString = $this->formatErrorString(
                $errorType,
                $errorMessage,
                $errorFile,
                $errorLine
            );

            trigger_error('[STRICT MODE] ' . $errorString, E_USER_ERROR);
        }

        /*log error if logger is available*/

        if ($this->getErrorLogger() instanceof LoggerInterface) {
            $this->logError($errorType, $errorMessage, $errorFile, $errorLine);
        }

        /*dismiss error while handling a fatal error*/

        if ($this->isShuttingDown) {
            return false;
        }

        /*call error callback if available*/

        if (is_callable($this->getErrorCallback())) {

            $errorString = $this->formatErrorString(
                $errorType,
                $errorMessage,
                $errorFile,
                $errorLine
            );

            $handlerResponse = call_user_func(
                $this->getErrorCallback(),
                new PhpErrorException(
                    $errorString,
                    $errorType
                )
            );

            if ($handlerResponse === true) {
                return true;
            }

            if (is_string($handlerResponse)) {
                echo $handlerResponse;
            }
        }

        return false;
    }

    /**
     * Handle a PHP fatal error
     *
     * Error is loggued if an errorLogger is available
     * Fatal error callback is called if available
     *
     * @param int    $errorType
     * @param string $errorMessage
     * @param string $errorFile
     * @param int    $errorLine
     */
    private function handleFatalError(
        int $errorType,
        string $errorMessage,
        string $errorFile,
        int $errorLine
    ) {

        /*dismiss all output previously generated if debug not enabled*/

        if ($this->hasDebug()) {
            ob_end_flush();
        } else {
            ob_end_clean();
        }

        /*log error if logger is available*/

        if ($this->getErrorLogger() instanceof LoggerInterface) {
            $this->logError($errorType, $errorMessage, $errorFile, $errorLine);
        }

        if (!$this->hasDebug()) {
            ob_start();

            /*at that point, the error handler assume another fatal error will
            occured. if not, this message will be dismiss.*/

            if (is_callable($this->getUnrecoverableErrorCallback())) {
                echo call_user_func($this->getUnrecoverableErrorCallback());
            } else {
                echo self::DEFAULT_UNRECOVERABLE_ERROR_MESSAGE;
            }
        }

        /*call fatal error callback if available*/

        if (is_callable($this->getFatalErrorCallback())) {

            if (!$this->hasDebug()) {
                ob_start(function () {
                    null;
                });
            }

            $errorString = $this->formatErrorString(
                $errorType,
                $errorMessage,
                $errorFile,
                $errorLine
            );

            $handlerResponse = call_user_func(
                $this->fatalErrorCallback,
                new PhpFatalErrorException(
                    $errorString,
                    $errorType
                )
            );

            if (!$this->hasDebug()) {

                $handlerResponse = ob_get_contents() . $handlerResponse;

                ob_end_clean();

                /*we also clean the unrecoverable error message, if we don't
                reached this line, the message will be displayed (php hack to
                handle second layer error)*/

                ob_end_clean();
            }

            echo $handlerResponse;
        }
    }

    /**
     * Called when the script end.
     * This check if the script end because of a fatal error.
     */
    private function onExecutionEnd()
    {

        if ($this->isShuttingDown) {
            return;
        }

        $this->isShuttingDown = true;

        $error = error_get_last();

        if (!is_array($error)) {

            /* no error occured */
            return;
        }

        $errorType = $error['type'] ?? 0;
        $errorMessage = $error['message'] ?? '';
        $errorFile = $error['file'] ?? '';
        $errorLine = $error['line'] ?? 0;

        if ($this->isFatalError($errorType)) {

            $this->handleFatalError(
                $errorType,
                $errorMessage,
                $errorFile,
                $errorLine
            );
        }
    }

    /**
     * Log the error
     *
     * @param int    $errorType
     * @param string $errorMessage
     * @param string $errorFile
     * @param int    $errorLine
     *
     * @throws Throwable
     */
    private function logError(
        int $errorType,
        string $errorMessage,
        string $errorFile,
        int $errorLine
    ) {

        try {

            $errorString = $this->formatErrorString(
                $errorType,
                $errorMessage,
                $errorFile,
                $errorLine
            );

            $this->getErrorLogger()->log(
                $this->getErrorLevel($errorType),
                $errorString
            );

        } catch (Throwable $throwable) {

            if (!$this->isShuttingDown) {
                throw $throwable;
            }
        }
    }

    /**
     * @param int    $errorType
     * @param string $errorMessage
     * @param string $errorFile
     * @param int    $errorLine
     *
     * @return string
     */
    private function formatErrorString(
        int $errorType,
        string $errorMessage,
        string $errorFile,
        int $errorLine
    ) : string
    {
        $errorString = 'PHP ' . $this->getErrorType($errorType) . ': '
            . $errorMessage;

        if (!empty($errorFile)) {
            $errorString .= ' in ' . $errorFile;

            if ($errorLine !== 0) {
                $errorString .= ' on line ' . $errorLine;
            }
        }

        return $errorString;
    }

    /**
     * Return the type of PHP error based on the constant value
     *
     * @param int $errorCode
     *
     * @return string
     */
    private function getErrorType(
        int $errorCode
    ) : string
    {
        switch ($errorCode) {

            case E_ERROR:
                return 'E_ERROR';

            case E_RECOVERABLE_ERROR:
                return 'E_RECOVERABLE_ERROR';

            case E_WARNING:
                return 'E_WARNING';

            case E_PARSE:
                return 'E_PARSE';

            case E_NOTICE:
                return 'E_NOTICE';

            case E_STRICT:
                return 'E_STRICT';

            case E_DEPRECATED:
                return 'E_DEPRECATED';

            case E_CORE_ERROR:
                return 'E_CORE_ERROR';

            case E_CORE_WARNING:
                return 'E_CORE_WARNING';

            case E_COMPILE_ERROR:
                return 'E_COMPILE_ERROR';

            case E_COMPILE_WARNING:
                return 'E_COMPILE_WARNING';

            case E_USER_ERROR:
                return 'E_USER_ERROR';

            case E_USER_WARNING:
                return 'E_USER_WARNING';

            case E_USER_NOTICE:
                return 'E_USER_NOTICE';

            case E_USER_DEPRECATED:
                return 'E_USER_DEPRECATED';

            default:
                return 'unknown';
        }
    }

    /**
     * Return the PSR log level or a PHP Error
     *
     * if php is shutting down, that means no handler caught this error.
     * When E_ERROR are uncaught, they create a fatal error. In that case, it
     * return CRITICAL instead of ERROR.
     *
     * @param int $errorCode
     *
     * @return string
     */
    private function getErrorLevel(
        int $errorCode
    ) : string
    {
        $uncaught = $this->isShuttingDown;

        switch ($errorCode) {

            case E_ERROR:
                return $uncaught ? LogLevel::CRITICAL : LogLevel::ERROR;

            case E_RECOVERABLE_ERROR:
                return $uncaught ? LogLevel::CRITICAL : LogLevel::ERROR;

            case E_WARNING:
                return LogLevel::WARNING;

            case E_PARSE:
                return LogLevel::CRITICAL;

            case E_NOTICE:
                return LogLevel::NOTICE;

            case E_STRICT:
                return LogLevel::INFO;

            case E_DEPRECATED:
                return LogLevel::INFO;

            case E_CORE_ERROR:
                return LogLevel::CRITICAL;

            case E_CORE_WARNING:
                return LogLevel::WARNING;

            case E_COMPILE_ERROR:
                return LogLevel::CRITICAL;

            case E_COMPILE_WARNING:
                return LogLevel::WARNING;

            case E_USER_ERROR:
                return $uncaught ? LogLevel::CRITICAL : LogLevel::ERROR;

            case E_USER_WARNING:
                return LogLevel::WARNING;

            case E_USER_NOTICE:
                return LogLevel::NOTICE;

            case E_USER_DEPRECATED:
                return LogLevel::INFO;

            default:
                return LogLevel::INFO;
        }
    }

    /**
     * Return true if this error stopped the execution (fatal error)
     *
     * @param int $errorCode
     *
     * @return bool
     */
    private function isFatalError(
        int $errorCode
    ) : bool
    {
        $level = $this->getErrorLevel($errorCode);

        return in_array($level, [
            LogLevel::CRITICAL,
            LogLevel::ALERT,
            LogLevel::EMERGENCY,
        ], true);
    }
}
