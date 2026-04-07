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

## Operación segura

### Rotación de secretos (API y callback)

Frecuencia recomendada:

- **PROD:** cada 90 días o inmediatamente ante sospecha de filtración.
- **TEST:** cada 30-60 días para mantener el procedimiento entrenado.

Procedimiento sugerido:

1. Genera nuevas credenciales en Webpay (API Key/API Secret) y un nuevo `Callback Secret`.
2. Registra fecha/hora de rotación, responsable y ticket de cambio.
3. En una ventana controlada:
   - Actualiza primero ambiente `TEST` y valida flujo completo.
   - Luego aplica en `PROD` en horario de bajo tráfico.
4. Ejecuta pruebas de humo:
   - Crear transacción desde checkout.
   - Confirmar retorno `token_ws`.
   - Validar callback firmado (`X-Clevers-Signature`) con pago aplicado en factura.
5. Monitorea por 30-60 minutos:
   - errores `401/403`,
   - fallos de firma HMAC,
   - diferencias entre pago autorizado y factura aplicada.
6. Revoca credenciales anteriores y guarda evidencia del cambio.

Buenas prácticas:

- Nunca reutilizar secretos entre ambientes.
- Almacenar secretos solo en vault/gestor seguro (no en chats ni tickets públicos).
- Limitar acceso de operadores con principio de mínimo privilegio.

### Checklist de go-live (PROD)

Antes de habilitar en producción:

- [ ] Ambiente del gateway configurado en `PROD`.
- [ ] `API Key` y `API Secret` de producción validados.
- [ ] `Callback Secret` robusto (largo, aleatorio) configurado.
- [ ] HTTPS válido y forzado en el dominio de WHMCS.
- [ ] Endpoint callback accesible externamente:
  - `modules/gateways/callback/webpaydirecto.php`
- [ ] Prueba end-to-end exitosa con una factura real de bajo monto:
  - transacción creada,
  - commit autorizado,
  - callback firmado recibido,
  - `addInvoicePayment` aplicado una sola vez.
- [ ] Validación de idempotencia en `mod_clevers_webpay_tx` (sin duplicados).
- [ ] Monitoreo/logs habilitados para errores de checkout, retorno y callback.
- [ ] Runbook de soporte y responsables de on-call definidos.

Primeras 24 horas post go-live:

- [ ] Revisar cada 2-4 horas tasa de éxito de pagos.
- [ ] Revisar pagos en Webpay sin aplicación de factura en WHMCS.
- [ ] Revisar intentos de callback con firma inválida o sin `token_ws`.

### Playbook de conciliación y reintentos

Objetivo: asegurar que cada pago **autorizado en Webpay** termine aplicado en su factura WHMCS, sin duplicación.

#### 1) Conciliación diaria

1. Exportar/consultar transacciones autorizadas del día en Webpay.
2. Contrastar con facturas pagadas en WHMCS (mismo monto e identificador de orden).
3. Clasificar diferencias:
   - **A. Autorizado en Webpay, no pagado en WHMCS**
   - **B. Pagado en WHMCS sin autorización correspondiente**
   - **C. Duplicado aparente**

#### 2) Reintentos para caso A

1. Verificar en logs si el callback llegó:
   - si falló firma, regenerar firma correcta y reenviar callback;
   - si no llegó, reenviar callback firmado.
2. Si el callback no es viable, ejecutar retorno/commit manual con `token_ws`.
3. Confirmar que la factura quedó pagada una sola vez.
4. Registrar incidente y causa raíz (red, firma, configuración, timeout, etc.).

#### 3) Tratamiento de duplicados (caso C)

1. Revisar tabla `mod_clevers_webpay_tx` y `transid` de WHMCS.
2. Confirmar si hubo doble callback o doble commit.
3. Si hay doble aplicación de pago:
   - revertir el registro sobrante según proceso financiero interno,
   - dejar trazabilidad en ticket de conciliación.

#### 4) SLA operativo recomendado

- Incidencias de pago no aplicado: **atención < 30 min** en horario hábil.
- Resolución objetivo: **< 4 horas** para casos simples, **< 1 día hábil** con revisión financiera.
