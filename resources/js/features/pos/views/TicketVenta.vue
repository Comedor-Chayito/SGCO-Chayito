<script setup>
/**
 * Ticket / Comprobante digital de venta (CC-35).
 * Carga los datos de una venta por su ID y muestra el desglose completo.
 * Permite imprimir con window.print() via estilos @media print.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 */
import { ref, computed, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ArrowLeft, Printer, ShoppingCart, CheckCircle2 } from '@lucide/vue';
import { obtenerComprobante } from '@/features/pos/services/posService.js';

const route  = useRoute();
const router = useRouter();

const venta    = ref(null);
const cargando = ref(true);
const error    = ref(null);

const TASA_IVA = 0.13;

/**
 * Carga los datos del comprobante desde el backend al montar la vista.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 */
onMounted(async () => {
  try {
    venta.value = await obtenerComprobante(route.params.id);
  } catch {
    error.value = 'No se pudo cargar el comprobante.';
  } finally {
    cargando.value = false;
  }
});

// Cálculos de IVA implícito
const total     = computed(() => Number(venta.value?.total || 0));
const costoNeto = computed(() => Math.round((total.value / (1 + TASA_IVA)) * 100) / 100);
const montoIva  = computed(() => Math.round(costoNeto.value * TASA_IVA * 100) / 100);

/**
 * Formatea un número como moneda en dólares.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 *
 * @param {number} valor Valor a formatear.
 * @returns {string} Cadena formateada (ej. "$12.50").
 */
function formatearMoneda(valor) {
  return `$${Number(valor).toFixed(2)}`;
}

/**
 * Formatea una fecha ISO como cadena legible en hora local.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 *
 * @param {string} fechaIso Cadena de fecha ISO 8601.
 * @returns {string} Fecha y hora formateadas.
 */
function formatearFecha(fechaIso) {
  if (!fechaIso) return '';
  return new Date(fechaIso).toLocaleString('es-SV', {
    year:   'numeric',
    month:  'long',
    day:    'numeric',
    hour:   '2-digit',
    minute: '2-digit',
  });
}

/**
 * Devuelve la etiqueta del canal de la comanda.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 *
 * @param {Object} comanda Objeto comanda.
 * @returns {string} Etiqueta descriptiva del canal.
 */
function etiquetaCanal(comanda) {
  if (!comanda) return '';
  if (comanda.canal === 'mesa') return comanda.mesa ? `Mesa ${comanda.mesa.numero}` : 'Mesa';
  if (comanda.canal === 'para_llevar') return 'Para llevar';
  if (comanda.canal === 'whatsapp') return 'WhatsApp';
  return comanda.canal;
}

/**
 * Abre el diálogo de impresión del navegador para el comprobante.
 * Los estilos print ocultan la barra de acciones y dejan solo el ticket.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-03 / RF-POS-002 (CC-35)
 */
function imprimirTicket() {
  window.print();
}
</script>

<template>
  <div class="min-h-[100dvh] bg-[#FAF7F4] flex flex-col" id="vista-ticket-venta">

    <!-- Barra de acciones (oculta al imprimir) -->
    <header class="sticky top-[var(--alto-shell,0px)] z-10 flex items-center justify-between gap-3 border-b border-[#E5E0DB] bg-white/95 px-4 py-3 backdrop-blur-md shadow-xs print:hidden">
      <div class="flex items-center gap-3">
        <RouterLink
          to="/caja"
          class="flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] text-[#3D3D3D] hover:bg-zinc-100 active:scale-95 transition-all"
          title="Volver a Caja"
        >
          <ArrowLeft :size="20" stroke-width="2" />
        </RouterLink>
        <h1 class="text-lg font-bold text-[#3D3D3D]">Comprobante de Venta</h1>
      </div>

      <div class="flex items-center gap-2" v-if="venta">
        <!-- Imprimir -->
        <button
          type="button"
          id="btn-imprimir-ticket"
          class="flex h-11 items-center gap-2 rounded-xl border border-[#E5E0DB] bg-white px-4 text-sm font-semibold text-[#3D3D3D] hover:bg-zinc-50 active:scale-95 transition-all cursor-pointer shadow-xs"
          @click="imprimirTicket"
        >
          <Printer :size="16" stroke-width="2.2" />
          <span class="hidden sm:inline">Imprimir</span>
        </button>

        <!-- Nueva venta -->
        <RouterLink
          to="/pos"
          id="btn-nueva-venta"
          class="flex h-11 items-center gap-2 rounded-xl bg-[#F26A21] px-4 text-sm font-semibold text-white hover:bg-[#FF8C42] active:scale-95 transition-all shadow-xs"
        >
          <ShoppingCart :size="16" stroke-width="2.2" />
          <span>Nueva venta</span>
        </RouterLink>
      </div>
    </header>

    <!-- Carga -->
    <div v-if="cargando" class="flex-1 flex items-center justify-center">
      <div class="flex flex-col items-center gap-3 text-[#6B6B6B]">
        <div class="w-8 h-8 rounded-full border-2 border-[#F26A21] border-t-transparent animate-spin"></div>
        <span class="text-sm">Cargando comprobante...</span>
      </div>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="flex-1 flex flex-col items-center justify-center p-8 text-center">
      <p class="text-[#C62828] font-semibold">{{ error }}</p>
      <button
        type="button"
        class="mt-4 px-4 py-2 rounded-xl border border-[#E5E0DB] bg-white text-sm font-semibold hover:bg-zinc-50 cursor-pointer"
        @click="router.push('/caja')"
      >
        Regresar
      </button>
    </div>

    <!-- Ticket imprimible -->
    <main v-else-if="venta" class="flex-1 flex items-start justify-center p-4 md:p-8">
      <div
        id="ticket-imprimible"
        class="w-full max-w-sm bg-white rounded-2xl border border-[#E5E0DB] shadow-sm overflow-hidden"
      >
        <!-- Encabezado del ticket -->
        <div class="px-6 pt-6 pb-4 text-center border-b border-dashed border-[#E5E0DB]">
          <div class="flex items-center justify-center gap-2 mb-1">
            <div class="w-8 h-8 rounded-lg bg-[#F26A21] flex items-center justify-center">
              <span class="text-white font-black text-xs">C</span>
            </div>
            <span class="font-black text-lg text-[#3D3D3D] tracking-tight">Comedor Chayito</span>
          </div>
          <p class="text-xs text-[#6B6B6B]">UES-FMO · Sistema de Gestión Operativa</p>
          <div class="mt-3 flex items-center justify-center gap-2">
            <CheckCircle2 :size="16" class="text-[#2E7D4F]" stroke-width="2.5" />
            <span class="text-xs font-bold text-[#2E7D4F] uppercase tracking-wide">Venta Completada</span>
          </div>
        </div>

        <!-- Datos de la venta -->
        <div class="px-6 py-3 border-b border-dashed border-[#E5E0DB] space-y-1.5 text-xs">
          <div class="flex justify-between text-[#6B6B6B]">
            <span>N.° Venta</span>
            <span class="font-bold text-[#3D3D3D]">#{{ venta.id }}</span>
          </div>
          <div class="flex justify-between text-[#6B6B6B]">
            <span>N.° Orden</span>
            <span class="font-bold text-[#3D3D3D]">#{{ venta.comanda?.id }}</span>
          </div>
          <div class="flex justify-between text-[#6B6B6B]">
            <span>Canal</span>
            <span class="font-semibold text-[#3D3D3D]">{{ etiquetaCanal(venta.comanda) }}</span>
          </div>
          <div class="flex justify-between text-[#6B6B6B]">
            <span>Fecha</span>
            <span class="font-semibold text-[#3D3D3D]">{{ formatearFecha(venta.created_at) }}</span>
          </div>
          <div v-if="venta.usuario" class="flex justify-between text-[#6B6B6B]">
            <span>Cajero/a</span>
            <span class="font-semibold text-[#3D3D3D]">{{ venta.usuario.name }}</span>
          </div>
        </div>

        <!-- Detalle de platillos -->
        <div class="px-6 py-3 border-b border-dashed border-[#E5E0DB]">
          <p class="text-[10px] font-bold uppercase tracking-wider text-[#6B6B6B] mb-2">Detalle</p>
          <div class="space-y-2">
            <div
              v-for="detalle in venta.comanda?.detalles"
              :key="detalle.id"
              class="flex items-start justify-between text-xs gap-2"
            >
              <div class="flex items-center gap-1.5 min-w-0">
                <span class="shrink-0 text-[#6B6B6B] font-bold">{{ detalle.cantidad }}×</span>
                <span class="font-medium text-[#3D3D3D] truncate">{{ detalle.platillo?.nombre }}</span>
              </div>
              <span class="shrink-0 font-semibold text-[#3D3D3D]">
                {{ formatearMoneda(detalle.precio_unitario * detalle.cantidad) }}
              </span>
            </div>
          </div>
        </div>

        <!-- Totales -->
        <div class="px-6 py-3 border-b border-dashed border-[#E5E0DB] space-y-1.5 text-xs">
          <div class="flex justify-between text-[#6B6B6B]">
            <span>Costo neto</span>
            <span>{{ formatearMoneda(costoNeto) }}</span>
          </div>
          <div class="flex justify-between text-[#6B6B6B]">
            <span>IVA (13% implícito)</span>
            <span>{{ formatearMoneda(montoIva) }}</span>
          </div>
          <div class="flex justify-between font-black text-sm text-[#3D3D3D] pt-1 border-t border-[#E5E0DB]">
            <span>Total</span>
            <span class="text-[#F26A21]">{{ formatearMoneda(total) }}</span>
          </div>
        </div>

        <!-- Cobro -->
        <div class="px-6 py-3 border-b border-dashed border-[#E5E0DB] space-y-1.5 text-xs">
          <div class="flex justify-between text-[#6B6B6B]">
            <span>Método de pago</span>
            <span class="font-semibold text-[#3D3D3D] capitalize">{{ venta.metodo_pago }}</span>
          </div>
          <div class="flex justify-between text-[#6B6B6B]">
            <span>Recibido</span>
            <span class="font-semibold text-[#3D3D3D]">{{ formatearMoneda(venta.monto_recibido) }}</span>
          </div>
          <div class="flex justify-between font-bold text-sm">
            <span class="text-[#3D3D3D]">Cambio</span>
            <span class="text-[#2E7D4F]">{{ formatearMoneda(venta.cambio) }}</span>
          </div>
        </div>

        <!-- Pie del ticket -->
        <div class="px-6 py-4 text-center text-[10px] text-[#6B6B6B] space-y-0.5">
          <p class="font-semibold">¡Gracias por su preferencia!</p>
          <p>Este comprobante no tiene valor fiscal.</p>
        </div>
      </div>
    </main>
  </div>
</template>

<style scoped>
@media print {
  @page {
    margin: 0;
  }

  #vista-ticket-venta {
    background: white;
  }

  main {
    padding: 0;
  }

  #ticket-imprimible {
    box-shadow: none;
    border: none;
    border-radius: 0;
    max-width: 100%;
  }
}
</style>
