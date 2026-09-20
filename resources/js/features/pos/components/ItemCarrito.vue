<script setup>
/**
 * Renglón de un ítem dentro del carrito de la comanda activa.
 * Soporta ítems individuales y "Platos" armados usando Accordion para desglose.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 */
import { Button } from '@/components/ui/button';
import { Accordion, AccordionItem, AccordionTrigger, AccordionContent } from '@/components/ui/accordion';

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['incrementar', 'decrementar', 'observaciones']);

const subtotalItem = () =>
  `$${(props.item.precio_unitario * props.item.cantidad).toFixed(2)}`;
</script>

<template>
  <li class="bg-white border border-zinc-200 rounded-xl p-3 shadow-sm flex flex-col gap-3 transition-all hover:border-zinc-300">
    
    <!-- Encabezado del Ítem -->
    <div class="flex justify-between items-start gap-2">
      <span class="font-bold text-sm text-zinc-800 leading-tight flex-1">
        {{ item.nombre }}
        <span v-if="item.tipo === 'plato'" class="text-xs text-zinc-400 font-normal ml-1">
          ({{ item.sub_items.length }} porciones)
        </span>
      </span>
      <span class="font-extrabold text-base text-[#F26A21] whitespace-nowrap">{{ subtotalItem() }}</span>
    </div>

    <!-- Desglose de Plato Armado (Accordion) -->
    <Accordion v-if="item.tipo === 'plato'" type="single" collapsible class="w-full">
      <AccordionItem value="desglose" class="border-none">
        <AccordionTrigger class="py-1 text-xs text-zinc-500 font-medium hover:no-underline hover:text-zinc-800">
          Ver desglose del plato
        </AccordionTrigger>
        <AccordionContent class="pt-2 pb-0">
          <ul class="flex flex-col gap-1.5">
            <li v-for="(sub, i) in item.sub_items" :key="i" class="flex justify-between items-center text-xs text-zinc-600 bg-zinc-50 px-2 py-1.5 rounded">
              <span><span class="font-bold mr-1">{{ sub.cantidad }}x</span> {{ sub.platillo.nombre }}</span>
              <span class="text-zinc-400 font-medium">+${{ (sub.platillo.precio_unitario * sub.cantidad).toFixed(2) }}</span>
            </li>
          </ul>
        </AccordionContent>
      </AccordionItem>
    </Accordion>

    <!-- Controles de Cantidad -->
    <div class="flex items-center justify-between">
      <div class="flex items-center bg-zinc-100 rounded-lg p-1 gap-1">
        <Button
          variant="ghost"
          size="icon"
          class="h-9 w-9 rounded-md text-red-600 hover:text-red-700 hover:bg-red-100 active:scale-95 transition-transform"
          :aria-label="`Quitar ${item.nombre}`"
          @click="emit('decrementar', item.id)"
        >
          <span class="text-xl font-bold leading-none">−</span>
        </Button>

        <span class="font-bold text-base w-8 text-center text-zinc-900">{{ item.cantidad }}</span>

        <Button
          variant="ghost"
          size="icon"
          class="h-9 w-9 rounded-md text-emerald-600 hover:text-emerald-700 hover:bg-emerald-100 active:scale-95 transition-transform"
          :aria-label="`Agregar otro ${item.nombre}`"
          @click="emit('incrementar', item.id)"
        >
          <span class="text-xl font-bold leading-none">+</span>
        </Button>
      </div>

      <span class="text-xs font-medium text-zinc-500 bg-zinc-50 px-2 py-1 rounded-md border border-zinc-100">
        ${{ item.precio_unitario.toFixed(2) }} c/u
      </span>
    </div>

    <!-- Observaciones -->
    <input
      type="text"
      class="w-full text-xs p-2.5 border border-zinc-200 rounded-lg outline-none text-zinc-800 bg-zinc-50 placeholder:text-zinc-400 focus:ring-2 focus:ring-[#F26A21]/20 focus:border-[#F26A21] transition-all"
      placeholder="Observaciones (sin picante, sin sal…)"
      maxlength="200"
      :value="item.observaciones"
      :aria-label="`Observaciones para ${item.nombre}`"
      @input="emit('observaciones', item.id, $event.target.value)"
    />
  </li>
</template>
