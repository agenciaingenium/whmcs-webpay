<?php

use WebpayDirecto\Config;
use WebpayDirecto\TransactionStore;
use WebpayDirecto\TransbankApi;

require_once __DIR__ . '/Config.class.php';
require_once __DIR__ . '/TransactionStore.class.php';
require_once __DIR__ . '/TransbankApi.class.php';
require_once __DIR__ . '/AmountNormalizer.class.php';

include '../../../../includes/functions.php';
include '../../../../includes/gatewayfunctions.php';
include '../../../../includes/invoicefunctions.php';

if (file_exists('../../../../dbconnect.php')) {
    include '../../../../dbconnect.php';
} elseif (file_exists('../../../../init.php')) {
    include '../../../../init.php';
}

try {
    global $CONFIG;

    $gatewayParams = getGatewayVariables(Config::GATEWAY_NAME);
    if (empty($gatewayParams['type'])) {
        throw new Exception('Gateway no configurado en WHMCS.');
    }

    $invoiceId = (int) filter_input(INPUT_POST, 'invoiceid', FILTER_SANITIZE_NUMBER_INT);
    $currency = strtoupper(trim((string) filter_input(INPUT_POST, 'currency', FILTER_UNSAFE_RAW)));
    $rawAmount = trim((string) filter_input(INPUT_POST, 'amount', FILTER_UNSAFE_RAW));

    if ($invoiceId <= 0) {
        throw new Exception('Datos inválidos para crear transacción.');
    }

    $amountFloat = \WebpayDirecto\AmountNormalizer::parse($rawAmount);
    $amount = \WebpayDirecto\AmountNormalizer::normalize($rawAmount, $currency);

    if ($amount <= 0) {
        throw new Exception('Monto inválido para Transbank.');
    }

    if ($currency === 'CLP' && abs($amountFloat - $amount) > 0.00001) {
        logActivity(
            'webpaydirecto: monto normalizado a CLP sin decimales. ' .
            'invoiceId=' . $invoiceId . ', original=' . $rawAmount . ', normalizado=' . $amount
        );
    }

    $sessionId = 'INV-' . $invoiceId;
    $buyOrder = substr('INV' . $invoiceId . '-' . time(), 0, 26);
    $returnUrl = rtrim((string) $CONFIG['SystemURL'], '/') . '/modules/gateways/webpaydirecto/webpaydirecto_return.php';

    $payload = [
        'buy_order' => $buyOrder,
        'session_id' => $sessionId,
        'amount' => $amount,
        'return_url' => $returnUrl,
    ];

    $environment = $gatewayParams['environment'] ?? 'TEST';
    $baseUrl = Config::ENDPOINTS[$environment] ?? Config::ENDPOINTS['TEST'];

    $api = new TransbankApi((string) $gatewayParams['apiKey'], (string) $gatewayParams['apiSecret'], $baseUrl);
    $response = $api->createTransaction($payload);

    logModuleCall(Config::GATEWAY_NAME, 'createTransaction', $payload, $response, null, null);

    if (empty($response['token']) || empty($response['url'])) {
        throw new Exception('Transbank no devolvió token/url válidos.');
    }

    TransactionStore::recordCreate(
        $invoiceId,
        (string) $payload['buy_order'],
        (string) $response['token'],
        (float) $payload['amount'],
        (string) $currency
    );

    $url = htmlspecialchars((string) $response['url'], ENT_QUOTES, 'UTF-8');
    $token = htmlspecialchars((string) $response['token'], ENT_QUOTES, 'UTF-8');

    echo "<!doctype html><html><body>";
    echo "<form id='tbkRedirect' method='POST' action='{$url}'>";
    echo "<input type='hidden' name='token_ws' value='{$token}'>";
    echo "</form>";
    echo "<script>document.getElementById('tbkRedirect').submit();</script>";
    echo '</body></html>';
    exit;
} catch (Throwable $e) {
    logActivity('webpaydirecto Intermediate error: ' . $e->getMessage());
    $errorUrl = rtrim((string) $CONFIG['SystemURL'], '/') . '/modules/gateways/webpaydirecto/webpaydirecto_error.php';
    header('Location: ' . $errorUrl);
    exit;
}
