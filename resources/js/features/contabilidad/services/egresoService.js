import api from '@/services/api.js';

/**
 * Servicio HTTP del módulo Contabilidad — egresos y su catálogo de subcategorías.
 * Todas las llamadas al backend pasan por este módulo.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005
 */

/**
 * Obtiene las subcategorías activas con que se clasifica un egreso.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 / CC-62
 *
 * @returns {Promise<Array<{ id: number, nombre: string }>>} Subcategorías ordenadas por nombre.
 */
export async function obtenerSubcategoriasEgreso() {
  const respuesta = await api.get('/subcategorias-egreso');
  return respuesta.data.data ?? [];
}

/**
 * Registra la factura de un proveedor como egreso.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 / CC-62
 *
 * @param   {{
 *   subcategoria_egreso_id: number,
 *   proveedor: string,
 *   numero_comprobante: string,
 *   fecha_comprobante: string,
 *   monto_total: number,
 *   descripcion?: string,
 * }} datos Datos de la factura.
 * @returns {Promise<Object>} Egreso creado con su correlativo.
 */
export async function registrarEgreso(datos) {
  const respuesta = await api.post('/egresos', datos);
  return respuesta.data.data;
}

/**
 * Obtiene el historial paginado de egresos con su desglose de IVA y los totales del filtro.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Contabilidad – RF-CON-006 / CC-69
 *
 * @param   {{
 *   desde?: string,
 *   hasta?: string,
 *   subcategoria_egreso_id?: number|null,
 * }} filtros Rango de fechas (AAAA-MM-DD) y subcategoría, todos opcionales.
 * @param   {number} [pagina=1] Número de página a consultar.
 * @returns {Promise<{ egresos: Object, totales: Object }>} Página de egresos y totales del filtro.
 */
export async function listarEgresos(filtros = {}, pagina = 1) {
  const params = { page: pagina };

  Object.entries(filtros).forEach(([clave, valor]) => {
    if (valor !== null && valor !== undefined && valor !== '') params[clave] = valor;
  });

  const respuesta = await api.get('/egresos', { params });
  return respuesta.data.data;
}

/**
 * Convierte un mes (AAAA-MM) en el rango de su primer y último día.
 * Arma las fechas como texto para no desplazar el día por zona horaria.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Contabilidad – RF-CON-006 / CC-69
 *
 * @param   {string} mes Mes en formato AAAA-MM.
 * @returns {{ desde: string, hasta: string }} Primer y último día del mes en AAAA-MM-DD.
 */
export function rangoDelMes(mes) {
  const [anio, numeroMes] = mes.split('-').map(Number);
  // El día 0 del mes siguiente es el último día de este mes
  const ultimoDia = new Date(anio, numeroMes, 0).getDate();

  return { desde: `${mes}-01`, hasta: `${mes}-${String(ultimoDia).padStart(2, '0')}` };
}
