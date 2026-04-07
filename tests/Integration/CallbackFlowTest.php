<?php

declare(strict_types=1);

namespace {

use TestSupport\FakeCapsule;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/Config.class.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/TransactionStore.class.php';

$GLOBALS['test_invoice_payments'] = [];

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

namespace WebpayDirecto {
class TransbankApi
{
    public function __construct(string $apiKey, string $apiSecret, string $baseUrl)
    {
    }

    public function commitTransaction(string $token): array
    {
        return [
            'status' => Config::STATUS_AUTHORIZED,
            'response_code' => 0,
            'amount' => 1000,
            'currency' => 'CLP',
            'authorization_code' => 'AUTH123',
            'session_id' => 'INV-20',
            'buy_order' => 'INV20-ORDER',
        ];
    }
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
}
}
