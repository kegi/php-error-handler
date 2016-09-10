<?php

namespace KeGi\PhpErrorHandler\Tests;

use KeGi\PhpErrorHandler\PhpErrorHandler;
use PHPUnit_Framework_TestCase;

class WebserverTest extends PHPUnit_Framework_TestCase
{
    /**
     * make sure that nothing happen when there is no error
     */
    public function testNoError()
    {

        $this->assertEquals(
            ['^$', 200, false],
            $this->fetch('no_error.php', [
                'debug' => true,
                'strict' => true,
                'catchError' => true,
                'catchFatalError' => true,
                'catchUnrecoverableError' => true,
            ]),
            'No error'
        );
    }

    /**
     * test every possibilities with a non-fatal error
     */
    public function testNonFatalError()
    {

        /*test non-stopping (strict=false) error handling*/
        /*should execute completly (contains $) and return code 200*/

        $this->assertEquals(
            ['^$', 200, true],
            $this->fetch('non_fatal_error.php', [
                'debug' => true,
            ]),
            'Non fatal error, [no handler, debug]'
        );

        $this->assertEquals(
            ['^$', 200, false],
            $this->fetch('non_fatal_error.php', []),
            'Non fatal error, [no handler, no debug]'
        );

        $this->assertEquals(
            ['^E$', 200, true],
            $this->fetch('non_fatal_error.php', [
                'debug' => true,
                'catchError' => true,
            ]),
            'Non fatal error, [handler, debug]'
        );

        $this->assertEquals(
            ['^E$', 200, false],
            $this->fetch('non_fatal_error.php', [
                'catchError' => true,
            ]),
            'Non fatal error, [handler, no debug]'
        );

        /*test strict mode error handling*/
        /*shouln'd contain "E", should stop execution and return code 500*/

        $this->assertEquals(
            ['^F', 500, true],
            $this->fetch('non_fatal_error.php', [
                'debug' => true,
                'strict' => true,
                'catchError' => true,
                'catchFatalError' => true,
            ]),
            'Non fatal error, [handler, debug, strict]'
        );

        $this->assertEquals(
            ['F', 500, false],
            $this->fetch('non_fatal_error.php', [
                'strict' => true,
                'catchError' => true,
                'catchFatalError' => true,
            ]),
            'Non fatal error, [handler, no debug, strict]'
        );

        $this->assertEquals(
            ['U', 500, false],
            $this->fetch('non_fatal_error.php', [
                'debug' => false,
                'strict' => true,
                'catchError' => true,
                'catchUnrecoverableError' => true,
            ]),
            'Non fatal error, [no fatal handler, no debug, strict]'
        );

        $this->assertEquals(
            ['DEFAULT_UNRECOVERABLE_ERROR_MESSAGE', 500, false],
            $this->fetch('non_fatal_error.php', [
                'debug' => false,
                'strict' => true,
                'catchError' => true,
            ]),
            'Non fatal error, [no handler, no debug, strict]'
        );

        /*second level fatal errors*/

        $this->assertEquals(
            ['^', 500, true],
            $this->fetch('non_fatal_error.php', [
                'debug' => true,
                'strict' => true,
                'catchError' => true,
                'catchFatalError' => true,
                'catchUnrecoverableError' => true,
                'fatalErrorOnFatalHandler' => true,
            ]),
            'Non fatal error, [fatal handler, debug, strict, fatal error on fatal handler]'
        );

        $this->assertEquals(
            ['U', 500, false],
            $this->fetch('non_fatal_error.php', [
                'strict' => true,
                'catchError' => true,
                'catchFatalError' => true,
                'catchUnrecoverableError' => true,
                'fatalErrorOnFatalHandler' => true,
            ]),
            'Non fatal error, [fatal handler, unrecoverable handler, no debug, strict, fatal error on fatal handler]'
        );

        $this->assertEquals(
            ['DEFAULT_UNRECOVERABLE_ERROR_MESSAGE', 500, false],
            $this->fetch('non_fatal_error.php', [
                'strict' => true,
                'catchError' => true,
                'catchFatalError' => true,
                'fatalErrorOnFatalHandler' => true,
            ]),
            'Non fatal error, [fatal handler, no unrecoverable handle, no debug, strict, fatal error on fatal handler]'
        );

        $this->assertEquals(
            ['^', 500, true],
            $this->fetch('non_fatal_error.php', [
                'debug' => true,
                'strict' => true,
                'catchError' => true,
                'catchUnrecoverableError' => true,
            ]),
            'Non fatal error, [no fatal handler, debug, strict]'
        );

        $this->assertEquals(
            ['', 500, false],
            $this->fetch('non_fatal_error.php', [
                'debug' => false,
                'strict' => true,
                'catchError' => true,
                'catchUnrecoverableError' => true,
                'fatalErrorOnUnrecoverableHandler' => true,
            ]),
            'Non fatal error, [no handler, no debug, strict, fatal error on unrecoverable handler]'
        );
    }

    /**
     * @param string $file
     * @param array  $data
     *
     * @return array
     */
    private function fetch(string $file, array $data = []) : array
    {
        $url = 'http://' . WEB_SERVER_HOST . ':' . WEB_SERVER_PORT . '/'
            . $file;

        if (!empty($data)) {
            $data = http_build_query($data, '', '&');

            if (strpos($url, '?') === false) {
                $url .= '?' . $data;
            } else {
                $url .= '&' . $data;
            }
        }

        $curlHandler = curl_init();
        curl_setopt_array($curlHandler, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => false,
            CURLOPT_VERBOSE => false,
            CURLOPT_CONNECTTIMEOUT => WEB_SERVER_TIMEOUT,
            CURLOPT_TIMEOUT => WEB_SERVER_TIMEOUT,
            CURLOPT_URL => $url,
        ]);

        $parsedContent = $this->parseResponse(trim(curl_exec($curlHandler)));

        return [
            $parsedContent[0],
            curl_getinfo($curlHandler)['http_code'] ?? 0,
            !empty($parsedContent[1]),
        ];
    }

    /**
     * @param string $response
     *
     * @return array
     */
    private function parseResponse(string $response) : array
    {

        preg_match_all(
            '#\%\%\%(?<debugOutput>[^\%]*)\%\%\%#',
            $response,
            $matches
        );

        $debugOutput = implode('', $matches['debugOutput'] ?? []);
        $generalOutput = preg_replace('#(\%\%\%[^\%]*\%\%\%)#', '', $response);

        if ($generalOutput
            === PhpErrorHandler::DEFAULT_UNRECOVERABLE_ERROR_MESSAGE
        ) {
            $debugOutput = 'DEFAULT_UNRECOVERABLE_ERROR_MESSAGE';
            $generalOutput = '';
        }

        return [trim($debugOutput), trim($generalOutput)];
    }
}
