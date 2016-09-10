<?php

use KeGi\PhpErrorHandler\Tests\Scenario\TestScenario;
require __DIR__ . '/../../vendor/autoload.php';

$scenario = new TestScenario(function () {
    trigger_error('non fatal error', E_USER_WARNING);
});
