<?php

namespace WebpayDirecto;

class Config
{
    public const GATEWAY_NAME = 'webpaydirecto';
    public const MIN_CALLBACK_SECRET_LENGTH = 32;

    public const ENDPOINTS = [
        'TEST' => 'https://webpay3gint.transbank.cl',
        'PROD' => 'https://webpay3g.transbank.cl',
    ];

    public const MODES = [
        'TEST' => 'Integración (TEST)',
        'PROD' => 'Producción (PROD)',
    ];

    public const API_PATH = '/rswebpaytransaction/api/webpay/v1.2/transactions';

    public const STATUS_AUTHORIZED = 'AUTHORIZED';

    public static function isCallbackSecretStrong(?string $secret): bool
    {
        return count(self::getCallbackSecretWeaknesses($secret)) === 0;
    }

    /**
     * @return string[]
     */
    public static function getCallbackSecretWeaknesses(?string $secret): array
    {
        $candidate = trim((string) $secret);
        $issues = [];

        if ($candidate === '') {
            $issues[] = 'vacío';
            return $issues;
        }

        if (strlen($candidate) < self::MIN_CALLBACK_SECRET_LENGTH) {
            $issues[] = 'longitud';
        }

        $classes = 0;
        $classes += preg_match('/[a-z]/', $candidate) ? 1 : 0;
        $classes += preg_match('/[A-Z]/', $candidate) ? 1 : 0;
        $classes += preg_match('/[0-9]/', $candidate) ? 1 : 0;
        $classes += preg_match('/[^a-zA-Z0-9]/', $candidate) ? 1 : 0;
        if ($classes < 3) {
            $issues[] = 'diversidad';
        }

        if (count(array_unique(str_split($candidate))) < 10) {
            $issues[] = 'entropía';
        }

        return $issues;
    }
}
