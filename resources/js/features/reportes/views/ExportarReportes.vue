<script setup>
/**
 * Pantalla para elegir el período y el formato de los reportes consolidados a exportar.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.1.3 / Sec-3.5.1 / CC-78
 */
import { computed, ref } from 'vue';
import { ArrowLeft, Download, FileSpreadsheet, LoaderCircle } from '@lucide/vue';
import { Toaster, toast } from 'vue-sonner';
import { useRouter } from 'vue-router';
import {
  PERIODOS_PREDEFINIDOS, PERIODO_PREDETERMINADO, calcularPeriodo, fechaLocalIso, identificarPeriodo,
} from '@/features/reportes/services/periodoDashboardService.js';
import {
  FORMATOS_EXPORTACION, FORMATO_PREDETERMINADO, descargarExportacion,
  obtenerMensajeErrorExportacion, validarPeriodoExportacion,
} from '@/features/reportes/services/exportacionService.js';
import EncabezadoOperacion from '@/shared/components/EncabezadoOperacion.vue';

const router = useRouter();
const filtros = ref(calcularPeriodo(PERIODO_PREDETERMINADO));
const formato = ref(FORMATO_PREDETERMINADO);
const exportando = ref(false);
const fechaMaxima = fechaLocalIso(new Date());
const periodoActivo = computed(() => identificarPeriodo(filtros.value));
const errorPeriodo = computed(() => validarPeriodoExportacion(filtros.value));
const etiquetaFormato = computed(
  () => FORMATOS_EXPORTACION.find((opcion) => opcion.clave === formato.value)?.etiqueta,
);

/**
 * Aplica un período predefinido al formulario de exportación.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.5.1 / CC-78
 *
 * @param {string} clave Clave del período en PERIODOS_PREDEFINIDOS.
 */
function aplicarPeriodo(clave) {
  filtros.value = calcularPeriodo(clave);
}

/**
 * Valida la selección y descarga el reporte del período en el formato elegido.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.5.1 / CC-78
 */
async function exportar() {
  if (errorPeriodo.value) {
    toast.error(errorPeriodo.value);
    return;
  }

  exportando.value = true;

  try {
    const nombreArchivo = await descargarExportacion(filtros.value, formato.value);
    toast.success(`Reporte descargado: ${nombreArchivo}`);
  } catch (error) {
    toast.error(await obtenerMensajeErrorExportacion(error, 'No se pudo generar la exportación.'));
  } finally {
    exportando.value = false;
  }
}

/**
 * Presenta una fecha ISO como texto legible sin alteración por zona horaria.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.5.1 / CC-78
 *
 * @param   {string} fecha Fecha en formato YYYY-MM-DD.
 * @returns {string} Fecha formateada para el resumen.
 */
function formatearFecha(fecha) {
  const [anio, mes, dia] = fecha.split('-').map(Number);
  return new Intl.DateTimeFormat('es-SV', { dateStyle: 'medium' }).format(new Date(anio, mes - 1, dia));
}
</script>

<template>
  <main id="vista-exportar-reportes" class="min-h-[100dvh] bg-[#FAF7F4]">
    <Toaster rich-colors position="top-center" />

    <EncabezadoOperacion
      titulo="Exportar reportes"
      subtitulo="Elige el período y el formato del reporte consolidado."
      :icono="FileSpreadsheet"
      clase-icono="bg-orange-100 text-[#C24E12]"
    >
      <template #navegacion>
        <button
          id="btn-volver-inicio-exportar"
          type="button"
          class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] text-[#3D3D3D] hover:bg-[#FAF7F4]"
          aria-label="Volver al inicio"
          @click="router.push('/')"
        >
          <ArrowLeft :size="20" />
        </button>
      </template>
    </EncabezadoOperacion>

    <div class="mx-auto max-w-3xl space-y-5 p-4 sm:p-6">

      <form class="space-y-5" @submit.prevent="exportar">
        <section class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
          <h2 class="mb-3 font-black text-[#3D3D3D]">1. Período</h2>

          <div class="mb-3 flex flex-wrap gap-2" role="group" aria-label="Períodos predefinidos">
            <button
              v-for="periodo in PERIODOS_PREDEFINIDOS"
              :id="`btn-exportar-periodo-${periodo.clave}`"
              :key="periodo.clave"
              type="button"
              :aria-pressed="periodoActivo === periodo.clave"
              :class="[
                'inline-flex h-11 items-center rounded-xl border px-4 text-sm font-bold',
                periodoActivo === periodo.clave
                  ? 'border-[#C24E12] bg-[#C24E12] text-white'
                  : 'border-[#E5E0DB] bg-white text-[#3D3D3D] hover:bg-[#FAF7F4]',
              ]"
              @click="aplicarPeriodo(periodo.clave)"
            >
              {{ periodo.etiqueta }}
            </button>
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <label for="input-fecha-desde-exportar" class="block text-sm font-bold text-[#3D3D3D]">
              Desde
              <input
                id="input-fecha-desde-exportar"
                v-model="filtros.fecha_desde"
                type="date"
                :max="filtros.fecha_hasta || fechaMaxima"
                required
                class="mt-1 h-11 w-full rounded-xl border border-[#E5E0DB] px-3 text-base font-normal outline-none focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
              >
            </label>
            <label for="input-fecha-hasta-exportar" class="block text-sm font-bold text-[#3D3D3D]">
              Hasta
              <input
                id="input-fecha-hasta-exportar"
                v-model="filtros.fecha_hasta"
                type="date"
                :min="filtros.fecha_desde"
                :max="fechaMaxima"
                required
                class="mt-1 h-11 w-full rounded-xl border border-[#E5E0DB] px-3 text-base font-normal outline-none focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
              >
            </label>
          </div>

          <p
            v-if="errorPeriodo"
            id="error-periodo-exportar"
            class="mt-3 rounded-xl bg-red-50 p-3 text-sm font-bold text-[#C62828]"
            role="alert"
          >
            {{ errorPeriodo }}
          </p>
        </section>

        <section class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
          <h2 class="mb-3 font-black text-[#3D3D3D]">2. Formato</h2>

          <div class="grid gap-3 sm:grid-cols-2" role="radiogroup" aria-label="Formato del archivo">
            <label
              v-for="opcion in FORMATOS_EXPORTACION"
              :key="opcion.clave"
              :for="`radio-formato-${opcion.clave}`"
              :class="[
                'flex min-h-11 cursor-pointer items-start gap-3 rounded-xl border p-3',
                formato === opcion.clave
                  ? 'border-[#C24E12] bg-orange-50'
                  : 'border-[#E5E0DB] bg-white hover:bg-[#FAF7F4]',
              ]"
            >
              <input
                :id="`radio-formato-${opcion.clave}`"
                v-model="formato"
                type="radio"
                name="formato"
                :value="opcion.clave"
                class="mt-1 h-4 w-4 accent-[#C24E12]"
              >
              <span>
                <span class="block text-sm font-bold text-[#3D3D3D]">{{ opcion.etiqueta }}</span>
                <span class="block text-xs text-[#6B6B6B]">{{ opcion.descripcion }}</span>
              </span>
            </label>
          </div>
        </section>

        <section class="flex flex-col gap-3 rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:p-5">
          <p id="resumen-exportacion" class="min-w-0 flex-1 text-sm text-[#3D3D3D]" aria-live="polite">
            <template v-if="!errorPeriodo">
              Se exportará del <strong>{{ formatearFecha(filtros.fecha_desde) }}</strong>
              al <strong>{{ formatearFecha(filtros.fecha_hasta) }}</strong> en formato
              <strong>{{ etiquetaFormato }}</strong>.
            </template>
            <template v-else>Corrige el período para continuar.</template>
          </p>
          <button
            id="btn-exportar-reporte"
            type="submit"
            class="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#F26A21] px-5 text-sm font-bold text-white hover:bg-[#C24E12] disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="exportando || Boolean(errorPeriodo)"
          >
            <LoaderCircle v-if="exportando" class="animate-spin" :size="18" />
            <Download v-else :size="18" />
            {{ exportando ? 'Generando…' : 'Exportar' }}
          </button>
        </section>
      </form>
    </div>
  </main>
</template>
