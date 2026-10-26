# Implementación de CC-66 y CC-67 — IVA Crédito Fiscal

**Autor:** Jeferson De La Cruz  
**Historia:** US-CON-02 — Desglose Automatizado de IVA Crédito Fiscal  
**Referencia ERS:** RF-CON-006  
**Fecha:** 2026-10-23

## CC-66 — Implementar fórmula de costo neto

Se creó el servicio centralizado `IvaCreditoFiscalService`, que aplica la fórmula:

```text
Costo neto = Monto total / 1.13
```

El resultado se redondea a dos decimales y se calcula exclusivamente en el backend. Así,
el cliente no puede registrar ni alterar el costo neto manualmente. Ejemplo: una compra
de `$113.00` genera un costo neto de `$100.00`.

Para la daily: “Trabajé en la CC-66, centralizando el cálculo del costo neto de cada compra
con IVA incluido. La fórmula se ejecuta en el backend y el valor queda guardado junto con el
comprobante, evitando cálculos manuales.”

## CC-67 — Implementar cálculo de IVA crédito fiscal

El mismo servicio calcula el IVA desde el costo neto exacto:

```text
IVA crédito fiscal = Costo neto × 0.13
```

Luego de calcular ambos valores, `CompraService` los guarda dentro de una transacción en la
tabla `compras`, junto con el monto total, fecha y usuario responsable. Ejemplo: una compra
de `$113.00` registra `$100.00` de costo neto y `$13.00` de IVA crédito fiscal.

Para la daily: “También completé la CC-67: el IVA crédito fiscal se deriva automáticamente
del costo neto, se persiste en la compra y queda disponible para la consulta contable. La
pantalla solo solicita fecha y monto total; el sistema controla el desglose.”

## Flujo implementado

1. Contadora o Administrador registra fecha y monto total de una compra.
2. El backend valida los datos y calcula costo neto e IVA con la tasa centralizada del 13%.
3. Se guarda el comprobante con los tres montos en la base de datos.
4. La vista de Contabilidad muestra el historial con el desglose y el usuario que lo registró.

## Puntos de prueba manual

- Ruta: `/contabilidad`
- Botón: `Registrar y calcular`
- Ejemplo: fecha actual y monto total `113.00`
- Resultado esperado: costo neto `$100.00` e IVA crédito fiscal `$13.00` en la tabla.

## Validaciones y seguridad

- Solo los roles `contadora` y `administrador` acceden a `/api/compras`.
- Se rechazan monto cero, más de dos decimales y fechas futuras.
- Los campos `costo_neto` e `iva_credito_fiscal` no son recibidos del formulario: siempre se
  recalculan en el servidor antes de persistirlos.
