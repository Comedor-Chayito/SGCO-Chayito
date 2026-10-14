<script setup>
/**
 * Pantalla de Cobro / Caja (CC-32).
 * Muestra las comandas marcadas como "listas" por la cocina y permite al cajero
 * seleccionar una, ingresar el monto recibido y procesar el cobro.
 * Al confirmar, llama al backend (POST /api/cobros) y redirige al ticket.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import {
  ArrowLeft,
  ShoppingBag,
  CreditCard,
  Banknote,
  CheckCircle2,
  Clock,
  Store,
  MessageSquare,
  Utensils,
  Receipt,
  ChefHat,
  ArrowRightLeft,
  ArrowDownCircle,
} from '@lucide/vue';
import { Toaster, toast } from 'vue-sonner';
import { obtenerComandas, procesarCobro } from '@/features/pos/services/posService.js';
import { useCalculoCambio } from '@/features/pos/composables/useCalculoCambio.js';
import PanelRetiros from '@/features/pos/components/PanelRetiros.vue';

const router = useRouter();

// Comandas que la cocina marcó como listas, leídas de la BD
const comandasListas = ref([]);

// Sondeo periódico para ver las comandas que cocina va terminando
const INTERVALO_POLLING_MS = 3000;
let pollingTimer = null;

// Comanda seleccionada para cobrar
const comandaSeleccionada = ref(null);

const procesando = ref(false);

// Panel lateral de retiros parciales del turno (CC-37)
const panelRetirosAbierto = ref(false);

// Métodos de pago aceptados por el comedor; el color acompaña al ícono y al texto
const METODOS_PAGO = [
  { valor: 'efectivo', etiqueta: 'Efectivo', icono: Banknote, color: '#F26A21' },
  { valor: 'tarjeta', etiqueta: 'Tarjeta', icono: CreditCard, color: '#2B6CB0' },
  { valor: 'transferencia', etiqueta: 'Transferencia', icono: ArrowRightLeft, color: '#2B6CB0' },
];

// IVA implícito (El Salvador: 13%)
const TASA_IVA = 0.13;

/**
 * Calcula el costo neto (sin IVA) de un total con IVA incluido.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 *
 * @param {number} total Total con IVA incluido.
 * @returns {number} Costo neto redondeado a 2 decimales.
 */
function calcularCostoNeto(total) {
  return Math.round((total / (1 + TASA_IVA)) * 100) / 100;
}

/**
 * Calcula el IVA implícito contenido en un total.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 *
 * @param {number} total Total con IVA incluido.
 * @returns {number} Monto de IVA redondeado a 2 decimales.
 */
function calcularIva(total) {
  return Math.round(calcularCostoNeto(total) * TASA_IVA * 100) / 100;
}

// Totales reactivos de la comanda seleccionada
const subtotal = computed(() =>
  comandaSeleccionada.value ? Number(comandaSeleccionada.value.subtotal) : 0
);
const costoNeto = computed(() => calcularCostoNeto(subtotal.value));
const montoIva = computed(() => calcularIva(subtotal.value));

// Formulario de cobro: monto, método y cambio en tiempo real (CC-33)
const {
  montoRecibido,
  montoRecibidoNumero,
  metodoPago,
  esEfectivo,
  esSuficiente,
  cambio,
  limpiar,
  seleccionarMetodo,
} = useCalculoCambio(subtotal);

/**
 * Devuelve la etiqueta e ícono descriptivos según el canal de la comanda.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 *
 * @param {Object} comanda Objeto de la comanda.
 * @returns {{ etiqueta: string, icono: Object }} Etiqueta e ícono Lucide.
 */
function infoCanal(comanda) {
  switch (comanda.canal) {
    case 'mesa':
      return { etiqueta: comanda.mesa ? `Mesa ${comanda.mesa.numero}` : 'Mesa', icono: Store };
    case 'para_llevar':
      return { etiqueta: 'Para llevar', icono: ShoppingBag };
    case 'whatsapp':
      return { etiqueta: 'WhatsApp', icono: MessageSquare };
    default:
      return { etiqueta: comanda.canal, icono: Utensils };
  }
}

/**
 * Consulta en el backend las comandas en estado "lista" pendientes de cobro.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-12
 * @módulo POS – US-POS-03 / RF-POS-002
 *
 * @param {boolean} esSilencioso Si es true, no muestra error al fallar (sondeo de fondo).
 */
async function cargarComandasListas(esSilencioso = false) {
  try {
    comandasListas.value = await obtenerComandas('lista');
  } catch (error) {
    if (!esSilencioso) {
      toast.error('Error al cargar las comandas listas para cobro.');
    }
  }
}

/**
 * Selecciona una comanda para cobrar y limpia el formulario anterior.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 *
 * @param {Object} comanda Comanda a seleccionar.
 */
function seleccionarComanda(comanda) {
  comandaSeleccionada.value = comanda;
  seleccionarMetodo('efectivo');
  limpiar();
}

/**
 * Confirma el cobro de la comanda seleccionada llamando al backend.
 * Si es exitoso, navega al ticket (la BD ya deja la comanda en "pagada").
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 */
async function confirmarCobro() {
  if (!comandaSeleccionada.value || !esSuficiente.value) return;

  procesando.value = true;
  try {
    const venta = await procesarCobro({
      comanda_id:     comandaSeleccionada.value.id,
      monto_recibido: montoRecibidoNumero.value,
      metodo_pago:    metodoPago.value,
    });

    toast.success(
      esEfectivo.value
        ? `Cobro procesado. Cambio: $${cambio.value.toFixed(2)}`
        : 'Cobro procesado.'
    );

    // Navegar al ticket con el ID de la venta
    router.push({ name: 'ticket-venta', params: { id: venta.id } });
  } catch (error) {
    toast.error(error.response?.data?.message || 'No se pudo procesar el cobro. Intenta de nuevo.');
  } finally {
    procesando.value = false;
  }
}

/**
 * Formatea un número como moneda en dólares.
 *
 * @param {number} valor Valor a formatear.
 * @returns {string} Cadena formateada (ej. "$12.50").
 */
function formatearMoneda(valor) {
  return `$${Number(valor).toFixed(2)}`;
}

onMounted(() => {
  cargarComandasListas();
  pollingTimer = setInterval(() => cargarComandasListas(true), INTERVALO_POLLING_MS);
});

onUnmounted(() => {
  if (pollingTimer) clearInterval(pollingTimer);
});
</script>

<template>
  <div class="flex min-h-[100dvh] flex-col bg-[#FAF7F4]" id="vista-pantalla-cobro">
    <Toaster richColors position="top-center" />

    <!-- Barra superior -->
    <header class="sticky top-0 z-30 flex items-center justify-between gap-3 border-b border-[#E5E0DB] bg-white/95 px-4 py-3 backdrop-blur-md shadow-xs">
      <div class="flex items-center gap-3">
        <RouterLink
          to="/cocina"
          class="flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] text-[#3D3D3D] hover:bg-zinc-100 active:scale-95 transition-all"
          title="Regresar a Cocina"
        >
          <ArrowLeft :size="20" stroke-width="2" />
        </RouterLink>

        <div class="flex items-center gap-2.5">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#2E7D4F] text-white shadow-xs">
            <Receipt :size="20" stroke-width="2" />
          </div>
          <div>
            <h1 class="text-lg font-bold leading-tight text-[#3D3D3D] tracking-tight">Pantalla de Cobro</h1>
            <p class="text-xs text-[#6B6B6B]">Comedor Chayito · Caja</p>
          </div>
        </div>
      </div>

      <!-- Badge de comandas en espera y acceso a retiros -->
      <div class="flex items-center gap-2">
        <button
          type="button"
          id="btn-abrir-retiros"
          class="flex h-11 items-center gap-2 rounded-xl border border-[#E5E0DB] bg-white px-3 text-sm font-semibold text-[#3D3D3D] hover:bg-zinc-50 active:scale-95 transition-all cursor-pointer shadow-xs"
          @click="panelRetirosAbierto = true"
        >
          <ArrowDownCircle :size="18" class="text-[#C62828]" stroke-width="2" />
          <span>Retiros</span>
        </button>

        <span
          v-if="comandasListas.length > 0"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-emerald-100 text-[#2E7D4F] text-xs font-bold"
        >
          <CheckCircle2 :size="14" stroke-width="2.5" />
          {{ comandasListas.length }} lista(s) para cobro
        </span>
      </div>
    </header>

    <!-- Layout principal: lista izquierda | formulario derecho -->
    <main class="flex-1 flex flex-col md:flex-row gap-0 overflow-hidden">

      <!-- ── Columna izquierda: Lista de comandas listas ── -->
      <aside class="w-full md:w-80 lg:w-96 border-b md:border-b-0 md:border-r border-[#E5E0DB] bg-white flex flex-col">
        <div class="px-4 py-3 border-b border-[#E5E0DB] flex items-center justify-between">
          <div class="flex items-center gap-2">
            <ChefHat :size="16" class="text-[#2E7D4F]" stroke-width="2" />
            <span class="text-sm font-bold text-[#3D3D3D]">Comandas listas</span>
          </div>
          <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-[#2E7D4F] text-xs font-bold">
            {{ comandasListas.length }}
          </span>
        </div>

        <!-- Sin comandas listas -->
        <div
          v-if="comandasListas.length === 0"
          class="flex-1 flex flex-col items-center justify-center p-8 text-center"
        >
          <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-[#FAF7F4] border border-[#E5E0DB] mb-3">
            <Clock :size="32" class="text-zinc-300" stroke-width="1.5" />
          </div>
          <p class="text-sm font-semibold text-zinc-600">Sin comandas en espera</p>
          <p class="text-xs text-zinc-400 mt-1 max-w-[200px]">Cuando el cocinero marque una orden como lista, aparecerá aquí.</p>
        </div>

        <!-- Lista de comandas listas -->
        <div v-else class="flex-1 overflow-y-auto p-3 space-y-2">
          <button
            v-for="comanda in comandasListas"
            :key="comanda.id"
            type="button"
            class="w-full text-left p-3 rounded-xl border transition-all cursor-pointer"
            :class="comandaSeleccionada?.id === comanda.id
              ? 'bg-emerald-50 border-[#2E7D4F] ring-1 ring-[#2E7D4F]/30'
              : 'bg-white border-[#E5E0DB] hover:border-[#2E7D4F]/40 hover:bg-emerald-50/40'"
            @click="seleccionarComanda(comanda)"
          >
            <div class="flex items-start justify-between gap-2 mb-2">
              <span class="font-bold text-sm text-[#3D3D3D]">Orden #{{ comanda.id }}</span>
              <span class="text-xs font-bold text-[#2E7D4F]">{{ formatearMoneda(comanda.subtotal) }}</span>
            </div>
            <div class="flex items-center gap-1.5 text-xs text-[#6B6B6B]">
              <component :is="infoCanal(comanda).icono" :size="12" stroke-width="2" />
              <span>{{ infoCanal(comanda).etiqueta }}</span>
              <span class="text-zinc-300">·</span>
              <span>{{ comanda.detalles?.length || 0 }} platillo(s)</span>
            </div>
          </button>
        </div>
      </aside>

      <!-- ── Columna derecha: Formulario de cobro ── -->
      <section class="flex-1 flex flex-col p-4 md:p-6 overflow-y-auto">

        <!-- Sin comanda seleccionada -->
        <div
          v-if="!comandaSeleccionada"
          class="flex-1 flex flex-col items-center justify-center text-center p-8"
        >
          <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-white border border-[#E5E0DB] shadow-xs mb-4">
            <Receipt :size="36" class="text-zinc-300" stroke-width="1.5" />
          </div>
          <h2 class="text-lg font-bold text-[#3D3D3D]">Selecciona una comanda</h2>
          <p class="text-sm text-[#6B6B6B] max-w-xs mt-1">
            Elige una orden de la lista de la izquierda para procesar el cobro.
          </p>
        </div>

        <!-- Pre-cuenta y formulario de cobro -->
        <div v-else class="max-w-lg w-full mx-auto space-y-5">

          <!-- Encabezado de la comanda -->
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-xl font-bold text-[#3D3D3D]">Orden #{{ comandaSeleccionada.id }}</h2>
              <div class="flex items-center gap-1.5 text-sm text-[#6B6B6B] mt-0.5">
                <component :is="infoCanal(comandaSeleccionada).icono" :size="14" stroke-width="2" />
                <span>{{ infoCanal(comandaSeleccionada).etiqueta }}</span>
                <span v-if="comandaSeleccionada.usuario">· {{ comandaSeleccionada.usuario.name }}</span>
              </div>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-emerald-100 text-[#2E7D4F] text-xs font-bold">Lista</span>
          </div>

          <!-- Desglose de platillos (pre-cuenta) -->
          <div class="bg-white rounded-2xl border border-[#E5E0DB] overflow-hidden shadow-xs">
            <div class="px-4 py-2.5 border-b border-[#E5E0DB] bg-[#FAF7F4]">
              <span class="text-xs font-bold text-[#6B6B6B] uppercase tracking-wider">Pre-cuenta</span>
            </div>
            <div class="divide-y divide-[#E5E0DB]/60">
              <div
                v-for="detalle in comandaSeleccionada.detalles"
                :key="detalle.id"
                class="flex items-center justify-between px-4 py-3 text-sm"
              >
                <div class="flex items-center gap-2 min-w-0">
                  <span class="inline-flex items-center justify-center min-w-[26px] h-6 px-1 rounded-md bg-[#3D3D3D] text-white text-xs font-bold shrink-0">
                    {{ detalle.cantidad }}×
                  </span>
                  <span class="font-medium text-[#3D3D3D] truncate">{{ detalle.platillo?.nombre || 'Platillo' }}</span>
                </div>
                <span class="font-semibold text-[#3D3D3D] shrink-0 ml-2">
                  {{ formatearMoneda(detalle.precio_unitario * detalle.cantidad) }}
                </span>
              </div>
            </div>

            <!-- Observación general -->
            <div
              v-if="comandaSeleccionada.observaciones"
              class="px-4 py-2.5 text-xs text-amber-800 bg-amber-50 border-t border-amber-100"
            >
              <span class="font-bold">Obs.:</span> {{ comandaSeleccionada.observaciones }}
            </div>

            <!-- Totales -->
            <div class="px-4 py-3 border-t border-[#E5E0DB] space-y-1.5 bg-[#FAF7F4]/60">
              <div class="flex justify-between text-xs text-[#6B6B6B]">
                <span>Costo neto</span>
                <span>{{ formatearMoneda(costoNeto) }}</span>
              </div>
              <div class="flex justify-between text-xs text-[#6B6B6B]">
                <span>IVA (13% implícito)</span>
                <span>{{ formatearMoneda(montoIva) }}</span>
              </div>
              <div class="flex justify-between text-base font-bold text-[#3D3D3D] pt-1 border-t border-[#E5E0DB]">
                <span>Total</span>
                <span class="text-[#F26A21]">{{ formatearMoneda(subtotal) }}</span>
              </div>
            </div>
          </div>

          <!-- Formulario de cobro -->
          <div class="bg-white rounded-2xl border border-[#E5E0DB] p-4 space-y-4 shadow-xs">
            <span class="text-xs font-bold text-[#6B6B6B] uppercase tracking-wider">Cobro</span>

            <!-- Método de pago -->
            <div class="grid grid-cols-3 gap-2">
              <button
                v-for="metodo in METODOS_PAGO"
                :key="metodo.valor"
                type="button"
                :id="`btn-metodo-${metodo.valor}`"
                class="flex flex-col sm:flex-row items-center justify-center gap-1 sm:gap-2 min-h-[44px] py-2 rounded-xl border text-sm font-semibold transition-all cursor-pointer"
                :class="metodoPago === metodo.valor
                  ? 'text-white shadow-xs'
                  : 'bg-white border-[#E5E0DB] text-[#6B6B6B] hover:border-[#F26A21]/50'"
                :style="metodoPago === metodo.valor
                  ? { backgroundColor: metodo.color, borderColor: metodo.color }
                  : null"
                :aria-pressed="metodoPago === metodo.valor"
                @click="seleccionarMetodo(metodo.valor)"
              >
                <component :is="metodo.icono" :size="18" stroke-width="2" />
                {{ metodo.etiqueta }}
              </button>
            </div>

            <!-- Monto recibido: solo en efectivo -->
            <div v-if="esEfectivo">
              <label class="block text-xs font-semibold text-[#3D3D3D] mb-1.5" for="monto-recibido">
                Monto recibido (USD)
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#6B6B6B] font-bold text-sm">$</span>
                <input
                  id="monto-recibido"
                  v-model="montoRecibido"
                  type="number"
                  min="0"
                  step="0.01"
                  inputmode="decimal"
                  placeholder="0.00"
                  class="w-full pl-8 pr-4 py-3 rounded-xl border border-[#E5E0DB] text-[#3D3D3D] font-semibold text-base focus:outline-none focus:ring-2 focus:ring-[#F26A21]/30 focus:border-[#F26A21] transition-all"
                  :class="montoRecibido && !esSuficiente ? 'border-[#C62828] ring-1 ring-[#C62828]/20' : ''"
                />
              </div>
              <p v-if="montoRecibido && !esSuficiente" class="text-xs text-[#C62828] mt-1 font-medium">
                El monto no cubre el total de ${{ subtotal.toFixed(2) }}
              </p>
            </div>

            <!-- Tarjeta / transferencia: se cobra el total exacto, sin cambio -->
            <div
              v-else
              class="flex items-center justify-between px-4 py-3 rounded-xl bg-[#2B6CB0]/5 border border-[#2B6CB0]/20"
            >
              <span class="text-sm font-semibold text-[#2B6CB0]">Se cobra el total exacto, sin cambio</span>
              <span class="text-xl font-bold text-[#2B6CB0]">{{ formatearMoneda(subtotal) }}</span>
            </div>

            <!-- Cambio calculado -->
            <div
              v-if="esEfectivo && esSuficiente && montoRecibido"
              class="flex items-center justify-between px-4 py-3 rounded-xl bg-emerald-50 border border-emerald-200"
            >
              <span class="text-sm font-semibold text-[#2E7D4F]">Cambio a entregar</span>
              <span class="text-xl font-bold text-[#2E7D4F]">{{ formatearMoneda(cambio) }}</span>
            </div>

            <!-- Botón confirmar cobro -->
            <button
              type="button"
              id="btn-confirmar-cobro"
              :disabled="!esSuficiente || procesando"
              class="w-full min-h-[52px] rounded-xl bg-[#F26A21] hover:bg-[#FF8C42] active:scale-[0.98] text-white font-bold text-base flex items-center justify-center gap-2.5 shadow-sm transition-all duration-150 disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
              @click="confirmarCobro"
            >
              <CheckCircle2 v-if="!procesando" :size="22" stroke-width="2.2" />
              <span v-if="procesando">Procesando...</span>
              <span v-else>Confirmar Cobro</span>
            </button>
          </div>
        </div>
      </section>
    </main>

    <PanelRetiros v-model:abierto="panelRetirosAbierto" />
  </div>
</template>
