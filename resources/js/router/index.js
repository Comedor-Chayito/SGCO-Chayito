import { createRouter, createWebHistory } from 'vue-router';
import { useSesionStore } from '@/stores/sesion.js';

/**
 * Configuración del enrutador SPA de SGCO-Chayito.
 * Rutas protegidas por rol definidas en CLAUDE.md sección 9.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Core – RF-ADM-008
 */

const routes = [
  {
    path: '/',
    name: 'inicio',
    component: () => import('@/views/Inicio.vue'),
  },
  {
    path: '/pos',
    name: 'pos',
    component: () => import('@/features/pos/views/POS.vue'),
    meta: { roles: ['mesero', 'cajero', 'administrador'] },
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
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Core – RF-ADM-008
 */
router.beforeEach((to) => {
  const authActivo = import.meta.env.VITE_AUTH_ENABLED === 'true';

  if (!authActivo) return true;

  const sesion = useSesionStore();

  if (!to.meta.roles) return true;

  if (!sesion.autenticado) {
    return { name: 'inicio' };
  }

  if (!to.meta.roles.includes(sesion.rol)) {
    return { name: 'inicio' };
  }

  return true;
});

export default router;
