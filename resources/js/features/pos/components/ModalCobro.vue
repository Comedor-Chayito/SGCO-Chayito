<script setup>
/**
 * Modal táctil de cobro de comandas y cálculo interactivo de cambio (CC-33 / RF-POS-002).
 * Permite seleccionar método de pago (efectivo o transferencia), ingresar el efectivo recibido
 * con atajos rápidos de billetes, calcular en tiempo real el cambio a entregar con advertencias
 * de montos insuficientes, y procesar la transacción contable.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-12
 * @módulo POS – US-POS-03 / CC-33
 */
import { ref, computed, watch } from 'vue';
import {
  X,
  Banknote,
  ArrowRightLeft,
  CheckCircle2,
  AlertTriangle,
  Receipt,
  Store,
  ShoppingBag,
  MessageSquare,
  Sparkles,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { toast } from 'vue-sonner';
import { useCalculoCambio } from '@/features/pos/composables/useCalculoCambio.js';
import { procesarCobro } from '@/features/pos/services/posService.js';

const props = defineProps({
  abierto: {
    type: Boolean,
    default: false,
  },
  comanda: {
    type: Object,
    default: null,
  },
});

const emit = defineEmits(['cerrar', 'cobroCompletado']);

// Subtotal normalizado de la comanda
const subtotalComanda = computed(() => {
  if (!props.comanda) return 0;
  return parseFloat(props.comanda.subtotal || 0);
});

// Composable de cálculo reactivo de cambio
const {
  total,
  totalFormateado,
  montoRecibido,
  montoRecibidoNumero,
  montoRecibidoFormateado,
  metodoPago,
  cambio,
  cambioFormateado,
  montoFaltante,
  montoFaltanteFormateado,
  esSuficiente,
  esMontoExacto,
  denominacionesSugeridas,
  fijarExacto,
  fijarMonto,
  sumarMonto,
  limpiar,
  seleccionarMetodo,
} = useCalculoCambio(subtotalComanda);

// Estado local de procesamiento y pantalla de comprobante
const procesando = ref(false);
const cobroExitoso = ref(false);
const ventaResultado = ref(null);

// Resetear estado al abrir con una nueva comanda
watch(
  () => props.abierto,
  (nuevoAbierto) => {
    if (nuevoAbierto) {
      limpiar();
      seleccionarMetodo('efectivo');
      cobroExitoso.value = false;
      ventaResultado.value = null;
    }
  }
);

// Descripción del canal de atención
const canalTexto = computed(() => {
  if (!props.comanda) return '';
  switch (props.comanda.canal) {
    case 'mesa':
      return props.comanda.mesa ? `Mesa ${props.comanda.mesa.numero}` : 'Mesa';
    case 'para_llevar':
      return 'Para llevar';
    case 'whatsapp':
      return 'WhatsApp';
    default:
      return props.comanda.canal;
  }
});

/**
 * Procesa la transacción de cobro contra la API REST.
 */
async function ejecutarCobro() {
  if (!props.comanda || !esSuficiente.value || procesando.value) return;

  procesando.value = true;
  try {
    const payload = {
      comanda_id: props.comanda.id,
      monto_recibido: montoRecibidoNumero.value,
      metodo_pago: metodoPago.value,
    };

    const resultado = await procesarCobro(payload);
    ventaResultado.value = resultado;
    cobroExitoso.value = true;

    toast.success('Cobro registrado exitosamente', {
      description: `Cambio entregado: $${parseFloat(resultado.cambio || 0).toFixed(2)}`,
      duration: 4000,
    });

    emit('cobroCompletado', {
      comandaId: props.comanda.id,
      venta: resultado,
    });
  } catch (error) {
    const mensaje =
      error.response?.data?.message ||
      error.response?.data?.error ||
      'No se pudo procesar el cobro. Verifique los datos.';
    toast.error(mensaje);
  } finally {
    procesando.value = false;
  }
}

/**
 * Cierra el modal y resetea los estados.
 */
function cerrarModal() {
  emit('cerrar');
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="abierto && comanda"
      class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-zinc-950/60 backdrop-blur-xs transition-opacity duration-200"
      @click.self="cerrarModal"
    >
      <div
        class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl border border-zinc-200 overflow-hidden flex flex-col max-h-[92vh] animate-in fade-in zoom-in-95 duration-200"
      >
        <!-- ── ENCABEZADO ── -->
        <header class="px-5 py-4 bg-zinc-900 text-white flex items-center justify-between shrink-0">
          <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-[#F26A21]/20 flex items-center justify-center text-[#F26A21]">
              <Receipt :size="20" stroke-width="2.2" />
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-base font-black tracking-tight leading-none">
                  Cobro de Comanda #{{ comanda.id }}
                </h3>
                <Badge variant="outline" class="text-[11px] font-bold border-zinc-700 text-zinc-300 py-0">
                  {{ canalTexto }}
                </Badge>
              </div>
              <p class="text-xs text-zinc-400 mt-0.5">
                Calculadora de cambio y comprobante fiscal (CC-33)
              </p>
            </div>
          </div>

          <button
            type="button"
            class="text-zinc-400 hover:text-white p-1 rounded-lg hover:bg-zinc-800 transition-colors"
            @click="cerrarModal"
          >
            <X :size="20" />
          </button>
        </header>

        <!-- ── CUERPO: PASO 1 - FORMULARIO DE COBRO Y CÁLCULO DE CAMBIO ── -->
        <div v-if="!cobroExitoso" class="p-5 overflow-y-auto space-y-4 flex-1">
          <!-- Total a Pagar Destacado -->
          <div class="p-4 rounded-xl bg-orange-50/80 border border-orange-200 flex items-center justify-between">
            <div>
              <span class="text-xs font-bold uppercase tracking-wider text-orange-950">
                Total a Cobrar
              </span>
              <p class="text-xs text-zinc-500 mt-0.5">
                {{ comanda.detalles?.length || 0 }} platillos ordenados
              </p>
            </div>
            <div class="text-3xl font-black text-[#F26A21] tabular-nums">
              {{ totalFormateado }}
            </div>
          </div>

          <!-- Selector de Método de Pago -->
          <div>
            <label class="block text-xs font-bold text-zinc-700 mb-1.5 uppercase tracking-wide">
              Método de Pago
            </label>
            <div class="grid grid-cols-2 gap-2">
              <button
                type="button"
                @click="seleccionarMetodo('efectivo')"
                class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border text-xs font-bold transition-all min-h-[44px]"
                :class="
                  metodoPago === 'efectivo'
                    ? 'border-[#F26A21] bg-orange-50/70 text-[#C24E12] shadow-xs'
                    : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50'
                "
              >
                <Banknote :size="16" stroke-width="2.2" />
                <span>Efectivo</span>
              </button>

              <button
                type="button"
                @click="seleccionarMetodo('transferencia')"
                class="flex items-center justify-center gap-2 py-2.5 px-3 rounded-xl border text-xs font-bold transition-all min-h-[44px]"
                :class="
                  metodoPago === 'transferencia'
                    ? 'border-[#F26A21] bg-orange-50/70 text-[#C24E12] shadow-xs'
                    : 'border-zinc-200 bg-white text-zinc-600 hover:bg-zinc-50'
                "
              >
                <ArrowRightLeft :size="16" stroke-width="2.2" />
                <span>Transferencia</span>
              </button>
            </div>
          </div>

          <!-- Panel de Cobro en Efectivo -->
          <div v-if="metodoPago === 'efectivo'" class="space-y-3">
            <!-- Campo de Efectivo Recibido -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <label for="input-efectivo-recibido" class="text-xs font-bold text-zinc-700 uppercase tracking-wide">
                  Efectivo Recibido ($ USD)
                </label>
                <button
                  v-if="montoRecibido"
                  type="button"
                  @click="limpiar"
                  class="text-[11px] font-bold text-red-600 hover:text-red-700"
                >
                  Limpiar monto
                </button>
              </div>

              <div class="relative">
                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-lg font-bold text-zinc-400 select-none">
                  $
                </span>
                <input
                  id="input-efectivo-recibido"
                  type="number"
                  step="0.01"
                  min="0"
                  v-model="montoRecibido"
                  placeholder="0.00"
                  class="w-full pl-8 pr-4 py-3 text-2xl font-black text-zinc-900 bg-zinc-50 border border-zinc-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#F26A21]/30 focus:border-[#F26A21] tabular-nums"
                  autofocus
                />
              </div>
            </div>

            <!-- Botones Rápidos de Denominaciones Táctiles (CC-33) -->
            <div>
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-wide">
                  Atajos Rápidos de Billetes
                </span>
                <span class="text-[11px] text-zinc-400">Pulsa para asignar</span>
              </div>

              <div class="flex flex-wrap gap-1.5">
                <!-- Botón de Pago Exacto -->
                <button
                  type="button"
                  @click="fijarExacto"
                  class="px-3 py-1.5 rounded-lg border border-orange-300 bg-orange-100/70 hover:bg-orange-200 text-[#C24E12] text-xs font-black transition-colors min-h-[38px] flex items-center gap-1 active:scale-95"
                  title="Fijar monto exacto de la cuenta"
                >
                  <Sparkles :size="13" />
                  <span>Exacto ({{ totalFormateado }})</span>
                </button>

                <!-- Billetes Habituales -->
                <button
                  v-for="billete in [5, 10, 20]"
                  :key="billete"
                  type="button"
                  @click="fijarMonto(billete)"
                  class="px-3 py-1.5 rounded-lg border border-zinc-200 bg-white hover:bg-zinc-100 text-zinc-800 text-xs font-bold transition-colors min-h-[38px] active:scale-95"
                >
                  ${{ billete }}.00
                </button>

                <!-- Incrementos rápidos +$1, +$5 -->
                <button
                  type="button"
                  @click="sumarMonto(1)"
                  class="px-2.5 py-1.5 rounded-lg border border-zinc-200 bg-zinc-50 hover:bg-zinc-100 text-zinc-600 text-xs font-semibold transition-colors min-h-[38px]"
                  title="Sumar $1.00 al monto actual"
                >
                  +$1
                </button>
                <button
                  type="button"
                  @click="sumarMonto(5)"
                  class="px-2.5 py-1.5 rounded-lg border border-zinc-200 bg-zinc-50 hover:bg-zinc-100 text-zinc-600 text-xs font-semibold transition-colors min-h-[38px]"
                  title="Sumar $5.00 al monto actual"
                >
                  +$5
                </button>
              </div>
            </div>

            <!-- RESULTADO REACTIVO DE CÁLCULO DE CAMBIO (CC-33) -->
            <div class="pt-1">
              <!-- Caso 1: Efectivo suficiente con cambio positivo -->
              <div
                v-if="esSuficiente && cambio > 0"
                class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-between text-emerald-950 animate-in fade-in duration-200"
              >
                <div class="flex items-center gap-2.5">
                  <div class="w-8 h-8 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                    <CheckCircle2 :size="18" stroke-width="2.5" />
                  </div>
                  <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-emerald-800 block">
                      Cambio a Entregar
                    </span>
                    <span class="text-[11px] text-emerald-700">
                      Entregado: {{ montoRecibidoFormateado }}
                    </span>
                  </div>
                </div>
                <div class="text-3xl font-black text-emerald-600 tabular-nums">
                  {{ cambioFormateado }}
                </div>
              </div>

              <!-- Caso 2: Pago exacto ($0.00 de cambio) -->
              <div
                v-else-if="esSuficiente && cambio === 0"
                class="p-3.5 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center justify-between text-emerald-950 animate-in fade-in duration-200"
              >
                <div class="flex items-center gap-2">
                  <CheckCircle2 :size="18" class="text-emerald-600 shrink-0" stroke-width="2.5" />
                  <span class="text-xs font-bold text-emerald-800">
                    Pago Exacto — Sin cambio a devolver
                  </span>
                </div>
                <span class="text-xl font-black text-emerald-600 tabular-nums">$0.00</span>
              </div>

              <!-- Caso 3: Monto insuficiente -->
              <div
                v-else-if="!esSuficiente && montoRecibidoNumero > 0"
                class="p-3.5 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-between text-amber-950 animate-in fade-in duration-200"
              >
                <div class="flex items-center gap-2">
                  <AlertTriangle :size="18" class="text-amber-600 shrink-0" stroke-width="2.2" />
                  <div>
                    <span class="text-xs font-bold text-amber-900 block">
                      Monto Insuficiente
                    </span>
                    <span class="text-[11px] text-amber-700">
                      Faltan {{ montoFaltanteFormateado }} para cubrir la cuenta
                    </span>
                  </div>
                </div>
                <span class="text-sm font-black text-amber-800 tabular-nums">
                  -{{ montoFaltanteFormateado }}
                </span>
              </div>

              <!-- Caso 4: Monto vacío o cero -->
              <div
                v-else
                class="p-3 rounded-xl bg-zinc-50 border border-zinc-200 text-center text-xs text-zinc-500"
              >
                Ingrese el efectivo recibido del cliente para calcular el cambio.
              </div>
            </div>
          </div>

          <!-- Panel de Transferencia -->
          <div
            v-else
            class="p-4 rounded-xl bg-blue-50 border border-blue-200 text-blue-950 text-xs space-y-1.5"
          >
            <div class="flex items-center gap-2 font-bold text-blue-800">
              <ArrowRightLeft :size="16" />
              <span>Pago por Transferencia Electrónica</span>
            </div>
            <p class="text-blue-700 leading-relaxed">
              El cobro se procesará por el monto total exacto de <strong class="text-blue-900">{{ totalFormateado }}</strong>.
              No se genera cambio en efectivo.
            </p>
          </div>
        </div>

        <!-- ── CUERPO: PASO 2 - COMPROBANTE DE COBRO EXITOSO ── -->
        <div v-else class="p-6 text-center space-y-4 flex-1 overflow-y-auto">
          <div class="w-14 h-14 rounded-full bg-emerald-100 text-emerald-600 mx-auto flex items-center justify-center animate-bounce-short">
            <CheckCircle2 :size="32" stroke-width="2.5" />
          </div>

          <div>
            <h4 class="text-lg font-black text-zinc-900">
              ¡Cobro Registrado con Éxito!
            </h4>
            <p class="text-xs text-zinc-500 mt-0.5">
              La orden #{{ comanda.id }} ha sido marcada como pagada.
            </p>
          </div>

          <!-- Resumen Financiero del Cobro -->
          <div class="bg-zinc-50 rounded-xl p-4 border border-zinc-200 text-left text-xs space-y-2">
            <div class="flex justify-between items-center py-1 border-b border-zinc-200">
              <span class="text-zinc-500">Total Cobrado:</span>
              <span class="font-bold text-zinc-900">{{ totalFormateado }}</span>
            </div>
            <div class="flex justify-between items-center py-1 border-b border-zinc-200">
              <span class="text-zinc-500">Monto Recibido:</span>
              <span class="font-bold text-zinc-900">{{ montoRecibidoFormateado }}</span>
            </div>
            <div class="flex justify-between items-center py-1 border-b border-zinc-200">
              <span class="text-zinc-500">Método de Pago:</span>
              <span class="font-bold text-zinc-800 uppercase">{{ metodoPago }}</span>
            </div>
            <div class="flex justify-between items-center pt-1 text-sm font-black text-emerald-600">
              <span>Cambio Entregado:</span>
              <span class="text-base">{{ cambioFormateado }}</span>
            </div>
          </div>
        </div>

        <!-- ── PIE DE ACCIONES ── -->
        <footer class="p-4 bg-zinc-50 border-t border-zinc-200 flex justify-end gap-2 shrink-0">
          <!-- Botones en Paso 1 -->
          <template v-if="!cobroExitoso">
            <Button
              type="button"
              variant="outline"
              class="min-h-[44px] px-4 font-semibold text-zinc-700"
              @click="cerrarModal"
              :disabled="procesando"
            >
              Cancelar
            </Button>

            <Button
              type="button"
              class="min-h-[44px] px-6 bg-[#F26A21] hover:bg-[#FF8C42] text-white font-bold transition-all disabled:opacity-50"
              @click="ejecutarCobro"
              :disabled="!esSuficiente || procesando"
            >
              <span v-if="procesando" class="flex items-center gap-2">
                <span class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></span>
                <span>Procesando...</span>
              </span>
              <span v-else class="flex items-center gap-1.5">
                <Banknote :size="18" stroke-width="2" />
                <span>Cobrar y Finalizar</span>
              </span>
            </Button>
          </template>

          <!-- Botones en Paso 2 (Exitoso) -->
          <template v-else>
            <Button
              type="button"
              class="w-full min-h-[44px] bg-[#2E7D4F] hover:bg-[#2E7D4F]/90 text-white font-bold"
              @click="cerrarModal"
            >
              Finalizar y Cerrar
            </Button>
          </template>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

