<script setup>
/**
 * Panel rojo de quiebre de stock (CC-58 / US-INV-03).
 * Lista los insumos en o por debajo del mínimo para orientar las compras de bodega.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-004 / CC-58
 */
import { computed } from 'vue';
import { OctagonAlert } from '@lucide/vue';
import { useCantidad } from '@/shared/composables/useCantidad.js';

const props = defineProps({
  alertas: { type: Array, required: true },
  error: { type: String, default: null },
});

const ETIQUETAS_ESTADO = {
  agotado: { texto: 'Agotado', clase: 'bg-[#C62828] text-white' },
  bajo: { texto: 'Bajo mínimo', clase: 'border border-[#C62828] bg-white text-[#C62828]' },
};

const { formatearCantidad } = useCantidad();

const totalAgotados = computed(
  () => props.alertas.filter((i) => i.estado_stock === 'agotado').length
);
</script>

<template>
  <section
    v-if="alertas.length > 0 || error"
    id="panel-alerta-stock"
    role="alert"
    class="rounded-2xl border-2 border-[#C62828] bg-[#C62828]/5 p-4 sm:p-5"
  >
    <div class="flex items-start gap-3">
      <OctagonAlert class="mt-0.5 shrink-0 text-[#C62828]" :size="22" />
      <div class="min-w-0 flex-1">
        <h2 class="text-base font-black text-[#C62828]">Quiebre de stock</h2>

        <p v-if="error" class="mt-0.5 text-sm text-[#3D3D3D]">{{ error }}</p>
        <p v-else class="mt-0.5 text-sm text-[#3D3D3D]">
          <strong>{{ alertas.length }} insumo(s)</strong> en o por debajo del mínimo
          <span v-if="totalAgotados > 0">· {{ totalAgotados }} agotado(s)</span>.
          Inclúyelos en la próxima compra.
        </p>
      </div>
    </div>

    <ul v-if="!error" class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
      <li
        v-for="insumo in alertas"
        :key="insumo.id"
        :id="`alerta-insumo-${insumo.id}`"
        class="flex min-h-[44px] items-center justify-between gap-3 rounded-xl border border-[#C62828]/30 bg-white px-3 py-2.5"
      >
        <div class="min-w-0">
          <p class="truncate text-sm font-bold text-[#111111]">{{ insumo.nombre }}</p>
          <p class="text-xs text-[#6B6B6B]">
            {{ formatearCantidad(insumo.stock_actual) }} /
            {{ formatearCantidad(insumo.stock_minimo) }} {{ insumo.unidad_medida }}
          </p>
        </div>
        <span
          class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold"
          :class="ETIQUETAS_ESTADO[insumo.estado_stock]?.clase"
        >
          {{ ETIQUETAS_ESTADO[insumo.estado_stock]?.texto }}
        </span>
      </li>
    </ul>
  </section>
</template>
