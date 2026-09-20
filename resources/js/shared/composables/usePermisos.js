import { computed } from 'vue';
import { useSesionStore } from '@/stores/sesion.js';

/**
 * Centraliza la restricción de menús, botones y vistas según el rol
 * del usuario autenticado (matriz de acceso de CLAUDE.md sección 9).
 *
 * @autor  manuelmv15
 * @fecha  2026-09-20
 * @módulo Admin – RF-ADM-008 (CC-86)
 *
 * @returns {{
 *   rolActual: import('vue').ComputedRef<string|null>,
 *   puedeVer: (rolesPermitidos: string[]) => boolean,
 * }} API del composable de permisos.
 */
export function usePermisos() {
  const sesion = useSesionStore();

  const rolActual = computed(() => sesion.rol);

  /**
   * Indica si el rol activo está autorizado para ver un menú, botón o vista.
   * Una lista vacía o ausente significa que no hay restricción de rol.
   *
   * @autor  manuelmv15
   * @fecha  2026-09-20
   * @módulo Admin – RF-ADM-008 (CC-86)
   *
   * @param  {string[]} [rolesPermitidos] Roles autorizados para el elemento.
   * @returns {boolean} true si el rol activo puede ver el elemento.
   */
  function puedeVer(rolesPermitidos) {
    if (!rolesPermitidos || rolesPermitidos.length === 0) return true;

    return rolesPermitidos.includes(rolActual.value);
  }

  return { rolActual, puedeVer };
}
