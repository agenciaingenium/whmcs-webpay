<?php

require_once __DIR__ . '/webpaydirecto/lib/Config.class.php';

use WebpayDirecto\Config;

function webpaydirecto_MetaData()
{
    return [
        'DisplayName' => 'Clevers Webpay',
        'ApiVersion' => '1.0',
        'DisableLocalCredCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

function webpaydirecto_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Clevers Webpay',
        ],
        'environment' => [
            'FriendlyName' => 'Ambiente',
            'Type' => 'dropdown',
            'Options' => Config::MODES,
            'Description' => 'Selecciona ambiente de integración o producción.',
        ],
        'apiKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => 60,
            'Default' => '',
            'Description' => 'Tbk-Api-Key-Id entregado por Transbank.',
        ],
        'apiSecret' => [
            'FriendlyName' => 'API Secret',
            'Type' => 'text',
            'Size' => 80,
            'Default' => '',
            'Description' => 'Tbk-Api-Key-Secret entregado por Transbank.',
        ],
        'callbackSecret' => [
            'FriendlyName' => 'Callback Secret',
            'Type' => 'text',
            'Size' => 80,
            'Default' => '',
            'Description' => 'Clave HMAC para endpoint server-to-server (opcional, recomendado).',
        ],
        'callbackWindowSeconds' => [
            'FriendlyName' => 'Callback Window (s)',
            'Type' => 'text',
            'Size' => 8,
            'Default' => '300',
            'Description' => 'Ventana máxima en segundos para validar timestamp anti-replay (default: 300).',
        ],
    ];
}

function webpaydirecto_link($params)
{
    $intermediateUrl = $params['systemurl'] . 'modules/gateways/webpaydirecto/lib/Intermediate.php';
    $payNowText = htmlspecialchars($params['langpaynow'], ENT_QUOTES, 'UTF-8');

    $fields = [
        'invoiceid' => $params['invoiceid'],
        'amount' => $params['amount'],
        'currency' => $params['currency'],
    ];

    $hiddenData = '';
    foreach ($fields as $key => $value) {
        $safeKey = htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8');
        $safeValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        $hiddenData .= "<input type=\"hidden\" name=\"{$safeKey}\" value=\"{$safeValue}\">";
    }

    return "<form method='POST' action='{$intermediateUrl}'>{$hiddenData}<button type='submit' class='btn btn-primary'>{$payNowText}</button></form>";
}
