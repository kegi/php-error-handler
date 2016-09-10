<?php

namespace KeGi\PhpErrorHandler\Tests\Scenario;

use KeGi\PhpErrorHandler\PhpErrorHandler;
use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Report\Clover;

class TestScenario
{
    /**
     * @var PhpErrorHandler
     */
    private $errorHandler;

    /**
     * @param callable $body
     */
    public function __construct($body)
    {

        error_reporting(-1);
        ini_set('display_errors', 'On');

        $debug = (bool)($_GET['debug'] ?? false);
        $strict = (bool)($_GET['strict'] ?? false);
        $catchError = (bool)($_GET['catchError'] ?? false);
        $catchFatalError = (bool)($_GET['catchFatalError'] ?? false);
        $catchUnrecoverableError = (bool)($_GET['catchUnrecoverableError'] ??
            false);
        $fatalErrorOnFatalHandler = (bool)($_GET['fatalErrorOnFatalHandler'] ??
            false);
        $fatalErrorOnUnrecoverableHandler
            = (bool)($_GET['fatalErrorOnUnrecoverableHandler'] ??
            false);

        $this->errorHandler = new PhpErrorHandler();

        $this->errorHandler->setDebug($debug);
        $this->errorHandler->setStrict($strict);

        if ($catchError) {
            $this->errorHandler->setErrorCallback(function () {
                $this->printDebugOutput('E');
            });
        }

        if ($catchFatalError) {
            $this->errorHandler->setFatalErrorCallback(
                function () use (&$fatalErrorOnFatalHandler) {

                    if ($fatalErrorOnFatalHandler) {
                        trigger_error('second fatal error', E_USER_ERROR);
                    }

                    $this->printDebugOutput('F');
                });
        }

        if ($catchUnrecoverableError) {
            $this->errorHandler->setUnrecoverableErrorCallback(
                function () use ($fatalErrorOnUnrecoverableHandler) {

                    if ($fatalErrorOnUnrecoverableHandler) {
                        trigger_error('second fatal error', E_USER_ERROR);
                    }

                    $this->printDebugOutput('U');
                });
        }

        $this->printDebugOutput('^');
        $body();
        $this->printDebugOutput('$');
    }

    /**
     * @param string $output
     */
    private function printDebugOutput(string $output)
    {
        echo '%%%' . $output . '%%%';
    }
}
