<?php

require_once __DIR__ . '/webpaydirecto/lib/Config.class.php';

use WebpayDirecto\Config;
use WHMCS\Database\Capsule;

function webpaydirecto_getStoredSetting(string $setting, string $default = ''): string
{
    try {
        $row = Capsule::table('tblpaymentgateways')
            ->where('gateway', Config::GATEWAY_NAME)
            ->where('setting', $setting)
            ->first();

        if ($row === null) {
            return $default;
        }

        return trim((string) ($row->value ?? $default));
    } catch (\Throwable $e) {
        return $default;
    }
}

function webpaydirecto_securityMessages(string $environment, string $callbackSecret): array
{
    $messages = [];
    $issues = Config::getCallbackSecretWeaknesses($callbackSecret);

    if (in_array('vacío', $issues, true)) {
        $messages[] = 'El <strong>Callback Secret</strong> está vacío. Configura un secreto robusto para validar callbacks server-to-server.';
    } elseif (count($issues) > 0) {
        $messages[] = 'El <strong>Callback Secret</strong> es débil. Requisitos mínimos: 32+ caracteres, alta diversidad y buena entropía.';
    }

    if ($environment === 'PROD' && !Config::isCallbackSecretStrong($callbackSecret)) {
        $messages[] = 'El ambiente <strong>PROD</strong> está activo sin un Callback Secret fuerte. Esto expone la validación del callback en producción.';
    }

    return $messages;
}

function webpaydirecto_securityNoticeHtml(): string
{
    $environment = webpaydirecto_getStoredSetting('environment', 'TEST');
    $callbackSecret = webpaydirecto_getStoredSetting('callbackSecret', '');

    $messages = webpaydirecto_securityMessages($environment, $callbackSecret);
    $noticeList = '';
    if (count($messages) > 0) {
        $noticeList = '<ul style="margin:8px 0 0 20px;"><li>' . implode('</li><li>', $messages) . '</li></ul>';
    }

    $baseMessage = count($messages) > 0
        ? '<strong style="color:#b22222;">Advertencias de seguridad detectadas</strong>'
        : '<strong style="color:#2e7d32;">Configuración de seguridad válida</strong>';

    $script = <<<HTML
<script>
(function () {
    var env = document.querySelector('[name="field_environment"]');
    var secret = document.querySelector('[name="field_callbackSecret"]');
    var container = document.getElementById('webpaydirecto-security-alert');

    if (!env || !secret || !container) {
        return;
    }

    function evaluate() {
        var value = (secret.value || '').trim();
        var categories = 0;
        categories += /[a-z]/.test(value) ? 1 : 0;
        categories += /[A-Z]/.test(value) ? 1 : 0;
        categories += /[0-9]/.test(value) ? 1 : 0;
        categories += /[^a-zA-Z0-9]/.test(value) ? 1 : 0;
        var uniqueChars = new Set(value.split('')).size;
        var strong = value.length >= 32 && categories >= 3 && uniqueChars >= 10;

        var warnings = [];
        if (value.length === 0) {
            warnings.push('El Callback Secret está vacío.');
        } else if (!strong) {
            warnings.push('El Callback Secret es débil (mínimo 32 caracteres con buena diversidad).');
        }

        if (env.value === 'PROD' && !strong) {
            warnings.push('PROD activo sin Callback Secret fuerte.');
        }

        if (warnings.length) {
            container.style.background = '#fff3f3';
            container.style.border = '1px solid #e29a9a';
            container.innerHTML = '<strong style="color:#b22222;">Advertencias de seguridad detectadas</strong><ul style="margin:8px 0 0 20px;"><li>' + warnings.join('</li><li>') + '</li></ul>';
            return;
        }

        container.style.background = '#f0fbf1';
        container.style.border = '1px solid #93d3a2';
        container.innerHTML = '<strong style="color:#2e7d32;">Configuración de seguridad válida</strong>';
    }

    env.addEventListener('change', evaluate);
    secret.addEventListener('input', evaluate);
    evaluate();
})();
</script>
HTML;

    return '<div id="webpaydirecto-security-alert" style="padding:10px;border:1px solid #ddd;background:#fff;">' . $baseMessage . $noticeList . '</div>' . $script;
}

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
            'Description' => 'Clave HMAC para endpoint server-to-server. Recomendado: 32+ caracteres con alta entropía.',
        ],
        'securityNotice' => [
            'FriendlyName' => 'Estado de seguridad',
            'Type' => 'System',
            'Description' => webpaydirecto_securityNoticeHtml(),
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
