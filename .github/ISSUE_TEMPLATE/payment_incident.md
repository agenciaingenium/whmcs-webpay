---
name: Payment incident
description: Reportar un incidente en producción que afecta pagos con Webpay
title: "[incident] "
labels: ["incident", "priority:high"]
assignees: []
---

## Severidad

<!-- Marcar con [x] la opción correcta. -->

- [ ] P0 — Pérdida de pagos o cobros duplicados
- [ ] P1 — Flujo de pago degradado (latencia alta, rechazos fuera de línea base)
- [ ] P2 — Errores intermitentes sin impacto financiero

## Resumen del incidente

<!-- Qué ocurrió y desde cuándo. -->

## Alcance

<!-- Ambiente afectado (TEST / PROD), número estimado de facturas impactadas. -->

## Datos clave

- `invoiceId` afectados:
- `token_ws` (sanitizados):
- Franja horaria (UTC o America/Santiago):
- Ambiente: `TEST` / `PROD`

## Detección

<!-- Cómo se detectó (alerta, reporte de cliente, monitoreo manual). -->

## Mitigación aplicada

<!-- Pasos ejecutados para detener el impacto. -->

## Pendiente

<!-- Acciones de remediación / comunicación a soporte o finanzas. -->

## Logs / evidencia

```text
Pegar extractos de Gateway Log, logs PHP y respuesta de Transbank sin secretos reales.
```
