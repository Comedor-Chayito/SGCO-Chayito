import api from '@/services/api.js';

/**
 * Servicio API del panel de configuración de catálogos: platillos, mesas y
 * subcategorías de egreso (US-ADM-03 / CC-110). Toda llamada pasa por api.js.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 */

/**
 * Lista todos los platillos del catálogo, disponibles y no disponibles.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @returns {Promise<Array>} Platillos con id, nombre, descripcion, precio_unitario y disponible.
 */
export async function obtenerPlatillos() {
  const respuesta = await api.get('/platillos/catalogo');
  return respuesta.data.data;
}

/**
 * Registra un platillo nuevo en el catálogo.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {object} datos { nombre, descripcion, precio_unitario }
 * @returns {Promise<object>} Respuesta { message, data } del backend.
 */
export async function crearPlatillo(datos) {
  const respuesta = await api.post('/platillos', datos);
  return respuesta.data;
}

/**
 * Edita el nombre, la descripción o el precio de un platillo.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {number} id    Identificador del platillo.
 * @param   {object} datos { nombre, descripcion, precio_unitario }
 * @returns {Promise<object>} Respuesta { message, data } del backend.
 */
export async function actualizarPlatillo(id, datos) {
  const respuesta = await api.put(`/platillos/${id}`, datos);
  return respuesta.data;
}

/**
 * Activa o desactiva un platillo del menú del día.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {number}  id         Identificador del platillo.
 * @param   {boolean} disponible true para incluirlo en el menú del día.
 * @returns {Promise<object>} Respuesta { message, data } del backend.
 */
export async function cambiarDisponibilidadPlatillo(id, disponible) {
  const respuesta = await api.patch(`/platillos/${id}/disponibilidad`, { disponible });
  return respuesta.data;
}

/**
 * Elimina un platillo sin historial. Si tiene comandas o receta, el backend responde 422.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {number} id Identificador del platillo.
 * @returns {Promise<object>} Respuesta { message, data } del backend.
 */
export async function eliminarPlatillo(id) {
  const respuesta = await api.delete(`/platillos/${id}`);
  return respuesta.data;
}

/**
 * Lista todas las mesas, activas e inactivas.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @returns {Promise<Array>} Mesas con id, numero, capacidad, estado y activa.
 */
export async function obtenerMesas() {
  const respuesta = await api.get('/mesas/todas');
  return respuesta.data.data;
}

/**
 * Registra una mesa nueva.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {object} datos { numero, capacidad }
 * @returns {Promise<object>} Respuesta { message, data } del backend.
 */
export async function crearMesa(datos) {
  const respuesta = await api.post('/mesas', datos);
  return respuesta.data;
}

/**
 * Edita el número o la capacidad de una mesa.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {number} id    Identificador de la mesa.
 * @param   {object} datos { numero, capacidad }
 * @returns {Promise<object>} Respuesta { message, data } del backend.
 */
export async function actualizarMesa(id, datos) {
  const respuesta = await api.put(`/mesas/${id}`, datos);
  return respuesta.data;
}

/**
 * Activa o desactiva una mesa. Con comandas abiertas el backend responde 422.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {number} id Identificador de la mesa.
 * @returns {Promise<object>} Respuesta { message, data } del backend.
 */
export async function alternarMesa(id) {
  const respuesta = await api.patch(`/mesas/${id}/toggle-activo`);
  return respuesta.data;
}

/**
 * Elimina una mesa sin comandas, o la desactiva si tiene historial.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {number} id Identificador de la mesa.
 * @returns {Promise<object>} Respuesta { message, data }; data es null si se borró.
 */
export async function eliminarMesa(id) {
  const respuesta = await api.delete(`/mesas/${id}`);
  return respuesta.data;
}

/**
 * Lista todas las subcategorías de egreso, activas e inactivas.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @returns {Promise<Array>} Subcategorías con id, nombre y activo.
 */
export async function obtenerSubcategorias() {
  const respuesta = await api.get('/subcategorias-egreso/todas');
  return respuesta.data.data;
}

/**
 * Registra una subcategoría de egreso nueva.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {object} datos { nombre }
 * @returns {Promise<object>} Respuesta { message, data } del backend.
 */
export async function crearSubcategoria(datos) {
  const respuesta = await api.post('/subcategorias-egreso', datos);
  return respuesta.data;
}

/**
 * Cambia el nombre de una subcategoría de egreso.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {number} id    Identificador de la subcategoría.
 * @param   {object} datos { nombre }
 * @returns {Promise<object>} Respuesta { message, data } del backend.
 */
export async function renombrarSubcategoria(id, datos) {
  const respuesta = await api.put(`/subcategorias-egreso/${id}`, datos);
  return respuesta.data;
}

/**
 * Activa o desactiva una subcategoría de egreso.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {number} id Identificador de la subcategoría.
 * @returns {Promise<object>} Respuesta { message, data } del backend.
 */
export async function alternarSubcategoria(id) {
  const respuesta = await api.patch(`/subcategorias-egreso/${id}/toggle-activo`);
  return respuesta.data;
}

/**
 * Extrae de un error de Axios los mensajes por campo y el mensaje general,
 * para mostrarlos debajo de cada input y en el aviso.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {Error}  error             Error lanzado por Axios.
 * @param   {string} mensajePorDefecto Texto a usar si el backend no envió mensaje.
 * @returns {{ campos: object, mensaje: string }} campos: { nombreCampo: 'primer error' }.
 */
export function interpretarError(error, mensajePorDefecto) {
  const errores = error.response?.data?.errors ?? {};
  const campos = {};

  Object.entries(errores).forEach(([campo, mensajes]) => {
    campos[campo] = Array.isArray(mensajes) ? mensajes[0] : mensajes;
  });

  const primerError = Object.values(campos)[0];
  const mensaje = primerError || error.response?.data?.message || mensajePorDefecto;

  return { campos, mensaje };
}

/**
 * Da formato de moneda en dólares a un precio, para mostrarlo en la tabla.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {string|number} precio Precio unitario.
 * @returns {string} Precio con formato, por ejemplo "$2.50".
 */
export function formatearPrecio(precio) {
  return `$${Number(precio).toFixed(2)}`;
}
