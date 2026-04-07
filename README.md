# Clevers Webpay for WHMCS

Gateway module para WHMCS que integra Webpay Directo (REST) con `apiKey` y `apiSecret`.

## Incluye

- Creación de transacción (`create`) y redirección automática a Webpay
- Confirmación (`commit`) en retorno `token_ws`
- Endpoint callback server-to-server con firma HMAC
- Registro automático de pago en factura (`addInvoicePayment`)
- Manejo de estados: autorizado, rechazado, abortado
- Normalización de montos CLP sin decimales (ej. `16535,24` -> `16535`)
- Idempotencia persistente en tabla `mod_clevers_webpay_tx`

## Compatibilidad de templates

- `six`
- `twenty-one`
- `lara`

Nota: Lara Theme de WHMCS Marketplace es principalmente tema de administración; este módulo no modifica vistas de admin y funciona de forma independiente.

## Instalación

1. Copia el contenido de esta carpeta en la raíz de WHMCS.
2. Ve a `Setup > Payments > Payment Gateways`.
3. Activa `Clevers Webpay`.
4. Configura:
   - `Ambiente` (`TEST` o `PROD`)
   - `API Key` (`Tbk-Api-Key-Id`)
   - `API Secret` (`Tbk-Api-Key-Secret`)
   - `Callback Secret` (recomendado para endpoint server-to-server)

## Callback server-to-server

Endpoint WHMCS:

`modules/gateways/callback/webpaydirecto.php`

Parámetros esperados:

- `token_ws` (obligatorio)
- `invoiceid` (opcional, recomendado para firma)
- `timestamp` (obligatorio, unix epoch en segundos)
- Header `X-Clevers-Signature` o parámetro `signature` (obligatorio)
  - También puede enviarse `X-Clevers-Timestamp` en header

Firma:

- Base: `token_ws|invoiceid|timestamp` (si no hay `invoiceid`, usa `token_ws|timestamp`)
- Algoritmo: `HMAC-SHA256`
- Clave: `Callback Secret` del gateway
- Anti-replay: se valida ventana configurable (`Callback Window (s)`, default 300)

Ejemplo:

```bash
TOKEN_WS="abc123"
INVOICE_ID="10"
SECRET="tu-callback-secret"
TIMESTAMP=$(date +%s)
SIG=$(printf "%s" "${TOKEN_WS}|${INVOICE_ID}|${TIMESTAMP}" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

curl -X POST "https://tu-whmcs/modules/gateways/callback/webpaydirecto.php" \
  -H "X-Clevers-Signature: ${SIG}" \
  -H "X-Clevers-Timestamp: ${TIMESTAMP}" \
  -d "token_ws=${TOKEN_WS}" \
  -d "invoiceid=${INVOICE_ID}" \
  -d "timestamp=${TIMESTAMP}"
```

## Archivos principales

- `modules/gateways/webpaydirecto.php`
- `modules/gateways/webpaydirecto/lib/Intermediate.php`
- `modules/gateways/webpaydirecto/webpaydirecto_return.php`
