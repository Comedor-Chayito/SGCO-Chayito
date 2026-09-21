<script setup>
/**
 * Componente de tarjeta de comanda para la cola de cocina (KDS).
 * Muestra el número de orden, mesa/canal, platillos con cantidades y observaciones,
 * tiempo transcurrido con alerta de demoras y botones táctiles ergonómicos (>=44px).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – US-POS-02 / RF-POS-001
 */
import { computed, ref, onMounted, onUnmounted } from 'vue';
import {
  Clock,
  Utensils,
  Flame,
  Check,
  AlertTriangle,
  MessageSquare,
  ShoppingBag,
  Store,
  Printer,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import ModalTicketCocina from '@/features/pos/components/ModalTicketCocina.vue';

const props = defineProps({
  comanda: {
    type: Object,
    required: true,
  },
  posicion: {
    type: Number,
    required: true,
  },
  procesando: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['cambiarEstado']);

// Control del modal de impresión térmica
const modalImpresionAbierto = ref(false);

// Temporizador reactivo para calcular minutos transcurridos en tiempo real
const ahora = ref(Date.now());
let timerInterval = null;

onMounted(() => {
  timerInterval = setInterval(() => {
    ahora.value = Date.now();
  }, 30000); // Se actualiza cada 30 segundos
});

onUnmounted(() => {
  if (timerInterval) clearInterval(timerInterval);
});

// Minutos transcurridos desde que se registró la comanda
const minutosTranscurridos = computed(() => {
  if (!props.comanda.created_at) return 0;
  const fechaCreacion = new Date(props.comanda.created_at).getTime();
  const diffMs = ahora.value - fechaCreacion;
  return Math.max(0, Math.floor(diffMs / 60000));
});

// Texto descriptivo del tiempo transcurrido
const tiempoTexto = computed(() => {
  const mins = minutosTranscurridos.value;
  if (mins === 0) return 'Hace un momento';
  if (mins === 1) return 'Hace 1 min';
  if (mins < 60) return `Hace ${mins} min`;
  const horas = Math.floor(mins / 60);
  const resto = mins % 60;
  return `Hace ${horas}h ${resto}m`;
});

// Nivel de urgencia según el tiempo transcurrido
// < 10m: normal, 10-20m: advertencia (#E0A800), > 20m: peligro (#C62828)
const urgencia = computed(() => {
  const mins = minutosTranscurridos.value;
  if (mins >= 20) return 'peligro';
  if (mins >= 10) return 'advertencia';
  return 'normal';
});

// Configuración visual por canal
const infoCanal = computed(() => {
  switch (props.comanda.canal) {
    case 'mesa':
      return {
        etiqueta: props.comanda.mesa ? `Mesa ${props.comanda.mesa.numero}` : 'Mesa',
        icono: Store,
        colorBg: 'bg-orange-50 text-[#C24E12] border-orange-200',
      };
    case 'para_llevar':
      return {
        etiqueta: 'Para llevar',
        icono: ShoppingBag,
        colorBg: 'bg-amber-50 text-amber-800 border-amber-200',
      };
    case 'whatsapp':
      return {
        etiqueta: 'WhatsApp',
        icono: MessageSquare,
        colorBg: 'bg-emerald-50 text-emerald-800 border-emerald-200',
      };
    default:
      return {
        etiqueta: props.comanda.canal,
        icono: Utensils,
        colorBg: 'bg-zinc-100 text-zinc-700 border-zinc-200',
      };
  }
});

// Estado de la orden
const esPendiente = computed(() => props.comanda.estado === 'pendiente');
const esEnCocina = computed(() => props.comanda.estado === 'en_cocina');

// Observación general a nivel de pedido (muestra comanda.observaciones o unifica notas de bandeja)
const observacionGeneral = computed(() => {
  if (props.comanda.observaciones && props.comanda.observaciones.trim()) {
    return props.comanda.observaciones.trim();
  }
  const obsDetalles = props.comanda.detalles
    ?.map(d => d.observaciones?.trim())
    .filter(Boolean) || [];

  if (obsDetalles.length > 0) {
    const unicas = [...new Set(obsDetalles)];
    return unicas.join(' · ');
  }
  return null;
});

/**
 * Emite el evento para avanzar el estado operativo de la comanda.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – US-POS-02 / RF-POS-001
 *
 * @param {string} nuevoEstado Estado objetivo a asignar.
 */
function solicitarCambioEstado(nuevoEstado) {
  emit('cambiarEstado', {
    id: props.comanda.id,
    nuevoEstado,
  });
}
</script>

<template>
  <div
    :id="`comanda-card-${comanda.id}`"
    class="flex flex-col rounded-2xl bg-white border shadow-sm transition-all duration-300 hover:shadow-md overflow-hidden relative"
    :class="[
      urgencia === 'peligro' ? 'border-[#C62828]/50 ring-1 ring-[#C62828]/30' :
      urgencia === 'advertencia' ? 'border-[#E0A800]/50' : 'border-[#E5E0DB]'
    ]"
  >
    <!-- Barra superior de estado y posición en fila -->
    <div
      class="px-4 py-2.5 flex items-center justify-between border-b text-xs font-semibold"
      :class="[
        esEnCocina ? 'bg-amber-500/10 text-amber-900 border-amber-200' : 'bg-blue-500/10 text-[#2B6CB0] border-blue-100'
      ]"
    >
      <div class="flex items-center gap-2">
        <span
          class="flex items-center justify-center w-5 h-5 rounded-full text-[11px] font-bold"
          :class="esEnCocina ? 'bg-amber-500 text-white' : 'bg-[#2B6CB0] text-white'"
        >
          {{ posicion }}
        </span>
        <span class="tracking-wide uppercase text-[11px]">
          {{ esEnCocina ? 'En Preparación' : 'Pendiente' }}
        </span>
        <span
          v-if="esEnCocina"
          class="inline-block w-2 h-2 rounded-full bg-amber-500 animate-ping"
        ></span>
      </div>

      <!-- Temporizador transcurrido -->
      <div
        class="flex items-center gap-1.5 px-2 py-0.5 rounded-full font-medium"
        :class="[
          urgencia === 'peligro' ? 'bg-[#C62828]/15 text-[#C62828] font-bold animate-pulse' :
          urgencia === 'advertencia' ? 'bg-[#E0A800]/15 text-[#C24E12] font-semibold' :
          'text-zinc-600'
        ]"
      >
        <Clock :size="13" stroke-width="2" />
        <span>{{ tiempoTexto }}</span>
        <AlertTriangle v-if="urgencia === 'peligro'" :size="13" class="text-[#C62828]" />
      </div>
    </div>

    <!-- Encabezado de la comanda: Número y Canal / Mesa -->
    <div class="p-4 pb-3 flex items-start justify-between gap-2 border-b border-[#E5E0DB]/60 bg-[#FAF7F4]/50">
      <div>
        <div class="flex items-center gap-2">
          <h3 class="text-lg font-bold text-[#3D3D3D] tracking-tight">
            Orden #{{ comanda.id }}
          </h3>
          <button
            type="button"
            @click="modalImpresionAbierto = true"
            class="flex items-center justify-center h-7 w-7 rounded-lg text-zinc-400 hover:text-[#F26A21] hover:bg-orange-50 border border-transparent hover:border-orange-200 transition-all cursor-pointer"
            title="Imprimir comanda térmica (58 mm / 80 mm)"
            :id="`btn-imprimir-${comanda.id}`"
          >
            <Printer :size="15" />
          </button>
        </div>
        <p class="text-sm text-[#6B6B6B] mt-0.5">
          {{ comanda.created_at ? new Date(comanda.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '' }}
          <span v-if="comanda.usuario"> · Atendió: {{ comanda.usuario.name }}</span>
        </p>
      </div>

      <!-- Insignia del canal / mesa -->
      <div
        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border text-xs font-semibold shadow-xs"
        :class="infoCanal.colorBg"
      >
        <component :is="infoCanal.icono" :size="14" stroke-width="2" />
        <span>{{ infoCanal.etiqueta }}</span>
      </div>
    </div>

    <!-- Lista de Platillos a preparar -->
    <div class="p-4 flex-1 space-y-2.5">
      <h4 class="text-[11px] font-bold text-[#6B6B6B] uppercase tracking-wider">
        Platillos ({{ comanda.detalles?.length || 0 }})
      </h4>

      <div class="space-y-2">
        <div
          v-for="detalle in comanda.detalles"
          :key="detalle.id"
          class="p-2.5 rounded-xl bg-[#FAF7F4] border border-[#E5E0DB]/80 flex flex-col gap-1.5"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2.5 min-w-0">
              <!-- Cantidad destacada -->
              <span class="inline-flex items-center justify-center min-w-[28px] h-7 px-1.5 rounded-lg bg-[#3D3D3D] text-white text-xs font-bold shrink-0">
                {{ detalle.cantidad }}×
              </span>
              <!-- Nombre del platillo -->
              <span class="font-semibold text-sm text-[#111111] truncate">
                {{ detalle.platillo?.nombre || 'Platillo' }}
              </span>
            </div>
          </div>

          <!-- Observaciones específicas del platillo (solo si difiere de la nota general) -->
          <div
            v-if="detalle.observaciones && detalle.observaciones.trim() !== observacionGeneral"
            class="ml-9 px-2 py-1 rounded-md bg-white border border-amber-200/80 text-amber-900 text-xs flex items-center gap-1.5"
          >
            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
            <span class="font-medium italic leading-tight">{{ detalle.observaciones }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Observación general al pie de la card -->
    <div
      v-if="observacionGeneral"
      class="mx-4 mb-3 p-3 rounded-xl bg-amber-50/90 border border-amber-200 text-amber-950 text-xs flex items-start gap-2.5 shadow-xs"
    >
      <AlertTriangle :size="16" class="text-[#C24E12] shrink-0 mt-0.5" />
      <div class="min-w-0 flex-1">
        <span class="font-bold text-[#C24E12] block">Observación del pedido:</span>
        <p class="mt-0.5 text-zinc-800 font-medium leading-relaxed">{{ observacionGeneral }}</p>
      </div>
    </div>

    <!-- Pie de tarjeta: Botón táctil ergonómico (mínimo 44px) -->
    <div class="p-4 pt-2 border-t border-[#E5E0DB]/60 bg-[#FAF7F4]/30">
      <!-- Caso 1: Comanda Pendiente -> Botón para iniciar preparación -->
      <button
        v-if="esPendiente"
        type="button"
        :disabled="procesando"
        @click="solicitarCambioEstado('en_cocina')"
        class="w-full min-h-[46px] px-4 py-2.5 rounded-xl bg-[#F26A21] hover:bg-[#FF8C42] active:scale-[0.98] text-white font-semibold text-sm flex items-center justify-center gap-2 shadow-sm transition-all duration-150 disabled:opacity-50 cursor-pointer"
        :id="`btn-iniciar-${comanda.id}`"
      >
        <Flame :size="18" stroke-width="2.2" />
        <span>Iniciar Preparación</span>
      </button>

      <!-- Caso 2: Comanda En Cocina -> Botón para marcar lista -->
      <div v-else-if="esEnCocina" class="flex gap-2">
        <button
          type="button"
          :disabled="procesando"
          @click="solicitarCambioEstado('pendiente')"
          title="Regresar a pendiente"
          class="min-h-[46px] min-w-[46px] px-3 rounded-xl border border-[#E5E0DB] bg-white hover:bg-zinc-50 active:scale-[0.98] text-[#6B6B6B] hover:text-[#3D3D3D] flex items-center justify-center transition-all cursor-pointer"
        >
          <span class="text-xs font-semibold">«</span>
        </button>

        <button
          type="button"
          :disabled="procesando"
          @click="solicitarCambioEstado('pagada')"
          class="flex-1 min-h-[46px] px-4 py-2.5 rounded-xl bg-[#2E7D4F] hover:bg-[#2E7D4F]/90 active:scale-[0.98] text-white font-semibold text-sm flex items-center justify-center gap-2 shadow-sm transition-all duration-150 disabled:opacity-50 cursor-pointer"
          :id="`btn-completar-${comanda.id}`"
        >
          <Check :size="18" stroke-width="2.5" />
          <span>Comanda Lista</span>
        </button>
      </div>

      <!-- Otros estados -->
      <div
        v-else
        class="min-h-[44px] flex items-center justify-center text-xs font-semibold text-[#2E7D4F] bg-emerald-50 rounded-xl border border-emerald-200"
      >
        Comanda completada
      </div>
    </div>

    <!-- Modal de Impresión Térmica para Cocina (US-POS-02 / RF-POS-001) -->
    <ModalTicketCocina
      :abierto="modalImpresionAbierto"
      :comanda-id="comanda.id"
      @cerrar="modalImpresionAbierto = false"
    />
  </div>
</template>
