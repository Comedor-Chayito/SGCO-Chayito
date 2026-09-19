<script setup>
/**
 * Componente Builder (Armador) de Platos para Comida a la Vista.
 * Permite seleccionar porciones rápidamente desde listas horizontales.
 * 
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 */
import { computed, markRaw } from 'vue';
import { useCarritoStore } from '@/features/pos/stores/useCarritoStore.js';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Beef, Wheat, Salad, X } from '@lucide/vue';
import TarjetaPlatillo from '@/features/pos/components/TarjetaPlatillo.vue';

const props = defineProps({
  platillos: {
    type: Array,
    required: true,
  }
});

const carrito = useCarritoStore();

const totalPorciones = computed(() =>
  carrito.platoEnConstruccion.reduce((total, seleccion) => total + seleccion.cantidad, 0)
);

// Clasificación Mock para el Frontend (Hasta que la DB lo soporte)
const clasificacion = computed(() => {
  const proteinas = [];
  const bases = [];
  const guarniciones = [];

  props.platillos.forEach(p => {
    const n = p.nombre.toLowerCase();
    if (n.includes('carne') || n.includes('pollo') || n.includes('res') || n.includes('cerdo') || n.includes('pescado') || n.includes('bistec') || n.includes('tortita')) {
      proteinas.push(p);
    } else if (n.includes('arroz') || n.includes('casamiento') || n.includes('frijol') || n.includes('espagueti')) {
      bases.push(p);
    } else {
      guarniciones.push(p);
    }
  });

  return { proteinas, bases, guarniciones };
});

const categorias = computed(() => [
  {
    id: 'proteinas',
    titulo: 'Proteínas',
    descripcion: 'Carnes y platos fuertes',
    icono: markRaw(Beef),
    platillos: clasificacion.value.proteinas,
  },
  {
    id: 'bases',
    titulo: 'Bases',
    descripcion: 'Arroces, pastas y frijoles',
    icono: markRaw(Wheat),
    platillos: clasificacion.value.bases,
  },
  {
    id: 'guarniciones',
    titulo: 'Guarniciones',
    descripcion: 'Acompañamientos y extras',
    icono: markRaw(Salad),
    platillos: clasificacion.value.guarniciones,
  },
].filter(categoria => categoria.platillos.length > 0));

function cantidadEnPlato(platilloId) {
  const sel = carrito.platoEnConstruccion.find(s => s.platillo.id === platilloId);
  return sel ? sel.cantidad : 0;
}
</script>

<template>
  <div class="flex h-full min-h-0 flex-col bg-[#FAF7F4]">
    
    <!-- Resumen compacto del plato en construcción -->
    <div class="shrink-0 border-b border-[#E5E0DB] bg-white px-3 py-3 sm:px-4">
      <div class="flex items-center justify-between gap-4">
        <div class="min-w-0">
          <div class="flex items-baseline gap-2">
            <h3 class="text-sm font-black text-zinc-900 sm:text-base">Bandeja en curso</h3>
            <span v-if="totalPorciones > 0" class="text-xs font-semibold text-zinc-500">
              {{ totalPorciones }} {{ totalPorciones === 1 ? 'porción' : 'porciones' }}
            </span>
          </div>
          <p class="mt-0.5 text-xs text-zinc-500">Toca cada opción para sumar porciones</p>
        </div>
        <span class="shrink-0 text-lg font-black tabular-nums text-[#C24E12] sm:text-xl">
          ${{ carrito.subtotalPlato.toFixed(2) }}
        </span>
      </div>

      <div v-if="carrito.platoEnConstruccion.length > 0" class="mt-2 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <div class="flex w-max gap-1.5 pr-3">
          <button
            v-for="(sel, i) in carrito.platoEnConstruccion"
            :key="sel.platillo.id"
            class="group flex h-8 shrink-0 touch-manipulation items-center gap-1.5 rounded-lg bg-zinc-900 pl-2.5 pr-1.5 text-xs font-bold text-white transition-colors hover:bg-zinc-800 active:scale-[0.98] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F26A21] focus-visible:ring-offset-2"
            :aria-label="`Quitar ${sel.platillo.nombre} de la bandeja`"
            @click="carrito.quitarSeleccionPlato(i)"
          >
            <span class="tabular-nums text-[#FFB17E]">{{ sel.cantidad }}x</span>
            <span>{{ sel.platillo.nombre }}</span>
            <span class="flex size-5 items-center justify-center rounded-md bg-white/10 text-white/70 group-hover:text-white" aria-hidden="true">
              <X class="size-3.5" :stroke-width="2.5" />
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- Renglones de selección -->
    <div class="flex-1 min-h-0 space-y-4 overflow-y-auto px-3 pt-3 pb-24 sm:px-4 sm:pt-4 md:pb-24">
      <section v-for="categoria in categorias" :key="categoria.id">
        <div class="mb-2 flex items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-2.5">
            <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-[#F26A21]/10 text-[#C24E12]">
              <component :is="categoria.icono" class="size-4.5" :stroke-width="2" />
            </span>
            <div class="min-w-0">
              <h4 class="text-sm font-black leading-tight text-zinc-900">{{ categoria.titulo }}</h4>
              <p class="truncate text-[11px] leading-tight text-zinc-500">{{ categoria.descripcion }}</p>
            </div>
          </div>
          <span class="shrink-0 text-[11px] font-semibold tabular-nums text-zinc-500">
            {{ categoria.platillos.length }} opciones
          </span>
        </div>

        <ScrollArea class="-mx-3 w-[calc(100%+1.5rem)] whitespace-nowrap sm:mx-0 sm:w-full">
          <div class="flex w-max gap-2 px-3 pb-3 sm:px-0 sm:pr-3">
            <TarjetaPlatillo
              v-for="platillo in categoria.platillos"
              :key="platillo.id"
              :platillo="platillo"
              :cantidad-seleccionada="cantidadEnPlato(platillo.id)"
              compacta
              @agregar="carrito.toggleSeleccionPlato"
            />
          </div>
          <ScrollBar orientation="horizontal" />
        </ScrollArea>
      </section>
    </div>
  </div>
</template>
