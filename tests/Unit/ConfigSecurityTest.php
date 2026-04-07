<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto/lib/Config.class.php';
require_once __DIR__ . '/../../modules/gateways/webpaydirecto.php';

final class ConfigSecurityTest extends TestCase
{
    public function testCallbackSecretStrengthValidation(): void
    {
        self::assertFalse(WebpayDirecto\Config::isCallbackSecretStrong('short-secret'));

        $strongSecret = 'Yq7!vK2#hL9@pN3$wT5%rX8&bM4*Qz6?';
        self::assertTrue(WebpayDirecto\Config::isCallbackSecretStrong($strongSecret));
    }

    public function testSecurityMessagesWarnWhenProdWithoutStrongSecret(): void
    {
        $messages = webpaydirecto_securityMessages('PROD', 'weak-secret');

        self::assertNotEmpty($messages);
        self::assertStringContainsString('PROD', implode(' ', $messages));
    }
}
