import {
  BarChart3,
  ChefHat,
  ClipboardList,
  FileDown,
  Landmark,
  Package,
  ShoppingCart,
  Users,
  WalletCards,
} from '@lucide/vue';

/**
 * Fuente única de los accesos globales visibles para cada rol del sistema.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-25
 * @módulo Core – RF-ADM-008 / Integración de navegación
 */

export const ITEMS_NAVEGACION = [
  {
    titulo: 'Operación',
    items: [
      { etiqueta: 'Punto de venta', ruta: 'pos', icono: ShoppingCart, roles: ['mesero', 'administrador', 'socio'] },
      { etiqueta: 'Cocina', ruta: 'cocina', icono: ChefHat, roles: ['cocinero', 'cocinera', 'administrador', 'socio'] },
      { etiqueta: 'Caja', ruta: 'caja', icono: WalletCards, roles: ['cajero', 'administrador', 'socio'] },
    ],
  },
  {
    titulo: 'Inventario',
    items: [
      { etiqueta: 'Consultar inventario', ruta: 'inventario', icono: Package, roles: ['cocinero', 'cocinera', 'administrador', 'socio'] },
      { etiqueta: 'Solicitar insumos', ruta: 'nueva-requisicion', icono: ClipboardList, roles: ['cocinero', 'cocinera', 'administrador', 'socio'] },
      { etiqueta: 'Gestionar requisiciones', ruta: 'gestion-requisiciones', icono: ClipboardList, roles: ['administrador', 'socio'] },
    ],
  },
  {
    titulo: 'Finanzas',
    items: [
      { etiqueta: 'Contabilidad', ruta: 'contabilidad', icono: Landmark, roles: ['contadora', 'administrador', 'socio'] },
      { etiqueta: 'Dashboard gerencial', ruta: 'reportes', icono: BarChart3, roles: ['socio', 'administrador'] },
      { etiqueta: 'Exportar reportes', ruta: 'exportar-reportes', icono: FileDown, roles: ['contadora', 'socio', 'administrador'] },
    ],
  },
  {
    titulo: 'Configuración',
    items: [
      { etiqueta: 'Usuarios y roles', ruta: 'admin', icono: Users, roles: ['administrador', 'socio'] },
    ],
  },
];

/**
 * Filtra los grupos de navegación conforme al rol autenticado actual.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-25
 * @módulo Core – RF-ADM-008 / Integración de navegación
 *
 * @param {string|null} rol Rol principal asignado al usuario activo.
 * @returns {Array<object>} Grupos que contienen al menos un acceso autorizado.
 */
export function obtenerNavegacionPorRol(rol) {
  return ITEMS_NAVEGACION
    .map((grupo) => ({
      ...grupo,
      items: grupo.items.filter((item) => item.roles.includes(rol)),
    }))
    .filter((grupo) => grupo.items.length > 0);
}
