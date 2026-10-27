<script setup>
/**
 * Componente de botón táctil de platillo en el menú del POS.
 * Utiliza shadcn Card para un diseño premium y feedback visual claro.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 */
import { Card, CardContent } from '@/components/ui/card';
import { Plus } from '@lucide/vue';

const props = defineProps({
  platillo: {
    type: Object,
    required: true,
  },
  compacta: {
    type: Boolean,
    default: false,
  },
  cantidadSeleccionada: {
    type: Number,
    default: 0,
  },
});

const emit = defineEmits(['agregar']);

function onTap() {
  emit('agregar', props.platillo);
}
</script>

<template>
  <button
    class="text-left touch-manipulation rounded-xl focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#F26A21] focus-visible:ring-offset-2 disabled:cursor-not-allowed"
    :class="compacta ? 'w-[8.25rem] shrink-0 sm:w-36 lg:w-40' : 'w-full'"
    :disabled="!platillo.disponible"
    :aria-label="`Agregar ${platillo.nombre} - $${parseFloat(platillo.precio_unitario).toFixed(2)}`"
    :aria-pressed="cantidadSeleccionada > 0"
    @click="onTap"
  >
    <Card
      class="h-full cursor-pointer py-0 transition-[transform,box-shadow,border-color,background-color] duration-150 group active:scale-[0.98]"
      :class="[
        compacta ? 'min-h-[88px]' : 'min-h-[108px]',
        !platillo.disponible
          ? 'opacity-40 bg-zinc-50 border-dashed'
          : cantidadSeleccionada > 0
            ? 'bg-[#FFF7F2] ring-[#F26A21] shadow-[0_8px_24px_-16px_rgba(194,78,18,0.55)]'
            : 'bg-white hover:ring-[#F26A21]/60 hover:shadow-[0_8px_24px_-18px_rgba(61,61,61,0.45)]'
      ]"
    >
      <CardContent
        class="h-full flex flex-col items-start justify-between text-left"
        :class="compacta ? 'p-3 gap-2' : 'p-4 gap-3'"
      >
        <span
          class="font-bold leading-tight text-zinc-800 whitespace-normal"
          :class="compacta ? 'text-[13px] pr-1' : 'text-sm'"
        >
          {{ platillo.nombre }}
        </span>

        <div class="flex w-full items-end justify-between gap-2">
          <span class="font-black text-[#C24E12] tabular-nums" :class="compacta ? 'text-sm' : 'text-base'">
            ${{ parseFloat(platillo.precio_unitario).toFixed(2) }}
          </span>
          <span
            class="flex size-7 shrink-0 items-center justify-center rounded-lg transition-colors"
            :class="cantidadSeleccionada > 0 ? 'bg-[#F26A21] text-white' : 'bg-zinc-100 text-zinc-500 group-hover:bg-[#F26A21]/10 group-hover:text-[#C24E12]'"
            aria-hidden="true"
          >
            <span v-if="cantidadSeleccionada > 0" class="text-xs font-black tabular-nums">
              {{ cantidadSeleccionada }}
            </span>
            <Plus v-else class="size-4" :stroke-width="2.5" />
          </span>
        </div>
      </CardContent>
    </Card>
  </button>
</template>
