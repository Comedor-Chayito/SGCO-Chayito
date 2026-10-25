import api from '@/services/api.js';

/**
 * Servicio HTTP de compras para el cálculo de IVA crédito fiscal.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 */

/**
 * Obtiene las compras y su desglose tributario calculado.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 *
 * @returns {Promise<Array>} Compras con monto total, costo neto e IVA crédito fiscal.
 */
export async function obtenerCompras() {
  const respuesta = await api.get('/compras');
  return respuesta.data.data;
}

/**
 * Registra una compra para que el backend calcule y persista su desglose de IVA.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 *
 * @param {{ fecha_compra: string, monto_total: number }} payload Datos del comprobante.
 * @returns {Promise<Object>} Compra creada con los valores tributarios calculados.
 */
export async function registrarCompra(payload) {
  const respuesta = await api.post('/compras', payload);
  return respuesta.data.data;
}
