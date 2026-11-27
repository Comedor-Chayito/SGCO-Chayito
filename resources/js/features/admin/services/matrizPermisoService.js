import api from '@/services/api.js';

/**
 * Servicio API para la consulta y visualización de la Matriz de Permisos RBAC (US-ADM-04 / CC-121).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-11-16
 * @módulo Admin – RF-ADM-008
 */

/**
 * Obtiene la matriz completa rol × permiso con módulos, metadatos y listados estructurados.
 *
 * @returns {Promise<{
 *   roles: Array<{ id: number, name: string, permisos: string[], total_permisos: number }>,
 *   permisos: Array<{ id: number, name: string, modulo: string, accion: string, descripcion: string }>,
 *   modulos: Array<{ clave: string, nombre: string, permisos: Array<object> }>,
 *   matriz: Record<string, Record<string, boolean>>,
 *   total_roles: number,
 *   total_permisos: number
 * }>}
 */
export async function obtenerMatrizPermisos() {
  const respuesta = await api.get('/matriz-permisos');
  return respuesta.data.data;
}

/**
 * Actualiza los permisos asignados a un rol oficial y registra la auditoría (US-ADM-04 / CC-122).
 *
 * @param   {string|number} rol Nombre o identificador numérico del rol.
 * @param   {string[]} permisos Lista de nombres de permisos a asignar.
 * @returns {Promise<object>} Rol actualizado con sus nuevos permisos.
 */
export async function actualizarPermisosRol(rol, permisos) {
  const respuesta = await api.put(`/roles/${rol}/permisos`, { permisos });
  return respuesta.data.data;
}

export default {
  obtenerMatrizPermisos,
  actualizarPermisosRol,
};
