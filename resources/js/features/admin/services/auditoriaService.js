import api from '@/services/api.js';

/**
 * Servicio API para la consulta de la Bitácora de Auditoría inalterable (US-ADM-04 / CC-124 / CC-125).
 * Permite consultar el historial de eventos y cambios del sistema en modo de solo lectura.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Administración y Seguridad – US-ADM-04 (CC-124, CC-125) / RF-ADM-008
 */

/**
 * Consulta la bitácora de auditoría con filtros y paginación.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-125
 *
 * @param   {object} [filtros={}] Parámetros de filtrado: usuario_id, accion, entidad, desde, hasta, por_pagina, page.
 * @returns {Promise<{
 *   current_page: number,
 *   data: Array<object>,
 *   last_page: number,
 *   per_page: number,
 *   total: number
 * }>} Respuesta paginada devuelta por la API.
 */
export async function obtenerAuditorias(filtros = {}) {
  const respuesta = await api.get('/auditorias', { params: filtros });
  return respuesta.data.data;
}

export default {
  obtenerAuditorias,
};
