<script setup>
/**
 * Vista central del módulo de Inventario.
 * Integra las pestañas: Insumos (CC-45), Recetas por platillo (CC-46)
 * y Compras con la alerta de quiebre de stock (CC-58).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – US-INV-01 / RF-INV-003
 */
import { ref, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSesionStore } from '@/stores/sesion.js';
import {
  Package,
  ArrowLeft,
  LogOut,
  ShieldCheck,
  FlaskConical,
  ShoppingCart,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

import Insumos from '@/features/inventario/views/Insumos.vue';
import RecetaPlatillo from '@/features/inventario/views/RecetaPlatillo.vue';
import Compras from '@/features/inventario/views/Compras.vue';
import { useInventarioStore } from '@/features/inventario/stores/useInventarioStore.js';

const router = useRouter();
const sesion = useSesionStore();
const inventario = useInventarioStore();

onMounted(() => inventario.cargarAlertas());

const pestanaActiva = ref('insumos');

const PESTANAS = [
  { id: 'insumos', etiqueta: 'Insumos de bodega', icono: Package },
  { id: 'recetas', etiqueta: 'Recetas por platillo', icono: FlaskConical },
  { id: 'compras', etiqueta: 'Compras', icono: ShoppingCart },
];

/**
 * Cierra la sesión del usuario y redirige al login.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-ADM-008
 */
async function cerrarSesion() {
  await sesion.cerrarSesion();
  router.push('/login');
}
</script>

<template>
  <main class="flex min-h-screen flex-col bg-[#FAF7F4]" id="vista-inventario">

    <!-- Cabecera Superior -->
    <header class="flex flex-col gap-4 border-b border-[#E5E0DB] bg-white px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
      <div class="flex items-center gap-3">
        <Button
          variant="ghost"
          size="icon"
          class="rounded-xl hover:bg-zinc-100"
          @click="router.back()"
          title="Volver"
        >
          <ArrowLeft :size="20" />
        </Button>

        <div class="flex items-center gap-2.5">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-orange-100 text-[#F26A21]">
            <Package :size="22" />
          </div>
          <div>
            <h1 class="text-lg font-bold leading-tight text-zinc-900">Inventario</h1>
            <p class="text-xs text-zinc-500">Comedor Chayito · Insumos y recetas</p>
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <div class="flex items-center gap-2 rounded-xl border border-zinc-200/80 bg-zinc-50 px-3 py-1.5 text-xs">
          <span class="font-medium text-zinc-700">{{ sesion.usuario?.name || 'Usuario' }}</span>
          <Badge variant="outline" class="border-orange-200 bg-white text-[10px] font-bold text-orange-600">
            <ShieldCheck :size="10" class="mr-1" />
            {{ (sesion.rol || 'Bodega').toUpperCase() }}
          </Badge>
        </div>

        <Button
          variant="ghost"
          class="flex items-center gap-1.5 rounded-xl text-xs font-semibold text-rose-600 hover:bg-rose-50 hover:text-rose-700"
          @click="cerrarSesion"
          title="Cerrar sesión activa"
        >
          <LogOut :size="16" />
          <span>Cerrar Sesión</span>
        </Button>
      </div>
    </header>

    <!-- Navegación de pestañas -->
    <nav class="border-b border-[#E5E0DB] bg-white px-4 sm:px-6">
      <div class="flex gap-1 overflow-x-auto">
        <button
          v-for="tab in PESTANAS"
          :key="tab.id"
          :id="`tab-inventario-${tab.id}`"
          class="relative flex shrink-0 items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-semibold transition-colors"
          :class="pestanaActiva === tab.id
            ? 'text-[#F26A21] after:absolute after:bottom-0 after:inset-x-0 after:h-0.5 after:bg-[#F26A21] after:rounded-t-full'
            : 'text-[#6B6B6B] hover:text-[#3D3D3D]'"
          @click="pestanaActiva = tab.id"
        >
          <component :is="tab.icono" :size="16" />
          {{ tab.etiqueta }}
          <span
            v-if="tab.id === 'compras' && inventario.alertas.length > 0"
            class="rounded-full bg-[#C62828] px-1.5 py-0.5 text-[10px] font-bold leading-none text-white"
            :title="`${inventario.alertas.length} insumo(s) en quiebre de stock`"
          >
            {{ inventario.alertas.length }}
          </span>
        </button>
      </div>
    </nav>

    <!-- Contenido de la pestaña activa -->
    <div class="flex-1">
      <Insumos v-if="pestanaActiva === 'insumos'" />
      <RecetaPlatillo v-else-if="pestanaActiva === 'recetas'" />
      <Compras v-else-if="pestanaActiva === 'compras'" />
    </div>

  </main>
</template>
