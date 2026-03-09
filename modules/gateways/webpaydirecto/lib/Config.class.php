<?php

namespace WebpayDirecto;

class Config
{
    public const GATEWAY_NAME = 'webpaydirecto';

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
}
