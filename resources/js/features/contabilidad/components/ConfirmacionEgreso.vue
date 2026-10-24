<script setup>
/**
 * Tarjeta de confirmación del último egreso registrado.
 * Muestra el correlativo en grande para anotarlo en la factura física.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 (CC-64)
 */
import { computed } from 'vue';
import { CheckCircle2, X } from '@lucide/vue';

const props = defineProps({
  egreso: { type: Object, required: true },
});

defineEmits(['cerrar']);

const formatoMoneda = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

const montoFormateado = computed(() => formatoMoneda.format(Number(props.egreso.monto_total)));

// AAAA-MM-DD → DD/MM/AAAA sin pasar por Date, para no desplazar el día por zona horaria
const fechaFormateada = computed(() =>
  String(props.egreso.fecha_comprobante ?? '').split('-').reverse().join('/')
);
</script>

<template>
  <section
    id="confirmacion-egreso"
    class="rounded-2xl border border-[#2E7D4F]/30 bg-[#2E7D4F]/5 p-5"
    role="status"
    aria-live="polite"
  >
    <div class="flex items-start justify-between gap-4">
      <div class="flex items-center gap-3">
        <CheckCircle2 :size="28" class="shrink-0 text-[#2E7D4F]" aria-hidden="true" />
        <div>
          <p class="text-sm font-semibold text-[#2E7D4F]">Egreso registrado correctamente</p>
          <p class="text-2xl font-bold tracking-wide text-[#111111]">
            {{ egreso.correlativo }}
          </p>
        </div>
      </div>

      <button
        type="button"
        class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl
          text-[#6B6B6B] hover:bg-white"
        title="Cerrar confirmación"
        aria-label="Cerrar confirmación"
        @click="$emit('cerrar')"
      >
        <X :size="18" />
      </button>
    </div>

    <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm md:grid-cols-5">
      <div>
        <dt class="text-xs text-[#6B6B6B]">Proveedor</dt>
        <dd class="font-medium text-[#3D3D3D]">{{ egreso.proveedor }}</dd>
      </div>
      <div>
        <dt class="text-xs text-[#6B6B6B]">N.º de factura o CCF</dt>
        <dd class="font-medium text-[#3D3D3D]">{{ egreso.numero_comprobante }}</dd>
      </div>
      <div>
        <dt class="text-xs text-[#6B6B6B]">Subcategoría</dt>
        <dd class="font-medium text-[#3D3D3D]">{{ egreso.subcategoria?.nombre }}</dd>
      </div>
      <div>
        <dt class="text-xs text-[#6B6B6B]">Fecha</dt>
        <dd class="font-medium text-[#3D3D3D]">{{ fechaFormateada }}</dd>
      </div>
      <div>
        <dt class="text-xs text-[#6B6B6B]">Monto total</dt>
        <dd class="font-bold text-[#C24E12]">{{ montoFormateado }}</dd>
      </div>
    </dl>
  </section>
</template>
