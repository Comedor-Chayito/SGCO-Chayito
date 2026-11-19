<script setup>
/**
 * Panel lateral de movimientos del turno: estado de la caja, saldo inicial, ventas por
 * método de pago, retiros, efectivo esperado en gaveta y la lista de movimientos del día.
 * Todos los cálculos vienen del backend (GET /api/movimientos-caja).
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo POS – US-POS-04 / RF-POS-002
 */
import { ref, watch } from 'vue';
import {
  ArrowDownCircle,
  ArrowRightLeft,
  ArrowUpCircle,
  Banknote,
  CreditCard,
  Lock,
  LockOpen,
  RefreshCw,
  Wallet,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { obtenerMovimientosCaja } from '@/features/pos/services/cajaService.js';

const abierto = defineModel('abierto', { type: Boolean, default: false });

const resumen = ref(null);
const cargando = ref(false);

// Etiqueta, icono y color de cada estado de la caja; el texto siempre acompaña al color
const ESTADOS_CAJA = {
  sin_abrir: { etiqueta: 'Caja sin abrir', icono: Lock, clase: 'bg-amber-50 text-[#3D3D3D]' },
  abierta: { etiqueta: 'Caja abierta', icono: LockOpen, clase: 'bg-emerald-50 text-[#2E7D4F]' },
  cerrada: { etiqueta: 'Caja cerrada', icono: Lock, clase: 'bg-red-50 text-[#C62828]' },
};

// Presentación de cada tipo de movimiento: signo del monto, icono y color
const TIPOS_MOVIMIENTO = {
  apertura: { signo: '', icono: LockOpen, clase: 'text-[#3D3D3D]' },
  venta: { signo: '+', icono: ArrowUpCircle, clase: 'text-[#2E7D4F]' },
  retiro: { signo: '−', icono: ArrowDownCircle, clase: 'text-[#C62828]' },
  cierre: { signo: '', icono: Lock, clase: 'text-[#3D3D3D]' },
};

const METODOS_PAGO = [
  { valor: 'efectivo', etiqueta: 'Efectivo', icono: Banknote },
  { valor: 'tarjeta', etiqueta: 'Tarjeta', icono: CreditCard },
  { valor: 'transferencia', etiqueta: 'Transferencia', icono: ArrowRightLeft },
];

/**
 * Consulta en el backend el resumen de caja de hoy.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo POS – US-POS-04 / RF-POS-002
 *
 * @returns {Promise<void>}
 */
async function cargarResumen() {
  cargando.value = true;
  try {
    resumen.value = await obtenerMovimientosCaja();
  } catch {
    toast.error('No se pudieron cargar los movimientos del turno.');
  } finally {
    cargando.value = false;
  }
}

/**
 * Formatea un número como moneda en dólares.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo POS – US-POS-04 / RF-POS-002
 *
 * @param   {number|string} valor Valor a formatear.
 * @returns {string} Cadena formateada (ej. "$12.50").
 */
function formatearMoneda(valor) {
  return `$${Number(valor).toFixed(2)}`;
}

/**
 * Devuelve la hora local (HH:MM) de una fecha ISO.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo POS – US-POS-04 / RF-POS-002
 *
 * @param   {string} fechaIso Cadena de fecha ISO 8601.
 * @returns {string} Hora formateada.
 */
function formatearHora(fechaIso) {
  return new Date(fechaIso).toLocaleTimeString('es-SV', { hour: '2-digit', minute: '2-digit' });
}

watch(abierto, (estaAbierto) => {
  if (estaAbierto) cargarResumen();
});
</script>

<template>
  <Sheet v-model:open="abierto">
    <SheetContent
      side="right"
      class="data-[side=right]:w-full data-[side=right]:sm:max-w-md p-0 flex flex-col bg-[#FAF7F4]"
    >
      <SheetTitle class="sr-only">Movimientos del turno</SheetTitle>
      <SheetDescription class="sr-only">
        Ventas, retiros y efectivo esperado en la caja de hoy.
      </SheetDescription>

      <!-- pt-14 deja libre la X del panel lateral, que va en posición absoluta -->
      <div id="panel-movimientos-turno" class="flex-1 overflow-y-auto p-4 pt-14 space-y-4">
        <header class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-2">
            <Wallet :size="18" class="text-[#3D3D3D]" stroke-width="2" />
            <h2 class="text-base font-bold text-[#3D3D3D]">Movimientos de hoy</h2>
          </div>
          <button
            type="button"
            id="btn-recargar-movimientos"
            class="flex h-11 w-11 items-center justify-center
              rounded-xl border border-[#E5E0DB] bg-white hover:bg-zinc-50
              text-[#3D3D3D]
              active:scale-95 transition-all cursor-pointer disabled:opacity-50"
            :disabled="cargando"
            title="Actualizar"
            aria-label="Actualizar movimientos"
            @click="cargarResumen"
          >
            <RefreshCw :size="18" :class="cargando ? 'animate-spin' : ''" stroke-width="2" />
          </button>
        </header>

        <p v-if="cargando && !resumen" class="py-6 text-center text-sm text-[#6B6B6B]">
          Cargando movimientos...
        </p>

        <template v-else-if="resumen">
          <!-- Estado de la caja: icono + texto -->
          <div
            id="estado-caja-turno"
            class="flex items-center gap-2 px-3 py-2 rounded-xl text-sm font-semibold"
            :class="ESTADOS_CAJA[resumen.estado].clase"
          >
            <component :is="ESTADOS_CAJA[resumen.estado].icono" :size="16" stroke-width="2.2" />
            {{ ESTADOS_CAJA[resumen.estado].etiqueta }}
          </div>

          <!-- Efectivo esperado en gaveta: el dato que más consulta el cajero -->
          <section class="p-4 rounded-2xl bg-white border border-[#E5E0DB]">
            <p class="text-xs font-bold uppercase tracking-wider text-[#6B6B6B]">
              Efectivo esperado en gaveta
            </p>
            <p id="efectivo-esperado" class="mt-1 text-3xl font-black text-[#3D3D3D]">
              {{ formatearMoneda(resumen.totales.efectivo_esperado) }}
            </p>
            <p class="mt-1 text-xs text-[#6B6B6B]">
              Saldo inicial {{ formatearMoneda(resumen.totales.saldo_inicial) }}
              + efectivo {{ formatearMoneda(resumen.totales.ventas_por_metodo.efectivo) }}
              − retiros {{ formatearMoneda(resumen.totales.total_retiros) }}
            </p>
          </section>

          <!-- Ventas por método de pago -->
          <section class="rounded-2xl bg-white border border-[#E5E0DB] overflow-hidden">
            <header class="flex items-center justify-between px-4 py-3 border-b border-[#E5E0DB]">
              <h3 class="text-sm font-bold text-[#3D3D3D]">
                Ventas ({{ resumen.totales.cantidad_ventas }})
              </h3>
              <span class="text-sm font-bold text-[#2E7D4F]">
                Total: {{ formatearMoneda(resumen.totales.total_ventas) }}
              </span>
            </header>
            <ul class="divide-y divide-[#E5E0DB]/60">
              <li
                v-for="metodo in METODOS_PAGO"
                :key="metodo.valor"
                class="flex items-center justify-between px-4 py-2.5 text-sm"
              >
                <span class="flex items-center gap-2 text-[#3D3D3D]">
                  <component :is="metodo.icono" :size="16" stroke-width="2" />
                  {{ metodo.etiqueta }}
                </span>
                <span class="font-semibold text-[#3D3D3D]">
                  {{ formatearMoneda(resumen.totales.ventas_por_metodo[metodo.valor]) }}
                </span>
              </li>
              <li class="flex items-center justify-between px-4 py-2.5 text-sm">
                <span class="flex items-center gap-2 text-[#3D3D3D]">
                  <ArrowDownCircle :size="16" class="text-[#C62828]" stroke-width="2" />
                  Retiros
                </span>
                <span class="font-semibold text-[#C62828]">
                  −{{ formatearMoneda(resumen.totales.total_retiros) }}
                </span>
              </li>
            </ul>
          </section>

          <!-- Lista cronológica de movimientos -->
          <section class="rounded-2xl bg-white border border-[#E5E0DB] overflow-hidden">
            <header class="px-4 py-3 border-b border-[#E5E0DB]">
              <h3 class="text-sm font-bold text-[#3D3D3D]">Detalle del día</h3>
            </header>
            <p
              v-if="resumen.movimientos.length === 0"
              class="px-4 py-6 text-center text-sm text-[#6B6B6B]"
            >
              Aún no hay movimientos hoy.
            </p>
            <ul v-else id="lista-movimientos-turno" class="divide-y divide-[#E5E0DB]/60">
              <li
                v-for="(movimiento, indice) in resumen.movimientos"
                :key="`${movimiento.tipo}-${indice}`"
                class="flex items-center gap-3 px-4 py-3 text-sm"
              >
                <component
                  :is="TIPOS_MOVIMIENTO[movimiento.tipo].icono"
                  :size="18"
                  class="shrink-0"
                  :class="TIPOS_MOVIMIENTO[movimiento.tipo].clase"
                  stroke-width="2"
                />
                <div class="min-w-0 flex-1">
                  <p class="font-medium text-[#3D3D3D] truncate">{{ movimiento.descripcion }}</p>
                  <p class="text-xs text-[#6B6B6B]">
                    {{ formatearHora(movimiento.hora) }}
                    <template v-if="movimiento.usuario"> · {{ movimiento.usuario }}</template>
                  </p>
                </div>
                <span
                  class="shrink-0 font-semibold"
                  :class="TIPOS_MOVIMIENTO[movimiento.tipo].clase"
                >
                  {{ TIPOS_MOVIMIENTO[movimiento.tipo].signo
                  }}{{ formatearMoneda(movimiento.monto) }}
                </span>
              </li>
            </ul>
          </section>
        </template>
      </div>
    </SheetContent>
  </Sheet>
</template>
