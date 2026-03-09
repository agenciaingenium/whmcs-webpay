# Clevers Webpay for WHMCS

Gateway module para WHMCS que integra Webpay Directo (REST) con `apiKey` y `apiSecret`.

## Incluye

- Creación de transacción (`create`) y redirección automática a Webpay
- Confirmación (`commit`) en retorno `token_ws`
- Registro automático de pago en factura (`addInvoicePayment`)
- Manejo de estados: autorizado, rechazado, abortado
- Normalización de montos CLP sin decimales (ej. `16535,24` -> `16535`)

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

## Archivos principales

- `modules/gateways/webpaydirecto.php`
- `modules/gateways/webpaydirecto/lib/Intermediate.php`
- `modules/gateways/webpaydirecto/webpaydirecto_return.php`
