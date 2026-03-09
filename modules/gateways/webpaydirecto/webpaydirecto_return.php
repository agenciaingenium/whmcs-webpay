<?php

use WebpayDirecto\Config;
use WebpayDirecto\TransbankApi;

require_once __DIR__ . '/lib/Config.class.php';
require_once __DIR__ . '/lib/TransbankApi.class.php';

include '../../../includes/functions.php';
include '../../../includes/gatewayfunctions.php';
include '../../../includes/invoicefunctions.php';

if (file_exists('../../../dbconnect.php')) {
    include '../../../dbconnect.php';
} elseif (file_exists('../../../init.php')) {
    include '../../../init.php';
}

$redirectToResult = function (int $invoiceId, string $status) {
    global $CONFIG;
    $url = rtrim((string) $CONFIG['SystemURL'], '/') .
        '/modules/gateways/webpaydirecto/webpaydirecto_result.php?invoiceid=' . $invoiceId .
        '&status=' . rawurlencode($status);
    header('Location: ' . $url);
    exit;
};

try {
    $gatewayParams = getGatewayVariables(Config::GATEWAY_NAME);
    if (empty($gatewayParams['type'])) {
        throw new Exception('Gateway no configurado en WHMCS.');
    }

    $environment = $gatewayParams['environment'] ?? 'TEST';
    $baseUrl = Config::ENDPOINTS[$environment] ?? Config::ENDPOINTS['TEST'];
    $api = new TransbankApi((string) $gatewayParams['apiKey'], (string) $gatewayParams['apiSecret'], $baseUrl);

    $tokenWs = trim((string) (filter_input(INPUT_POST, 'token_ws', FILTER_UNSAFE_RAW)
        ?: filter_input(INPUT_GET, 'token_ws', FILTER_UNSAFE_RAW)));

    if (!empty($tokenWs)) {
        $commitResponse = $api->commitTransaction($tokenWs);
        logModuleCall(Config::GATEWAY_NAME, 'commitTransaction', ['token_ws' => $tokenWs], $commitResponse, null, null);

        $sessionId = (string) ($commitResponse['session_id'] ?? '');
        $buyOrder = (string) ($commitResponse['buy_order'] ?? '');

        $invoiceId = (int) preg_replace('/\D+/', '', $sessionId);
        if ($invoiceId <= 0) {
            $invoiceId = (int) preg_replace('/\D+/', '', $buyOrder);
        }

        if ($invoiceId <= 0) {
            throw new Exception('No se pudo resolver invoiceId desde session_id/buy_order.');
        }

        checkCbInvoiceID($invoiceId, Config::GATEWAY_NAME);

        $status = (string) ($commitResponse['status'] ?? '');
        $responseCode = (int) ($commitResponse['response_code'] ?? -1);

        if ($status === Config::STATUS_AUTHORIZED && $responseCode === 0) {
            $amount = (float) ($commitResponse['amount'] ?? 0);
            $transactionId = $tokenWs;

            checkCbTransID($transactionId);
            addInvoicePayment($invoiceId, $transactionId, $amount, 0, Config::GATEWAY_NAME);
            logTransaction(Config::GATEWAY_NAME, $commitResponse, 'Pago autorizado y registrado');
            $redirectToResult($invoiceId, 'authorized');
        } else {
            logTransaction(Config::GATEWAY_NAME, $commitResponse, 'Transacción rechazada o abortada');
            $redirectToResult($invoiceId, 'failed');
        }
    }

    $tbkSession = trim((string) (filter_input(INPUT_POST, 'TBK_ID_SESION', FILTER_UNSAFE_RAW)
        ?: filter_input(INPUT_GET, 'TBK_ID_SESION', FILTER_UNSAFE_RAW)));

    $invoiceId = (int) preg_replace('/\D+/', '', $tbkSession);
    if ($invoiceId > 0) {
        logTransaction(Config::GATEWAY_NAME, $_REQUEST, 'Retorno abortado por usuario');
        $redirectToResult($invoiceId, 'aborted');
    }

    throw new Exception('Retorno de Transbank sin token_ws ni sesión válida.');
} catch (Throwable $e) {
    logActivity('webpaydirecto return error: ' . $e->getMessage());
    global $CONFIG;
    $errorUrl = rtrim((string) $CONFIG['SystemURL'], '/') . '/modules/gateways/webpaydirecto/webpaydirecto_error.php';
    header('Location: ' . $errorUrl);
    exit;
}
