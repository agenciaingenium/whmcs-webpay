<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TestSupport\FakeCapsule;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/TransactionStore.class.php';

final class TransactionStoreTest extends TestCase
{
    protected function setUp(): void
    {
        FakeCapsule::reset();
    }

    public function testMarkCommitAttemptIsIdempotentPerToken(): void
    {
        WebpayDirecto\TransactionStore::markCommitAttempt('token-1', 'callback');
        WebpayDirecto\TransactionStore::markCommitAttempt('token-1', 'callback');

        $rows = FakeCapsule::$tables[WebpayDirecto\TransactionStore::TABLE] ?? [];
        self::assertCount(1, $rows);
        self::assertSame(2, $rows[0]['commit_attempts']);
    }

    public function testSaveCommitResultMarksPaymentRecorded(): void
    {
        WebpayDirecto\TransactionStore::saveCommitResult('token-2', [
            'invoice_id' => 12,
            'payment_recorded' => true,
            'status' => 'AUTHORIZED',
        ]);

        self::assertTrue(WebpayDirecto\TransactionStore::isPaymentRecorded('token-2'));
    }
}
