<?php

declare(strict_types=1);

namespace {

use TestSupport\FakeCapsule;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/Config.class.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/TransactionStore.class.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/TransbankApi.class.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/PaymentProcessor.class.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/TransbankClientInterface.php';

$GLOBALS['test_invoice_payments'] = [];
$GLOBALS['test_create_response'] = [];
$GLOBALS['test_commit_response'] = [];
$GLOBALS['test_transbank_api_factory'] = null;
$GLOBALS['test_last_create_payload'] = null;
}

namespace {

use TestSupport\FakeCapsule;

final class CreateCommitFlowTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        FakeCapsule::reset();
        $GLOBALS['test_invoice_payments'] = [];
        $GLOBALS['test_create_response'] = [];
        $GLOBALS['test_commit_response'] = [];
        $GLOBALS['test_last_create_payload'] = null;

        $GLOBALS['test_transbank_api_factory'] = static function (): WebpayDirecto\TransbankClientInterface {
            return new class implements WebpayDirecto\TransbankClientInterface {
                public function createTransaction(array $payload): array
                {
                    $GLOBALS['test_last_create_payload'] = $payload;

                    if (!empty($GLOBALS['test_create_response'])) {
                        return $GLOBALS['test_create_response'];
                    }

                    return [
                        'token' => 'create-' . $payload['buy_order'],
                        'url' => 'https://webpay.test/redirect',
                    ];
                }

                public function commitTransaction(string $token): array
                {
                    if (!empty($GLOBALS['test_commit_response'])) {
                        $response = $GLOBALS['test_commit_response'];
                        $response['session_id'] = $response['session_id'] ?? 'INV-100';
                        $response['buy_order'] = $response['buy_order'] ?? 'INV100-ORDER';
                        return $response;
                    }

                    return [
                        'status' => WebpayDirecto\Config::STATUS_AUTHORIZED,
                        'response_code' => 0,
                        'amount' => 16535,
                        'currency' => 'CLP',
                        'authorization_code' => 'AUTH-E2E',
                        'session_id' => 'INV-100',
                        'buy_order' => 'INV100-ORDER',
                    ];
                }
            };
        };
    }

    public function testEndToEndFlowCreatesAndCommitsTransaction(): void
    {
        $token = WebpayDirecto\TransbankApi::create([], 'https://test')->createTransaction([
            'buy_order' => 'INV100-ORDER',
            'session_id' => 'INV-100',
            'amount' => 16535,
            'return_url' => 'https://example.test/return',
        ]);

        self::assertSame('create-INV100-ORDER', $token['token']);
        self::assertNotNull($GLOBALS['test_last_create_payload']);
        self::assertSame(16535, $GLOBALS['test_last_create_payload']['amount']);
        self::assertSame('INV100-ORDER', $GLOBALS['test_last_create_payload']['buy_order']);

        WebpayDirecto\TransactionStore::recordCreate(100, 'INV100-ORDER', 'create-INV100-ORDER', 16535, 'CLP');

        $result = WebpayDirecto\PaymentProcessor::processCommitToken('create-INV100-ORDER', 'return');

        self::assertTrue($result['authorized']);
        self::assertTrue($result['paymentRecorded']);
        self::assertSame(100, $result['invoiceId']);
        self::assertCount(1, $GLOBALS['test_invoice_payments']);
    }

    public function testRejectedCommitFromCreateFlowDoesNotRecordPayment(): void
    {
        $GLOBALS['test_commit_response'] = [
            'status' => 'FAILED',
            'response_code' => -1,
            'amount' => 16535,
            'currency' => 'CLP',
            'authorization_code' => '',
            'session_id' => 'INV-101',
            'buy_order' => 'INV101-ORDER',
        ];

        $result = WebpayDirecto\PaymentProcessor::processCommitToken('create-INV101-ORDER', 'callback');

        self::assertFalse($result['authorized']);
        self::assertFalse($result['paymentRecorded']);
        self::assertCount(0, $GLOBALS['test_invoice_payments']);
    }

    public function testAbortedCommitFromCreateFlowIsIdempotent(): void
    {
        $GLOBALS['test_commit_response'] = [
            'status' => 'ABORTED',
            'response_code' => -2,
            'amount' => 0,
            'currency' => 'CLP',
            'authorization_code' => '',
            'session_id' => 'INV-102',
            'buy_order' => 'INV102-ORDER',
        ];

        $first = WebpayDirecto\PaymentProcessor::processCommitToken('create-INV102-ORDER', 'callback');
        $second = WebpayDirecto\PaymentProcessor::processCommitToken('create-INV102-ORDER', 'callback');

        self::assertFalse($first['authorized']);
        self::assertFalse($second['authorized']);
        self::assertCount(0, $GLOBALS['test_invoice_payments']);

        $rows = FakeCapsule::$tables[WebpayDirecto\TransactionStore::TABLE] ?? [];
        self::assertGreaterThanOrEqual(1, count($rows));
        self::assertSame(2, $rows[0]['commit_attempts']);
    }
}
}
