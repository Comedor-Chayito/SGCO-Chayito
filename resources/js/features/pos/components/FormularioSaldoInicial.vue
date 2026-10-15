<script setup>
/**
 * Formulario de saldo inicial de caja por denominación (CC-39).
 * Permite al cajero contar las piezas de billetes y monedas para calcular
 * en tiempo real el saldo inicial y registrar la apertura de caja del día.
 * Si ya se abrió caja hoy, muestra el arqueo inalterable registrado.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-39)
 */
import { ref, computed, onMounted } from 'vue';
import {
  Banknote,
  Coins,
  CheckCircle2,
  AlertCircle,
  Plus,
  Minus,
  RotateCcw,
  Loader2,
  Lock,
  Calendar,
  User,
  DollarSign,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { registrarAperturaCaja, obtenerAperturaTurno } from '@/features/pos/services/cajaService.js';

const emit = defineEmits(['apertura-registrada', 'cerrar']);

// ─── Denominaciones oficiales de El Salvador (USD) ───
const DENOMINACIONES_BASE = [
  // Billetes
  { valor: 100,  tipo: 'billete', etiqueta: '$100', descripcion: 'Billete de cien dólares' },
  { valor: 50,   tipo: 'billete', etiqueta: '$50',  descripcion: 'Billete de cincuenta dólares' },
  { valor: 20,   tipo: 'billete', etiqueta: '$20',  descripcion: 'Billete de veinte dólares' },
  { valor: 10,   tipo: 'billete', etiqueta: '$10',  descripcion: 'Billete de diez dólares' },
  { valor: 5,    tipo: 'billete', etiqueta: '$5',   descripcion: 'Billete de cinco dólares' },
  { valor: 1,    tipo: 'billete', etiqueta: '$1',   descripcion: 'Billete de un dólar' },
  // Monedas
  { valor: 1.00, tipo: 'moneda',  etiqueta: '$1.00', descripcion: 'Moneda de un dólar' },
  { valor: 0.25, tipo: 'moneda',  etiqueta: '$0.25', descripcion: 'Moneda de veinticinco centavos (cora)' },
  { valor: 0.10, tipo: 'moneda',  etiqueta: '$0.10', descripcion: 'Moneda de diez centavos' },
  { valor: 0.05, tipo: 'moneda',  etiqueta: '$0.05', descripcion: 'Moneda de cinco centavos (níquel)' },
  { valor: 0.01, tipo: 'moneda',  etiqueta: '$0.01', descripcion: 'Moneda de un centavo' },
];

// Estado de cantidades por denominación: clave es el valor numérico
const cantidades = ref(
  DENOMINACIONES_BASE.reduce((acc, d) => {
    acc[d.valor] = 0;
    return acc;
  }, {})
);

const observaciones = ref('');
const cargando = ref(false);
const guardando = ref(false);
const aperturaExistente = ref(null);
const pestanaActiva = ref('todos'); // 'todos' | 'billetes' | 'monedas'

/**
 * Incrementa la cantidad de piezas de una denominación.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 (CC-39)
 *
 * @param {number} valor Valor de la denominación.
 */
function incrementar(valor) {
  cantidades.value[valor] = (cantidades.value[valor] || 0) + 1;
}

/**
 * Decrementa la cantidad de piezas de una denominación (mínimo 0).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 (CC-39)
 *
 * @param {number} valor Valor de la denominación.
 */
function decrementar(valor) {
  if (cantidades.value[valor] > 0) {
    cantidades.value[valor] -= 1;
  }
}

/**
 * Actualiza la cantidad verificando que sea un entero positivo.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 (CC-39)
 *
 * @param {number} valor Valor de la denominación.
 * @param {Event} evento Evento de cambio del input.
 */
function actualizarCantidad(valor, evento) {
  const val = parseInt(evento.target.value, 10);
  cantidades.value[valor] = isNaN(val) || val < 0 ? 0 : val;
}

/**
 * Limpia todas las cantidades a cero.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 (CC-39)
 */
function reiniciarCantidades() {
  DENOMINACIONES_BASE.forEach((d) => {
    cantidades.value[d.valor] = 0;
  });
  observaciones.value = '';
}

// ─── Cálculos reactivos ───
const subtotalBilletes = computed(() => {
  return DENOMINACIONES_BASE
    .filter((d) => d.tipo === 'billete')
    .reduce((sum, d) => sum + (cantidades.value[d.valor] || 0) * d.valor, 0);
});

const subtotalMonedas = computed(() => {
  return DENOMINACIONES_BASE
    .filter((d) => d.tipo === 'moneda')
    .reduce((sum, d) => sum + (cantidades.value[d.valor] || 0) * d.valor, 0);
});

const granTotal = computed(() => {
  return Math.round((subtotalBilletes.value + subtotalMonedas.value) * 100) / 100;
});

const totalPiezas = computed(() => {
  return Object.values(cantidades.value).reduce((sum, cant) => sum + (Number(cant) || 0), 0);
});

const denominacionesFiltradas = computed(() => {
  if (pestanaActiva.value === 'billetes') {
    return DENOMINACIONES_BASE.filter((d) => d.tipo === 'billete');
  }
  if (pestanaActiva.value === 'monedas') {
    return DENOMINACIONES_BASE.filter((d) => d.tipo === 'moneda');
  }
  return DENOMINACIONES_BASE;
});

/**
 * Consulta si ya existe apertura registrada hoy.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 (CC-39)
 */
async function cargarAperturaHoy() {
  cargando.value = true;
  try {
    const data = await obtenerAperturaTurno();
    aperturaExistente.value = data;
  } catch (error) {
    console.error('Error al cargar apertura:', error);
  } finally {
    cargando.value = false;
  }
}

/**
 * Envía el formulario para registrar la apertura de caja y el saldo inicial.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 (CC-39)
 */
async function guardarApertura() {
  if (granTotal.value <= 0) {
    toast.error('El saldo inicial debe ser mayor a $0.00. Ingrese al menos una denominación.');
    return;
  }

  guardando.value = true;
  try {
    const desglose = DENOMINACIONES_BASE.map((d) => ({
      denominacion: d.valor,
      tipo: d.tipo,
      cantidad: cantidades.value[d.valor] || 0,
    }));

    const resultado = await registrarAperturaCaja({
      denominaciones: desglose,
      observaciones: observaciones.value.trim() || null,
    });

    aperturaExistente.value = resultado;
    toast.success(`Apertura registrada con éxito: Saldo inicial $${Number(resultado.saldo_inicial).toFixed(2)}`);
    emit('apertura-registrada', resultado);
  } catch (error) {
    const msg = error.response?.data?.message || 'Error al registrar la apertura de caja.';
    toast.error(msg);
  } finally {
    guardando.value = false;
  }
}

/**
 * Formatea número a moneda en dólares ($X.XX).
 *
 * @param {number|string} valor
 * @returns {string}
 */
function formatearMoneda(valor) {
  return `$${Number(valor).toFixed(2)}`;
}

/**
 * Formatea fecha ISO para mostrar fecha y hora amigable.
 *
 * @param {string} iso
 * @returns {string}
 */
function formatearFechaHora(iso) {
  if (!iso) return '';
  return new Date(iso).toLocaleString('es-SV', {
    dateStyle: 'medium',
    timeStyle: 'short',
  });
}

onMounted(() => {
  cargarAperturaHoy();
});
</script>

<template>
  <div class="flex flex-col h-full bg-[#FAF7F4] text-[#111111] overflow-hidden" id="formulario-saldo-inicial">
    <!-- Header del formulario -->
    <header class="p-4 border-b border-[#E5E0DB] bg-white flex items-center justify-between shrink-0">
      <div class="flex items-center gap-2.5">
        <div class="w-10 h-10 rounded-xl bg-[#F26A21]/10 text-[#C24E12] flex items-center justify-center font-bold">
          <DollarSign :size="22" stroke-width="2.5" />
        </div>
        <div>
          <h2 class="text-base font-bold text-[#3D3D3D] leading-tight">Saldo Inicial de Caja</h2>
          <p class="text-xs text-[#6B6B6B]">Apertura de turno por denominación</p>
        </div>
      </div>

      <div v-if="aperturaExistente" class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 text-[#2E7D4F] border border-emerald-200 text-xs font-semibold">
        <Lock :size="13" stroke-width="2.5" />
        <span>Caja Abierta</span>
      </div>
    </header>

    <!-- Cargando estado inicial -->
    <div v-if="cargando" class="flex-1 flex flex-col items-center justify-center p-8 gap-3 text-[#6B6B6B]">
      <Loader2 :size="32" class="animate-spin text-[#F26A21]" />
      <span class="text-sm font-medium">Verificando apertura del turno...</span>
    </div>

    <!-- VISTA 1: Caja ya abierta hoy (Modo Lectura / Bitácora Inalterable) -->
    <div v-else-if="aperturaExistente" class="flex-1 overflow-y-auto p-4 space-y-4">
      <div class="p-4 rounded-2xl bg-white border border-[#E5E0DB] shadow-xs space-y-3">
        <div class="flex items-start justify-between">
          <div class="flex items-center gap-2 text-xs text-[#6B6B6B]">
            <Calendar :size="14" />
            <span>{{ aperturaExistente.fecha }}</span>
          </div>
          <span class="text-xs font-semibold px-2 py-0.5 rounded-md bg-[#FAF7F4] border border-[#E5E0DB] text-[#3D3D3D]">
            Turno activo
          </span>
        </div>

        <div>
          <span class="text-xs font-medium text-[#6B6B6B] uppercase tracking-wider">Saldo Inicial Registrado</span>
          <div class="text-3xl font-black text-[#2E7D4F] tracking-tight">
            {{ formatearMoneda(aperturaExistente.saldo_inicial) }}
          </div>
        </div>

        <div class="pt-2 border-t border-[#E5E0DB] text-xs text-[#6B6B6B] flex items-center justify-between">
          <div class="flex items-center gap-1.5">
            <User :size="13" />
            <span>Cajero: <strong class="text-[#3D3D3D]">{{ aperturaExistente.usuario?.name || 'Cajero' }}</strong></span>
          </div>
          <span>{{ formatearFechaHora(aperturaExistente.created_at) }}</span>
        </div>

        <div v-if="aperturaExistente.observaciones" class="p-2.5 rounded-lg bg-[#FAF7F4] border border-[#E5E0DB] text-xs text-[#3D3D3D]">
          <strong>Observaciones:</strong> {{ aperturaExistente.observaciones }}
        </div>
      </div>

      <!-- Detalle por denominación registrado -->
      <div class="bg-white rounded-2xl border border-[#E5E0DB] p-4 shadow-xs">
        <h3 class="text-xs font-bold text-[#3D3D3D] uppercase tracking-wider mb-3 flex items-center gap-1.5">
          <Banknote :size="14" class="text-[#C24E12]" />
          Desglose de Denominaciones Registrado
        </h3>

        <div class="divide-y divide-[#E5E0DB]/60">
          <div
            v-for="item in aperturaExistente.denominaciones"
            :key="item.denominacion"
            class="py-2.5 flex items-center justify-between text-xs"
            :class="{ 'opacity-40': Number(item.cantidad) === 0 }"
          >
            <div class="flex items-center gap-2">
              <span
                class="px-2 py-0.5 rounded-md font-bold text-[11px]"
                :class="item.tipo === 'billete' ? 'bg-amber-100 text-[#C24E12]' : 'bg-blue-100 text-[#2B6CB0]'"
              >
                ${{ Number(item.denominacion).toFixed(2) }}
              </span>
              <span class="text-[#6B6B6B]">
                {{ item.tipo === 'billete' ? 'Billete' : 'Moneda' }}
              </span>
            </div>

            <div class="flex items-center gap-4">
              <span class="font-medium text-[#3D3D3D]">
                {{ item.cantidad }} {{ Number(item.cantidad) === 1 ? 'pieza' : 'piezas' }}
              </span>
              <span class="font-bold text-[#111111] min-w-[65px] text-right">
                {{ formatearMoneda(item.subtotal ?? item.denominacion * item.cantidad) }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- VISTA 2: Formulario activo para abrir caja por denominación -->
    <div v-else class="flex-1 flex flex-col overflow-hidden">
      <!-- Tarjeta resumen superior con Gran Total -->
      <div class="p-4 bg-white border-b border-[#E5E0DB] shrink-0 space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-[#6B6B6B] uppercase tracking-wider">Total Saldo Inicial</span>
          <button
            type="button"
            id="btn-reiniciar-conteo"
            @click="reiniciarCantidades"
            class="flex items-center gap-1 text-xs font-semibold text-[#6B6B6B] hover:text-[#C62828] transition-colors py-1 px-2 rounded-md hover:bg-zinc-100 cursor-pointer"
            title="Poner todas las denominaciones a cero"
          >
            <RotateCcw :size="13" />
            <span>Poner a cero</span>
          </button>
        </div>

        <div class="flex items-baseline justify-between">
          <div class="text-3xl sm:text-4xl font-black text-[#3D3D3D] tracking-tight">
            {{ formatearMoneda(granTotal) }}
          </div>
          <span class="text-xs font-medium text-[#6B6B6B]">
            {{ totalPiezas }} {{ totalPiezas === 1 ? 'pieza' : 'piezas en total' }}
          </span>
        </div>

        <!-- Subtotales desglosados -->
        <div class="grid grid-cols-2 gap-2 pt-2 border-t border-[#E5E0DB]">
          <div class="flex items-center justify-between p-2 rounded-xl bg-amber-50/60 border border-amber-200/60">
            <div class="flex items-center gap-1.5 text-xs text-[#C24E12] font-semibold">
              <Banknote :size="15" />
              <span>Billetes</span>
            </div>
            <span class="text-xs font-bold text-[#3D3D3D]">{{ formatearMoneda(subtotalBilletes) }}</span>
          </div>

          <div class="flex items-center justify-between p-2 rounded-xl bg-blue-50/60 border border-blue-200/60">
            <div class="flex items-center gap-1.5 text-xs text-[#2B6CB0] font-semibold">
              <Coins :size="15" />
              <span>Monedas</span>
            </div>
            <span class="text-xs font-bold text-[#3D3D3D]">{{ formatearMoneda(subtotalMonedas) }}</span>
          </div>
        </div>

        <!-- Filtro pestañas -->
        <div class="flex p-1 bg-[#FAF7F4] border border-[#E5E0DB] rounded-xl text-xs font-semibold">
          <button
            type="button"
            @click="pestanaActiva = 'todos'"
            class="flex-1 py-1.5 rounded-lg transition-all cursor-pointer text-center"
            :class="pestanaActiva === 'todos' ? 'bg-white shadow-xs text-[#3D3D3D]' : 'text-[#6B6B6B] hover:text-[#3D3D3D]'"
          >
            Todos
          </button>
          <button
            type="button"
            @click="pestanaActiva = 'billetes'"
            class="flex-1 py-1.5 rounded-lg transition-all cursor-pointer text-center"
            :class="pestanaActiva === 'billetes' ? 'bg-white shadow-xs text-[#C24E12]' : 'text-[#6B6B6B] hover:text-[#3D3D3D]'"
          >
            Billetes
          </button>
          <button
            type="button"
            @click="pestanaActiva = 'monedas'"
            class="flex-1 py-1.5 rounded-lg transition-all cursor-pointer text-center"
            :class="pestanaActiva === 'monedas' ? 'bg-white shadow-xs text-[#2B6CB0]' : 'text-[#6B6B6B] hover:text-[#3D3D3D]'"
          >
            Monedas
          </button>
        </div>
      </div>

      <!-- Lista táctil de denominaciones -->
      <div class="flex-1 overflow-y-auto p-4 space-y-2.5">
        <div
          v-for="item in denominacionesFiltradas"
          :key="item.valor"
          :id="`fila-denominacion-${item.valor}`"
          class="flex items-center justify-between p-2.5 sm:p-3 rounded-2xl border transition-all bg-white"
          :class="cantidades[item.valor] > 0 ? 'border-[#F26A21]/40 shadow-xs ring-1 ring-[#F26A21]/20' : 'border-[#E5E0DB]'"
        >
          <!-- Identificador de la denominación -->
          <div class="flex items-center gap-3">
            <div
              class="w-11 h-11 rounded-xl flex flex-col items-center justify-center font-black text-sm shrink-0 border"
              :class="item.tipo === 'billete' ? 'bg-amber-50 border-amber-200 text-[#C24E12]' : 'bg-blue-50 border-blue-200 text-[#2B6CB0]'"
            >
              <span>{{ item.etiqueta }}</span>
            </div>
            <div>
              <div class="text-xs font-bold text-[#3D3D3D]">{{ item.descripcion }}</div>
              <div class="text-xs font-semibold" :class="cantidades[item.valor] > 0 ? 'text-[#F26A21]' : 'text-[#6B6B6B]'">
                Subtotal: {{ formatearMoneda((cantidades[item.valor] || 0) * item.valor) }}
              </div>
            </div>
          </div>

          <!-- Controles táctiles (+ / - e input directo) -->
          <div class="flex items-center gap-1.5">
            <!-- Botón Decrementar (mínimo 44x44px táctil) -->
            <button
              type="button"
              :id="`btn-decrementar-${item.valor}`"
              @click="decrementar(item.valor)"
              :disabled="cantidades[item.valor] === 0"
              class="w-11 h-11 flex items-center justify-center rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] text-[#3D3D3D] hover:bg-zinc-200 active:scale-95 disabled:opacity-40 disabled:cursor-not-allowed transition-all cursor-pointer text-lg font-bold"
              aria-label="Restar una pieza"
            >
              <Minus :size="18" stroke-width="2.5" />
            </button>

            <!-- Input directo de piezas -->
            <input
              type="number"
              :id="`input-cantidad-${item.valor}`"
              min="0"
              step="1"
              :value="cantidades[item.valor]"
              @input="actualizarCantidad(item.valor, $event)"
              class="w-14 h-11 text-center font-bold text-base text-[#111111] bg-white border border-[#E5E0DB] rounded-xl focus:outline-none focus:border-[#F26A21] focus:ring-1 focus:ring-[#F26A21]"
            />

            <!-- Botón Incrementar (mínimo 44x44px táctil) -->
            <button
              type="button"
              :id="`btn-incrementar-${item.valor}`"
              @click="incrementar(item.valor)"
              class="w-11 h-11 flex items-center justify-center rounded-xl border border-[#F26A21] bg-[#F26A21] text-white hover:bg-[#FF8C42] active:scale-95 transition-all cursor-pointer text-lg font-bold shadow-xs"
              aria-label="Sumar una pieza"
            >
              <Plus :size="18" stroke-width="2.5" />
            </button>
          </div>
        </div>

        <!-- Observaciones opcionales -->
        <div class="pt-2">
          <label for="campo-observaciones-apertura" class="block text-xs font-bold text-[#3D3D3D] mb-1">
            Observaciones de apertura (opcional)
          </label>
          <textarea
            id="campo-observaciones-apertura"
            v-model="observaciones"
            rows="2"
            maxlength="500"
            placeholder="Ej. Fondo de cambio entregado por gerencia..."
            class="w-full text-xs p-3 rounded-xl border border-[#E5E0DB] bg-white text-[#111111] focus:outline-none focus:border-[#F26A21] focus:ring-1 focus:ring-[#F26A21] resize-none"
          ></textarea>
        </div>
      </div>

      <!-- Botón de acción inferior fijo -->
      <footer class="p-4 bg-white border-t border-[#E5E0DB] shrink-0">
        <button
          type="button"
          id="btn-guardar-apertura"
          @click="guardarApertura"
          :disabled="guardando || granTotal <= 0"
          class="w-full h-12 flex items-center justify-center gap-2 rounded-xl bg-[#F26A21] text-white font-bold text-sm hover:bg-[#FF8C42] active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed transition-all shadow-xs cursor-pointer"
        >
          <Loader2 v-if="guardando" :size="18" class="animate-spin" />
          <CheckCircle2 v-else :size="18" stroke-width="2.5" />
          <span>Confirmar Apertura ({{ formatearMoneda(granTotal) }})</span>
        </button>
      </footer>
    </div>
  </div>
</template>
