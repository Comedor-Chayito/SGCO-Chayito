import api from '@/services/api.js';

/**
 * Servicio HTTP del módulo POS.
 * Todas las llamadas al backend pasan por este módulo, nunca directamente desde componentes.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 */

/**
 * Obtiene la lista de platillos disponibles en el menú del día.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 *
 * @returns {Promise<Array>} Lista de platillos con id, nombre, descripcion, precio_unitario.
 */
export async function obtenerPlatillos() {
  const respuesta = await api.get('/platillos');
  return respuesta.data.data;
}

/**
 * Obtiene la lista de mesas del comedor con su estado actual.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 *
 * @returns {Promise<Array>} Lista de mesas con id, numero, capacidad, estado.
 */
export async function obtenerMesas() {
  const respuesta = await api.get('/mesas');
  return respuesta.data.data;
}

/**
 * Envía una comanda al backend para registrarla en estado "pendiente".
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 *
 * @param   {{
 *   canal: string,
 *   mesa_id: number|null,
 *   observaciones: string,
 *   items: Array<{platillo_id: number, cantidad: number, observaciones: string}>
 * }} payload Datos de la comanda a registrar.
 * @returns {Promise<Object>} Comanda creada con sus detalles.
 */
export async function registrarComanda(payload) {
  const respuesta = await api.post('/comandas', payload);
  return respuesta.data.data;
}

/**
 * Obtiene la lista de comandas registradas, opcionalmente filtradas por estado.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-001
 *
 * @param   {string} [estado] Filtro opcional por estado (pendiente, en_cocina, etc.).
 * @returns {Promise<Array>} Lista de comandas con sus detalles y mesas.
 */
export async function obtenerComandas(estado = '') {
  const params = estado ? { estado } : {};
  const respuesta = await api.get('/comandas', { params });
  return respuesta.data.data;
}

/**
 * Procesa el cobro de una comanda en caja y genera el comprobante de venta.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002
 *
 * @param   {{ comanda_id: number, monto_recibido: number, metodo_pago?: string }} payload
 * @returns {Promise<Object>} Datos del comprobante de venta, total y cambio calculado.
 */
export async function procesarCobro(payload) {
  const respuesta = await api.post('/cobros', payload);
  return respuesta.data.data;
}

/**
 * Obtiene los detalles de un comprobante de venta o ticket por su ID.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002
 *
 * @param   {number} ventaId Identificador de la venta.
 * @returns {Promise<Object>} Comprobante con desglose de ítems, totales y cambio.
 */
export async function obtenerComprobante(ventaId) {
  const respuesta = await api.get(`/cobros/${ventaId}`);
  return respuesta.data.data;
}


