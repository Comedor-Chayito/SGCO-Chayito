import api from '@/services/api.js';

/**
 * Servicio HTTP para consultar los KPIs financieros del dashboard gerencial.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
 */

/**
 * Obtiene ingresos, gastos netos, utilidad y margen bruto del periodo indicado.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 *
 * @param {{ fecha_desde?: string, fecha_hasta?: string }} filtros Rango de fechas opcional.
 * @returns {Promise<Object>} Periodo consultado y KPIs financieros consolidados.
 */
export async function obtenerDashboardGerencial(filtros = {}) {
  const respuesta = await api.get('/reportes/dashboard', { params: filtros });
  return respuesta.data.data;
}
