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

## Deployment y operación

### 1) Configuración de webhook en Transbank

Para este módulo, el webhook corresponde al callback server-to-server de WHMCS:

`https://TU-DOMINIO-WHMCS/modules/gateways/callback/webpaydirecto.php`

Recomendaciones:

- Usa siempre `https` con certificado TLS válido.
- Define un `Callback Secret` robusto y distinto entre `TEST` y `PROD`.
- Asegura conectividad desde internet pública hacia el endpoint (sin bloqueos de WAF/firewall para Transbank).
- Monitorea respuestas no `200` del callback y registra `invoiceid`/`token_ws` para trazabilidad.

Validación sugerida (firma HMAC):

```bash
TOKEN_WS="abc123"
INVOICE_ID="10"
SECRET="tu-callback-secret"
SIG=$(printf "%s" "${TOKEN_WS}|${INVOICE_ID}" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

curl -X POST "https://tu-whmcs/modules/gateways/callback/webpaydirecto.php" \
  -H "X-Clevers-Signature: ${SIG}" \
  -d "token_ws=${TOKEN_WS}" \
  -d "invoiceid=${INVOICE_ID}"
```

### 2) Pruebas con ambiente TEST

Checklist mínimo de QA en `TEST`:

- Flujo exitoso: pago aprobado y factura marcada como pagada en WHMCS.
- Flujo rechazado/abortado: la factura no debe marcarse como pagada.
- Reintento de callback con mismo `token_ws`: debe respetar idempotencia (sin doble pago).
- Verificación de montos CLP sin decimales en creación y confirmación de transacción.
- Validación de retorno de usuario (`webpaydirecto_return.php`) y callback server-to-server en paralelo.

Buenas prácticas:

- Ejecuta pruebas con facturas de distintos montos y estados.
- Usa datos de prueba de Transbank y evita mezclar credenciales de `PROD`.
- Documenta evidencia (capturas, `token_ws`, `invoiceid`, estado final de factura).

### 3) Checklist pre-producción

Antes de habilitar `PROD`, valida:

- [ ] Credenciales productivas (`API Key` / `API Secret`) cargadas correctamente.
- [ ] `Ambiente` configurado en `PROD`.
- [ ] `Callback Secret` productivo definido y resguardado.
- [ ] Endpoint callback accesible por `https` y probado manualmente.
- [ ] Cron de WHMCS operativo y zona horaria revisada para correlación de eventos.
- [ ] Permisos de archivos y backups al día.
- [ ] Plan de rollback definido (volver a gateway anterior o desactivar módulo).
- [ ] Equipo de soporte informado sobre códigos de rechazo y procedimiento de conciliación.

### 4) Logs y monitoreo

Puntos de observabilidad recomendados:

- `Utilities > Logs > Gateway Log` en WHMCS para request/response del gateway.
- Logs de PHP/web server (`php-fpm`, `apache` o `nginx`) para errores de runtime.
- Registro interno de transacciones (`mod_clevers_webpay_tx`) para auditoría e idempotencia.

Alertas sugeridas:

- Tasa de rechazo fuera de umbral esperado.
- Errores HTTP 4xx/5xx en callback.
- Diferencias entre transacciones autorizadas y facturas pagadas.

Operación diaria:

- Reconciliar pagos autorizados vs. facturas aplicadas.
- Revisar callbacks fallidos y reintentar de forma controlada.
- Rotar secretos cuando corresponda y actualizar documentación operativa.

## Callback server-to-server

Endpoint WHMCS:

`modules/gateways/callback/webpaydirecto.php`

Parámetros esperados:

- `token_ws` (obligatorio)
- `invoiceid` (opcional, recomendado para firma)
- Header `X-Clevers-Signature` o parámetro `signature` (obligatorio)

Firma:

- Base: `token_ws|invoiceid` (si no hay `invoiceid`, usa solo `token_ws`)
- Algoritmo: `HMAC-SHA256`
- Clave: `Callback Secret` del gateway

Ejemplo:

```bash
TOKEN_WS="abc123"
INVOICE_ID="10"
SECRET="tu-callback-secret"
SIG=$(printf "%s" "${TOKEN_WS}|${INVOICE_ID}" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')

curl -X POST "https://tu-whmcs/modules/gateways/callback/webpaydirecto.php" \
  -H "X-Clevers-Signature: ${SIG}" \
  -d "token_ws=${TOKEN_WS}" \
  -d "invoiceid=${INVOICE_ID}"
```

## Archivos principales

- `modules/gateways/webpaydirecto.php`
- `modules/gateways/webpaydirecto/lib/Intermediate.php`
- `modules/gateways/webpaydirecto/webpaydirecto_return.php`
