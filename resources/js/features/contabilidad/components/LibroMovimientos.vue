<script setup>
/**
 * Libro de movimientos: ventas cobradas (ingresos) y egresos registrados en una sola tabla,
 * con totales de ingresos, egresos y flujo neto de todo el filtro (no solo de la página).
 * Se filtra por mes y por tipo de movimiento.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Contabilidad – RF-CON-006
 */
import { onMounted, ref, watch } from 'vue';
import {
  ArrowDownCircle,
  ArrowUpCircle,
  BookOpen,
  ChevronLeft,
  ChevronRight,
  Loader2,
  RotateCw,
} from '@lucide/vue';
import { rangoDelMes } from '@/features/contabilidad/services/egresoService.js';
import {
  listarMovimientosContables,
} from '@/features/contabilidad/services/movimientoContableService.js';

const formatoMoneda = new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' });

// Presentación de cada tipo: etiqueta, icono y color; la etiqueta siempre acompaña al color
const TIPOS = {
  ingreso: { etiqueta: 'Ingreso', icono: ArrowUpCircle, clase: 'bg-emerald-50 text-[#2E7D4F]' },
  egreso: { etiqueta: 'Egreso', icono: ArrowDownCircle, clase: 'bg-red-50 text-[#C62828]' },
};

/**
 * Devuelve el mes local actual en formato AAAA-MM.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Contabilidad – RF-CON-006
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
 * @fecha  2026-11-18
 * @módulo Contabilidad – RF-CON-006
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
 * @fecha  2026-11-18
 * @módulo Contabilidad – RF-CON-006
 *
 * @param   {string} fecha Fecha en formato AAAA-MM-DD.
 * @returns {string} Fecha en formato DD/MM/AAAA.
 */
function fechaLegible(fecha) {
  return String(fecha ?? '').split('-').reverse().join('/');
}

// ─── Estado ───
const mes = ref(mesActual());
const tipo = ref('');
const movimientos = ref([]);
const totales = ref({
  cantidad_ingresos: 0,
  cantidad_egresos: 0,
  total_ingresos: 0,
  total_egresos: 0,
  flujo_neto: 0,
});
const paginaActual = ref(1);
const totalPaginas = ref(1);
const cargando = ref(false);
const error = ref('');

/**
 * Consulta una página del libro con el mes y el tipo elegidos.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Contabilidad – RF-CON-006
 *
 * @param   {number} [pagina=1] Número de página.
 * @returns {Promise<void>}
 */
async function cargar(pagina = 1) {
  cargando.value = true;
  error.value = '';

  try {
    const filtros = { ...(mes.value ? rangoDelMes(mes.value) : {}), tipo: tipo.value };
    const resultado = await listarMovimientosContables(filtros, pagina);

    movimientos.value = resultado.movimientos.data ?? [];
    paginaActual.value = resultado.movimientos.current_page || 1;
    totalPaginas.value = resultado.movimientos.last_page || 1;
    totales.value = resultado.totales;
  } catch (err) {
    error.value = err.response?.data?.message ?? 'No se pudo cargar el libro de movimientos.';
  } finally {
    cargando.value = false;
  }
}

/**
 * Vuelve a la primera página; se llama al registrar un egreso nuevo.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Contabilidad – RF-CON-006
 *
 * @returns {Promise<void>}
 */
function recargar() {
  return cargar(1);
}

defineExpose({ recargar });

watch([mes, tipo], () => cargar(1));

onMounted(() => cargar(1));
</script>

<template>
  <section
    id="libro-movimientos"
    class="rounded-2xl border border-[#E5E0DB] bg-white p-6 shadow-sm"
  >
    <header class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div class="flex items-center gap-3">
        <div
          class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FAF7F4] text-[#C24E12]"
        >
          <BookOpen :size="22" aria-hidden="true" />
        </div>
        <div>
          <h2 class="text-lg font-bold text-[#3D3D3D]">Libro de movimientos</h2>
          <p class="text-xs text-[#6B6B6B]">
            Ventas cobradas y egresos registrados del período, con su flujo neto.
          </p>
        </div>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
        <div class="space-y-1">
          <label for="filtro-mes-libro" class="text-xs font-semibold text-[#3D3D3D]">Mes</label>
          <input
            id="filtro-mes-libro"
            v-model="mes"
            type="month"
            :max="mesActual()"
            class="min-h-11 w-full rounded-xl border border-[#E5E0DB] bg-white px-3
              text-sm text-[#111111] focus:border-[#F26A21] focus:outline-none"
          />
        </div>
        <div class="space-y-1">
          <label for="filtro-tipo-libro" class="text-xs font-semibold text-[#3D3D3D]">Tipo</label>
          <select
            id="filtro-tipo-libro"
            v-model="tipo"
            class="min-h-11 w-full rounded-xl border border-[#E5E0DB] bg-white px-3
              text-sm text-[#111111] focus:border-[#F26A21] focus:outline-none"
          >
            <option value="">Todos</option>
            <option value="ingreso">Ingresos</option>
            <option value="egreso">Egresos</option>
          </select>
        </div>
        <button
          type="button"
          :disabled="cargando"
          class="flex min-h-11 min-w-11 items-center justify-center rounded-xl border
            border-[#E5E0DB] bg-white text-[#3D3D3D] hover:bg-[#FAF7F4] disabled:opacity-50"
          title="Actualizar libro"
          aria-label="Actualizar libro"
          @click="cargar(paginaActual)"
        >
          <RotateCw :size="16" :class="{ 'animate-spin': cargando }" aria-hidden="true" />
        </button>
      </div>
    </header>

    <!-- Totales de todo el filtro -->
    <dl id="totales-libro" class="mb-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
      <div class="rounded-xl bg-[#FAF7F4] p-4">
        <dt class="text-xs text-[#6B6B6B]">
          Ingresos ({{ totales.cantidad_ingresos }} ventas)
        </dt>
        <dd class="text-xl font-bold text-[#2E7D4F]">{{ moneda(totales.total_ingresos) }}</dd>
      </div>
      <div class="rounded-xl bg-[#FAF7F4] p-4">
        <dt class="text-xs text-[#6B6B6B]">
          Egresos ({{ totales.cantidad_egresos }} facturas)
        </dt>
        <dd class="text-xl font-bold text-[#C62828]">{{ moneda(totales.total_egresos) }}</dd>
      </div>
      <div class="rounded-xl bg-[#FAF7F4] p-4">
        <dt class="text-xs text-[#6B6B6B]">Flujo neto (ingresos − egresos, con IVA)</dt>
        <dd
          id="flujo-neto"
          class="text-xl font-bold"
          :class="totales.flujo_neto < 0 ? 'text-[#C62828]' : 'text-[#111111]'"
        >
          {{ moneda(totales.flujo_neto) }}
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
      <table class="w-full min-w-[800px] text-left text-sm">
        <thead class="border-b border-[#E5E0DB] text-xs text-[#6B6B6B]">
          <tr>
            <th scope="col" class="py-2 pr-3 font-semibold">Fecha</th>
            <th scope="col" class="py-2 pr-3 font-semibold">Tipo</th>
            <th scope="col" class="py-2 pr-3 font-semibold">Referencia</th>
            <th scope="col" class="py-2 pr-3 font-semibold">Concepto</th>
            <th scope="col" class="py-2 pr-3 font-semibold">Detalle</th>
            <th scope="col" class="py-2 text-right font-semibold">Monto</th>
          </tr>
        </thead>
        <tbody class="text-[#3D3D3D]">
          <tr v-if="cargando && !movimientos.length">
            <td colspan="6" class="py-8 text-center text-[#6B6B6B]">
              <Loader2 :size="18" class="mr-2 inline animate-spin" aria-hidden="true" />
              Cargando movimientos…
            </td>
          </tr>
          <tr v-else-if="!movimientos.length">
            <td colspan="6" class="py-8 text-center text-[#6B6B6B]">
              No hay movimientos con estos filtros.
            </td>
          </tr>
          <tr
            v-for="movimiento in movimientos"
            :key="`${movimiento.tipo}-${movimiento.origen_id}`"
            class="border-b border-[#E5E0DB] last:border-0"
          >
            <td class="py-2 pr-3">{{ fechaLegible(movimiento.fecha) }}</td>
            <td class="py-2 pr-3">
              <span
                class="inline-flex items-center gap-1 rounded-full px-2 py-0.5
                  text-xs font-semibold"
                :class="TIPOS[movimiento.tipo].clase"
              >
                <component :is="TIPOS[movimiento.tipo].icono" :size="14" aria-hidden="true" />
                {{ TIPOS[movimiento.tipo].etiqueta }}
              </span>
            </td>
            <td class="py-2 pr-3 font-semibold text-[#111111]">{{ movimiento.referencia }}</td>
            <td class="py-2 pr-3">{{ movimiento.concepto }}</td>
            <td class="py-2 pr-3 text-[#6B6B6B]">{{ movimiento.detalle }}</td>
            <td
              class="py-2 text-right font-semibold"
              :class="movimiento.tipo === 'ingreso' ? 'text-[#2E7D4F]' : 'text-[#C62828]'"
            >
              {{ movimiento.tipo === 'ingreso' ? '+' : '−' }}{{ moneda(movimiento.monto) }}
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
