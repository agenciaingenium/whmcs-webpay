<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/AmountNormalizer.class.php';

final class AmountNormalizerTest extends TestCase
{
    public function testParseSpanishDecimalAmount(): void
    {
        self::assertSame(16535.24, WebpayDirecto\AmountNormalizer::parse('16535,24'));
    }

    public function testNormalizeClpRoundsWithoutDecimals(): void
    {
        self::assertSame(16535, WebpayDirecto\AmountNormalizer::normalize('16535,24', 'CLP'));
    }

    public function testNormalizeUsdKeepsTwoDecimals(): void
    {
        self::assertSame(16535.24, WebpayDirecto\AmountNormalizer::normalize('16.535,24', 'USD'));
    }
}
