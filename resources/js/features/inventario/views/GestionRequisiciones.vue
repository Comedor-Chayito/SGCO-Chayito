<script setup>
/**
 * Bandeja administrativa para aprobar requisiciones y transferir stock a áreas operativas.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
 */
import { computed, onMounted, ref } from 'vue';
import {
  ArrowLeft, Check, ClipboardCheck, LoaderCircle, PackageCheck, RefreshCw, Send, X,
} from '@lucide/vue';
import { Toaster, toast } from 'vue-sonner';
import { useRouter } from 'vue-router';
import {
  obtenerRequisiciones,
  resolverRequisicion,
  transferirRequisicion,
} from '@/features/inventario/services/inventarioService.js';

const router = useRouter();
const requisiciones = ref([]);
const cargando = ref(true);
const procesando = ref(false);
const requisicionEnRevision = ref(null);
const cantidadesAprobadas = ref({});

const etiquetasEstado = {
  pendiente: 'Pendiente',
  aprobada: 'Aprobada',
  rechazada: 'Rechazada',
  transferida: 'Transferida',
};

const clasesEstado = {
  pendiente: 'bg-amber-100 text-amber-800',
  aprobada: 'bg-blue-100 text-blue-800',
  rechazada: 'bg-rose-100 text-rose-800',
  transferida: 'bg-emerald-100 text-emerald-800',
};

/**
 * Cuenta las requisiciones que aún requieren una decisión de bodega.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53
 *
 * @returns {number} Total de requisiciones pendientes.
 */
const pendientes = computed(() => requisiciones.value.filter(({ estado }) => estado === 'pendiente').length);

/**
 * Carga la bandeja con las requisiciones y sus detalles desde el API.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
 */
async function cargarRequisiciones() {
  cargando.value = true;

  try {
    requisiciones.value = await obtenerRequisiciones();
  } catch (error) {
    toast.error(obtenerMensajeError(error, 'No se pudieron cargar las requisiciones.'));
  } finally {
    cargando.value = false;
  }
}

/**
 * Abre la revisión e inicializa cada cantidad aprobada con lo solicitado.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53
 *
 * @param {Object} requisicion Requisición pendiente seleccionada.
 */
function iniciarRevision(requisicion) {
  requisicionEnRevision.value = requisicion;
  cantidadesAprobadas.value = Object.fromEntries(
    requisicion.detalles.map((detalle) => [detalle.id, Number(detalle.cantidad_solicitada)]),
  );
}

/**
 * Cierra la sección de revisión y descarta valores que aún no se enviaron.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53
 */
function cancelarRevision() {
  requisicionEnRevision.value = null;
  cantidadesAprobadas.value = {};
}

/**
 * Verifica que ninguna cantidad aprobada exceda lo solicitado ni sea negativa.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53
 *
 * @returns {boolean} True si todas las cantidades se pueden aprobar.
 */
function cantidadesValidas() {
  const detalleInvalido = requisicionEnRevision.value.detalles.some((detalle) => {
    const cantidad = Number(cantidadesAprobadas.value[detalle.id]);
    return !Number.isFinite(cantidad) || cantidad <= 0 || cantidad > Number(detalle.cantidad_solicitada);
  });

  if (detalleInvalido) {
    toast.error('Cada cantidad aprobada debe ser mayor que cero y no superar la solicitada.');
    return false;
  }

  return true;
}

/**
 * Envía la aprobación con las cantidades autorizadas por bodega.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53
 */
async function aprobarRequisicion() {
  if (!requisicionEnRevision.value || !cantidadesValidas()) return;

  procesando.value = true;

  try {
    await resolverRequisicion(requisicionEnRevision.value.id, {
      estado: 'aprobada',
      detalles: requisicionEnRevision.value.detalles.map((detalle) => ({
        id: detalle.id,
        cantidad_aprobada: Number(cantidadesAprobadas.value[detalle.id]),
      })),
    });
    toast.success('Requisición aprobada. Ya está disponible para transferir.');
    cancelarRevision();
    await cargarRequisiciones();
  } catch (error) {
    toast.error(obtenerMensajeError(error, 'No se pudo aprobar la requisición.'));
  } finally {
    procesando.value = false;
  }
}

/**
 * Rechaza la requisición seleccionada sin alterar el inventario de bodega.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53
 */
async function rechazarRequisicion() {
  if (!requisicionEnRevision.value) return;

  procesando.value = true;

  try {
    await resolverRequisicion(requisicionEnRevision.value.id, { estado: 'rechazada' });
    toast.success('Requisición rechazada. El stock de bodega no fue modificado.');
    cancelarRevision();
    await cargarRequisiciones();
  } catch (error) {
    toast.error(obtenerMensajeError(error, 'No se pudo rechazar la requisición.'));
  } finally {
    procesando.value = false;
  }
}

/**
 * Ejecuta la transferencia final y descuenta bodega dentro de una sola operación.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-54
 *
 * @param {Object} requisicion Requisición aprobada que se transferirá.
 */
async function transferirStock(requisicion) {
  procesando.value = true;

  try {
    await transferirRequisicion(requisicion.id);
    toast.success(`Stock transferido al área de ${formatearArea(requisicion.area_solicitante)}.`);
    await cargarRequisiciones();
  } catch (error) {
    toast.error(obtenerMensajeError(error, 'No se pudo transferir el stock.'));
  } finally {
    procesando.value = false;
  }
}

/**
 * Convierte el identificador de área en un texto legible para la interfaz.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
 *
 * @param {string} area Identificador almacenado para el área.
 * @returns {string} Nombre visible del área.
 */
function formatearArea(area) {
  return area.charAt(0).toUpperCase() + area.slice(1);
}

/**
 * Muestra una fecha de resolución de forma compacta y legible.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53
 *
 * @param {string|null} fecha Fecha devuelta por el API.
 * @returns {string} Fecha formateada o una leyenda cuando no existe.
 */
function formatearFecha(fecha) {
  if (!fecha) return 'Aún sin resolver';
  return new Intl.DateTimeFormat('es-SV', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(fecha));
}

/**
 * Obtiene un mensaje útil desde errores de validación o de red del API.
 *
 * @autor  Alexis
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-53 / CC-54
 *
 * @param {unknown} error Error devuelto por la solicitud HTTP.
 * @param {string} mensajePredeterminado Mensaje cuando el API no aporta detalle.
 * @returns {string} Mensaje listo para mostrar al usuario.
 */
function obtenerMensajeError(error, mensajePredeterminado) {
  const errores = error?.response?.data?.errors;
  return (errores ? Object.values(errores).flat()[0] : null)
    || error?.response?.data?.message
    || mensajePredeterminado;
}

onMounted(cargarRequisiciones);
</script>

<template>
  <main id="vista-gestion-requisiciones" class="min-h-[100dvh] bg-[#FAF7F4]">
    <Toaster rich-colors position="top-center" />

    <header class="border-b border-[#E5E0DB] bg-white px-4 py-4 sm:px-6">
      <div class="mx-auto flex max-w-5xl items-center gap-3">
        <button
          id="btn-volver-inicio-requisiciones"
          type="button"
          class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#E5E0DB] text-[#3D3D3D] transition hover:bg-[#FAF7F4]"
          aria-label="Volver al inicio"
          @click="router.push('/')"
        >
          <ArrowLeft :size="20" />
        </button>
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-100 text-[#F26A21]">
          <ClipboardCheck :size="22" />
        </div>
        <div class="min-w-0 flex-1">
          <h1 class="text-lg font-black text-[#3D3D3D]">Gestión de requisiciones</h1>
          <p class="text-xs text-[#6B6B6B]">Aprueba solicitudes y transfiere stock a las áreas.</p>
        </div>
        <button
          id="btn-recargar-requisiciones"
          type="button"
          class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] text-[#3D3D3D] transition hover:bg-[#FAF7F4] disabled:cursor-wait"
          :disabled="cargando || procesando"
          aria-label="Recargar requisiciones"
          @click="cargarRequisiciones"
        >
          <RefreshCw :size="19" :class="{ 'animate-spin': cargando }" />
        </button>
      </div>
    </header>

    <div class="mx-auto max-w-5xl space-y-4 p-4 pb-10 sm:p-6">
      <section class="rounded-2xl border border-orange-100 bg-orange-50 p-4 text-sm text-[#6B6B6B]">
        <strong class="text-[#3D3D3D]">{{ pendientes }}</strong>
        {{ pendientes === 1 ? ' requisición pendiente de aprobación.' : ' requisiciones pendientes de aprobación.' }}
        La transferencia descuenta bodega y acredita el área en una sola operación.
      </section>

      <section
        v-if="requisicionEnRevision"
        id="panel-revision-requisicion"
        class="rounded-2xl border-2 border-[#F26A21] bg-white p-4 shadow-sm sm:p-5"
      >
        <div class="flex flex-wrap items-start justify-between gap-3">
          <div>
            <h2 class="text-base font-black text-[#3D3D3D]">Revisar requisición #{{ requisicionEnRevision.id }}</h2>
            <p class="mt-1 text-sm text-[#6B6B6B]">
              Define cuánto autorizar. Puedes aprobar una cantidad parcial.
            </p>
          </div>
          <button
            id="btn-cancelar-revision"
            type="button"
            class="inline-flex h-10 items-center gap-1 rounded-xl border border-[#E5E0DB] px-3 text-sm font-semibold text-[#3D3D3D] hover:bg-[#FAF7F4]"
            :disabled="procesando"
            @click="cancelarRevision"
          >
            <X :size="17" /> Cancelar
          </button>
        </div>

        <div class="mt-4 space-y-3">
          <label
            v-for="detalle in requisicionEnRevision.detalles"
            :key="detalle.id"
            :for="`input-cantidad-aprobada-${detalle.id}`"
            class="grid gap-2 rounded-xl bg-[#FAF7F4] p-3 sm:grid-cols-[minmax(0,1fr)_10rem] sm:items-center"
          >
            <span>
              <span class="block font-bold text-[#3D3D3D]">{{ detalle.insumo.nombre }}</span>
              <span class="text-xs text-[#6B6B6B]">
                Solicitado: {{ detalle.cantidad_solicitada }} {{ detalle.insumo.unidad_medida }}
                · Bodega: {{ detalle.insumo.stock_actual }}
              </span>
            </span>
            <span>
              <span class="mb-1 block text-xs font-bold text-[#3D3D3D]">Cantidad aprobada</span>
              <input
                :id="`input-cantidad-aprobada-${detalle.id}`"
                v-model="cantidadesAprobadas[detalle.id]"
                type="number"
                min="0.01"
                :max="detalle.cantidad_solicitada"
                step="0.01"
                inputmode="decimal"
                class="h-11 w-full rounded-xl border border-[#E5E0DB] bg-white px-3 text-base text-[#111111] outline-none focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
              >
            </span>
          </label>
        </div>

        <div class="mt-5 grid gap-2 sm:grid-cols-2">
          <button
            :id="`btn-rechazar-requisicion-${requisicionEnRevision.id}`"
            type="button"
            class="inline-flex h-12 items-center justify-center gap-2 rounded-xl border border-rose-200 bg-white px-4 font-bold text-rose-700 transition hover:bg-rose-50 disabled:cursor-wait disabled:opacity-60"
            :disabled="procesando"
            @click="rechazarRequisicion"
          >
            <X :size="18" /> Rechazar
          </button>
          <button
            :id="`btn-aprobar-requisicion-${requisicionEnRevision.id}`"
            type="button"
            class="inline-flex h-12 items-center justify-center gap-2 rounded-xl bg-[#F26A21] px-4 font-bold text-white transition hover:bg-[#D95516] disabled:cursor-wait disabled:opacity-60"
            :disabled="procesando"
            @click="aprobarRequisicion"
          >
            <LoaderCircle v-if="procesando" class="animate-spin" :size="18" />
            <Check v-else :size="18" /> Aprobar cantidades
          </button>
        </div>
      </section>

      <div v-if="cargando" class="flex justify-center gap-2 py-14 text-sm text-[#6B6B6B]">
        <LoaderCircle class="animate-spin" :size="20" /> Cargando requisiciones…
      </div>

      <section v-else-if="requisiciones.length === 0" class="rounded-2xl border border-dashed border-[#E5E0DB] bg-white p-10 text-center text-sm text-[#6B6B6B]">
        No hay requisiciones registradas por el momento.
      </section>

      <section v-else class="space-y-3" aria-label="Listado de requisiciones">
        <article
          v-for="requisicion in requisiciones"
          :key="requisicion.id"
          class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5"
        >
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="flex items-center gap-2">
                <h2 class="font-black text-[#3D3D3D]">Requisición #{{ requisicion.id }}</h2>
                <span :class="['rounded-full px-2.5 py-1 text-xs font-bold', clasesEstado[requisicion.estado]]">
                  {{ etiquetasEstado[requisicion.estado] }}
                </span>
              </div>
              <p class="mt-1 text-sm text-[#6B6B6B]">
                {{ formatearArea(requisicion.area_solicitante) }} · Solicitó {{ requisicion.usuario_solicita?.name || 'Usuario' }}
              </p>
            </div>
            <p class="text-xs text-[#6B6B6B]">{{ formatearFecha(requisicion.fecha_resolucion) }}</p>
          </div>

          <ul class="mt-4 divide-y divide-[#E5E0DB] rounded-xl border border-[#E5E0DB]">
            <li v-for="detalle in requisicion.detalles" :key="detalle.id" class="flex items-center justify-between gap-3 px-3 py-2.5 text-sm">
              <span class="font-medium text-[#3D3D3D]">{{ detalle.insumo.nombre }}</span>
              <span class="shrink-0 text-[#6B6B6B]">
                {{ detalle.cantidad_aprobada ?? detalle.cantidad_solicitada }} / {{ detalle.cantidad_solicitada }} {{ detalle.insumo.unidad_medida }}
              </span>
            </li>
          </ul>

          <p v-if="requisicion.observaciones" class="mt-3 text-sm text-[#6B6B6B]">
            <strong class="text-[#3D3D3D]">Nota:</strong> {{ requisicion.observaciones }}
          </p>

          <div v-if="requisicion.estado === 'pendiente' || requisicion.estado === 'aprobada'" class="mt-4">
            <button
              v-if="requisicion.estado === 'pendiente'"
              :id="`btn-revisar-requisicion-${requisicion.id}`"
              type="button"
              class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-[#3D3D3D] px-4 text-sm font-bold text-white transition hover:bg-[#222222] sm:w-auto"
              :disabled="procesando"
              @click="iniciarRevision(requisicion)"
            >
              <ClipboardCheck :size="18" /> Revisar solicitud
            </button>
            <button
              v-else
              :id="`btn-transferir-requisicion-${requisicion.id}`"
              type="button"
              class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 px-4 text-sm font-bold text-white transition hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60 sm:w-auto"
              :disabled="procesando"
              @click="transferirStock(requisicion)"
            >
              <LoaderCircle v-if="procesando" class="animate-spin" :size="18" />
              <Send v-else :size="18" /> Transferir a {{ formatearArea(requisicion.area_solicitante) }}
            </button>
          </div>
          <p v-if="requisicion.estado === 'transferida'" class="mt-4 inline-flex items-center gap-2 text-sm font-semibold text-emerald-700">
            <PackageCheck :size="18" /> Stock transferido al área operativa.
          </p>
        </article>
      </section>
    </div>
  </main>
</template>
