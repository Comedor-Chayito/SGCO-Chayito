import api from '@/services/api.js';

/**
 * Servicio HTTP del módulo Caja — retiros y movimientos de efectivo.
 * Todas las llamadas al backend pasan por este módulo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002
 */

/**
 * Registra un retiro o ingreso de efectivo en el turno actual.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002
 *
 * @param   {{
 *   tipo: 'ingreso'|'egreso',
 *   monto: number,
 *   detalle: string
 * }} datos Datos del movimiento a registrar.
 * @returns {Promise<Object>} Movimiento creado con id y timestamp.
 */
export async function registrarRetiro(datos) {
  const respuesta = await api.post('/retiros', datos);
  return respuesta.data.data;
}

/**
 * Obtiene la lista de retiros/ingresos del turno actual.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002
 *
 * @returns {Promise<Array>} Lista de movimientos con id, tipo,
 *   monto, detalle, created_at.
 */
export async function obtenerRetirosTurno() {
  const respuesta = await api.get('/retiros', {
    params: { turno: 'actual' },
  });
  return respuesta.data.data;
}

/**
 * Obtiene el resumen de la caja del turno actual.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002
 *
 * @returns {Promise<Object>} Resumen con saldo_inicial,
 *   ventas_del_dia, total_retiros, saldo_actual.
 */
export async function obtenerResumenCaja() {
  const respuesta = await api.get('/caja/turno');
  return respuesta.data.data;
}
