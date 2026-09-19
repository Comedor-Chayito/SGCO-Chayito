<script setup>
/**
 * Contenido principal del carrito POS. 
 * Extraído para ser renderizado en un Sheet (móvil) o Panel lateral (desktop).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 */
import { useCarritoStore } from '@/features/pos/stores/useCarritoStore.js';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { ShoppingCart } from '@lucide/vue';
import ItemCarrito from '@/features/pos/components/ItemCarrito.vue';

const emit = defineEmits(['confirmar']);
const carrito = useCarritoStore();

function onConfirmar() {
  emit('confirmar');
}
</script>

<template>
  <div class="flex flex-col h-full bg-zinc-50 relative">
    
    <!-- Encabezado del Carrito -->
    <div class="px-6 py-4 bg-white border-b border-zinc-200 flex justify-between items-center shadow-sm shrink-0">
      <h2 class="text-xl font-black text-zinc-900 leading-tight">Ticket Actual</h2>
      <button 
        class="text-sm font-bold text-red-500 hover:text-red-700 hover:bg-red-50 px-3 py-1.5 rounded-lg transition-colors"
        @click="carrito.limpiar()"
        title="Vaciar ticket"
      >
        Limpiar
      </button>
    </div>

    <!-- Lista de Ítems (Scrollable) -->
    <div class="flex-1 overflow-hidden relative">
      <ScrollArea class="h-full px-4 py-4" v-if="!carrito.estaVacio">
        <ul class="space-y-3 pb-24">
          <ItemCarrito
            v-for="item in carrito.items"
            :key="item.id"
            :item="item"
            @incrementar="carrito.incrementar"
            @decrementar="carrito.decrementar"
            @observaciones="carrito.actualizarObservaciones"
          />
        </ul>
      </ScrollArea>

      <!-- Estado Vacío -->
      <div v-else class="h-full flex flex-col items-center justify-center text-zinc-400 p-8 text-center animate-in fade-in duration-500">
        <div class="bg-zinc-100 p-6 rounded-full mb-4">
          <ShoppingCart class="w-12 h-12 text-zinc-300" />
        </div>
        <p class="text-lg font-bold text-zinc-500 mb-1">El ticket está vacío</p>
        <p class="text-sm">Agrega platillos desde el menú para empezar a ordenar.</p>
      </div>
    </div>

    <!-- Resumen y Acción (Fijo al fondo) -->
    <div class="p-4 bg-white border-t border-zinc-200 shrink-0 shadow-[0_-4px_15px_-5px_rgba(0,0,0,0.05)]">
      <div class="flex justify-between items-center mb-4">
        <span class="text-sm font-bold text-zinc-600 uppercase tracking-wider">Total</span>
        <span class="text-2xl font-black text-[#F26A21]">{{ carrito.subtotalFormateado }}</span>
      </div>

      <Button
        class="w-full h-14 text-lg font-bold rounded-xl transition-all shadow-md active:scale-[0.98]"
        :class="[
          carrito.estaVacio || carrito.cargando 
            ? 'bg-zinc-200 text-zinc-400 hover:bg-zinc-200 cursor-not-allowed'
            : 'bg-[#F26A21] text-white hover:bg-[#e05e1c] hover:shadow-lg hover:-translate-y-0.5'
        ]"
        :disabled="carrito.estaVacio || carrito.cargando"
        @click="onConfirmar"
      >
        <span v-if="carrito.cargando" class="flex items-center gap-2">
          <span class="w-5 h-5 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
          Registrando...
        </span>
        <span v-else>Registrar Orden</span>
      </Button>
    </div>
  </div>
</template>
