import api from '@/services/api.js';

/**
 * Servicio HTTP del módulo Inventario.
 * Centraliza las llamadas para insumos, recetas y requisiciones internas.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003 / RF-INV-004 / CC-45 / CC-46 / CC-52 / CC-53 / CC-54
 */

// ─── CC-45: Insumos ────────────────────────────────────────────────────────────

/**
 * Obtiene la lista completa de insumos del catálogo de bodega.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param   {string} [busqueda=''] Texto de búsqueda opcional por nombre.
 * @returns {Promise<Array>} Lista de insumos con id, nombre, unidad_medida, stock_actual, stock_minimo.
 */
export async function obtenerInsumos(busqueda = '') {
  const params = busqueda ? { busqueda } : {};
  const respuesta = await api.get('/insumos', { params });
  return respuesta.data.data;
}

/**
 * Crea un nuevo insumo en el catálogo de bodega.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param   {{ nombre: string, unidad_medida: string, stock_actual?: number, stock_minimo?: number }} payload
 * @returns {Promise<Object>} Insumo creado.
 */
export async function crearInsumo(payload) {
  const respuesta = await api.post('/insumos', payload);
  return respuesta.data.data;
}

/**
 * Actualiza un insumo existente por su ID.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param   {number} id      Identificador del insumo.
 * @param   {Object} payload Datos a actualizar.
 * @returns {Promise<Object>} Insumo actualizado.
 */
export async function actualizarInsumo(id, payload) {
  const respuesta = await api.put(`/insumos/${id}`, payload);
  return respuesta.data.data;
}

/**
 * Elimina un insumo del catálogo por su ID.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param   {number} id Identificador del insumo a eliminar.
 * @returns {Promise<void>}
 */
export async function eliminarInsumo(id) {
  await api.delete(`/insumos/${id}`);
}

// ─── CC-57: Alertas de quiebre de stock ────────────────────────────────────────

/**
 * Obtiene los insumos con stock en o por debajo del mínimo, agotados primero.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-004 / CC-57
 *
 * @returns {Promise<Array>} Insumos en alerta con estado_stock ('bajo' | 'agotado').
 */
export async function obtenerAlertasStock() {
  const respuesta = await api.get('/insumos/alertas');
  return respuesta.data.data;
}

// ─── CC-46: Recetas (ingredientes por platillo)────────────────────────────────

/**
 * Obtiene los platillos del menú para configurar su receta.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-003 / CC-46
 *
 * @returns {Promise<Array>} Platillos con id, nombre, precio_unitario y disponible.
 */
export async function obtenerPlatillos() {
  const respuesta = await api.get('/platillos');
  return respuesta.data.data ?? [];
}

/**
 * Obtiene la lista de ingredientes (receta) de un platillo específico.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param   {number} platilloId Identificador del platillo.
 * @returns {Promise<Array>} Renglones de receta con insumo, cantidad y unidad.
 */
export async function obtenerRecetaPlatillo(platilloId) {
  const respuesta = await api.get(`/platillos/${platilloId}/recetas`);
  return respuesta.data.data;
}

/**
 * Agrega un insumo con su porción base a la receta de un platillo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param   {number} platilloId Identificador del platillo.
 * @param   {{ insumo_id: number, cantidad_por_porcion: number, unidad_consumo: string }} payload Datos del ingrediente.
 * @returns {Promise<Object>} Renglón de receta creado.
 */
export async function agregarIngrediente(platilloId, payload) {
  const respuesta = await api.post(`/platillos/${platilloId}/recetas`, payload);
  return respuesta.data.data;
}

/**
 * Actualiza la porción de un ingrediente en la receta de un platillo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param   {number} platilloId Identificador del platillo.
 * @param   {number} recetaId   Identificador del renglón de receta.
 * @param   {{ cantidad_por_porcion: number, unidad_consumo: string }} payload Porción y unidad actualizadas.
 * @returns {Promise<Object>} Renglón de receta actualizado.
 */
export async function actualizarIngrediente(platilloId, recetaId, payload) {
  const respuesta = await api.put(`/platillos/${platilloId}/recetas/${recetaId}`, payload);
  return respuesta.data.data;
}

/**
 * Elimina un ingrediente de la receta de un platillo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param   {number} platilloId Identificador del platillo.
 * @param   {number} recetaId   Identificador del renglón a eliminar.
 * @returns {Promise<void>}
 */
export async function eliminarIngrediente(platilloId, recetaId) {
  await api.delete(`/platillos/${platilloId}/recetas/${recetaId}`);
}

// ─── CC-52: Requisiciones internas ──────────────────────────────────────────

/**
 * Envía una requisición de insumos para aprobación de bodega.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 *
 * @param   {{ area_solicitante: string, observaciones?: string, detalles: Array<{ insumo_id: number, cantidad_solicitada: number }> }} payload
 * @returns {Promise<Object>} Requisición pendiente creada con sus detalles.
 */
export async function crearRequisicion(payload) {
  const respuesta = await api.post('/requisiciones', payload);
  return respuesta.data.data;
}

// ─── CC-53 / CC-54: Gestión de bodega ───────────────────────────────────────

/**
 * Obtiene las requisiciones para que el administrador las apruebe y transfiera.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
 *
 * @returns {Promise<Array>} Requisiciones con solicitante, detalles e insumos.
 */
export async function obtenerRequisiciones() {
  const respuesta = await api.get('/requisiciones');
  return respuesta.data.data;
}

/**
 * Aprueba cantidades por detalle o rechaza una requisición pendiente.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53
 *
 * @param {number} requisicionId Identificador de la requisición.
 * @param {{ estado: 'aprobada'|'rechazada', detalles?: Array<{ id: number, cantidad_aprobada: number }> }} payload Resolución.
 * @returns {Promise<Object>} Requisición resuelta.
 */
export async function resolverRequisicion(requisicionId, payload) {
  const respuesta = await api.patch(`/requisiciones/${requisicionId}/resolucion`, payload);
  return respuesta.data.data;
}

/**
 * Transfiere el stock aprobado de bodega general al área de la requisición.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-54
 *
 * @param {number} requisicionId Identificador de la requisición aprobada.
 * @returns {Promise<Object>} Requisición marcada como transferida.
 */
export async function transferirRequisicion(requisicionId) {
  const respuesta = await api.post(`/requisiciones/${requisicionId}/transferir`);
  return respuesta.data.data;
}
