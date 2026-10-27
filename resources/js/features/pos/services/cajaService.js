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

/**
 * Ejecuta el cierre diario de caja enviando saldo inicial, saldo final y observaciones.
 * El backend consolida automáticamente ventas y retiros del día.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 *
 * @param   {{ fecha: string, saldo_inicial: number, saldo_final: number, observaciones?: string }} datos
 * @returns {Promise<Object>} Registro del cierre creado con los totales consolidados.
 */
export async function cerrarCaja(datos) {
  const respuesta = await api.post('/cierres-caja', datos);
  return respuesta.data.data;
}

/**
 * Obtiene el reporte de cierre de caja registrado para una fecha específica.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 *
 * @param   {string} fecha Fecha en formato YYYY-MM-DD.
 * @returns {Promise<Object>} Datos del cierre con usuario y métricas calculadas.
 */
export async function obtenerCierre(fecha) {
  const respuesta = await api.get(`/cierres-caja/${fecha}`);
  return respuesta.data.data;
}

/**
 * Obtiene el historial paginado de cierres de caja.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 *
 * @param   {number} [porPagina=15] Cantidad de registros por página.
 * @param   {number} [pagina=1] Número de página a consultar.
 * @returns {Promise<Object>} Objeto paginado con la colección de cierres de caja.
 */
export async function historialCierres(porPagina = 15, pagina = 1) {
  const respuesta = await api.get('/cierres-caja', {
    params: { por_pagina: porPagina, page: pagina },
  });
  return respuesta.data.data;
}
