<script setup>
/**
 * Componente raíz de la aplicación SGCO-Chayito.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Core – Estructura raíz SPA
 */
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppShell from '@/shared/components/AppShell.vue';
import { useSesionStore } from '@/stores/sesion.js';

const route = useRoute();
const sesion = useSesionStore();

/**
 * Evita montar navegación operativa en login, inicio público y página no encontrada.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-25
 * @módulo Core – RF-ADM-008 / Integración de navegación
 *
 * @returns {boolean} true cuando la vista autenticada debe usar el contenedor global.
 */
const usarContenedorGlobal = computed(() => (
  sesion.autenticado
  && !route.meta.publica
  && route.name !== 'no-encontrado'
));
</script>

<template>
  <RouterView v-slot="{ Component }">
    <AppShell v-if="usarContenedorGlobal">
      <component :is="Component" />
    </AppShell>
    <component :is="Component" v-else />
  </RouterView>
</template>
