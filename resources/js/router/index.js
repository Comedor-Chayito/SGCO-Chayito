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
 * Guarda de navegación global: verifica autenticación y rol.
 * El guard solo se activa cuando VITE_AUTH_ENABLED=true en el .env.
 * Hasta que US-ADM-02 implemente Sanctum, la bandera permanece desactivada.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo Core – RF-ADM-008
 */
router.beforeEach((to) => {
  const authActivo =
    import.meta.env.VITE_AUTH_ENABLED === 'true';

  if (!authActivo) return true;

  const sesion = useSesionStore();

  // Rutas públicas (login, inicio) — accesibles sin token
  if (to.meta.publica || to.name === 'inicio') {
    // Si ya está autenticado, no dejarlo volver al login
    if (to.name === 'login' && sesion.autenticado) {
      return { name: 'pos' };
    }
    return true;
  }

  // Sin token → redirigir al login
  if (!sesion.autenticado) {
    return { name: 'login' };
  }

  // Verificar rol si la ruta lo exige
  if (
    to.meta.roles &&
    !to.meta.roles.includes(sesion.rol)
  ) {
    return { name: 'pos' };
  }

  return true;
});

export default router;
