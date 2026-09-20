<script setup>
/**
 * Paso 2 del Wizard POS: Menú de Comida.
 * Rediseñado para soportar "Armar Plato" (Bandeja) y "A la Carta".
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 */
import { computed } from 'vue';
import { useCarritoStore } from '@/features/pos/stores/useCarritoStore.js';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { CupSoda, UtensilsCrossed, ArrowLeft } from '@lucide/vue';
import ArmadorPlato from '@/features/pos/components/ArmadorPlato.vue';
import TarjetaPlatillo from '@/features/pos/components/TarjetaPlatillo.vue';

const props = defineProps({
  platillos: {
    type: Array,
    required: true,
  },
  mesas: {
    type: Array,
    required: true,
  }
});

const carrito = useCarritoStore();

const tituloCanal = computed(() => {
  if (carrito.canal === 'mesa') {
    const mesa = props.mesas.find(m => m.id === carrito.mesaId);
    return mesa ? `Mesa ${mesa.numero}` : 'Mesa';
  }
  if (carrito.canal === 'para_llevar') return 'Para Llevar';
  return 'WhatsApp';
});

// Mock: Clasificamos temporalmente hasta que el backend envíe categorias_id
const platillosALaCarta = computed(() => {
  return props.platillos.filter(p => {
    const n = p.nombre.toLowerCase();
    return n.includes('sopa') || n.includes('pupusa') || n.includes('jugo') || n.includes('soda') || n.includes('agua') || n.includes('licuado');
  });
});

const platillosBandeja = computed(() => {
  return props.platillos.filter(p => !platillosALaCarta.value.includes(p));
});

function volver() {
  carrito.pasoActual = 1;
}
</script>

<template>
  <div class="flex h-full min-h-0 flex-col animate-in fade-in slide-in-from-right-4 duration-300">
    
    <!-- Encabezado de Navegación -->
    <header class="flex h-14 shrink-0 items-center justify-between border-b border-[#E5E0DB] bg-white px-3 sm:px-4">
      <div class="flex items-center gap-3">
        <Button variant="outline" size="sm" class="h-9 rounded-lg border-[#E5E0DB] px-3 font-bold text-zinc-700 shadow-none active:scale-[0.98]" @click="volver">
          <ArrowLeft class="size-4" :stroke-width="2.25" /> Volver
        </Button>
        <div class="flex flex-col">
          <span class="text-[11px] font-medium leading-none text-zinc-500">Orden actual</span>
          <span class="mt-0.5 text-sm font-black leading-tight text-zinc-900">{{ tituloCanal }}</span>
        </div>
      </div>
    </header>

    <!-- Sistema de Pestañas y Contenido -->
    <main class="flex min-h-0 flex-1 flex-col overflow-hidden bg-[#FAF7F4]">
      <Tabs defaultValue="armar" class="flex h-full min-h-0 flex-1 flex-col">
        <div class="shrink-0 border-b border-[#E5E0DB] bg-white px-3 py-2 sm:px-4">
          <TabsList class="grid h-11 w-full grid-cols-2 rounded-xl bg-zinc-100 p-1">
            <TabsTrigger value="armar" class="rounded-lg text-sm font-bold transition-all data-active:bg-white data-active:text-[#C24E12] data-active:shadow-sm sm:text-base">
              <UtensilsCrossed class="size-4 opacity-70" /> Armar bandeja
            </TabsTrigger>
            <TabsTrigger value="alacarta" class="rounded-lg text-sm font-bold transition-all data-active:bg-white data-active:text-[#C24E12] data-active:shadow-sm sm:text-base">
              <CupSoda class="size-4 opacity-70" /> A la carta
            </TabsTrigger>
          </TabsList>
        </div>

        <!-- Pestaña: Armador de Platos (Bandeja) -->
        <TabsContent value="armar" class="m-0 min-h-0 flex-1 overflow-hidden p-0 data-[state=inactive]:hidden">
          <ArmadorPlato :platillos="platillosBandeja" />
        </TabsContent>

        <!-- Pestaña: A la Carta (Sopas, Pupusas, Bebidas) -->
        <TabsContent value="alacarta" class="m-0 min-h-0 flex-1 overflow-y-auto p-3 pb-24 sm:p-4 sm:pb-24 data-[state=inactive]:hidden">
          <div class="grid grid-cols-[repeat(auto-fill,minmax(140px,1fr))] gap-2.5 sm:gap-3">
            <TarjetaPlatillo
              v-for="platillo in platillosALaCarta"
              :key="platillo.id"
              :platillo="platillo"
              @agregar="carrito.agregarItem"
            />
          </div>
        </TabsContent>
      </Tabs>
    </main>

  </div>
</template>
