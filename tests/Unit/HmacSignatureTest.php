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

    public function testVerifyTimedCallbackSignatureWithKnownVector(): void
    {
        $secret = 'my-secret-key';
        $token = 'abc123';
        $invoiceId = 10;
        $timestamp = '1710000000';
        $signature = hash_hmac('sha256', 'abc123|10|1710000000', $secret);

        self::assertTrue(WebpayDirecto\PaymentProcessor::verifyTimedCallbackSignature(
            $secret,
            $token,
            $invoiceId,
            $timestamp,
            $signature,
            300,
            1710000200
        ));
    }

    public function testVerifyTimedCallbackSignatureFailsWhenPayloadAltered(): void
    {
        $secret = 'my-secret-key';
        $signature = hash_hmac('sha256', 'abc123|10|1710000000', $secret);

        self::assertFalse(WebpayDirecto\PaymentProcessor::verifyTimedCallbackSignature(
            $secret,
            'abc123',
            11,
            '1710000000',
            $signature,
            300,
            1710000050
        ));
    }

    public function testVerifyTimedCallbackSignatureFailsOutsideReplayWindow(): void
    {
        $secret = 'my-secret-key';
        $signature = hash_hmac('sha256', 'abc123|10|1710000000', $secret);

        self::assertFalse(WebpayDirecto\PaymentProcessor::verifyTimedCallbackSignature(
            $secret,
            'abc123',
            10,
            '1710000000',
            $signature,
            300,
            1710000601
        ));
    }
}
