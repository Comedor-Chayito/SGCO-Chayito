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
import {
  Package,
  ArrowLeft,
  FlaskConical,
  ShoppingCart,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';

import Insumos from '@/features/inventario/views/Insumos.vue';
import RecetaPlatillo from '@/features/inventario/views/RecetaPlatillo.vue';
import Compras from '@/features/inventario/views/Compras.vue';
import { useInventarioStore } from '@/features/inventario/stores/useInventarioStore.js';
import EncabezadoOperacion from '@/shared/components/EncabezadoOperacion.vue';

const router = useRouter();
const inventario = useInventarioStore();

onMounted(() => inventario.cargarAlertas());

const pestanaActiva = ref('insumos');

const PESTANAS = [
  { id: 'insumos', etiqueta: 'Insumos de bodega', icono: Package },
  { id: 'recetas', etiqueta: 'Recetas por platillo', icono: FlaskConical },
  { id: 'compras', etiqueta: 'Compras', icono: ShoppingCart },
];

</script>

<template>
  <main class="flex min-h-screen flex-col bg-[#FAF7F4]" id="vista-inventario">

    <EncabezadoOperacion
      titulo="Inventario"
      subtitulo="Insumos, recetas y compras de bodega"
      :icono="Package"
    >
      <template #navegacion>
        <Button
          variant="ghost"
          size="icon"
          class="rounded-xl hover:bg-zinc-100"
          @click="router.back()"
          title="Volver"
        >
          <ArrowLeft :size="20" />
        </Button>
      </template>
    </EncabezadoOperacion>

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
