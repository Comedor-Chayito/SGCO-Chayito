<script setup>
/**
 * Vista de Reporte de Cierre de Caja y Cuadre Diario (CC-42).
 * Permite ejecutar el cierre del turno comercial consolidando ventas y retiros,
 * visualizar el reporte de cuadre con desglose contable, e imprimirlo.
 * También permite consultar el historial de cierres previos.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 */
import { ref, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import {
  ArrowLeft,
  Printer,
  Calendar,
  DollarSign,
  CheckCircle2,
  AlertTriangle,
  XCircle,
  History,
  Lock,
  FileText,
  RefreshCw,
  Receipt,
  Scale,
  Landmark,
  CreditCard,
  Banknote,
  ArrowDownCircle,
  HelpCircle,
} from '@lucide/vue';
import { Toaster, toast } from 'vue-sonner';
import {
  cerrarCaja,
  obtenerCierre,
  historialCierres,
  obtenerAperturaTurno,
} from '@/features/pos/services/cajaService.js';

const router = useRouter();

// Estado de navegación entre pestañas: 'reporte' | 'historial'
const pestanaActiva = ref('reporte');

// Fecha actual (YYYY-MM-DD) en la zona horaria local; toISOString() la daría en UTC
const ahora = new Date();
const fechaHoy = [
  ahora.getFullYear(),
  String(ahora.getMonth() + 1).padStart(2, '0'),
  String(ahora.getDate()).padStart(2, '0'),
].join('-');
const fechaSeleccionada = ref(fechaHoy);

// Datos del cierre activo mostrado
const cierreActual = ref(null);
const cargando = ref(true);
const procesandoCierre = ref(false);

// Apertura de caja del día: de ella sale el saldo inicial y sin ella no se puede cerrar
const aperturaHoy = ref(null);

// Formulario de cierre si la caja aún no ha sido cerrada hoy
const formCierre = ref({
  saldo_final: '',
  observaciones: '',
});

// Historial de cierres
const listaHistorial = ref([]);
const cargandoHistorial = ref(false);
const paginaActual = ref(1);
const totalPaginas = ref(1);

/**
 * Formatea un número decimal como moneda en dólares ($0.00).
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 *
 * @param   {number|string} valor Monto a formatear.
 * @returns {string} Cadena formateada.
 */
function formatearMoneda(valor) {
  const num = Number(valor) || 0;
  return `$${num.toFixed(2)}`;
}

/**
 * Formatea la diferencia del cuadre con su signo delante: +$2.00 sobrante, −$2.00 faltante.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-19
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 *
 * @param   {number|string} valor Diferencia del cuadre.
 * @returns {string} Diferencia con signo y formato de moneda.
 */
function formatearDiferencia(valor) {
  const num = Number(valor) || 0;
  const signo = num > 0 ? '+' : num < 0 ? '−' : '';
  return `${signo}${formatearMoneda(Math.abs(num))}`;
}

/**
 * Formatea una fecha YYYY-MM-DD a formato legible en español.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 *
 * @param   {string} fechaStr Cadena de fecha.
 * @returns {string} Fecha formateada.
 */
function formatearFecha(fechaStr) {
  if (!fechaStr) return '';
  const [año, mes, dia] = fechaStr.split('-');
  const fecha = new Date(Number(año), Number(mes) - 1, Number(dia));
  return fecha.toLocaleDateString('es-SV', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Carga el estado del cierre para la fecha seleccionada y la apertura del día.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 *
 * @param   {string} fecha Fecha en formato YYYY-MM-DD.
 */
async function cargarCierre(fecha = fechaSeleccionada.value) {
  cargando.value = true;
  cierreActual.value = null;

  try {
    const data = await obtenerCierre(fecha);
    cierreActual.value = data;
  } catch (error) {
    if (error.response?.status === 404) {
      cierreActual.value = null;
      // Si es hoy y no hay cierre, consultar si hay saldo inicial registrado en apertura
      if (fecha === fechaHoy) {
        await cargarAperturaHoy();
      }
    } else {
      toast.error('Error al cargar la información del cierre.');
    }
  } finally {
    cargando.value = false;
  }
}

/**
 * Consulta la apertura del turno de hoy, de la que sale el saldo inicial del cierre.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 */
async function cargarAperturaHoy() {
  try {
    aperturaHoy.value = await obtenerAperturaTurno(fechaHoy);
  } catch {
    aperturaHoy.value = null;
  }
}

/**
 * Ejecuta el registro del cierre diario de caja.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 */
async function procesarNuevoCierre() {
  if (!aperturaHoy.value) {
    toast.error('Primero registra la apertura de caja del día.');
    return;
  }

  if (formCierre.value.saldo_final === '' || formCierre.value.saldo_final === null) {
    toast.error('Debes ingresar el saldo final contado en caja.');
    return;
  }

  const saldoFinalNum = Number(formCierre.value.saldo_final);
  if (isNaN(saldoFinalNum) || saldoFinalNum < 0) {
    toast.error('El saldo final debe ser un número válido mayor o igual a 0.');
    return;
  }

  procesandoCierre.value = true;
  try {
    const nuevoCierre = await cerrarCaja({
      fecha: fechaHoy,
      saldo_final: saldoFinalNum,
      observaciones: formCierre.value.observaciones?.trim() || null,
    });

    toast.success('¡Cierre de caja registrado exitosamente!');
    cierreActual.value = nuevoCierre;
  } catch (error) {
    toast.error(error.response?.data?.message || 'Error al procesar el cierre de caja.');
  } finally {
    procesandoCierre.value = false;
  }
}

/**
 * Consulta el historial paginado de cierres anteriores.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 *
 * @param   {number} [pagina=1] Número de página.
 */
async function cargarHistorial(pagina = 1) {
  cargandoHistorial.value = true;
  try {
    const res = await historialCierres(10, pagina);
    listaHistorial.value = res.data || [];
    paginaActual.value = res.current_page || 1;
    totalPaginas.value = res.last_page || 1;
  } catch {
    toast.error('Error al cargar el historial de cierres.');
  } finally {
    cargandoHistorial.value = false;
  }
}

/**
 * Visualiza el reporte de un cierre seleccionado desde el historial.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 *
 * @param   {Object} cierre Registro de cierre a visualizar.
 */
function verDetalleCierre(cierre) {
  fechaSeleccionada.value = cierre.fecha;
  cierreActual.value = cierre;
  pestanaActiva.value = 'reporte';
}

/**
 * Abre el cuadro de diálogo del navegador para imprimir el reporte.
 *
 * @autor  Cristian Alexis
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-42)
 */
function imprimirReporte() {
  window.print();
}

/**
 * Total vendido en el día sumando los tres métodos de pago.
 */
const totalVendidoDia = computed(() => {
  if (!cierreActual.value) return 0;
  const c = cierreActual.value;
  return Number(c.total_ventas_efectivo || 0)
    + Number(c.total_ventas_tarjeta || 0)
    + Number(c.total_ventas_transferencia || 0);
});

/**
 * Efectivo que debía haber en la gaveta: saldo inicial + ventas en efectivo − retiros.
 */
const efectivoEsperado = computed(() => {
  if (!cierreActual.value) return 0;
  const c = cierreActual.value;
  return Number(c.saldo_inicial || 0)
    + Number(c.total_ventas_efectivo || 0)
    - Number(c.total_retiros || 0);
});

/**
 * Estado y color del cuadre según la diferencia calculada.
 * Fórmula: diferencia = venta_del_dia - total_ventas_efectivo (contado − esperado),
 * positivo = sobrante, negativo = faltante.
 */
const estadoCuadre = computed(() => {
  if (!cierreActual.value) return null;
  const dif = Number(cierreActual.value.diferencia) || 0;
  if (Math.abs(dif) < 0.01) {
    return {
      tipo: 'exacto',
      texto: 'Cuadre Exacto',
      colorBadge: 'bg-emerald-50 text-[#2E7D4F] border-emerald-200',
      icono: CheckCircle2,
      descripcion: 'El efectivo físico coincide exactamente con las ventas registradas.',
    };
  }
  if (dif > 0) {
    return {
      tipo: 'sobrante',
      texto: `Sobrante de ${formatearMoneda(dif)}`,
      colorBadge: 'bg-amber-50 text-[#E0A800] border-amber-200',
      icono: AlertTriangle,
      descripcion: 'Hay más efectivo en caja del registrado por las ventas del sistema.',
    };
  }
  return {
    tipo: 'faltante',
    texto: `Faltante de ${formatearMoneda(Math.abs(dif))}`,
    colorBadge: 'bg-rose-50 text-[#C62828] border-rose-200',
    icono: XCircle,
    descripcion: 'Falta efectivo en caja en comparación con las ventas registradas.',
  };
});

onMounted(() => {
  cargarCierre(fechaHoy);
});
</script>

<template>
  <div class="min-h-[100dvh] flex flex-col bg-[#FAF7F4]" id="vista-reporte-cierre">
    <Toaster richColors position="top-center" />

    <!-- Barra superior de navegación (oculta al imprimir) -->
    <header class="sticky top-0 z-30 flex flex-wrap items-center justify-between gap-2 border-b border-[#E5E0DB] bg-white px-4 py-3 shadow-xs print:hidden">
      <div class="flex min-w-0 items-center gap-3">
        <button
          type="button"
          id="btn-regresar-caja"
          @click="router.push('/caja')"
          class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] text-[#3D3D3D] hover:bg-zinc-100 active:scale-95 transition-all cursor-pointer"
          title="Regresar a Caja"
        >
          <ArrowLeft :size="20" stroke-width="2" />
        </button>
        <div class="flex min-w-0 items-center gap-2.5">
          <div class="hidden sm:flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#F26A21] text-white shadow-xs">
            <Scale :size="20" stroke-width="2" />
          </div>
          <div class="min-w-0">
            <h1 class="text-base sm:text-lg font-bold leading-tight text-[#3D3D3D]">Cierre Diario y Cuadre de Caja</h1>
            <p class="hidden sm:block text-xs text-[#6B6B6B]">Comedor Chayito · Módulo POS</p>
          </div>
        </div>
      </div>

      <!-- Selector de pestañas y botones de acción (en móvil bajan a su propia fila) -->
      <div class="flex w-full sm:w-auto items-center gap-2">
        <div class="flex flex-1 sm:flex-none items-center rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] p-1 text-xs font-semibold">
          <button
            type="button"
            id="tab-reporte"
            @click="pestanaActiva = 'reporte'"
            class="flex-1 sm:flex-none min-h-11 rounded-lg px-3 transition-all cursor-pointer"
            :class="pestanaActiva === 'reporte' ? 'bg-white text-[#3D3D3D] shadow-xs font-bold' : 'text-[#6B6B6B] hover:text-[#3D3D3D]'"
          >
            <span class="flex items-center justify-center gap-1.5">
              <FileText :size="15" />
              Reporte
            </span>
          </button>
          <button
            type="button"
            id="tab-historial"
            @click="() => { pestanaActiva = 'historial'; cargarHistorial(1); }"
            class="flex-1 sm:flex-none min-h-11 rounded-lg px-3 transition-all cursor-pointer"
            :class="pestanaActiva === 'historial' ? 'bg-white text-[#3D3D3D] shadow-xs font-bold' : 'text-[#6B6B6B] hover:text-[#3D3D3D]'"
          >
            <span class="flex items-center justify-center gap-1.5">
              <History :size="15" />
              Historial
            </span>
          </button>
        </div>

        <button
          v-if="cierreActual && pestanaActiva === 'reporte'"
          type="button"
          id="btn-imprimir-reporte"
          @click="imprimirReporte"
          aria-label="Imprimir reporte"
          class="flex h-11 min-w-11 shrink-0 items-center justify-center gap-2 rounded-xl border border-[#E5E0DB] bg-white px-3.5 text-xs font-bold text-[#3D3D3D] hover:bg-zinc-50 active:scale-95 transition-all cursor-pointer shadow-xs"
        >
          <Printer :size="16" stroke-width="2.2" />
          <span class="hidden sm:inline">Imprimir Reporte</span>
        </button>
      </div>
    </header>

    <!-- Contenido principal -->
    <main class="flex-1 p-3 sm:p-5 md:p-6 overflow-y-auto max-w-4xl w-full mx-auto">

      <!-- PESTAÑA: REPORTE / CIERRE -->
      <section v-if="pestanaActiva === 'reporte'" class="space-y-4">

        <!-- Selector de fecha para consultar cierres pasados (oculto en impresión) -->
        <div class="flex flex-wrap items-center justify-between gap-3 bg-white p-3.5 rounded-2xl border border-[#E5E0DB] shadow-xs print:hidden">
          <div class="flex items-center gap-2 text-sm text-[#3D3D3D] font-medium">
            <Calendar :size="18" class="text-[#F26A21]" />
            <span>Consultar fecha:</span>
            <input
              type="date"
              id="input-fecha-cierre"
              v-model="fechaSeleccionada"
              :max="fechaHoy"
              @change="cargarCierre(fechaSeleccionada)"
              class="rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] px-3 py-1.5 text-sm font-semibold text-[#3D3D3D] focus:outline-hidden focus:ring-2 focus:ring-[#F26A21]/30 cursor-pointer"
            />
          </div>

          <button
            type="button"
            id="btn-recargar-cierre"
            @click="cargarCierre(fechaSeleccionada)"
            class="flex items-center gap-1.5 text-xs font-semibold text-[#6B6B6B] hover:text-[#F26A21] transition-colors cursor-pointer"
          >
            <RefreshCw :size="14" :class="{ 'animate-spin': cargando }" />
            <span>Actualizar</span>
          </button>
        </div>

        <!-- Indicador de carga -->
        <div v-if="cargando" class="flex flex-col items-center justify-center p-12 bg-white rounded-2xl border border-[#E5E0DB]">
          <div class="h-8 w-8 rounded-full border-2 border-[#F26A21] border-t-transparent animate-spin mb-3"></div>
          <p class="text-sm text-[#6B6B6B]">Consultando datos de caja...</p>
        </div>

        <!-- CASO 1: YA EXISTE CIERRE (Mostrar Reporte de Cuadre) -->
        <article
          v-else-if="cierreActual"
          id="reporte-imprimible"
          class="bg-white rounded-2xl border border-[#E5E0DB] shadow-xs overflow-hidden"
        >
          <!-- Encabezado del Reporte -->
          <div class="border-b border-[#E5E0DB] p-5 sm:p-6 bg-gradient-to-b from-[#FAF7F4] to-white">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
              <div>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-100 text-[#2E7D4F] text-xs font-bold mb-2">
                  <CheckCircle2 :size="13" stroke-width="2.5" />
                  Caja Cerrada · Bitácora Inalterable
                </span>
                <h2 class="text-xl sm:text-2xl font-black text-[#3D3D3D] tracking-tight">Reporte de Cierre de Caja</h2>
                <p class="text-xs sm:text-sm text-[#6B6B6B] capitalize mt-0.5">
                  {{ formatearFecha(cierreActual.fecha) }}
                </p>
              </div>

              <!-- Badge de estado del cuadre -->
              <div
                v-if="estadoCuadre"
                class="flex items-center gap-2 px-3.5 py-2 rounded-xl border text-sm font-black shadow-2xs self-start sm:self-center"
                :class="estadoCuadre.colorBadge"
              >
                <component :is="estadoCuadre.icono" :size="18" stroke-width="2.5" />
                <span>{{ estadoCuadre.texto }}</span>
              </div>
            </div>

            <!-- Fila de metadatos (Cajero, ID, Hora de creación) -->
            <div class="mt-4 pt-3 border-t border-[#E5E0DB]/80 grid grid-cols-2 sm:grid-cols-3 gap-2 text-xs text-[#6B6B6B]">
              <div>
                <span class="font-medium text-[#111111]">Cajero/a:</span>
                <p class="font-semibold text-[#3D3D3D]">{{ cierreActual.usuario?.name || 'Cajero del turno' }}</p>
              </div>
              <div>
                <span class="font-medium text-[#111111]">Hora de cierre:</span>
                <p class="font-semibold text-[#3D3D3D]">
                  {{ cierreActual.created_at ? new Date(cierreActual.created_at).toLocaleTimeString('es-SV', { hour: '2-digit', minute: '2-digit' }) : 'N/D' }}
                </p>
              </div>
              <div class="col-span-2 sm:col-span-1">
                <span class="font-medium text-[#111111]">Comedor:</span>
                <p class="font-semibold text-[#3D3D3D]">Comedor Chayito</p>
              </div>
            </div>
          </div>

          <!-- Cuadrícula de Métricas Contables (Fórmula del negocio) -->
          <div class="p-5 sm:p-6 space-y-6">
            <!-- Ventas del día por método de pago -->
            <h3 class="text-xs font-bold uppercase tracking-wider text-[#6B6B6B]">
              Ventas del Día por Método de Pago
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
              <!-- Ventas Efectivo -->
              <div class="p-4 rounded-xl border border-[#E5E0DB] bg-emerald-50/40 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs text-emerald-800 mb-1">
                  <span class="font-semibold">Efectivo</span>
                  <Banknote :size="16" class="text-[#2E7D4F]" />
                </div>
                <div id="total-ventas-efectivo" class="text-xl font-black text-[#2E7D4F]">
                  {{ formatearMoneda(cierreActual.total_ventas_efectivo) }}
                </div>
                <p class="text-[11px] text-[#2E7D4F]/80 mt-1">Cobrado en billete y moneda</p>
              </div>

              <!-- Ventas Tarjeta -->
              <div class="p-4 rounded-xl border border-[#E5E0DB] bg-blue-50/40 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs text-blue-800 mb-1">
                  <span class="font-semibold">Tarjeta</span>
                  <CreditCard :size="16" class="text-[#2B6CB0]" />
                </div>
                <div id="total-ventas-tarjeta" class="text-xl font-black text-[#2B6CB0]">
                  {{ formatearMoneda(cierreActual.total_ventas_tarjeta) }}
                </div>
                <p class="text-[11px] text-[#2B6CB0]/80 mt-1">No entra a la gaveta</p>
              </div>

              <!-- Ventas Transferencia -->
              <div class="p-4 rounded-xl border border-[#E5E0DB] bg-blue-50/40 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs text-blue-800 mb-1">
                  <span class="font-semibold">Transferencia</span>
                  <Landmark :size="16" class="text-[#2B6CB0]" />
                </div>
                <div id="total-ventas-transferencia" class="text-xl font-black text-[#2B6CB0]">
                  {{ formatearMoneda(cierreActual.total_ventas_transferencia) }}
                </div>
                <p class="text-[11px] text-[#2B6CB0]/80 mt-1">No entra a la gaveta</p>
              </div>
            </div>

            <!-- Total vendido: suma de los tres métodos -->
            <div class="flex items-center justify-between p-4 rounded-xl border-2 border-[#F26A21]/30 bg-orange-50/30">
              <span class="text-sm font-bold text-[#C24E12]">Total vendido del día</span>
              <span id="total-vendido-dia" class="text-2xl font-black text-[#C24E12]">
                {{ formatearMoneda(totalVendidoDia) }}
              </span>
            </div>

            <!-- Cuadre de efectivo: solo el efectivo físico de la gaveta -->
            <h3 class="text-xs font-bold uppercase tracking-wider text-[#6B6B6B]">
              Cuadre de Efectivo en Caja
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
              <!-- Saldo Inicial -->
              <div class="p-4 rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs text-[#6B6B6B] mb-1">
                  <span>Saldo Inicial</span>
                  <DollarSign :size="16" class="text-zinc-500" />
                </div>
                <div class="text-xl font-black text-[#3D3D3D]">
                  {{ formatearMoneda(cierreActual.saldo_inicial) }}
                </div>
                <p class="text-[11px] text-[#6B6B6B] mt-1">Según la apertura de caja</p>
              </div>

              <!-- Retiros Parciales -->
              <div class="p-4 rounded-xl border border-[#E5E0DB] bg-rose-50/40 flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs text-rose-800 mb-1">
                  <span class="font-semibold">Retiros Parciales</span>
                  <ArrowDownCircle :size="16" class="text-[#C62828]" />
                </div>
                <div class="text-xl font-black text-[#C62828]">
                  {{ formatearMoneda(cierreActual.total_retiros) }}
                </div>
                <p class="text-[11px] text-[#C62828]/80 mt-1">Efectivo retirado a caja fuerte</p>
              </div>

              <!-- Saldo Final Físico -->
              <div class="p-4 rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs text-[#6B6B6B] mb-1">
                  <span>Saldo Final Contado</span>
                  <Receipt :size="16" class="text-zinc-500" />
                </div>
                <div class="text-xl font-black text-[#3D3D3D]">
                  {{ formatearMoneda(cierreActual.saldo_final) }}
                </div>
                <p class="text-[11px] text-[#6B6B6B] mt-1">Efectivo físico presente en gaveta</p>
              </div>

              <!-- Venta del Día (Fórmula del negocio) -->
              <div class="p-4 rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] flex flex-col justify-between">
                <div class="flex items-center justify-between text-xs text-[#C24E12] mb-1">
                  <span class="font-bold">Venta Calculada del Día</span>
                  <Scale :size="16" class="text-[#F26A21]" />
                </div>
                <div class="text-xl font-black text-[#C24E12]">
                  {{ formatearMoneda(cierreActual.venta_del_dia) }}
                </div>
                <p class="text-[11px] text-[#6B6B6B] mt-1">(Retiros + Saldo Final) − Saldo Inicial</p>
              </div>
            </div>

            <!-- Fila de Cuadre y Conciliación -->
            <div class="p-4 sm:p-5 rounded-2xl border border-[#E5E0DB] bg-[#FAF7F4] flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
              <div>
                <h4 class="text-sm font-bold text-[#3D3D3D]">Resultado del Cuadre de Caja</h4>
                <p id="detalle-cuadre" class="text-xs text-[#6B6B6B] mt-0.5">
                  Esperado en caja {{ formatearMoneda(efectivoEsperado) }}
                  · Contado {{ formatearMoneda(cierreActual.saldo_final) }}
                </p>
                <p class="text-xs font-medium text-[#3D3D3D] mt-1.5" v-if="estadoCuadre">
                  {{ estadoCuadre.descripcion }}
                </p>
              </div>

              <div class="text-right self-end sm:self-center">
                <div
                  class="text-2xl font-black"
                  :class="Number(cierreActual.diferencia) === 0 ? 'text-[#2E7D4F]' : (Number(cierreActual.diferencia) > 0 ? 'text-[#E0A800]' : 'text-[#C62828]')"
                >
                  {{ formatearDiferencia(cierreActual.diferencia) }}
                </div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#6B6B6B]">Diferencia de Cuadre</span>
              </div>
            </div>

            <!-- Observaciones si existen -->
            <div v-if="cierreActual.observaciones" class="p-4 rounded-xl border border-[#E5E0DB] bg-white">
              <span class="text-xs font-bold uppercase tracking-wider text-[#6B6B6B] block mb-1">
                Observaciones del Cierre
              </span>
              <p class="text-sm text-[#3D3D3D] whitespace-pre-wrap">{{ cierreActual.observaciones }}</p>
            </div>
          </div>

          <!-- Pie del Reporte (para impresión) -->
          <div class="border-t border-[#E5E0DB] p-5 text-center text-xs text-[#6B6B6B] space-y-1 bg-[#FAF7F4]">
            <p class="font-bold text-[#3D3D3D]">Comedor Chayito · Control y Cuadre de Caja Diario</p>
            <p>Reporte oficial generado para auditoría contable. Bitácora inalterable.</p>
          </div>
        </article>

        <!-- CASO 2: NO EXISTE CIERRE PARA HOY (Formulario para registrar cierre) -->
        <article
          v-else-if="fechaSeleccionada === fechaHoy"
          class="bg-white rounded-2xl border border-[#E5E0DB] shadow-xs p-5 sm:p-7 max-w-xl mx-auto"
        >
          <div class="flex items-center gap-3 border-b border-[#E5E0DB] pb-4 mb-5">
            <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-orange-100 text-[#F26A21]">
              <Lock :size="22" stroke-width="2.2" />
            </div>
            <div>
              <h2 class="text-lg font-bold text-[#3D3D3D]">Cierre Diario de Caja</h2>
              <p class="text-xs text-[#6B6B6B]">El turno aún está abierto para la fecha de hoy.</p>
            </div>
          </div>

          <!-- Sin apertura no hay saldo inicial confiable: se pide abrir la caja primero -->
          <div
            v-if="!aperturaHoy"
            id="aviso-sin-apertura"
            class="mb-5 p-3.5 rounded-xl border border-amber-200 bg-amber-50/60 flex items-start gap-3"
          >
            <AlertTriangle :size="18" class="text-[#E0A800] shrink-0 mt-0.5" />
            <div class="text-xs">
              <span class="font-bold text-[#3D3D3D]">No hay apertura de caja registrada hoy.</span>
              <p class="text-[#3D3D3D] mt-0.5">
                El saldo inicial sale del conteo de la apertura. Regístrala antes de cerrar.
              </p>
              <button
                type="button"
                id="btn-ir-apertura"
                @click="router.push('/caja/apertura')"
                class="mt-2 min-h-11 px-4 rounded-xl bg-[#F26A21] text-xs font-bold text-white hover:bg-[#FF8C42] cursor-pointer"
              >
                Registrar apertura de caja
              </button>
            </div>
          </div>

          <form @submit.prevent="procesarNuevoCierre" class="space-y-4">
            <!-- Saldo Inicial: solo lectura, viene de la apertura -->
            <div v-if="aperturaHoy">
              <span class="block text-xs font-bold text-[#3D3D3D] uppercase tracking-wider mb-1">
                Saldo Inicial ($)
              </span>
              <div
                id="saldo-inicial-cierre"
                class="flex items-center justify-between w-full rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] px-4 py-2.5"
              >
                <span class="text-base font-bold text-[#3D3D3D]">
                  {{ formatearMoneda(aperturaHoy.saldo_inicial) }}
                </span>
                <Lock :size="15" class="text-[#6B6B6B]" />
              </div>
              <span class="text-[11px] text-[#6B6B6B]">
                Registrado en la apertura de caja; no se puede modificar al cerrar.
              </span>
            </div>

            <!-- Saldo Final Contado en Gaveta -->
            <div>
              <label class="block text-xs font-bold text-[#3D3D3D] uppercase tracking-wider mb-1">
                Saldo Final Contado en Caja ($) *
              </label>
              <input
                type="number"
                step="0.01"
                min="0"
                id="input-saldo-final-cierre"
                v-model="formCierre.saldo_final"
                placeholder="0.00"
                required
                class="w-full rounded-xl border-2 border-[#F26A21]/40 bg-white px-4 py-2.5 text-xl font-black text-[#3D3D3D] focus:border-[#F26A21] focus:outline-hidden focus:ring-3 focus:ring-[#F26A21]/20"
              />
              <span class="text-[11px] text-[#6B6B6B]">Efectivo total en billetes y monedas contado al cierre.</span>
            </div>

            <!-- Observaciones -->
            <div>
              <label class="block text-xs font-bold text-[#3D3D3D] uppercase tracking-wider mb-1">
                Observaciones (Opcional)
              </label>
              <textarea
                id="input-observaciones-cierre"
                v-model="formCierre.observaciones"
                rows="3"
                maxlength="500"
                placeholder="Detalles sobre faltantes, sobrantes o justificaciones de arqueo..."
                class="w-full rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] p-3 text-sm text-[#3D3D3D] focus:bg-white focus:outline-hidden focus:ring-2 focus:ring-[#F26A21]/30 resize-none"
              ></textarea>
            </div>

            <!-- Botón de acción -->
            <button
              type="submit"
              id="btn-confirmar-cierre"
              :disabled="procesandoCierre || !aperturaHoy"
              class="w-full flex items-center justify-center gap-2 rounded-xl bg-[#F26A21] py-3 text-sm font-bold text-white shadow-xs hover:bg-[#FF8C42] active:scale-98 transition-all cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
            >
              <Lock :size="17" />
              <span>{{ procesandoCierre ? 'Procesando Cierre...' : 'Registrar Cierre Diario y Generar Reporte' }}</span>
            </button>
          </form>
        </article>

        <!-- CASO 3: FECHA PASADA SIN CIERRE REGISTRADO -->
        <div v-else class="bg-white rounded-2xl border border-[#E5E0DB] p-8 text-center max-w-md mx-auto">
          <div class="h-14 w-14 rounded-2xl bg-[#FAF7F4] border border-[#E5E0DB] flex items-center justify-center mx-auto mb-3">
            <HelpCircle :size="28" class="text-zinc-400" />
          </div>
          <h3 class="text-base font-bold text-[#3D3D3D]">No hay cierre registrado</h3>
          <p class="text-xs text-[#6B6B6B] mt-1">No se encontró bitácora de cierre para el día {{ formatearFecha(fechaSeleccionada) }}.</p>
          <button
            type="button"
            @click="cargarCierre(fechaHoy)"
            class="mt-4 px-4 py-2 rounded-xl bg-[#FAF7F4] border border-[#E5E0DB] text-xs font-bold text-[#3D3D3D] hover:bg-zinc-100 transition-colors cursor-pointer"
          >
            Volver a la fecha de hoy
          </button>
        </div>

      </section>

      <!-- PESTAÑA: HISTORIAL DE CIERRES -->
      <section v-else class="space-y-4">
        <div class="bg-white rounded-2xl border border-[#E5E0DB] shadow-xs overflow-hidden">
          <div class="p-4 border-b border-[#E5E0DB] flex items-center justify-between">
            <div class="flex items-center gap-2">
              <History :size="18" class="text-[#F26A21]" />
              <h2 class="text-base font-bold text-[#3D3D3D]">Historial de Cierres de Caja</h2>
            </div>
            <button
              type="button"
              @click="cargarHistorial(paginaActual)"
              class="text-xs font-semibold text-[#6B6B6B] hover:text-[#F26A21] flex items-center gap-1 cursor-pointer"
            >
              <RefreshCw :size="13" :class="{ 'animate-spin': cargandoHistorial }" />
              Actualizar
            </button>
          </div>

          <!-- Indicador de carga -->
          <div v-if="cargandoHistorial" class="p-8 text-center">
            <div class="h-7 w-7 rounded-full border-2 border-[#F26A21] border-t-transparent animate-spin mx-auto mb-2"></div>
            <span class="text-xs text-[#6B6B6B]">Cargando historial...</span>
          </div>

          <!-- Sin registros -->
          <div v-else-if="listaHistorial.length === 0" class="p-8 text-center text-xs text-[#6B6B6B]">
            No hay registros de cierres de caja en el sistema.
          </div>

          <!-- Tabla de historial -->
          <div v-else class="overflow-x-auto">
            <table class="w-full text-left text-xs">
              <thead class="bg-[#FAF7F4] border-b border-[#E5E0DB] text-[#6B6B6B] uppercase font-bold text-[10px] tracking-wider">
                <tr>
                  <th class="p-3.5">Fecha</th>
                  <th class="p-3.5">Cajero</th>
                  <th class="p-3.5 text-right">Saldo Inicial</th>
                  <th class="p-3.5 text-right">Venta del Día</th>
                  <th class="p-3.5 text-right">Saldo Final</th>
                  <th class="p-3.5 text-center">Cuadre</th>
                  <th class="p-3.5 text-center">Acción</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#E5E0DB]">
                <tr
                  v-for="cierre in listaHistorial"
                  :key="cierre.id"
                  class="hover:bg-zinc-50 transition-colors"
                >
                  <td class="p-3.5 font-bold text-[#3D3D3D] whitespace-nowrap">
                    {{ cierre.fecha }}
                  </td>
                  <td class="p-3.5 text-[#6B6B6B] whitespace-nowrap">
                    {{ cierre.usuario?.name || 'Cajero' }}
                  </td>
                  <td class="p-3.5 text-right font-medium text-[#3D3D3D]">
                    {{ formatearMoneda(cierre.saldo_inicial) }}
                  </td>
                  <td class="p-3.5 text-right font-bold text-[#F26A21]">
                    {{ formatearMoneda(cierre.venta_del_dia) }}
                  </td>
                  <td class="p-3.5 text-right font-medium text-[#3D3D3D]">
                    {{ formatearMoneda(cierre.saldo_final) }}
                  </td>
                  <td class="p-3.5 text-center">
                    <span
                      class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold"
                      :class="Number(cierre.diferencia) === 0 ? 'bg-emerald-100 text-[#2E7D4F]' : (Number(cierre.diferencia) > 0 ? 'bg-amber-100 text-[#E0A800]' : 'bg-rose-100 text-[#C62828]')"
                    >
                      {{ Number(cierre.diferencia) === 0 ? 'Exacto' : formatearDiferencia(cierre.diferencia) }}
                    </span>
                  </td>
                  <td class="p-3.5 text-center">
                    <button
                      type="button"
                      @click="verDetalleCierre(cierre)"
                      class="px-2.5 py-1 rounded-lg border border-[#E5E0DB] bg-white text-[11px] font-bold text-[#3D3D3D] hover:bg-zinc-100 active:scale-95 transition-all cursor-pointer shadow-2xs"
                    >
                      Ver Reporte
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Paginación -->
          <div v-if="totalPaginas > 1" class="p-3.5 border-t border-[#E5E0DB] flex items-center justify-between text-xs text-[#6B6B6B]">
            <span>Página {{ paginaActual }} de {{ totalPaginas }}</span>
            <div class="flex items-center gap-1.5">
              <button
                type="button"
                :disabled="paginaActual <= 1"
                @click="cargarHistorial(paginaActual - 1)"
                class="px-3 py-1 rounded-lg border border-[#E5E0DB] bg-white font-semibold hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
              >
                Anterior
              </button>
              <button
                type="button"
                :disabled="paginaActual >= totalPaginas"
                @click="cargarHistorial(paginaActual + 1)"
                class="px-3 py-1 rounded-lg border border-[#E5E0DB] bg-white font-semibold hover:bg-zinc-50 disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
              >
                Siguiente
              </button>
            </div>
          </div>
        </div>
      </section>

    </main>
  </div>
</template>

<style scoped>
@media print {
  @page {
    margin: 1cm;
    size: auto;
  }

  body {
    background: white !important;
  }

  #vista-reporte-cierre {
    background: white !important;
  }

  main {
    padding: 0 !important;
    max-width: 100% !important;
  }

  #reporte-imprimible {
    border: none !important;
    box-shadow: none !important;
  }
}
</style>
