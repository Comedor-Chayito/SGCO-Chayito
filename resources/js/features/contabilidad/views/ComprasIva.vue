<script setup>
/**
 * Registro de compras con desglose automático de costo neto e IVA crédito fiscal.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 */
import { onMounted, ref } from 'vue';
import { ArrowLeft, Calculator, Landmark, LoaderCircle, ReceiptText, RefreshCw } from '@lucide/vue';
import { Toaster, toast } from 'vue-sonner';
import { useRouter } from 'vue-router';
import { obtenerCompras, registrarCompra } from '@/features/contabilidad/services/compraService.js';

const router = useRouter();
const compras = ref([]);
const cargando = ref(true);
const guardando = ref(false);
const formulario = ref(crearFormulario());

/**
 * Crea el estado inicial del formulario con la fecha local actual.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 *
 * @returns {{ fecha_compra: string, monto_total: string }} Campos iniciales de la compra.
 */
function crearFormulario() {
  return {
    fecha_compra: new Date().toLocaleDateString('en-CA'),
    monto_total: '',
  };
}

/**
 * Consulta las compras registradas y sus valores tributarios persistidos.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 */
async function cargarCompras() {
  cargando.value = true;

  try {
    compras.value = await obtenerCompras();
  } catch (error) {
    toast.error(obtenerMensajeError(error, 'No se pudieron cargar las compras.'));
  } finally {
    cargando.value = false;
  }
}

/**
 * Envía el monto total; el API calcula costo neto e IVA para evitar manipulaciones del cliente.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 */
async function guardarCompra() {
  if (!formulario.value.fecha_compra || Number(formulario.value.monto_total) <= 0) {
    toast.error('Ingresa una fecha y un monto total mayor que cero.');
    return;
  }

  guardando.value = true;

  try {
    const compra = await registrarCompra({
      fecha_compra: formulario.value.fecha_compra,
      monto_total: Number(formulario.value.monto_total),
    });
    compras.value.unshift(compra);
    formulario.value = crearFormulario();
    toast.success('Compra registrada con costo neto e IVA crédito fiscal.');
  } catch (error) {
    toast.error(obtenerMensajeError(error, 'No se pudo registrar la compra.'));
  } finally {
    guardando.value = false;
  }
}

/**
 * Formatea importes de la base como moneda de dos decimales.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 *
 * @param {string|number} monto Importe a mostrar.
 * @returns {string} Valor monetario en dólares estadounidenses.
 */
function formatearMoneda(monto) {
  return new Intl.NumberFormat('es-SV', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
  }).format(Number(monto));
}

/**
 * Formatea la fecha contable sin alterar el día por diferencias horarias.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 *
 * @param {string} fecha Fecha ISO almacenada por el API.
 * @returns {string} Fecha legible para la tabla de compras.
 */
function formatearFecha(fecha) {
  const [anio, mes, dia] = fecha.split('-').map(Number);
  return new Intl.DateTimeFormat('es-SV', { dateStyle: 'medium' }).format(new Date(anio, mes - 1, dia));
}

/**
 * Extrae el primer error de validación devuelto por el contrato de la API.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-006 / CC-66 / CC-67
 *
 * @param {unknown} error Error recibido en una petición HTTP.
 * @param {string} mensajePredeterminado Texto usado cuando no hay detalle del API.
 * @returns {string} Mensaje apto para mostrar a la persona usuaria.
 */
function obtenerMensajeError(error, mensajePredeterminado) {
  const errores = error?.response?.data?.errors;
  return (errores ? Object.values(errores).flat()[0] : null)
    || error?.response?.data?.message
    || mensajePredeterminado;
}

onMounted(cargarCompras);
</script>

<template>
  <main id="vista-compras-iva" class="min-h-[100dvh] bg-[#FAF7F4] p-4 sm:p-6">
    <Toaster rich-colors position="top-center" />

    <div class="mx-auto max-w-5xl space-y-5">
      <header class="flex flex-wrap items-center gap-3 rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
        <button
          id="btn-volver-inicio-contabilidad"
          type="button"
          class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] text-[#3D3D3D] hover:bg-[#FAF7F4]"
          aria-label="Volver al inicio"
          @click="router.push('/')"
        >
          <ArrowLeft :size="20" />
        </button>
        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
          <Landmark :size="22" />
        </div>
        <div class="min-w-0 flex-1">
          <h1 class="text-lg font-black text-[#3D3D3D]">Compras e IVA crédito fiscal</h1>
          <p class="text-xs text-[#6B6B6B]">Desglose automático conforme a la tasa del 13%.</p>
        </div>
        <button
          id="btn-recargar-compras"
          type="button"
          class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] text-[#3D3D3D] hover:bg-[#FAF7F4] disabled:cursor-wait"
          :disabled="cargando || guardando"
          aria-label="Recargar compras"
          @click="cargarCompras"
        >
          <RefreshCw :size="19" :class="{ 'animate-spin': cargando }" />
        </button>
      </header>

      <section class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950">
        <div class="flex items-start gap-3">
          <Calculator class="mt-0.5 shrink-0 text-emerald-700" :size="20" />
          <p>
            El sistema calcula y guarda automáticamente <strong>costo neto = monto total / 1.13</strong>
            e <strong>IVA crédito fiscal = costo neto × 0.13</strong>.
          </p>
        </div>
      </section>

      <section class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
        <div class="flex items-center gap-2">
          <ReceiptText class="text-[#F26A21]" :size="21" />
          <h2 class="font-black text-[#3D3D3D]">Registrar compra</h2>
        </div>
        <p class="mt-1 text-sm text-[#6B6B6B]">Ingresa el total con IVA incluido; los demás valores no se digitan.</p>

        <form class="mt-4 grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto] sm:items-end" @submit.prevent="guardarCompra">
          <label for="input-fecha-compra" class="block text-sm font-bold text-[#3D3D3D]">
            Fecha de compra
            <input
              id="input-fecha-compra"
              v-model="formulario.fecha_compra"
              type="date"
              required
              class="mt-1 h-12 w-full rounded-xl border border-[#E5E0DB] bg-white px-3 text-base font-normal outline-none focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
            >
          </label>
          <label for="input-monto-total-compra" class="block text-sm font-bold text-[#3D3D3D]">
            Monto total (IVA incluido)
            <input
              id="input-monto-total-compra"
              v-model="formulario.monto_total"
              type="number"
              min="0.01"
              max="99999999.99"
              step="0.01"
              inputmode="decimal"
              required
              placeholder="0.00"
              class="mt-1 h-12 w-full rounded-xl border border-[#E5E0DB] bg-white px-3 text-base font-normal outline-none focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
            >
          </label>
          <button
            id="btn-registrar-compra"
            type="submit"
            class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[#F26A21] px-5 text-sm font-bold text-white transition hover:bg-[#D95516] disabled:cursor-wait disabled:opacity-60"
            :disabled="guardando"
          >
            <LoaderCircle v-if="guardando" class="animate-spin" :size="18" />
            <ReceiptText v-else :size="18" />
            Registrar y calcular
          </button>
        </form>
      </section>

      <section class="overflow-hidden rounded-2xl border border-[#E5E0DB] bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-[#E5E0DB] p-4 sm:px-5">
          <h2 class="font-black text-[#3D3D3D]">Compras registradas</h2>
          <span class="rounded-full bg-[#FAF7F4] px-2.5 py-1 text-xs font-bold text-[#6B6B6B]">{{ compras.length }} registros</span>
        </div>

        <div v-if="cargando" class="flex justify-center gap-2 p-10 text-sm text-[#6B6B6B]">
          <LoaderCircle class="animate-spin" :size="20" /> Cargando compras…
        </div>
        <div v-else-if="compras.length === 0" class="p-10 text-center text-sm text-[#6B6B6B]">
          Aún no hay compras registradas.
        </div>
        <div v-else class="overflow-x-auto">
          <table class="min-w-full text-left text-sm">
            <thead class="bg-[#FAF7F4] text-xs uppercase tracking-wide text-[#6B6B6B]">
              <tr>
                <th class="px-4 py-3 font-bold sm:px-5">Fecha</th>
                <th class="px-4 py-3 text-right font-bold">Monto total</th>
                <th class="px-4 py-3 text-right font-bold">Costo neto</th>
                <th class="px-4 py-3 text-right font-bold">IVA crédito</th>
                <th class="px-4 py-3 font-bold sm:px-5">Registró</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#E5E0DB]">
              <tr v-for="compra in compras" :key="compra.id" class="text-[#3D3D3D]">
                <td class="whitespace-nowrap px-4 py-3.5 sm:px-5">{{ formatearFecha(compra.fecha_compra) }}</td>
                <td class="whitespace-nowrap px-4 py-3.5 text-right font-medium">{{ formatearMoneda(compra.monto_total) }}</td>
                <td class="whitespace-nowrap px-4 py-3.5 text-right">{{ formatearMoneda(compra.costo_neto) }}</td>
                <td class="whitespace-nowrap px-4 py-3.5 text-right font-bold text-emerald-700">{{ formatearMoneda(compra.iva_credito_fiscal) }}</td>
                <td class="whitespace-nowrap px-4 py-3.5 text-[#6B6B6B] sm:px-5">{{ compra.usuario?.name || 'Usuario' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </main>
</template>
