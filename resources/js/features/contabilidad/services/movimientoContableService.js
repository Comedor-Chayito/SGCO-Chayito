import api from '@/services/api.js';

/**
 * Servicio HTTP del libro de movimientos contables: ventas cobradas (ingresos) y egresos.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Contabilidad – RF-CON-006
 */

/**
 * Consulta una página del libro de movimientos con sus totales de todo el filtro.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Contabilidad – RF-CON-006
 *
 * @param   {{ desde?: string, hasta?: string, tipo?: string }} filtros Rango (AAAA-MM-DD) y
 *          tipo ('ingreso' o 'egreso'), todos opcionales.
 * @param   {number} [pagina=1] Número de página a consultar.
 * @returns {Promise<{ movimientos: Object, totales: Object }>} Página y totales del filtro.
 */
export async function listarMovimientosContables(filtros = {}, pagina = 1) {
  const params = { page: pagina };

  Object.entries(filtros).forEach(([clave, valor]) => {
    if (valor !== null && valor !== undefined && valor !== '') params[clave] = valor;
  });

  const respuesta = await api.get('/movimientos-contables', { params });
  return respuesta.data.data;
}
