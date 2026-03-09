<?php

use WebpayDirecto\Config;
use WebpayDirecto\PaymentProcessor;

require_once __DIR__ . '/../webpaydirecto/lib/Config.class.php';
require_once __DIR__ . '/../webpaydirecto/lib/TransbankApi.class.php';
require_once __DIR__ . '/../webpaydirecto/lib/TransactionStore.class.php';
require_once __DIR__ . '/../webpaydirecto/lib/PaymentProcessor.class.php';

include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';

if (file_exists('../../../dbconnect.php')) {
    include '../../../dbconnect.php';
} elseif (file_exists('../../../init.php')) {
    include '../../../init.php';
}

header('Content-Type: application/json');

try {
    $gatewayParams = getGatewayVariables(Config::GATEWAY_NAME);
    if (empty($gatewayParams['type'])) {
        throw new Exception('Gateway no configurado en WHMCS.');
    }

    $tokenWs = trim((string) (filter_input(INPUT_POST, 'token_ws', FILTER_UNSAFE_RAW)
        ?: filter_input(INPUT_GET, 'token_ws', FILTER_UNSAFE_RAW)));
    $invoiceId = (int) preg_replace('/\D+/', '', (string) (filter_input(INPUT_POST, 'invoiceid', FILTER_UNSAFE_RAW)
        ?: filter_input(INPUT_GET, 'invoiceid', FILTER_UNSAFE_RAW)));

    if ($tokenWs === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'token_ws es obligatorio']);
        exit;
    }

    $callbackSecret = trim((string) ($gatewayParams['callbackSecret'] ?? ''));
    if ($callbackSecret === '') {
        http_response_code(503);
        echo json_encode(['ok' => false, 'message' => 'Callback secret no configurado']);
        exit;
    }

    $providedSignature = trim((string) (
        $_SERVER['HTTP_X_CLEVERS_SIGNATURE']
        ?? filter_input(INPUT_POST, 'signature', FILTER_UNSAFE_RAW)
        ?? filter_input(INPUT_GET, 'signature', FILTER_UNSAFE_RAW)
        ?? ''
    ));

    if (!PaymentProcessor::verifyCallbackSignature($callbackSecret, $tokenWs, $invoiceId ?: null, $providedSignature)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'message' => 'Firma inválida']);
        exit;
    }

    $result = PaymentProcessor::processCommitToken($tokenWs, 'callback');

    http_response_code(200);
    echo json_encode([
        'ok' => true,
        'invoiceId' => $result['invoiceId'],
        'authorized' => $result['authorized'],
        'paymentRecorded' => $result['paymentRecorded'],
    ]);
    exit;
} catch (Throwable $e) {
    logActivity('webpaydirecto callback error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error interno procesando callback']);
    exit;
}
