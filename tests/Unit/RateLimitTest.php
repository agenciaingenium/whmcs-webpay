<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use TestSupport\FakeCapsule;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/TransactionStore.class.php';

final class RateLimitTest extends TestCase
{
    protected function setUp(): void
    {
        FakeCapsule::reset();
    }

    public function testIsCallbackRateLimitedReturnsFalseBelowThreshold(): void
    {
        WebpayDirecto\TransactionStore::recordCallbackAttempt('token-rl-1');
        WebpayDirecto\TransactionStore::recordCallbackAttempt('token-rl-1');

        self::assertFalse(WebpayDirecto\TransactionStore::isCallbackRateLimited('token-rl-1', 5, 60));
    }

    public function testIsCallbackRateLimitedReturnsTrueAtThreshold(): void
    {
        for ($i = 0; $i < 5; $i++) {
            WebpayDirecto\TransactionStore::recordCallbackAttempt('token-rl-2');
        }

        self::assertTrue(WebpayDirecto\TransactionStore::isCallbackRateLimited('token-rl-2', 5, 60));
    }

    public function testZeroMaxAttemptsNeverRateLimits(): void
    {
        for ($i = 0; $i < 100; $i++) {
            WebpayDirecto\TransactionStore::recordCallbackAttempt('token-rl-3');
        }

        self::assertFalse(WebpayDirecto\TransactionStore::isCallbackRateLimited('token-rl-3', 0, 60));
    }

    public function testCountsAreScopedPerToken(): void
    {
        for ($i = 0; $i < 5; $i++) {
            WebpayDirecto\TransactionStore::recordCallbackAttempt('token-a');
        }
        WebpayDirecto\TransactionStore::recordCallbackAttempt('token-b');

        self::assertTrue(WebpayDirecto\TransactionStore::isCallbackRateLimited('token-a', 5, 60));
        self::assertFalse(WebpayDirecto\TransactionStore::isCallbackRateLimited('token-b', 5, 60));
    }
}
