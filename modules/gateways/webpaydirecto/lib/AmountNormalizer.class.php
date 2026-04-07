<?php

namespace WebpayDirecto;

class AmountNormalizer
{
    public static function parse(string $raw): float
    {
        $value = preg_replace('/[^\d,.\-]/', '', trim($raw));
        if ($value === '' || $value === null) {
            return 0.0;
        }

        $hasComma = strpos($value, ',') !== false;
        $hasDot = strpos($value, '.') !== false;

        if ($hasComma && $hasDot) {
            $lastComma = strrpos($value, ',');
            $lastDot = strrpos($value, '.');
            if ($lastComma > $lastDot) {
                $value = str_replace('.', '', $value);
                $value = str_replace(',', '.', $value);
            } else {
                $value = str_replace(',', '', $value);
            }
        } elseif ($hasComma) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : 0.0;
    }

    public static function normalize(string $raw, string $currency)
    {
        $parsed = self::parse($raw);
        $currency = strtoupper(trim($currency));

        if ($currency === 'CLP') {
            return (int) round($parsed);
        }

        return round($parsed, 2);
    }
}
