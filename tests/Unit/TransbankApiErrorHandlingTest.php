<?php

declare(strict_types=1);

namespace WebpayDirecto {
    if (!defined('CURLOPT_RETURNTRANSFER')) {
        define('CURLOPT_RETURNTRANSFER', 19913);
        define('CURLOPT_CUSTOMREQUEST', 10036);
        define('CURLOPT_HTTPHEADER', 10023);
        define('CURLOPT_TIMEOUT', 13);
        define('CURLOPT_POSTFIELDS', 10015);
        define('CURLINFO_HTTP_CODE', 2097154);
    }

    $GLOBALS['tbk_mock_body'] = '';
    $GLOBALS['tbk_mock_http_code'] = 200;
    $GLOBALS['tbk_mock_error'] = '';

    function curl_init(string $url)
    {
        return fopen('php://memory', 'r+');
    }

    function curl_setopt_array($ch, array $options): bool
    {
        return true;
    }

    function curl_exec($ch)
    {
        return $GLOBALS['tbk_mock_error'] !== '' ? false : $GLOBALS['tbk_mock_body'];
    }

    function curl_error($ch): string
    {
        return $GLOBALS['tbk_mock_error'];
    }

    function curl_errno($ch): int
    {
        return $GLOBALS['tbk_mock_error'] !== '' ? 7 : 0;
    }

    function curl_getinfo($ch, int $option): int
    {
        return $GLOBALS['tbk_mock_http_code'];
    }

    function curl_close($ch): void
    {
        if (is_resource($ch)) {
            fclose($ch);
        }
    }
}

namespace {
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/Config.class.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/TransbankApi.class.php';

final class TransbankApiErrorHandlingTest extends \PHPUnit\Framework\TestCase
{
    public function testThrowsWhenTransbankReturnsErrorHttpCode(): void
    {
        $GLOBALS['tbk_mock_error'] = '';
        $GLOBALS['tbk_mock_http_code'] = 422;
        $GLOBALS['tbk_mock_body'] = json_encode(['error_message' => 'Invalid amount']);

        $api = new WebpayDirecto\TransbankApi('key', 'secret', 'https://example.test');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Transbank devolvió HTTP 422: Invalid amount');

        $api->createTransaction(['buy_order' => '123']);
    }

    public function testThrowsWhenCurlFails(): void
    {
        $GLOBALS['tbk_mock_error'] = 'Connection refused';
        $GLOBALS['tbk_mock_http_code'] = 0;
        $GLOBALS['tbk_mock_body'] = '';

        $api = new WebpayDirecto\TransbankApi('key', 'secret', 'https://example.test');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error cURL Transbank: Connection refused');

        $api->commitTransaction('tok_123');
    }
}
}
