<?php

namespace WebpayDirecto;

use WHMCS\Database\Capsule;

class PaymentProcessor
{
    public static function processCommitToken(string $tokenWs, string $source = 'return'): array
    {
        TransactionStore::markCommitAttempt($tokenWs, $source);

        $gatewayParams = getGatewayVariables(Config::GATEWAY_NAME);
        if (empty($gatewayParams['type'])) {
            throw new \Exception('Gateway no configurado en WHMCS.');
        }

        $environment = $gatewayParams['environment'] ?? 'TEST';
        $baseUrl = Config::ENDPOINTS[$environment] ?? Config::ENDPOINTS['TEST'];
        $api = new TransbankApi((string) $gatewayParams['apiKey'], (string) $gatewayParams['apiSecret'], $baseUrl);

        $commitResponse = $api->commitTransaction($tokenWs);
        logModuleCall(Config::GATEWAY_NAME, 'commitTransaction', ['token_ws' => $tokenWs, 'source' => $source], $commitResponse, null, null);

        $sessionId = (string) ($commitResponse['session_id'] ?? '');
        $buyOrder = (string) ($commitResponse['buy_order'] ?? '');

        $invoiceId = self::resolveInvoiceId($sessionId, $buyOrder);
        if ($invoiceId <= 0) {
            throw new \Exception('No se pudo resolver invoiceId desde session_id/buy_order.');
        }

        checkCbInvoiceID($invoiceId, Config::GATEWAY_NAME);

        $status = (string) ($commitResponse['status'] ?? '');
        $responseCode = (int) ($commitResponse['response_code'] ?? -1);
        $authorized = ($status === Config::STATUS_AUTHORIZED && $responseCode === 0);

        $paymentRecorded = false;
        if ($authorized) {
            $amount = (float) ($commitResponse['amount'] ?? 0);
            $transactionId = $tokenWs;
            $transactionAlreadyUsed = self::isTransactionIdUsed($transactionId);
            $alreadyRecordedInStore = TransactionStore::isPaymentRecorded($tokenWs);

            if (!$transactionAlreadyUsed && !$alreadyRecordedInStore) {
                addInvoicePayment($invoiceId, $transactionId, $amount, 0, Config::GATEWAY_NAME);
                $paymentRecorded = true;
                logTransaction(Config::GATEWAY_NAME, $commitResponse, 'Pago autorizado y registrado');
            } else {
                $paymentRecorded = true;
                logTransaction(Config::GATEWAY_NAME, array_merge($commitResponse, [
                    'duplicate_rejected' => true,
                    'duplicate_reason' => $transactionAlreadyUsed ? 'transaction_id_already_used' : 'payment_already_recorded',
                ]), 'Transacción duplicada rechazada');
            }
        } else {
            logTransaction(Config::GATEWAY_NAME, $commitResponse, 'Transacción rechazada o abortada');
        }

        TransactionStore::saveCommitResult($tokenWs, [
            'invoice_id' => $invoiceId,
            'buy_order' => $buyOrder,
            'amount' => (float) ($commitResponse['amount'] ?? 0),
            'currency' => (string) ($commitResponse['currency'] ?? ''),
            'status' => $status,
            'response_code' => $responseCode,
            'authorization_code' => (string) ($commitResponse['authorization_code'] ?? ''),
            'payment_recorded' => $paymentRecorded,
            'raw_response' => json_encode($commitResponse, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'source' => $source,
        ]);

        return [
            'invoiceId' => $invoiceId,
            'authorized' => $authorized,
            'paymentRecorded' => $paymentRecorded,
            'response' => $commitResponse,
        ];
    }

    public static function verifyCallbackSignature(string $secret, string $tokenWs, ?int $invoiceId, string $provided): bool
    {
        if ($secret === '') {
            return false;
        }

        $base = $tokenWs;
        if (!empty($invoiceId)) {
            $base .= '|' . $invoiceId;
        }

        $expected = hash_hmac('sha256', $base, $secret);
        return hash_equals($expected, trim($provided));
    }

    private static function resolveInvoiceId(string $sessionId, string $buyOrder): int
    {
        $invoiceId = (int) preg_replace('/\D+/', '', $sessionId);
        if ($invoiceId > 0) {
            return $invoiceId;
        }

        return (int) preg_replace('/\D+/', '', $buyOrder);
    }

    private static function isTransactionIdUsed(string $transactionId): bool
    {
        return Capsule::table('tblaccounts')
            ->where('transid', $transactionId)
            ->exists();
    }
}
