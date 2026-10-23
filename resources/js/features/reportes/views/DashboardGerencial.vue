<script setup>
/**
 * Dashboard exclusivo para Socio y Administrador con KPIs de salud financiera.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
 */
import { computed, onMounted, ref } from 'vue';
import {
  ArrowLeft, BadgeDollarSign, ChartNoAxesCombined, CircleDollarSign, LoaderCircle,
  ReceiptText, RefreshCw, ShoppingBag, TrendingDown, TrendingUp,
} from '@lucide/vue';
import { Toaster, toast } from 'vue-sonner';
import { useRouter } from 'vue-router';
import { obtenerDashboardGerencial } from '@/features/reportes/services/dashboardGerencialService.js';

const router = useRouter();
const cargando = ref(true);
const dashboard = ref(null);
const filtros = ref(crearFiltrosIniciales());
const barraActiva = ref(null);
const segmentoActivo = ref(null);

const COLORES_GRAFICO = ['#2B6CB0', '#E05A1A', '#2E7D4F', '#7C3AED', '#C58B00', '#C2415A'];

/**
 * Construye el rango predeterminado desde el primer día del mes hasta hoy.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 *
 * @returns {{ fecha_desde: string, fecha_hasta: string }} Rango de fechas local en formato ISO.
 */
function crearFiltrosIniciales() {
  const hoy = new Date();
  const inicioMes = new Date(hoy.getFullYear(), hoy.getMonth(), 1);

  return {
    fecha_desde: fechaLocalIso(inicioMes),
    fecha_hasta: fechaLocalIso(hoy),
  };
}

/**
 * Convierte una fecha local a formato ISO sin desplazarla por zona horaria.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 *
 * @param {Date} fecha Fecha que se desea representar.
 * @returns {string} Fecha en formato YYYY-MM-DD.
 */
function fechaLocalIso(fecha) {
  const anio = fecha.getFullYear();
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const dia = String(fecha.getDate()).padStart(2, '0');

  return `${anio}-${mes}-${dia}`;
}

/**
 * Obtiene los indicadores actualizados para el rango elegido en el formulario.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 */
async function cargarDashboard() {
  if (filtros.value.fecha_desde > filtros.value.fecha_hasta) {
    toast.error('La fecha inicial no puede ser posterior a la fecha final.');
    return;
  }

  cargando.value = true;

  try {
    dashboard.value = await obtenerDashboardGerencial(filtros.value);
    barraActiva.value = null;
    segmentoActivo.value = null;
  } catch (error) {
    toast.error(obtenerMensajeError(error, 'No se pudieron obtener los indicadores.'));
  } finally {
    cargando.value = false;
  }
}

/**
 * Obtiene la serie de ventas mensuales que alimenta la gráfica de barras.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 *
 * @returns {Array<{periodo: string, etiqueta: string, total: number}>} Últimos seis meses con ventas.
 */
const ventasMensuales = computed(() => dashboard.value?.graficos?.ventas_mensuales ?? []);

/**
 * Define la referencia superior para escalar visualmente las barras de venta.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 *
 * @returns {number} Mayor venta mensual, con mínimo de uno para evitar división entre cero.
 */
const ventaMensualMaxima = computed(() => Math.max(
  ...ventasMensuales.value.map((venta) => Number(venta.total)),
  1,
));

/**
 * Obtiene el detalle de rentabilidad estimada por platillo retornado por el API.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 *
 * @returns {Array<{platillo_id: number, nombre: string, unidades: number, ingresos: number, utilidad_estimada: number}>} Aportes por platillo.
 */
const rentabilidadPorPlatillo = computed(() => dashboard.value?.graficos?.rentabilidad_por_platillo ?? []);

/**
 * Calcula los segmentos de la dona según participación de ingresos por platillo.
 *
 * La utilidad que se muestra para cada segmento es estimada: el sistema aún no conserva
 * costos de ingredientes por platillo para calcular un margen individual exacto.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 *
 * @returns {Array<object>} Segmentos con porcentaje, color y desplazamiento SVG.
 */
const segmentosRentabilidad = computed(() => {
  const ingresosTotales = rentabilidadPorPlatillo.value.reduce(
    (acumulado, platillo) => acumulado + Number(platillo.ingresos),
    0,
  );
  let porcentajeAcumulado = 0;

  return rentabilidadPorPlatillo.value.map((platillo, indice) => {
    const porcentaje = ingresosTotales > 0
      ? (Number(platillo.ingresos) / ingresosTotales) * 100
      : 0;
    const segmento = {
      ...platillo,
      color: COLORES_GRAFICO[indice % COLORES_GRAFICO.length],
      porcentaje,
      desplazamiento: -porcentajeAcumulado,
    };

    porcentajeAcumulado += porcentaje;
    return segmento;
  });
});

/**
 * Expone la barra elegida o, inicialmente, el mes más reciente de la serie.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 *
 * @returns {{periodo: string, etiqueta: string, total: number}|null} Venta mensual enfocada.
 */
const detalleBarraActiva = computed(() => barraActiva.value ?? ventasMensuales.value.at(-1) ?? null);

/**
 * Expone el segmento elegido o el primer platillo de la gráfica de dona.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 *
 * @returns {object|null} Platillo enfocado para el detalle interactivo.
 */
const detalleSegmentoActivo = computed(() => segmentoActivo.value ?? segmentosRentabilidad.value[0] ?? null);

/**
 * Calcula la altura porcentual de una barra sin ocultar un mes con venta positiva pequeña.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 *
 * @param {{total: number}} venta Dato mensual recibido del API.
 * @returns {string} Altura CSS de la barra.
 */
function obtenerAlturaBarra(venta) {
  const porcentaje = (Number(venta.total) / ventaMensualMaxima.value) * 100;
  return `${venta.total > 0 ? Math.max(porcentaje, 5) : 0}%`;
}

/**
 * Selecciona una barra mediante puntero, teclado o foco para actualizar su detalle.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 *
 * @param {{periodo: string, etiqueta: string, total: number}} venta Mes seleccionado.
 */
function seleccionarBarra(venta) {
  barraActiva.value = venta;
}

/**
 * Selecciona un segmento de la dona mediante leyenda, puntero, teclado o foco.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / US-REP-01
 *
 * @param {object} segmento Platillo y aporte estimado seleccionado.
 */
function seleccionarSegmento(segmento) {
  segmentoActivo.value = segmento;
}

/**
 * Define si el resultado financiero del periodo es positivo, neutro o negativo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 *
 * @returns {'positiva'|'neutra'|'negativa'} Estado visual de salud financiera.
 */
const saludFinanciera = computed(() => {
  const utilidad = Number(dashboard.value?.kpis?.utilidad_bruta ?? 0);

  if (utilidad > 0) return 'positiva';
  if (utilidad < 0) return 'negativa';
  return 'neutra';
});

/**
 * Devuelve la leyenda contextual que acompaña la utilidad bruta del periodo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 *
 * @returns {string} Texto comprensible del estado financiero.
 */
const mensajeSaludFinanciera = computed(() => {
  if (saludFinanciera.value === 'positiva') return 'Operación rentable en el periodo seleccionado.';
  if (saludFinanciera.value === 'negativa') return 'Los gastos netos superan los ingresos del periodo.';
  return 'Sin utilidad ni pérdida registrada en el periodo seleccionado.';
});

/**
 * Formatea un importe contable con dos decimales en dólares estadounidenses.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 *
 * @param {number|string} monto Importe a presentar.
 * @returns {string} Valor monetario formateado.
 */
function formatearMoneda(monto) {
  return new Intl.NumberFormat('es-SV', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(Number(monto));
}

/**
 * Formatea un porcentaje conservando dos decimales para el margen bruto.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 *
 * @param {number|string} porcentaje Margen bruto calculado por el backend.
 * @returns {string} Porcentaje visible para el KPI.
 */
function formatearPorcentaje(porcentaje) {
  return `${Number(porcentaje).toFixed(2)}%`;
}

/**
 * Presenta una fecha ISO como texto legible sin alteración por zona horaria.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 *
 * @param {string} fecha Fecha del periodo retornada por el API.
 * @returns {string} Fecha formateada para la cabecera del dashboard.
 */
function formatearFecha(fecha) {
  const [anio, mes, dia] = fecha.split('-').map(Number);
  return new Intl.DateTimeFormat('es-SV', { dateStyle: 'medium' }).format(new Date(anio, mes - 1, dia));
}

/**
 * Extrae el primer error de validación o el mensaje general del contrato API.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75 / CC-76
 *
 * @param {unknown} error Error recibido en la petición HTTP.
 * @param {string} mensajePredeterminado Texto para fallos sin detalle.
 * @returns {string} Mensaje seguro para mostrar en la interfaz.
 */
function obtenerMensajeError(error, mensajePredeterminado) {
  const errores = error?.response?.data?.errors;
  return (errores ? Object.values(errores).flat()[0] : null)
    || error?.response?.data?.message
    || mensajePredeterminado;
}

onMounted(cargarDashboard);
</script>

<template>
  <main id="vista-dashboard-gerencial" class="min-h-[100dvh] bg-[#FAF7F4] p-4 sm:p-6">
    <Toaster rich-colors position="top-center" />

    <div class="mx-auto max-w-6xl space-y-5">
      <header class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
        <button
          id="btn-volver-inicio-dashboard"
          type="button"
          class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] text-[#3D3D3D] hover:bg-[#FAF7F4]"
          aria-label="Volver al inicio"
          @click="router.push('/')"
        >
          <ArrowLeft :size="20" />
        </button>
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
          <ChartNoAxesCombined :size="22" />
        </div>
        <div class="min-w-0 flex-1">
          <h1 class="text-lg font-black text-[#3D3D3D]">Dashboard gerencial</h1>
          <p class="text-xs text-[#6B6B6B]">KPIs financieros en tiempo real para decisión estratégica.</p>
        </div>
        <button
          id="btn-recargar-dashboard"
          type="button"
          class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] text-[#3D3D3D] hover:bg-[#FAF7F4] disabled:cursor-wait"
          :disabled="cargando"
          aria-label="Actualizar indicadores"
          @click="cargarDashboard"
        >
          <RefreshCw :size="19" :class="{ 'animate-spin': cargando }" />
        </button>
      </header>

      <section class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
        <form class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end" @submit.prevent="cargarDashboard">
          <label for="input-fecha-desde-dashboard" class="block text-sm font-bold text-[#3D3D3D]">
            Desde
            <input
              id="input-fecha-desde-dashboard"
              v-model="filtros.fecha_desde"
              type="date"
              required
              class="mt-1 h-11 w-full rounded-xl border border-[#E5E0DB] px-3 text-base font-normal outline-none focus:border-[#2B6CB0] focus:ring-2 focus:ring-[#2B6CB0]/20"
            >
          </label>
          <label for="input-fecha-hasta-dashboard" class="block text-sm font-bold text-[#3D3D3D]">
            Hasta
            <input
              id="input-fecha-hasta-dashboard"
              v-model="filtros.fecha_hasta"
              type="date"
              required
              class="mt-1 h-11 w-full rounded-xl border border-[#E5E0DB] px-3 text-base font-normal outline-none focus:border-[#2B6CB0] focus:ring-2 focus:ring-[#2B6CB0]/20"
            >
          </label>
          <button
            id="btn-aplicar-periodo-dashboard"
            type="submit"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#2B6CB0] px-5 text-sm font-bold text-white hover:bg-[#1E4F80] disabled:cursor-wait disabled:opacity-60"
            :disabled="cargando"
          >
            <LoaderCircle v-if="cargando" class="animate-spin" :size="18" />
            <RefreshCw v-else :size="18" /> Actualizar
          </button>
        </form>
      </section>

      <div v-if="cargando && !dashboard" class="flex justify-center gap-2 py-14 text-sm text-[#6B6B6B]">
        <LoaderCircle class="animate-spin" :size="20" /> Cargando indicadores…
      </div>

      <template v-else-if="dashboard">
        <section
          :class="[
            'rounded-2xl border p-4 sm:p-5',
            saludFinanciera === 'positiva' && 'border-emerald-200 bg-emerald-50',
            saludFinanciera === 'negativa' && 'border-rose-200 bg-rose-50',
            saludFinanciera === 'neutra' && 'border-amber-200 bg-amber-50',
          ]"
        >
          <div class="flex items-start gap-3">
            <TrendingUp v-if="saludFinanciera === 'positiva'" class="mt-0.5 text-emerald-700" :size="22" />
            <TrendingDown v-else-if="saludFinanciera === 'negativa'" class="mt-0.5 text-rose-700" :size="22" />
            <CircleDollarSign v-else class="mt-0.5 text-amber-700" :size="22" />
            <div>
              <h2 class="font-black text-[#3D3D3D]">Salud financiera: {{ saludFinanciera }}</h2>
              <p class="mt-1 text-sm text-[#3D3D3D]">{{ mensajeSaludFinanciera }}</p>
              <p class="mt-1 text-xs text-[#6B6B6B]">
                Periodo: {{ formatearFecha(dashboard.periodo.fecha_desde) }} a {{ formatearFecha(dashboard.periodo.fecha_hasta) }}
              </p>
            </div>
          </div>
        </section>

        <section class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4" aria-label="KPIs financieros">
          <article class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between gap-3">
              <span class="text-sm font-bold text-[#6B6B6B]">Ingresos</span>
              <BadgeDollarSign class="text-emerald-700" :size="22" />
            </div>
            <p class="mt-3 text-2xl font-black text-[#3D3D3D]">{{ formatearMoneda(dashboard.kpis.ingresos) }}</p>
            <p class="mt-1 text-xs text-[#6B6B6B]">{{ dashboard.kpis.ventas_registradas }} ventas registradas</p>
          </article>

          <article class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between gap-3">
              <span class="text-sm font-bold text-[#6B6B6B]">Gastos netos</span>
              <ShoppingBag class="text-[#C24E12]" :size="22" />
            </div>
            <p class="mt-3 text-2xl font-black text-[#3D3D3D]">{{ formatearMoneda(dashboard.kpis.gastos_netos) }}</p>
            <p class="mt-1 text-xs text-[#6B6B6B]">{{ dashboard.kpis.compras_registradas }} compras registradas</p>
          </article>

          <article class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between gap-3">
              <span class="text-sm font-bold text-[#6B6B6B]">Utilidad bruta</span>
              <TrendingUp class="text-[#2B6CB0]" :size="22" />
            </div>
            <p class="mt-3 text-2xl font-black text-[#3D3D3D]">{{ formatearMoneda(dashboard.kpis.utilidad_bruta) }}</p>
            <p class="mt-1 text-xs text-[#6B6B6B]">Ingresos menos gastos netos</p>
          </article>

          <article class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between gap-3">
              <span class="text-sm font-bold text-[#6B6B6B]">Margen bruto</span>
              <ReceiptText class="text-violet-700" :size="22" />
            </div>
            <p class="mt-3 text-2xl font-black text-[#3D3D3D]">{{ formatearPorcentaje(dashboard.kpis.margen_bruto_porcentaje) }}</p>
            <p class="mt-1 text-xs text-[#6B6B6B]">Utilidad bruta / ingresos</p>
          </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-2" aria-label="Gráficas financieras interactivas">
          <article id="grafico-ventas-mensuales" class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h2 class="font-black text-[#3D3D3D]">Ventas mensuales</h2>
                <p class="mt-1 text-xs text-[#6B6B6B]">Últimos seis meses. Selecciona una barra para inspeccionar el importe.</p>
              </div>
              <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-[#2B6CB0]">Interactivo</span>
            </div>

            <div v-if="ventasMensuales.length" class="mt-5">
              <div class="flex h-56 items-end gap-2 border-b border-[#E5E0DB] px-1 sm:gap-3" role="list" aria-label="Barras de ventas por mes">
                <button
                  v-for="venta in ventasMensuales"
                  :id="`barra-venta-${venta.periodo}`"
                  :key="venta.periodo"
                  type="button"
                  role="listitem"
                  class="group flex h-full min-w-0 flex-1 flex-col justify-end rounded-t-lg px-0.5 outline-none focus-visible:ring-2 focus-visible:ring-[#2B6CB0]"
                  :aria-label="`${venta.etiqueta}: ${formatearMoneda(venta.total)}`"
                  :aria-pressed="detalleBarraActiva?.periodo === venta.periodo"
                  @click="seleccionarBarra(venta)"
                  @focus="seleccionarBarra(venta)"
                  @mouseenter="seleccionarBarra(venta)"
                >
                  <span
                    class="w-full rounded-t-lg bg-[#2B6CB0] transition-all duration-300 group-hover:bg-[#1E4F80] group-focus-visible:bg-[#1E4F80]"
                    :class="detalleBarraActiva?.periodo === venta.periodo && 'ring-2 ring-[#1E4F80] ring-offset-2'"
                    :style="{ height: obtenerAlturaBarra(venta) }"
                  />
                  <span class="mt-2 truncate text-center text-[11px] font-bold text-[#6B6B6B]">{{ venta.etiqueta }}</span>
                </button>
              </div>

              <output v-if="detalleBarraActiva" class="mt-4 block rounded-xl bg-[#FAF7F4] p-3 text-sm text-[#3D3D3D]" aria-live="polite">
                <span class="font-bold">{{ detalleBarraActiva.etiqueta }}:</span>
                {{ formatearMoneda(detalleBarraActiva.total) }} en ventas.
              </output>
            </div>
            <p v-else class="mt-5 rounded-xl bg-[#FAF7F4] p-4 text-sm text-[#6B6B6B]">Aún no hay ventas para graficar.</p>
          </article>

          <article id="grafico-rentabilidad-platillos" class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h2 class="font-black text-[#3D3D3D]">Rentabilidad por platillo</h2>
                <p class="mt-1 text-xs text-[#6B6B6B]">Estimación proporcional a los ingresos del periodo.</p>
              </div>
              <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-800">Estimado</span>
            </div>

            <div v-if="segmentosRentabilidad.length" class="mt-5 grid items-center gap-5 sm:grid-cols-[12rem_minmax(0,1fr)]">
              <div class="relative mx-auto h-48 w-48">
                <svg class="h-full w-full -rotate-90" viewBox="0 0 42 42" role="img" aria-label="Dona de rentabilidad estimada por platillo">
                  <circle cx="21" cy="21" r="15.9155" fill="none" stroke="#EDE8E3" stroke-width="6" />
                  <circle
                    v-for="segmento in segmentosRentabilidad"
                    :key="segmento.platillo_id"
                    :id="`segmento-rentabilidad-${segmento.platillo_id}`"
                    cx="21"
                    cy="21"
                    r="15.9155"
                    fill="none"
                    :stroke="segmento.color"
                    stroke-width="6"
                    pathLength="100"
                    class="cursor-pointer transition-[stroke-width] duration-200 focus:outline-none"
                    :class="detalleSegmentoActivo?.platillo_id === segmento.platillo_id ? 'stroke-[7]' : 'hover:stroke-[7]'"
                    :stroke-dasharray="`${segmento.porcentaje} ${100 - segmento.porcentaje}`"
                    :stroke-dashoffset="segmento.desplazamiento"
                    role="button"
                    tabindex="0"
                    :aria-label="`${segmento.nombre}: ${formatearMoneda(segmento.utilidad_estimada)} de utilidad estimada`"
                    @click="seleccionarSegmento(segmento)"
                    @focus="seleccionarSegmento(segmento)"
                    @mouseenter="seleccionarSegmento(segmento)"
                    @keydown.enter.prevent="seleccionarSegmento(segmento)"
                    @keydown.space.prevent="seleccionarSegmento(segmento)"
                  />
                </svg>
                <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                  <span class="text-[10px] font-bold uppercase tracking-wide text-[#6B6B6B]">Utilidad</span>
                  <span class="max-w-28 text-sm font-black text-[#3D3D3D]">{{ formatearMoneda(dashboard.kpis.utilidad_bruta) }}</span>
                </div>
              </div>

              <div class="min-w-0 space-y-2" role="list" aria-label="Leyenda de rentabilidad por platillo">
                <button
                  v-for="segmento in segmentosRentabilidad"
                  :id="`leyenda-rentabilidad-${segmento.platillo_id}`"
                  :key="segmento.platillo_id"
                  type="button"
                  role="listitem"
                  class="flex w-full items-center gap-2 rounded-lg p-2 text-left outline-none transition hover:bg-[#FAF7F4] focus-visible:ring-2 focus-visible:ring-[#2B6CB0]"
                  :class="detalleSegmentoActivo?.platillo_id === segmento.platillo_id && 'bg-[#FAF7F4]'"
                  :aria-pressed="detalleSegmentoActivo?.platillo_id === segmento.platillo_id"
                  @click="seleccionarSegmento(segmento)"
                  @focus="seleccionarSegmento(segmento)"
                >
                  <span class="h-3 w-3 shrink-0 rounded-full" :style="{ backgroundColor: segmento.color }" />
                  <span class="min-w-0 flex-1 truncate text-sm font-bold text-[#3D3D3D]">{{ segmento.nombre }}</span>
                  <span class="text-xs font-bold text-[#6B6B6B]">{{ segmento.porcentaje.toFixed(0) }}%</span>
                </button>
              </div>
            </div>

            <output v-if="detalleSegmentoActivo" class="mt-4 block rounded-xl bg-[#FAF7F4] p-3 text-sm text-[#3D3D3D]" aria-live="polite">
              <span class="font-bold">{{ detalleSegmentoActivo.nombre }}:</span>
              {{ detalleSegmentoActivo.unidades }} unidades, {{ formatearMoneda(detalleSegmentoActivo.ingresos) }} en ingresos y
              {{ formatearMoneda(detalleSegmentoActivo.utilidad_estimada) }} de utilidad estimada.
            </output>
            <p v-else class="mt-5 rounded-xl bg-[#FAF7F4] p-4 text-sm text-[#6B6B6B]">Aún no hay platillos pagados en el periodo para estimar rentabilidad.</p>

            <p class="mt-4 text-xs leading-5 text-[#6B6B6B]">
              La dona distribuye la utilidad bruta global según la participación de ingresos. Es una estimación porque aún no se registran costos de receta por platillo.
            </p>
          </article>
        </section>
      </template>
    </div>
  </main>
</template>
