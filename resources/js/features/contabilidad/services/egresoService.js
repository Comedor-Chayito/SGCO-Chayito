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
