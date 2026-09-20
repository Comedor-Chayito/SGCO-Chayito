<script setup>
/**
 * Renglón compacto de una comanda completada para el panel lateral de cocina.
 * Se muestra en una sola línea y se expande en una tarjeta simple al pulsar "Más info".
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – US-POS-02 / RF-POS-001
 */
import { ref, computed } from 'vue';
import {
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Clock,
  RotateCcw,
  Store,
  ShoppingBag,
  MessageSquare,
  Utensils,
  AlertTriangle,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';

const props = defineProps({
  comanda: {
    type: Object,
    required: true,
  },
  procesando: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['reactivar']);

const expandido = ref(false);

function toggleExpandir() {
  expandido.value = !expandido.value;
}

// Canal formateado
const canalTexto = computed(() => {
  if (props.comanda.canal === 'mesa') {
    return props.comanda.mesa ? `Mesa ${props.comanda.mesa.numero}` : 'Mesa';
  }
  if (props.comanda.canal === 'para_llevar') return 'Para llevar';
  if (props.comanda.canal === 'whatsapp') return 'WhatsApp';
  return props.comanda.canal;
});

// Resumen de platillos (total de unidades)
const totalItems = computed(() => {
  if (!props.comanda.detalles) return 0;
  return props.comanda.detalles.reduce((acc, d) => acc + (d.cantidad || 1), 0);
});

// Hora de despacho
const horaDespacho = computed(() => {
  const fecha = props.comanda.updated_at || props.comanda.created_at;
  if (!fecha) return '';
  return new Date(fecha).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
});

// Observación general de la orden
const observacionTexto = computed(() => {
  if (props.comanda.observaciones && props.comanda.observaciones.trim()) {
    return props.comanda.observaciones.trim();
  }
  const obsDetalles = props.comanda.detalles
    ?.map(d => d.observaciones?.trim())
    .filter(Boolean) || [];

  if (obsDetalles.length > 0) {
    return [...new Set(obsDetalles)].join(' · ');
  }
  return null;
});
</script>

<template>
  <div
    class="rounded-xl border border-[#E5E0DB] bg-white transition-all overflow-hidden"
    :class="{ 'shadow-sm border-[#2E7D4F]/40': expandido }"
  >
    <!-- Renglón compacto de 1 línea -->
    <div
      class="flex items-center justify-between gap-2 px-3 py-2.5 cursor-pointer hover:bg-zinc-50 transition-colors select-none"
      @click="toggleExpandir"
    >
      <!-- Identificador y Mesa/Canal -->
      <div class="flex items-center gap-2 min-w-0 flex-1">
        <span class="flex items-center justify-center w-6 h-6 rounded-md bg-[#2E7D4F]/10 text-[#2E7D4F] text-xs font-bold shrink-0">
          #{{ comanda.id }}
        </span>

        <span class="font-bold text-xs text-[#3D3D3D] truncate">
          {{ canalTexto }}
        </span>

        <span class="text-zinc-300">·</span>

        <span class="text-[11px] text-[#6B6B6B] shrink-0">
          {{ totalItems }} platillo(s)
        </span>
      </div>

      <!-- Hora y botón Más Info -->
      <div class="flex items-center gap-1.5 shrink-0">
        <span class="text-[11px] text-zinc-500 font-medium hidden sm:inline">
          {{ horaDespacho }}
        </span>

        <button
          type="button"
          class="flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold text-[#2E7D4F] hover:bg-[#2E7D4F]/10 active:scale-95 transition-all"
          :title="expandido ? 'Ocultar detalles' : 'Ver más información'"
          @click.stop="toggleExpandir"
        >
          <span class="text-[11px]">{{ expandido ? 'Menos' : 'Info' }}</span>
          <ChevronUp v-if="expandido" :size="14" stroke-width="2.5" />
          <ChevronDown v-else :size="14" stroke-width="2.5" />
        </button>
      </div>
    </div>

    <!-- Tarjeta desplegable simple con toda la información -->
    <div
      v-if="expandido"
      class="p-3 bg-[#FAF7F4] border-t border-[#E5E0DB] space-y-2.5 text-xs animate-in fade-in slide-in-from-top-1 duration-200"
    >
      <!-- Encabezado de la tarjeta simple -->
      <div class="flex items-center justify-between text-zinc-600 pb-1.5 border-b border-[#E5E0DB]/80">
        <div class="flex items-center gap-1.5">
          <Clock :size="13" class="text-zinc-400" />
          <span>Registrada: {{ comanda.created_at ? new Date(comanda.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '' }}</span>
        </div>
        <span v-if="comanda.usuario">Atendió: {{ comanda.usuario.name }}</span>
      </div>

      <!-- Desglose de platillos -->
      <div>
        <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wider block mb-1">
          Platillos preparados:
        </span>
        <ul class="space-y-1">
          <li
            v-for="d in comanda.detalles"
            :key="d.id"
            class="flex items-center justify-between bg-white px-2.5 py-1.5 rounded-lg border border-[#E5E0DB]/60"
          >
            <span class="font-medium text-[#111111]">
              <span class="font-bold text-[#2E7D4F] mr-1">{{ d.cantidad }}×</span>
              {{ d.platillo?.nombre || 'Platillo' }}
            </span>
            <span v-if="d.precio_unitario" class="text-zinc-500 tabular-nums">
              ${{ (parseFloat(d.precio_unitario) * d.cantidad).toFixed(2) }}
            </span>
          </li>
        </ul>
      </div>

      <!-- Observación si existió -->
      <div
        v-if="observacionTexto"
        class="p-2 rounded-lg bg-amber-50 border border-amber-200/80 text-amber-900 text-[11px] flex items-start gap-1.5"
      >
        <AlertTriangle :size="13" class="text-[#C24E12] shrink-0 mt-0.5" />
        <div>
          <span class="font-bold text-[#C24E12]">Observación:</span>
          <p class="mt-0.5 leading-snug">{{ observacionTexto }}</p>
        </div>
      </div>

      <!-- Pie de la tarjeta simple: Total y Reabrir -->
      <div class="pt-1.5 flex items-center justify-between gap-2 border-t border-[#E5E0DB]/80">
        <span class="font-bold text-[#3D3D3D]">
          Total: <span class="text-[#F26A21]">${{ parseFloat(comanda.subtotal || 0).toFixed(2) }}</span>
        </span>

        <!-- Botón para reactivar a cocina si se marcó completada por error -->
        <button
          type="button"
          :disabled="procesando"
          @click="emit('reactivar', comanda.id)"
          class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-[#E5E0DB] bg-white hover:bg-zinc-100 active:scale-95 text-[11px] font-semibold text-zinc-700 transition-all cursor-pointer disabled:opacity-50"
          title="Regresar orden a la cola de cocina"
        >
          <RotateCcw :size="12" stroke-width="2" />
          <span>Reactivar comanda</span>
        </button>
      </div>
    </div>
  </div>
</template>
