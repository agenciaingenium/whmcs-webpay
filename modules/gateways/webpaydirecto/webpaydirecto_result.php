<?php

use WHMCS\ClientArea;

define('CLIENTAREA', true);

require '../../../init.php';

$status = strtolower(trim((string) (filter_input(INPUT_GET, 'status', FILTER_UNSAFE_RAW) ?: 'unknown')));
$invoiceId = (int) preg_replace('/\D+/', '', (string) (filter_input(INPUT_GET, 'invoiceid', FILTER_UNSAFE_RAW) ?: '0'));

$title = 'Resultado del pago';
$message = 'No se pudo determinar el estado del pago.';

if ($status === 'authorized') {
    $title = 'Pago exitoso';
    $message = 'Tu pago con Webpay fue autorizado correctamente.';
} elseif ($status === 'failed') {
    $title = 'Pago rechazado';
    $message = 'La transacción fue rechazada por Webpay o no pudo autorizarse.';
} elseif ($status === 'aborted') {
    $title = 'Pago cancelado';
    $message = 'El flujo de pago fue cancelado antes de completarse.';
}

$invoiceUrl = rtrim((string) $CONFIG['SystemURL'], '/') . '/viewinvoice.php';
if ($invoiceId > 0) {
    $invoiceUrl .= '?id=' . $invoiceId;
}

$ca = new ClientArea();
$ca->setPageTitle($title);
$ca->addToBreadCrumb('index.php', Lang::trans('globalsystemname'));
$ca->addToBreadCrumb('viewinvoice.php', 'Factura');
$ca->assign('resultTitle', $title);
$ca->assign('resultMessage', $message);
$ca->assign('invoiceUrl', $invoiceUrl);
$ca->setTemplate('webpaydirecto_result');
$ca->output();
