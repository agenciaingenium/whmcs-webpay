<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/PaymentProcessor.class.php';

final class HmacSignatureTest extends TestCase
{
    public function testVerifyCallbackSignatureWithKnownVector(): void
    {
        $secret = 'my-secret-key';
        $token = 'abc123';
        $invoiceId = 10;
        $signature = 'ff63bc6b85199fddbae99aab303d65d6c57a24546230132647af7b0f846cb40f';

        self::assertTrue(WebpayDirecto\PaymentProcessor::verifyCallbackSignature($secret, $token, $invoiceId, $signature));
    }

    public function testVerifyCallbackSignatureWithoutInvoiceIdUsesTokenOnly(): void
    {
        $secret = 'my-secret-key';
        $token = 'abc123';
        $signature = hash_hmac('sha256', $token, $secret);

        self::assertTrue(WebpayDirecto\PaymentProcessor::verifyCallbackSignature($secret, $token, null, $signature));
    }

    public function testVerifyCallbackSignatureFailsForInvalidSignature(): void
    {
        self::assertFalse(WebpayDirecto\PaymentProcessor::verifyCallbackSignature('secret', 'token', 1, 'bad-signature'));
    }
}
