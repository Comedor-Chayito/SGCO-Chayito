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
