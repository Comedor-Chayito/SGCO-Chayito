import api from '@/services/api.js';

/**
 * Servicio HTTP del módulo Caja — retiros parciales de efectivo.
 * Todas las llamadas al backend pasan por este módulo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002
 */

/**
 * Registra un retiro parcial de efectivo en el turno actual.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002 (CC-37)
 *
 * @param   {{ monto: number, motivo: string }} datos Datos del retiro a registrar.
 * @returns {Promise<Object>} Retiro creado con id, monto, motivo y created_at.
 */
export async function registrarRetiro(datos) {
  const respuesta = await api.post('/retiros', datos);
  return respuesta.data.data;
}

/**
 * Obtiene los retiros del turno actual (día comercial) del usuario autenticado.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002 (CC-37)
 *
 * @returns {Promise<Array>} Lista de retiros con id, monto, motivo y created_at.
 */
export async function obtenerRetirosTurno() {
  const respuesta = await api.get('/retiros');
  return respuesta.data.data;
}

/**
 * Registra la apertura de caja y el saldo inicial por denominación del turno.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-39)
 *
 * @param   {{ fecha?: string, denominaciones: Array<{denominacion: number, tipo: string, cantidad: number}>, observaciones?: string }} datos
 * @returns {Promise<Object>} Apertura creada con id, saldo_inicial, denominaciones, fecha y usuario.
 */
export async function registrarAperturaCaja(datos) {
  const respuesta = await api.post('/aperturas-caja', datos);
  return respuesta.data.data;
}

/**
 * Consulta si existe una apertura de caja registrada para el turno de hoy.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-39)
 *
 * @param   {string} [fecha] Fecha opcional a consultar (YYYY-MM-DD).
 * @returns {Promise<Object|null>} Apertura de caja del turno o null si no se ha abierto.
 */
export async function obtenerAperturaTurno(fecha) {
  const params = fecha ? { fecha } : {};
  const respuesta = await api.get('/aperturas-caja/turno', { params });
  return respuesta.data.data;
}

