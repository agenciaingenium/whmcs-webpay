# Clevers Webpay for WHMCS

![CI](https://github.com/agenciaingenium/whmcs-webpay/actions/workflows/ci.yml/badge.svg?branch=main)

Gateway module para WHMCS que integra Webpay Directo (REST) con `apiKey` y `apiSecret`.

## Incluye

- Creación de transacción (`create`) y redirección automática a Webpay
- Confirmación (`commit`) en retorno `token_ws`
- Endpoint callback server-to-server con firma HMAC
- Registro automático de pago en factura (`addInvoicePayment`)
- Manejo de estados: autorizado, rechazado, abortado
- Normalización de montos CLP sin decimales (ej. `16535,24` -> `16535`)
- Idempotencia persistente en tabla `mod_clevers_webpay_tx`

## Soporte de versiones

Baseline técnico recomendado para este módulo:

- **PHP:** 7.4, 8.1, 8.2 y 8.3 (verificado en CI).
- **WHMCS:** 8.x.
- **Integración:** API REST de Webpay Directo de Transbank.

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

### 5) Guía corta de rollback operativo (PROD)

Usar este procedimiento cuando haya incidentes críticos (alta tasa de rechazo, pagos no aplicados o errores 5xx sostenidos) después de un cambio en producción.

#### Pasos exactos

1. **Declarar incidente y congelar cambios.**
   - Crear/actualizar ticket de incidente y avisar a soporte/finanzas.
   - Detener despliegues y cambios de configuración durante el rollback.
2. **Respaldar evidencia antes del rollback.**
   - Exportar `Gateway Log` del rango afectado.
   - Guardar muestra de `token_ws`, `invoiceid` y errores observados.
3. **Desactivar Clevers Webpay en WHMCS.**
   - Ir a `Setup > Payments > Payment Gateways`.
   - Cambiar el gateway de checkout al proveedor anterior (o desactivar temporalmente Webpay).
4. **Revertir código/configuración al último estado estable.**
   - Restaurar release/tag anterior del módulo en servidor.
   - Si aplica, restaurar también configuración previa (`Ambiente`, secretos y callback).
5. **Validar endpoint y cache operacional.**
   - Confirmar que el checkout ya no enruta al flujo incidentado.
   - Limpiar OPcache/cache de aplicación si el stack lo requiere.
6. **Comunicar fin de rollback.**
   - Registrar hora exacta de rollback y versión restaurada.
   - Informar a soporte que se reanudó operación en modo estable.

#### Verificación post rollback (obligatoria)

- [ ] Checkout funcional con el gateway activo tras rollback.
- [ ] Nueva transacción de prueba completada según flujo esperado.
- [ ] No aparecen errores 5xx recientes en `Gateway Log` ni en logs de PHP/web server.
- [ ] No hay incrementos anómalos de rechazos respecto de la línea base previa.
- [ ] Conciliación rápida: pagos autorizados recientes están aplicándose en WHMCS.
- [ ] Ticket del incidente actualizado con causa preliminar, impacto y próximos pasos.

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
