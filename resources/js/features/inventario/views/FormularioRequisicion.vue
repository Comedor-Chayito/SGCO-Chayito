<script setup>
/**
 * Formulario móvil para solicitar insumos de bodega por área operativa.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 */
import { onMounted, ref } from 'vue';
import { ArrowLeft, ClipboardPlus, LoaderCircle, PackagePlus, Plus, Trash2 } from '@lucide/vue';
import { Toaster, toast } from 'vue-sonner';
import { useRouter } from 'vue-router';
import { crearRequisicion, obtenerInsumos } from '@/features/inventario/services/inventarioService.js';

const router = useRouter();
const insumos = ref([]);
const cargandoInsumos = ref(true);
const enviando = ref(false);
const detalles = ref([crearDetalleVacio()]);
const form = ref({
  area_solicitante: '',
  observaciones: '',
});

const AREAS = [
  { valor: 'cocina', etiqueta: 'Cocina' },
  { valor: 'servicio', etiqueta: 'Servicio' },
  { valor: 'despacho', etiqueta: 'Despacho' },
];

/**
 * Crea un renglón vacío para seleccionar un insumo y su cantidad solicitada.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 *
 * @returns {{ insumo_id: string, cantidad_solicitada: string }} Renglón inicial del formulario.
 */
function crearDetalleVacio() {
  return { insumo_id: '', cantidad_solicitada: '' };
}

/**
 * Carga los insumos disponibles para construir los renglones de la requisición.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 */
async function cargarInsumos() {
  cargandoInsumos.value = true;

  try {
    insumos.value = await obtenerInsumos();
  } catch {
    toast.error('No se pudieron cargar los insumos de bodega.');
  } finally {
    cargandoInsumos.value = false;
  }
}

/**
 * Agrega un nuevo renglón de insumo al formulario.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 */
function agregarDetalle() {
  detalles.value.push(crearDetalleVacio());
}

/**
 * Elimina un renglón de insumo, manteniendo siempre al menos uno visible.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 *
 * @param {number} indice Posición del renglón que se eliminará.
 */
function eliminarDetalle(indice) {
  if (detalles.value.length === 1) {
    detalles.value[0] = crearDetalleVacio();
    return;
  }

  detalles.value.splice(indice, 1);
}

/**
 * Determina si un insumo ya fue seleccionado en otro renglón del formulario.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 *
 * @param {number} insumoId Identificador del insumo a evaluar.
 * @param {number} indiceActual Índice del selector que se está renderizando.
 * @returns {boolean} True cuando el insumo ya está usado por otro renglón.
 */
function insumoYaSeleccionado(insumoId, indiceActual) {
  return detalles.value.some((detalle, indice) => (
    indice !== indiceActual && Number(detalle.insumo_id) === Number(insumoId)
  ));
}

/**
 * Busca el insumo de bodega correspondiente al valor elegido en un renglón.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 *
 * @param {{ insumo_id: string }} detalle Renglón de requisición seleccionado.
 * @returns {Object|undefined} Insumo encontrado o undefined si aún no se selecciona.
 */
function obtenerInsumoDetalle(detalle) {
  return insumos.value.find((insumo) => Number(insumo.id) === Number(detalle.insumo_id));
}

/**
 * Reinicia el formulario después de enviar una requisición correctamente.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 */
function reiniciarFormulario() {
  form.value = { area_solicitante: '', observaciones: '' };
  detalles.value = [crearDetalleVacio()];
}

/**
 * Obtiene el primer mensaje de validación que devuelve el API.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 *
 * @param {unknown} error Error recibido durante el envío HTTP.
 * @returns {string} Mensaje apto para mostrar al usuario.
 */
function obtenerMensajeError(error) {
  const errores = error?.response?.data?.errors;
  const primerError = errores ? Object.values(errores).flat()[0] : null;

  return primerError || error?.response?.data?.message || 'No se pudo enviar la requisición.';
}

/**
 * Valida los campos esenciales antes de enviar la requisición al API.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 *
 * @returns {boolean} True cuando existe área, insumos y cantidades válidas.
 */
function formularioValido() {
  if (!form.value.area_solicitante) {
    toast.error('Selecciona el área que solicita los insumos.');
    return false;
  }

  const detalleInvalido = detalles.value.some((detalle) => (
    !detalle.insumo_id || Number(detalle.cantidad_solicitada) <= 0
  ));

  if (detalleInvalido) {
    toast.error('Selecciona un insumo y una cantidad mayor que cero en cada renglón.');
    return false;
  }

  return true;
}

/**
 * Envía la solicitud de insumos y conserva el stock intacto hasta la aprobación.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-22
 * @módulo Inventario – RF-INV-004 / CC-52
 */
async function enviarRequisicion() {
  if (!formularioValido()) return;

  enviando.value = true;

  try {
    const requisicion = await crearRequisicion({
      area_solicitante: form.value.area_solicitante,
      observaciones: form.value.observaciones.trim() || undefined,
      detalles: detalles.value.map((detalle) => ({
        insumo_id: Number(detalle.insumo_id),
        cantidad_solicitada: Number(detalle.cantidad_solicitada),
      })),
    });

    toast.success(`Requisición #${requisicion.id} enviada para aprobación.`);
    reiniciarFormulario();
  } catch (error) {
    toast.error(obtenerMensajeError(error));
  } finally {
    enviando.value = false;
  }
}

onMounted(cargarInsumos);
</script>

<template>
  <main class="min-h-[100dvh] bg-[#FAF7F4]" id="vista-formulario-requisicion">
    <Toaster rich-colors position="top-center" />

    <header class="border-b border-[#E5E0DB] bg-white px-4 py-4 sm:px-6">
      <div class="mx-auto flex max-w-3xl items-center gap-3">
        <button
          id="btn-volver-inventario"
          type="button"
          class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-[#E5E0DB] text-[#3D3D3D] transition hover:bg-[#FAF7F4]"
          aria-label="Volver a inventario"
          @click="router.push('/inventario')"
        >
          <ArrowLeft :size="20" />
        </button>
        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-orange-100 text-[#F26A21]">
          <ClipboardPlus :size="22" />
        </div>
        <div>
          <h1 class="text-lg font-black text-[#3D3D3D]">Nueva requisición</h1>
          <p class="text-xs text-[#6B6B6B]">Solicita insumos a bodega desde tu área.</p>
        </div>
      </div>
    </header>

    <form class="mx-auto max-w-3xl space-y-4 p-4 pb-10 sm:p-6" @submit.prevent="enviarRequisicion">
      <section class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
        <h2 class="text-base font-bold text-[#3D3D3D]">Datos de la solicitud</h2>

        <label for="select-area-solicitante" class="mt-4 block text-sm font-bold text-[#3D3D3D]">
          Área solicitante
        </label>
        <select
          id="select-area-solicitante"
          v-model="form.area_solicitante"
          required
          class="mt-1 h-12 w-full rounded-xl border border-[#E5E0DB] bg-white px-3 text-base text-[#111111] outline-none focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
        >
          <option value="" disabled>Selecciona un área</option>
          <option v-for="area in AREAS" :key="area.valor" :value="area.valor">{{ area.etiqueta }}</option>
        </select>

        <label for="input-observaciones" class="mt-4 block text-sm font-bold text-[#3D3D3D]">
          Observaciones <span class="font-normal text-[#6B6B6B]">(opcional)</span>
        </label>
        <textarea
          id="input-observaciones"
          v-model="form.observaciones"
          maxlength="1000"
          rows="3"
          placeholder="Ej.: Preparación para el turno de almuerzo."
          class="mt-1 w-full resize-y rounded-xl border border-[#E5E0DB] px-3 py-2.5 text-base text-[#111111] outline-none placeholder:text-[#6B6B6B] focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
        />
      </section>

      <section class="rounded-2xl border border-[#E5E0DB] bg-white p-4 shadow-sm sm:p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="text-base font-bold text-[#3D3D3D]">Insumos solicitados</h2>
            <p class="mt-0.5 text-xs text-[#6B6B6B]">Indica la cantidad en la unidad de bodega.</p>
          </div>
          <PackagePlus class="shrink-0 text-[#F26A21]" :size="22" />
        </div>

        <div v-if="cargandoInsumos" class="flex items-center justify-center gap-2 py-10 text-sm text-[#6B6B6B]">
          <LoaderCircle class="animate-spin" :size="20" />
          Cargando insumos…
        </div>

        <div v-else-if="insumos.length === 0" class="rounded-xl bg-[#FAF7F4] p-4 text-sm text-[#6B6B6B]">
          No hay insumos registrados en bodega. Regístralos primero desde Inventario.
        </div>

        <div v-else class="mt-4 space-y-3">
          <article
            v-for="(detalle, indice) in detalles"
            :key="indice"
            class="rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] p-3"
          >
            <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_10rem_auto] sm:items-end">
              <label :for="`select-insumo-${indice}`" class="block text-sm font-bold text-[#3D3D3D]">
                Insumo {{ indice + 1 }}
                <select
                  :id="`select-insumo-${indice}`"
                  v-model="detalle.insumo_id"
                  required
                  class="mt-1 h-12 w-full rounded-xl border border-[#E5E0DB] bg-white px-3 text-base font-normal text-[#111111] outline-none focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
                >
                  <option value="" disabled>Selecciona un insumo</option>
                  <option
                    v-for="insumo in insumos"
                    :key="insumo.id"
                    :value="String(insumo.id)"
                    :disabled="insumoYaSeleccionado(insumo.id, indice)"
                  >
                    {{ insumo.nombre }} · {{ insumo.unidad_medida }}
                  </option>
                </select>
              </label>

              <label :for="`input-cantidad-${indice}`" class="block text-sm font-bold text-[#3D3D3D]">
                Cantidad
                <input
                  :id="`input-cantidad-${indice}`"
                  v-model="detalle.cantidad_solicitada"
                  type="number"
                  inputmode="decimal"
                  min="0.01"
                  step="0.01"
                  required
                  placeholder="0.00"
                  class="mt-1 h-12 w-full rounded-xl border border-[#E5E0DB] bg-white px-3 text-base font-normal text-[#111111] outline-none focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
                >
              </label>

              <button
                :id="`btn-eliminar-detalle-${indice}`"
                type="button"
                class="inline-flex h-12 min-w-[44px] items-center justify-center rounded-xl border border-rose-200 bg-white px-3 text-rose-700 transition hover:bg-rose-50"
                :aria-label="`Eliminar insumo ${indice + 1}`"
                @click="eliminarDetalle(indice)"
              >
                <Trash2 :size="18" />
              </button>
            </div>

            <p v-if="obtenerInsumoDetalle(detalle)" class="mt-2 text-xs text-[#6B6B6B]">
              Disponible: {{ obtenerInsumoDetalle(detalle).stock_actual }}
              {{ obtenerInsumoDetalle(detalle).unidad_medida }}
            </p>
          </article>
        </div>

        <button
          id="btn-agregar-detalle"
          type="button"
          class="mt-4 inline-flex min-h-[44px] w-full items-center justify-center gap-2 rounded-xl border border-dashed border-[#F26A21] px-4 py-2.5 text-sm font-bold text-[#C24E12] transition hover:bg-orange-50 sm:w-auto"
          :disabled="cargandoInsumos || insumos.length === 0"
          @click="agregarDetalle"
        >
          <Plus :size="18" />
          Agregar otro insumo
        </button>
      </section>

      <button
        id="btn-enviar-requisicion"
        type="submit"
        class="inline-flex min-h-[48px] w-full items-center justify-center gap-2 rounded-xl bg-[#F26A21] px-5 text-base font-black text-white shadow-md transition hover:bg-[#e05e1c] disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="enviando || cargandoInsumos || insumos.length === 0"
      >
        <LoaderCircle v-if="enviando" class="animate-spin" :size="20" />
        <ClipboardPlus v-else :size="20" />
        {{ enviando ? 'Enviando requisición…' : 'Enviar para aprobación' }}
      </button>

      <p class="text-center text-xs text-[#6B6B6B]">
        El stock se transfiere únicamente después de la aprobación de bodega.
      </p>
    </form>
  </main>
</template>
