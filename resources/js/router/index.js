import { createRouter, createWebHistory } from 'vue-router';
import { useSesionStore } from '@/stores/sesion.js';

/**
 * Configuración del enrutador SPA de SGCO-Chayito.
 * Rutas protegidas por rol definidas en CLAUDE.md sección 9.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo Core – RF-ADM-008
 */

const routes = [
  {
    path: '/',
    name: 'inicio',
    component: () => import('@/views/Inicio.vue'),
  },
  {
    path: '/login',
    name: 'login',
    component: () =>
      import('@/features/admin/views/Login.vue'),
    meta: { publica: true },
  },
  {
    path: '/pos',
    name: 'pos',
    component: () => import('@/features/pos/views/POS.vue'),
    meta: { roles: ['mesero', 'cajero', 'administrador'] },
  },
  {
    path: '/cocina',
    name: 'cocina',
    component: () => import('@/features/pos/views/ColaCocina.vue'),
    meta: { roles: ['cocinero', 'cajero', 'administrador'] },
  },
  {
    path: '/pos/cocina',
    redirect: '/cocina',
  },
  {
    path: '/inventario',
    name: 'inventario',
    component: () => import('@/views/Inventario.vue'),
    meta: { roles: ['cocinero', 'administrador'] },
  },
  {
    path: '/contabilidad',
    name: 'contabilidad',
    component: () => import('@/views/Contabilidad.vue'),
    meta: { roles: ['contadora', 'administrador'] },
  },
  {
    path: '/reportes',
    name: 'reportes',
    component: () => import('@/views/Reportes.vue'),
    meta: { roles: ['contadora', 'administrador'] },
  },
  {
    path: '/admin',
    name: 'admin',
    component: () => import('@/views/Admin.vue'),
    meta: { roles: ['administrador'] },
  },
  // TODO: ELIMINAR — ruta temporal de prueba CC-37
  {
    path: '/prueba-retiro',
    name: 'prueba-retiro',
    component: () =>
      import('@/features/pos/views/PruebaRetiro.vue'),
  },
  {
    path: '/:pathMatch(.*)*',
    name: 'no-encontrado',
    component: () => import('@/views/NoEncontrado.vue'),
  },
];

const router = createRouter({
  history: createWebHistory(),
  routes,
});

/**
 * Determina la ruta de inicio por defecto según el rol del usuario autenticado.
 *
 * @param   {string|null} rol Rol asignado al usuario.
 * @returns {object} Objeto de ruta de Vue Router.
 */
export function obtenerRutaInicioPorRol(rol) {
  switch (rol) {
    case 'cocinero':
    case 'cocinera':
      return { name: 'cocina' };
    case 'contadora':
      return { name: 'contabilidad' };
    case 'mesero':
    case 'cajero':
    case 'administrador':
    default:
      return { name: 'pos' };
  }
}

/**
 * Guarda de navegación global: verifica autenticación y rol.
 * El guard solo se activa cuando VITE_AUTH_ENABLED=true en el .env.
 * Hasta que US-ADM-02 implemente Sanctum, la bandera permanece desactivada.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo Core – RF-ADM-008
 */
router.beforeEach(async (to) => {
  const authActivo = import.meta.env.VITE_AUTH_ENABLED !== 'false';

  if (!authActivo) return true;

  const sesion = useSesionStore();

  // Si hay token pero no se ha cargado el usuario, intentar refrescarlo
  if (sesion.autenticado && !sesion.usuario) {
    await sesion.cargarUsuario();
  }

  const rutaDestino = obtenerRutaInicioPorRol(sesion.rol);

  // Rutas públicas (login, inicio)
  if (to.meta.publica || to.name === 'inicio') {
    // Si ya está autenticado, no permitir volver a login
    if (to.name === 'login' && sesion.autenticado) {
      return rutaDestino;
    }
    return true;
  }

  // Si no está autenticado, redirigir a login
  if (!sesion.autenticado) {
    return { name: 'login' };
  }

  // Verificar restricción de roles
  if (to.meta.roles && to.meta.roles.length > 0) {
    const rolActual = sesion.rol;
    if (!rolActual || !to.meta.roles.includes(rolActual)) {
      // Si el usuario no tiene acceso a la ruta solicitada, redirigir a su vista permitida
      if (to.name === rutaDestino.name) {
        return { name: 'login' };
      }
      return rutaDestino;
    }
  }

  return true;
});

export default router;
