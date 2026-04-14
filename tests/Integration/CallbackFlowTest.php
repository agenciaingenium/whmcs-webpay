<?php

declare(strict_types=1);

namespace {

use TestSupport\FakeCapsule;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/Config.class.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/TransactionStore.class.php';

$GLOBALS['test_invoice_payments'] = [];
$GLOBALS['test_commit_response'] = [];
$GLOBALS['test_commit_exception'] = null;
$GLOBALS['test_add_invoice_payment_hook'] = null;
$GLOBALS['test_transbank_api_factory'] = null;

function getGatewayVariables(string $gateway): array
{
    return [
        'type' => 'CC',
        'environment' => 'TEST',
        'apiKey' => 'test-key',
        'apiSecret' => 'test-secret',
    ];
}

function logModuleCall(string $module, string $action, array $request, array $response): void
{
}

function checkCbInvoiceID(int $invoiceId, string $gateway): void
{
}

function addInvoicePayment(int $invoiceId, string $transactionId, float $amount, float $fees, string $gateway): void
{
    if (is_callable($GLOBALS['test_add_invoice_payment_hook'])) {
        $hook = $GLOBALS['test_add_invoice_payment_hook'];
        $GLOBALS['test_add_invoice_payment_hook'] = null;
        $hook();
    }

    $GLOBALS['test_invoice_payments'][] = compact('invoiceId', 'transactionId', 'amount', 'fees', 'gateway');
    FakeCapsule::$tables['tblaccounts'][] = ['transid' => $transactionId];
}

function logTransaction(string $gateway, array $payload, string $message): void
{
}

function logActivity(string $message): void
{
}
}

namespace {

use TestSupport\FakeCapsule;

require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/PaymentProcessor.class.php';

final class CallbackFlowTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        FakeCapsule::reset();
        $GLOBALS['test_invoice_payments'] = [];
        $GLOBALS['test_commit_response'] = [];
        $GLOBALS['test_commit_exception'] = null;
        $GLOBALS['test_add_invoice_payment_hook'] = null;
        $GLOBALS['test_transbank_api_factory'] = static function (): object {
            return new class {
                public function commitTransaction(string $token): array
                {
                    if ($GLOBALS['test_commit_exception'] instanceof \Throwable) {
                        throw $GLOBALS['test_commit_exception'];
                    }

                    if (!empty($GLOBALS['test_commit_response'])) {
                        return $GLOBALS['test_commit_response'];
                    }

                    return [
                        'status' => WebpayDirecto\Config::STATUS_AUTHORIZED,
                        'response_code' => 0,
                        'amount' => 1000,
                        'currency' => 'CLP',
                        'authorization_code' => 'AUTH123',
                        'session_id' => 'INV-20',
                        'buy_order' => 'INV20-ORDER',
                    ];
                }
            };
        };
    }

    public function testDoubleCallbackWithSameTokenIsIdempotent(): void
    {
        $first = WebpayDirecto\PaymentProcessor::processCommitToken('token-xyz', 'callback');
        $second = WebpayDirecto\PaymentProcessor::processCommitToken('token-xyz', 'callback');

        self::assertTrue($first['authorized']);
        self::assertTrue($first['paymentRecorded']);
        self::assertTrue($second['paymentRecorded']);
        self::assertCount(1, $GLOBALS['test_invoice_payments']);
    }

    public function testCommitRejectedDoesNotRecordPayment(): void
    {
        $GLOBALS['test_commit_response'] = [
            'status' => 'FAILED',
            'response_code' => -1,
            'amount' => 1000,
            'currency' => 'CLP',
            'authorization_code' => '',
            'session_id' => 'INV-21',
            'buy_order' => 'INV21-ORDER',
        ];

        $result = WebpayDirecto\PaymentProcessor::processCommitToken('token-rejected', 'callback');

        self::assertFalse($result['authorized']);
        self::assertFalse($result['paymentRecorded']);
        self::assertCount(0, $GLOBALS['test_invoice_payments']);
        self::assertFalse(WebpayDirecto\TransactionStore::isPaymentRecorded('token-rejected'));
    }

    public function testCommitTimeoutBubblesExceptionAndKeepsAttemptCount(): void
    {
        $GLOBALS['test_commit_exception'] = new \Exception('timeout api');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('timeout api');

        try {
            WebpayDirecto\PaymentProcessor::processCommitToken('token-timeout', 'callback');
        } finally {
            $rows = FakeCapsule::$tables[WebpayDirecto\TransactionStore::TABLE] ?? [];
            self::assertCount(1, $rows);
            self::assertSame(1, $rows[0]['commit_attempts']);
            self::assertSame('RECEIVED', $rows[0]['status']);
            self::assertCount(0, $GLOBALS['test_invoice_payments']);
        }
    }

    public function testConcurrentDuplicateCallbacksOnlyRecordOnePayment(): void
    {
        $GLOBALS['test_add_invoice_payment_hook'] = static function (): void {
            WebpayDirecto\PaymentProcessor::processCommitToken('token-race', 'callback');
        };

        $first = WebpayDirecto\PaymentProcessor::processCommitToken('token-race', 'callback');

        self::assertTrue($first['authorized']);
        self::assertTrue($first['paymentRecorded']);
        self::assertCount(1, $GLOBALS['test_invoice_payments']);
        self::assertTrue(WebpayDirecto\TransactionStore::isPaymentRecorded('token-race'));
    }
}
}
