import { ref, computed, toValue } from 'vue';

// Único método con monto recibido y cambio; tarjeta y transferencia van por el total exacto
export const METODO_EFECTIVO = 'efectivo';

/**
 * Composable para el cálculo reactivo del cambio y gestión del cobro (efectivo, tarjeta y
 * transferencia).
 * Soporta cálculo en tiempo real, validación de montos insuficientes, denominaciones rápidas
 * y formateo estandarizado de moneda en USD (RF-POS-002 / CC-33).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-12
 * @módulo POS – US-POS-03 / CC-33
 *
 * @param  {import('vue').MaybeRefOrGetter<number>} totalReferencia Monto total de la orden a cobrar.
 * @returns {Object} API reactiva para el cálculo del cambio.
 */
export function useCalculoCambio(totalReferencia) {
  const montoRecibido = ref('');
  const metodoPago = ref(METODO_EFECTIVO); // 'efectivo' | 'tarjeta' | 'transferencia'

  /**
   * Indica si el método seleccionado es efectivo (requiere monto recibido y cambio).
   */
  const esEfectivo = computed(() => metodoPago.value === METODO_EFECTIVO);

  /**
   * Total numérico normalizado de la orden.
   */
  const total = computed(() => {
    const val = Number(toValue(totalReferencia));
    return Number.isFinite(val) ? Math.max(0, Math.round(val * 100) / 100) : 0;
  });

  /**
   * Total formateado en moneda ($X.XX).
   */
  const totalFormateado = computed(() => `$${total.value.toFixed(2)}`);

  /**
   * Monto recibido numérico normalizado.
   */
  const montoRecibidoNumero = computed(() => {
    if (!esEfectivo.value) {
      return total.value;
    }
    const limpio = String(montoRecibido.value).replace(/[^0-9.]/g, '');
    const num = parseFloat(limpio);
    return Number.isFinite(num) ? Math.round(num * 100) / 100 : 0;
  });

  /**
   * Monto recibido formateado en moneda ($X.XX).
   */
  const montoRecibidoFormateado = computed(() => `$${montoRecibidoNumero.value.toFixed(2)}`);

  /**
   * Determina si el monto ingresado cubre la totalidad de la cuenta.
   */
  const esSuficiente = computed(() => {
    if (!esEfectivo.value) {
      return true;
    }
    return montoRecibidoNumero.value >= total.value && montoRecibidoNumero.value > 0;
  });

  /**
   * Indica si el pago recibido es exactamente igual al total.
   */
  const esMontoExacto = computed(() => {
    if (!esEfectivo.value) {
      return true;
    }
    return Math.abs(montoRecibidoNumero.value - total.value) < 0.005 && total.value > 0;
  });

  /**
   * Cambio a entregar al cliente redondeado estrictamente a 2 decimales.
   * Si el monto es insuficiente o el método no es efectivo, es 0.00.
   */
  const cambio = computed(() => {
    if (!esEfectivo.value) {
      return 0;
    }
    if (montoRecibidoNumero.value >= total.value) {
      return Math.round((montoRecibidoNumero.value - total.value) * 100) / 100;
    }
    return 0;
  });

  /**
   * Cambio formateado para visualización en pantalla ($X.XX).
   */
  const cambioFormateado = computed(() => `$${cambio.value.toFixed(2)}`);

  /**
   * Dinero faltante cuando el cliente entrega un monto inferior al total.
   */
  const montoFaltante = computed(() => {
    if (!esEfectivo.value) {
      return 0;
    }
    if (montoRecibidoNumero.value < total.value) {
      return Math.round((total.value - montoRecibidoNumero.value) * 100) / 100;
    }
    return 0;
  });

  /**
   * Dinero faltante formateado en moneda ($X.XX).
   */
  const montoFaltanteFormateado = computed(() => `$${montoFaltante.value.toFixed(2)}`);

  /**
   * Billetes y denominaciones rápidas sugeridas según el total de la orden.
   * Incluye los billetes estándar ($1, $5, $10, $20) más sugerencias automáticas
   * del siguiente número entero o billete superior.
   */
  const denominacionesSugeridas = computed(() => {
    const t = total.value;
    const base = [1, 5, 10, 20];
    const sugerencias = new Set(base.filter((d) => d >= t));

    // Si el total supera 20, agregar denominaciones mayores o múltiplos
    if (t > 20) {
      sugerencias.add(50);
      sugerencias.add(100);
    }

    // Agregar el siguiente entero redondeado hacia arriba si hay centavos
    const enteroSuperior = Math.ceil(t);
    if (enteroSuperior > t) {
      sugerencias.add(enteroSuperior);
    }

    return Array.from(sugerencias).sort((a, b) => a - b);
  });

  /**
   * Asigna el total de la orden como monto recibido (pago exacto).
   */
  function fijarExacto() {
    montoRecibido.value = total.value > 0 ? total.value.toFixed(2) : '0.00';
  }

  /**
   * Fija un monto o billete específico directamente.
   *
   * @param {number} valor Monto a fijar.
   */
  function fijarMonto(valor) {
    montoRecibido.value = Number(valor).toFixed(2);
  }

  /**
   * Suma una cantidad al monto recibido actual (atajo de billetes o monedas).
   *
   * @param {number} incremento Cantidad a incrementar.
   */
  function sumarMonto(incremento) {
    const actual = montoRecibidoNumero.value;
    montoRecibido.value = (actual + Number(incremento)).toFixed(2);
  }

  /**
   * Limpia el monto recibido.
   */
  function limpiar() {
    montoRecibido.value = '';
  }

  /**
   * Cambia el método de pago y fija el total como monto recibido si no es efectivo.
   *
   * @param {'efectivo'|'tarjeta'|'transferencia'} metodo Método seleccionado.
   */
  function seleccionarMetodo(metodo) {
    metodoPago.value = metodo;
    if (metodo !== METODO_EFECTIVO) {
      montoRecibido.value = total.value.toFixed(2);
    }
  }

  return {
    total,
    totalFormateado,
    montoRecibido,
    montoRecibidoNumero,
    montoRecibidoFormateado,
    metodoPago,
    esEfectivo,
    cambio,
    cambioFormateado,
    montoFaltante,
    montoFaltanteFormateado,
    esSuficiente,
    esMontoExacto,
    denominacionesSugeridas,
    fijarExacto,
    fijarMonto,
    sumarMonto,
    limpiar,
    seleccionarMetodo,
  };
}

