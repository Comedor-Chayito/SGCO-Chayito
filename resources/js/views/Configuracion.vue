<script setup>
/**
 * Vista central de Configuración de catálogos del sistema.
 * Integra las pestañas de administración para: Platillos y Precios (menú del día),
 * Mesas y Subcategorías de egreso.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-17
 * @módulo Admin – US-ADM-03 (CC-110, CC-111) / RF-ADM-008
 */
import { ref } from 'vue';
import {
  SlidersHorizontal,
  UtensilsCrossed,
  Grid2X2,
  Tags,
} from '@lucide/vue';
import { Toaster } from 'vue-sonner';
import EncabezadoOperacion from '@/shared/components/EncabezadoOperacion.vue';
import TablaPlatillos from '@/features/admin/components/TablaPlatillos.vue';
import TablaMesas from '@/features/admin/components/TablaMesas.vue';
import TablaSubcategorias from '@/features/admin/components/TablaSubcategorias.vue';

const pestanaActiva = ref('platillos');

const PESTANAS = [
  {
    id: 'platillos',
    etiqueta: 'Platillos y Precios',
    descripcion: 'Gestión de menú del día, precios base y disponibilidad en POS',
    icono: UtensilsCrossed,
  },
  {
    id: 'mesas',
    etiqueta: 'Mesas',
    descripcion: 'Control de mesas activas para atención en salón',
    icono: Grid2X2,
  },
  {
    id: 'subcategorias',
    etiqueta: 'Subcategorías de egreso',
    descripcion: 'Clasificación contable para registro de gastos',
    icono: Tags,
  },
];
</script>

<template>
  <main class="flex min-h-screen flex-col bg-[#FAF7F4]" id="vista-configuracion">
    <Toaster position="top-right" rich-colors />

    <EncabezadoOperacion
      titulo="Configuración de catálogos"
      subtitulo="Gestión de platillos, precios, menú del día, mesas y subcategorías"
      :icono="SlidersHorizontal"
    />

    <!-- Navegación de pestañas (CC-110) -->
    <nav class="border-b border-[#E5E0DB] bg-white px-4 sm:px-6">
      <div class="flex gap-1 overflow-x-auto">
        <button
          v-for="tab in PESTANAS"
          :key="tab.id"
          :id="`tab-configuracion-${tab.id}`"
          type="button"
          class="relative flex shrink-0 items-center gap-2 whitespace-nowrap px-4 py-3 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2B6CB0]"
          :class="pestanaActiva === tab.id
            ? 'text-[#F26A21] after:absolute after:bottom-0 after:inset-x-0 after:h-0.5 after:bg-[#F26A21] after:rounded-t-full'
            : 'text-[#6B6B6B] hover:text-[#3D3D3D]'"
          @click="pestanaActiva = tab.id"
        >
          <component :is="tab.icono" :size="16" />
          {{ tab.etiqueta }}
        </button>
      </div>
    </nav>

    <!-- Contenido de las pestañas -->
    <div class="flex-1 p-4 sm:p-6 lg:p-8">
      <div class="mx-auto max-w-7xl">

        <!-- Pestaña: Platillos y Precios -->
        <section v-if="pestanaActiva === 'platillos'" id="panel-catalogo-platillos">
          <TablaPlatillos />
        </section>

        <!-- Pestaña: Mesas -->
        <section v-else-if="pestanaActiva === 'mesas'" id="panel-catalogo-mesas">
          <TablaMesas />
        </section>

        <!-- Pestaña: Subcategorías de egreso -->
        <section v-else-if="pestanaActiva === 'subcategorias'" id="panel-catalogo-subcategorias">
          <TablaSubcategorias />
        </section>

      </div>
    </div>

  </main>
</template>
