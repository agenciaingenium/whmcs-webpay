<?php

use WebpayDirecto\Config;
use WebpayDirecto\PaymentProcessor;

require_once __DIR__ . '/lib/Config.class.php';
require_once __DIR__ . '/lib/TransbankApi.class.php';
require_once __DIR__ . '/lib/TransactionStore.class.php';
require_once __DIR__ . '/lib/PaymentProcessor.class.php';

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
    $tokenWs = trim((string) (filter_input(INPUT_POST, 'token_ws', FILTER_UNSAFE_RAW)
        ?: filter_input(INPUT_GET, 'token_ws', FILTER_UNSAFE_RAW)));

    if (!empty($tokenWs)) {
        $result = PaymentProcessor::processCommitToken($tokenWs, 'return');
        $redirectToResult((int) $result['invoiceId'], $result['authorized'] ? 'authorized' : 'failed');
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
