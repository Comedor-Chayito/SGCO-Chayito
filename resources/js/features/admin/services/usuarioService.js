import api from '@/services/api.js';

/**
 * Servicio API para la gestión de usuarios y roles RBAC (US-ADM-01 / CC-84).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Admin – RF-ADM-008
 */

/**
 * Obtiene la lista de usuarios con filtros opcionales de búsqueda, rol y estado activo.
 *
 * @param   {object} [filtros={}] Filtros: busqueda, rol, activo.
 * @returns {Promise<Array>} Lista de usuarios.
 */
export async function obtenerUsuarios(filtros = {}) {
  const respuesta = await api.get('/usuarios', { params: filtros });
  return respuesta.data.data;
}

/**
 * Registra un nuevo usuario en el sistema.
 *
 * @param   {object} datos { name, email, password, rol, activo }
 * @returns {Promise<object>} Usuario creado.
 */
export async function crearUsuario(datos) {
  const respuesta = await api.post('/usuarios', datos);
  return respuesta.data.data;
}

/**
 * Obtiene los detalles de un usuario por su ID.
 *
 * @param   {number|string} id ID del usuario.
 * @returns {Promise<object>} Detalle del usuario.
 */
export async function obtenerUsuario(id) {
  const respuesta = await api.get(`/usuarios/${id}`);
  return respuesta.data.data;
}

/**
 * Actualiza los datos o el rol de un usuario existente.
 *
 * @param   {number|string} id ID del usuario.
 * @param   {object} datos Datos a actualizar.
 * @returns {Promise<object>} Usuario actualizado.
 */
export async function actualizarUsuario(id, datos) {
  const respuesta = await api.put(`/usuarios/${id}`, datos);
  return respuesta.data.data;
}

/**
 * Desactiva la cuenta de un usuario y revoca sus tokens activos.
 *
 * @param   {number|string} id ID del usuario.
 * @returns {Promise<object>} Usuario desactivado.
 */
export async function desactivarUsuario(id) {
  const respuesta = await api.delete(`/usuarios/${id}`);
  return respuesta.data.data;
}

/**
 * Alterna el estado activo de un usuario (activar / desactivar).
 *
 * @param   {number|string} id ID del usuario.
 * @returns {Promise<object>} Usuario con estado actualizado.
 */
export async function toggleActivoUsuario(id) {
  const respuesta = await api.patch(`/usuarios/${id}/toggle-activo`);
  return respuesta.data.data;
}

/**
 * Obtiene el catálogo oficial de roles disponibles en el sistema.
 *
 * @returns {Promise<Array<string>>} Lista de nombres de roles.
 */
export async function obtenerRoles() {
  const respuesta = await api.get('/roles');
  return respuesta.data.data;
}

/**
 * Elimina permanentemente una cuenta de usuario si no posee restricciones de integridad.
 *
 * @param   {number|string} id ID del usuario.
 * @returns {Promise<object>} Respuesta del servidor.
 */
export async function eliminarUsuario(id) {
  const respuesta = await api.delete(`/usuarios/${id}`);
  return respuesta.data;
}

