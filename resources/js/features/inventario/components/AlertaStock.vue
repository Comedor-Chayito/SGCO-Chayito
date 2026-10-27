<script setup>
/**
 * Alerta de quiebre de stock (CC-58 / US-INV-03).
 * Resume los insumos en o por debajo del mínimo y muestra una tarjeta por insumo
 * con su nivel de existencia y lo que falta para llegar al mínimo.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-004 / CC-58
 */
import { computed } from 'vue';
import { OctagonAlert, TriangleAlert } from '@lucide/vue';
import { useCantidad } from '@/shared/composables/useCantidad.js';
import { calcularNivelStock } from '@/features/inventario/services/stockService.js';

const props = defineProps({
  alertas: { type: Array, required: true },
  error: { type: String, default: null },
});

const ESTILOS_ESTADO = {
  agotado: {
    texto: 'Agotado',
    icono: OctagonAlert,
    etiqueta: 'bg-[#C62828] text-white',
    borde: 'border-l-[#C62828]',
    barra: 'bg-[#C62828]',
    faltante: 'text-[#C62828]',
  },
  bajo: {
    texto: 'Bajo mínimo',
    icono: TriangleAlert,
    etiqueta: 'bg-[#E0A800] text-[#111111]',
    borde: 'border-l-[#E0A800]',
    barra: 'bg-[#E0A800]',
    faltante: 'text-[#3D3D3D]',
  },
};

const { formatearCantidad } = useCantidad();

const totalAgotados = computed(
  () => props.alertas.filter((i) => i.estado_stock === 'agotado').length
);

const tarjetas = computed(() =>
  props.alertas.map((insumo) => ({
    ...insumo,
    ...calcularNivelStock(insumo),
    estilo: ESTILOS_ESTADO[insumo.estado_stock] ?? ESTILOS_ESTADO.bajo,
  }))
);
</script>

<template>
  <section v-if="alertas.length > 0 || error" id="panel-alerta-stock">
    <!-- Resumen -->
    <div
      role="alert"
      class="mb-4 flex items-center gap-3 rounded-xl border border-[#C62828]/30 border-l-4 border-l-[#C62828] bg-white px-4 py-3"
    >
      <OctagonAlert class="shrink-0 text-[#C62828]" :size="20" />
      <p v-if="error" class="text-sm text-[#3D3D3D]">{{ error }}</p>
      <p v-else class="text-sm text-[#3D3D3D]">
        <strong class="text-[#C62828]">Quiebre de stock:</strong>
        {{ alertas.length }} insumo(s) en o por debajo del mínimo<span v-if="totalAgotados > 0">,
          <strong>{{ totalAgotados }} agotado(s)</strong></span>.
      </p>
    </div>

    <!-- Tarjetas -->
    <ul v-if="!error" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
      <li
        v-for="insumo in tarjetas"
        :key="insumo.id"
        :id="`alerta-insumo-${insumo.id}`"
        class="rounded-xl border border-[#E5E0DB] border-l-4 bg-white p-4 shadow-sm"
        :class="insumo.estilo.borde"
      >
        <div class="flex items-start justify-between gap-3">
          <p class="min-w-0 truncate font-bold text-[#111111]">{{ insumo.nombre }}</p>
          <span
            class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-xs font-bold"
            :class="insumo.estilo.etiqueta"
          >
            <component :is="insumo.estilo.icono" :size="12" />
            {{ insumo.estilo.texto }}
          </span>
        </div>

        <p class="mt-1 text-sm text-[#6B6B6B]">
          Hay {{ formatearCantidad(insumo.stock_actual) }} {{ insumo.unidad_medida }}
          · Mínimo {{ formatearCantidad(insumo.stock_minimo) }} {{ insumo.unidad_medida }}
        </p>

        <div class="mt-3 flex items-center gap-3">
          <div
            role="progressbar"
            :aria-valuenow="insumo.porcentaje"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-label="`Existencia de ${insumo.nombre} respecto al mínimo`"
            class="h-2 flex-1 overflow-hidden rounded-full bg-[#E5E0DB]"
          >
            <div
              class="h-full rounded-full"
              :class="insumo.estilo.barra"
              :style="{ width: `${insumo.porcentaje}%` }"
            />
          </div>
          <span class="w-10 text-right text-xs font-semibold text-[#6B6B6B]">
            {{ insumo.porcentaje }} %
          </span>
        </div>

        <p class="mt-2 text-sm font-semibold" :class="insumo.estilo.faltante">
          <template v-if="insumo.faltante > 0">
            Faltan {{ formatearCantidad(insumo.faltante) }} {{ insumo.unidad_medida }}
          </template>
          <template v-else>Justo en el mínimo</template>
        </p>
      </li>
    </ul>
  </section>
</template>
