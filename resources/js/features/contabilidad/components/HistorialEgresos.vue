<script setup>
/**
 * Historial de egresos con su desglose de IVA crédito fiscal y los totales del mes.
 * Se filtra por mes y subcategoría; los totales cubren todo el filtro, no solo la página.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Contabilidad – RF-CON-006 (CC-69)
 */
import { onMounted, ref, watch } from 'vue';
import { ChevronLeft, ChevronRight, History, Loader2, RotateCw } from '@lucide/vue';
import {
  listarEgresos,
  obtenerSubcategoriasEgreso,
  rangoDelMes,
} from '@/features/contabilidad/services/egresoService.js';

const formatoMoneda = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

/**
 * Devuelve el mes local actual en formato AAAA-MM.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Contabilidad – RF-CON-006 (CC-69)
 *
 * @returns {string} Mes actual.
 */
function mesActual() {
  const hoy = new Date();
  return `${hoy.getFullYear()}-${String(hoy.getMonth() + 1).padStart(2, '0')}`;
}

/**
 * Formatea un monto como moneda en dólares.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Contabilidad – RF-CON-006 (CC-69)
 *
 * @param   {number|string} monto Monto a formatear.
 * @returns {string} Monto con símbolo y dos decimales.
 */
function moneda(monto) {
  return formatoMoneda.format(Number(monto));
}

/**
 * Convierte AAAA-MM-DD a DD/MM/AAAA sin pasar por Date, para no desplazar el día.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Contabilidad – RF-CON-006 (CC-69)
 *
 * @param   {string} fecha Fecha en formato AAAA-MM-DD.
 * @returns {string} Fecha en formato DD/MM/AAAA.
 */
function fechaLegible(fecha) {
  return String(fecha ?? '').split('-').reverse().join('/');
}

// ─── Estado ───
const mes = ref(mesActual());
const subcategoriaId = ref('');
const subcategorias = ref([]);
const egresos = ref([]);
const totales = ref({ cantidad: 0, monto_total: 0, costo_neto: 0, iva_credito_fiscal: 0 });
const paginaActual = ref(1);
const totalPaginas = ref(1);
const cargando = ref(false);
const error = ref('');

/**
 * Consulta una página del historial con el mes y la subcategoría elegidos.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Contabilidad – RF-CON-006 (CC-69)
 *
 * @param   {number} [pagina=1] Número de página.
 * @returns {Promise<void>}
 */
async function cargar(pagina = 1) {
  cargando.value = true;
  error.value = '';

  try {
    const filtros = {
      ...(mes.value ? rangoDelMes(mes.value) : {}),
      subcategoria_egreso_id: subcategoriaId.value,
    };
    const resultado = await listarEgresos(filtros, pagina);

    egresos.value = resultado.egresos.data ?? [];
    paginaActual.value = resultado.egresos.current_page || 1;
    totalPaginas.value = resultado.egresos.last_page || 1;
    totales.value = resultado.totales;
  } catch (err) {
    error.value = err.response?.data?.message ?? 'No se pudo cargar el historial de egresos.';
  } finally {
    cargando.value = false;
  }
}

/**
 * Vuelve a la primera página; se llama al registrar un egreso nuevo.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Contabilidad – RF-CON-006 (CC-69)
 *
 * @returns {Promise<void>}
 */
function recargar() {
  return cargar(1);
}

defineExpose({ recargar });

watch([mes, subcategoriaId], () => cargar(1));

onMounted(async () => {
  cargar(1);

  try {
    subcategorias.value = await obtenerSubcategoriasEgreso();
  } catch {
    // El filtro por subcategoría queda vacío; el historial se sigue mostrando
    subcategorias.value = [];
  }
});
</script>

<template>
  <section
    id="historial-egresos"
    class="rounded-2xl border border-[#E5E0DB] bg-white p-6 shadow-sm"
  >
    <header class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div class="flex items-center gap-3">
        <div
          class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FAF7F4] text-[#C24E12]"
        >
          <History :size="22" aria-hidden="true" />
        </div>
        <div>
          <h2 class="text-lg font-bold text-[#3D3D3D]">Historial de egresos</h2>
          <p class="text-xs text-[#6B6B6B]">
            Desglose de IVA crédito fiscal por factura y totales del mes.
          </p>
        </div>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="space-y-1">
          <label for="filtro-mes" class="text-xs font-semibold text-[#3D3D3D]">Mes</label>
          <input
            id="filtro-mes"
            v-model="mes"
            type="month"
            :max="mesActual()"
            class="min-h-11 w-full rounded-xl border border-[#E5E0DB] bg-white px-3
              text-sm text-[#111111] focus:border-[#F26A21] focus:outline-none"
          />
        </div>
        <div class="space-y-1">
          <label for="filtro-subcategoria" class="text-xs font-semibold text-[#3D3D3D]">
            Subcategoría
          </label>
          <select
            id="filtro-subcategoria"
            v-model="subcategoriaId"
            class="min-h-11 w-full rounded-xl border border-[#E5E0DB] bg-white px-3
              text-sm text-[#111111] focus:border-[#F26A21] focus:outline-none"
          >
            <option value="">Todas</option>
            <option
              v-for="subcategoria in subcategorias"
              :key="subcategoria.id"
              :value="subcategoria.id"
            >
              {{ subcategoria.nombre }}
            </option>
          </select>
        </div>
        <button
          type="button"
          :disabled="cargando"
          class="flex min-h-11 min-w-11 items-center justify-center rounded-xl border
            border-[#E5E0DB] bg-white text-[#3D3D3D] hover:bg-[#FAF7F4] disabled:opacity-50"
          title="Actualizar historial"
          aria-label="Actualizar historial"
          @click="cargar(paginaActual)"
        >
          <RotateCw :size="16" :class="{ 'animate-spin': cargando }" aria-hidden="true" />
        </button>
      </div>
    </header>

    <!-- Reporte mensual: totales de todo el filtro -->
    <dl id="totales-egresos" class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-4">
      <div class="rounded-xl bg-[#FAF7F4] p-4">
        <dt class="text-xs text-[#6B6B6B]">Facturas</dt>
        <dd class="text-xl font-bold text-[#111111]">{{ totales.cantidad }}</dd>
      </div>
      <div class="rounded-xl bg-[#FAF7F4] p-4">
        <dt class="text-xs text-[#6B6B6B]">Monto total</dt>
        <dd class="text-xl font-bold text-[#111111]">{{ moneda(totales.monto_total) }}</dd>
      </div>
      <div class="rounded-xl bg-[#FAF7F4] p-4">
        <dt class="text-xs text-[#6B6B6B]">Costo neto</dt>
        <dd class="text-xl font-bold text-[#111111]">{{ moneda(totales.costo_neto) }}</dd>
      </div>
      <div class="rounded-xl bg-[#FAF7F4] p-4">
        <dt class="text-xs text-[#6B6B6B]">IVA crédito fiscal</dt>
        <dd class="text-xl font-bold text-[#C24E12]">
          {{ moneda(totales.iva_credito_fiscal) }}
        </dd>
      </div>
    </dl>

    <div
      v-if="error"
      class="mb-4 rounded-xl border border-[#C62828]/20 bg-[#C62828]/5 px-4 py-3
        text-sm font-medium text-[#C62828]"
      role="alert"
    >
      {{ error }}
    </div>

    <div class="overflow-x-auto">
      <table class="w-full min-w-[900px] text-left text-sm">
        <thead class="border-b border-[#E5E0DB] text-xs text-[#6B6B6B]">
          <tr>
            <th scope="col" class="py-2 pr-3 font-semibold">Correlativo</th>
            <th scope="col" class="py-2 pr-3 font-semibold">Proveedor</th>
            <th scope="col" class="py-2 pr-3 font-semibold">N.º de factura o CCF</th>
            <th scope="col" class="py-2 pr-3 font-semibold">Subcategoría</th>
            <th scope="col" class="py-2 pr-3 font-semibold">Fecha</th>
            <th scope="col" class="py-2 pr-3 text-right font-semibold">Monto total</th>
            <th scope="col" class="py-2 pr-3 text-right font-semibold">Costo neto</th>
            <th scope="col" class="py-2 text-right font-semibold">IVA crédito</th>
          </tr>
        </thead>
        <tbody class="text-[#3D3D3D]">
          <tr v-if="cargando && !egresos.length">
            <td colspan="8" class="py-8 text-center text-[#6B6B6B]">
              <Loader2 :size="18" class="mr-2 inline animate-spin" aria-hidden="true" />
              Cargando egresos…
            </td>
          </tr>
          <tr v-else-if="!egresos.length">
            <td colspan="8" class="py-8 text-center text-[#6B6B6B]">
              No hay egresos registrados con estos filtros.
            </td>
          </tr>
          <tr
            v-for="egreso in egresos"
            :key="egreso.id"
            class="border-b border-[#E5E0DB] last:border-0"
          >
            <td class="py-2 pr-3 font-semibold text-[#111111]">{{ egreso.correlativo }}</td>
            <td class="py-2 pr-3">{{ egreso.proveedor }}</td>
            <td class="py-2 pr-3">{{ egreso.numero_comprobante }}</td>
            <td class="py-2 pr-3">{{ egreso.subcategoria?.nombre }}</td>
            <td class="py-2 pr-3">{{ fechaLegible(egreso.fecha_comprobante) }}</td>
            <td class="py-2 pr-3 text-right">{{ moneda(egreso.monto_total) }}</td>
            <td class="py-2 pr-3 text-right">{{ moneda(egreso.costo_neto) }}</td>
            <td class="py-2 text-right font-semibold text-[#C24E12]">
              {{ moneda(egreso.iva_credito_fiscal) }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <footer
      v-if="totalPaginas > 1"
      class="mt-4 flex items-center justify-between text-sm text-[#6B6B6B]"
    >
      <span>Página {{ paginaActual }} de {{ totalPaginas }}</span>
      <div class="flex gap-2">
        <button
          type="button"
          :disabled="cargando || paginaActual <= 1"
          class="flex min-h-11 items-center gap-1 rounded-xl border border-[#E5E0DB] px-3
            text-[#3D3D3D] hover:bg-[#FAF7F4] disabled:opacity-50"
          @click="cargar(paginaActual - 1)"
        >
          <ChevronLeft :size="16" aria-hidden="true" />
          Anterior
        </button>
        <button
          type="button"
          :disabled="cargando || paginaActual >= totalPaginas"
          class="flex min-h-11 items-center gap-1 rounded-xl border border-[#E5E0DB] px-3
            text-[#3D3D3D] hover:bg-[#FAF7F4] disabled:opacity-50"
          @click="cargar(paginaActual + 1)"
        >
          Siguiente
          <ChevronRight :size="16" aria-hidden="true" />
        </button>
      </div>
    </footer>
  </section>
</template>
