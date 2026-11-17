<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { X } from '@lucide/vue';
import { usePermisos } from '@/shared/composables/usePermisos.js';
import {
  registrarEntrada, registrarAjuste, obtenerMovimientos, obtenerEgresosEntrada,
} from '../services/inventarioService.js';
import { formatearCantidad } from '../services/cantidadService.js';

const props = defineProps({
  insumo: { type: Object, required: true },
  modo: { type: String, required: true },
});
const emit = defineEmits(['cerrar', 'guardado']);
const { puedeVer } = usePermisos();
const dialogo = ref(null);
const cantidad = ref('');
const motivo = ref('');
const egresoSeleccionado = ref(null);
const enviando = ref(false);
const error = ref('');
const erroresCampos = ref({});
const pagina = ref(null);
const cargando = ref(false);
const errorCarga = ref('');
const paginaSolicitada = ref(1);
const focoAnterior = document.activeElement;
const titulo = { entrada: 'Entrada de stock', ajuste: 'Ajuste de stock', historial: 'Historial' };
const claseCampo = 'min-h-11 w-full rounded-lg border border-[#E5E0DB] bg-[#FAF7F4] '
  + 'px-3 text-sm text-[#111111] focus:outline-2 focus:outline-[#C24E12]';
const claseBoton = 'min-h-11 rounded-xl border border-[#E5E0DB] px-4 text-sm font-bold '
  + 'text-[#3D3D3D] hover:bg-[#FAF7F4] disabled:opacity-50 focus-visible:outline-2 '
  + 'focus-visible:outline-[#C24E12]';

/**
 * Abre el diálogo nativo y carga los datos sin observadores.
 * @autor Equipo de desarrollo
 * @fecha 2026-11-17
 * @módulo Inventario RF-INV-003 / CC-113
 * @returns {void}
 */
function iniciar() {
  if (!puedeVer(['administrador', 'socio'])) return emit('cerrar');
  dialogo.value.showModal();
  if (props.modo !== 'ajuste') cargarPagina();
}

/**
 * Evita cerrar durante el envío y permite Escape mediante cancel del diálogo.
 * @autor Equipo de desarrollo
 * @fecha 2026-11-17
 * @módulo Inventario RF-INV-003 / CC-113
 * @returns {void}
 */
function cerrar() {
  if (!enviando.value) emit('cerrar');
}

/**
 * Restituye el foco al control de origen al desmontar el diálogo.
 * @autor Equipo de desarrollo
 * @fecha 2026-11-17
 * @módulo Inventario RF-INV-003 / CC-113
 * @returns {void}
 */
function restaurarFoco() {
  dialogo.value?.close();
  if (focoAnterior?.isConnected) focoAnterior.focus();
}

/**
 * Consulta movimientos o egresos y conserva la página para reintentar errores.
 * @autor Equipo de desarrollo
 * @fecha 2026-11-17
 * @módulo Inventario RF-INV-003 / CC-113
 * @param {number} numero Página solicitada.
 * @returns {Promise<void>}
 */
async function cargarPagina(numero = 1) {
  if (cargando.value) return;
  paginaSolicitada.value = numero;
  cargando.value = true;
  errorCarga.value = '';

  try {
    pagina.value = props.modo === 'historial'
      ? await obtenerMovimientos(props.insumo.id, numero) : await obtenerEgresosEntrada(numero);
  } catch {
    errorCarga.value = props.modo === 'historial'
      ? 'No se pudo cargar el historial. Intenta de nuevo.'
      : 'No se pudieron cargar los egresos. Puedes continuar sin vincular.';
  } finally {
    cargando.value = false;
  }
}

/**
 * Envía el movimiento una sola vez y comunica el insumo actualizado al catálogo.
 * @autor Equipo de desarrollo
 * @fecha 2026-11-17
 * @módulo Inventario RF-INV-003 / CC-113
 * @returns {Promise<void>}
 */
async function guardar() {
  if (enviando.value || !puedeVer(['administrador', 'socio'])) return;
  enviando.value = true;
  error.value = '';
  erroresCampos.value = {};
  const datos = { cantidad: cantidad.value, motivo: motivo.value.trim() || undefined };
  if (props.modo === 'entrada' && egresoSeleccionado.value) {
    datos.egreso_id = egresoSeleccionado.value.id;
  }

  try {
    const registrar = props.modo === 'entrada' ? registrarEntrada : registrarAjuste;
    const resultado = await registrar(props.insumo.id, datos);
    emit('guardado', resultado);
  } catch (err) {
    erroresCampos.value = err.response?.data?.data?.errores ?? {};
    error.value = err.response?.status < 500
      ? err.response.data.message : 'No se pudo registrar el movimiento. Intenta de nuevo.';
    error.value ||= 'No se pudo registrar el movimiento. Verifica los datos.';
  } finally {
    enviando.value = false;
  }
}

/**
 * Presenta la fecha de auditoría en la zona horaria del usuario.
 * @autor Equipo de desarrollo
 * @fecha 2026-11-17
 * @módulo Inventario RF-INV-003 / CC-113
 * @param {string} fecha Fecha y hora del movimiento.
 * @returns {string} Fecha legible.
 */
function formatearFecha(fecha) {
  const valor = new Date(fecha);
  return Number.isNaN(valor.getTime()) ? 'Fecha no disponible'
    : new Intl.DateTimeFormat('es-SV', { dateStyle: 'medium', timeStyle: 'short' }).format(valor);
}

onMounted(iniciar);
onBeforeUnmount(restaurarFoco);
</script>

<template>
  <Teleport to="body">
    <dialog ref="dialogo" aria-labelledby="titulo-movimiento"
      aria-describedby="detalle-insumo-movimiento"
      class="fixed inset-0 m-auto max-h-[90dvh] w-[calc(100%-2rem)] max-w-3xl
        overflow-y-auto rounded-2xl bg-white p-5 text-[#111111] shadow-2xl
        backdrop:bg-black/40 sm:p-6"
      @cancel.prevent="cerrar">
      <header class="mb-5 flex items-start justify-between gap-3">
        <div>
          <h2 id="titulo-movimiento" class="text-lg font-black text-[#3D3D3D]">
            {{ titulo[modo] }}
          </h2>
          <p id="detalle-insumo-movimiento" class="mt-1 text-sm text-[#6B6B6B]">
            {{ insumo.nombre }} · Stock: {{ formatearCantidad(insumo.stock_actual) }}
            {{ insumo.unidad_medida }}
          </p>
        </div>
        <button :class="claseBoton" :disabled="enviando" aria-label="Cerrar" @click="cerrar">
          <X :size="18" />
        </button>
      </header>

      <form v-if="modo !== 'historial'" id="form-movimiento" class="space-y-4"
        :aria-busy="enviando" @submit.prevent="guardar">
        <fieldset :disabled="enviando" class="space-y-4">
          <div>
            <label for="cantidad-movimiento" class="mb-1 block text-sm font-bold">
              {{ modo === 'entrada' ? 'Cantidad de entrada' : 'Cantidad a sumar o restar' }}
              ({{ insumo.unidad_medida }}) *
            </label>
            <input id="cantidad-movimiento" v-model="cantidad" :class="claseCampo"
              type="number" step="0.000001" :min="modo === 'entrada' ? '0.000001' : undefined"
              required aria-describedby="ayuda-cantidad" />
            <p id="ayuda-cantidad" class="mt-1 text-xs text-[#6B6B6B]">
              {{ modo === 'entrada' ? 'Cantidad positiva, hasta 6 decimales.'
                : 'Es un cambio, no el saldo final. Usa signo negativo para restar; no puede ser cero.' }}
            </p>
          </div>
          <div>
            <label for="motivo-movimiento" class="mb-1 block text-sm font-bold">
              Motivo {{ modo === 'ajuste' ? '*' : '(opcional)' }}
            </label>
            <textarea id="motivo-movimiento" v-model="motivo" :class="claseCampo"
              rows="3" :required="modo === 'ajuste'" />
          </div>
        </fieldset>
        <div v-if="error" role="alert" class="text-sm text-[#C62828]">
          <p>{{ error }}</p>
          <ul class="mt-1 list-inside list-disc">
            <li v-for="(mensajes, campo) in erroresCampos" :key="campo">
              {{ Array.isArray(mensajes) ? mensajes.join(' ') : mensajes }}
            </li>
          </ul>
        </div>
      </form>

      <section v-if="modo !== 'ajuste'" class="mt-4 space-y-3" aria-live="polite"
        :aria-label="modo === 'entrada' ? 'Egreso opcional' : 'Movimientos de stock'">
        <template v-if="modo === 'entrada'">
          <h3 class="text-sm font-bold">Egreso vinculado (opcional)</h3>
          <p class="break-words text-sm text-[#6B6B6B]">
            {{ egresoSeleccionado
              ? `${egresoSeleccionado.correlativo} · ${egresoSeleccionado.proveedor}
                · Factura ${egresoSeleccionado.numero_comprobante || 'sin número'}`
              : 'Sin vincular. Puedes registrar la entrada sin un egreso.' }}
          </p>
          <button v-if="egresoSeleccionado" type="button" :class="claseBoton"
            :disabled="enviando" @click="egresoSeleccionado = null">Quitar vínculo</button>
        </template>
        <p v-if="cargando" role="status" class="py-6 text-center">Cargando...</p>
        <div v-else-if="errorCarga" role="alert" class="space-y-2">
          <p class="text-sm text-[#C62828]">{{ errorCarga }}</p>
          <button :class="claseBoton" :disabled="enviando"
            @click="cargarPagina(paginaSolicitada)">Reintentar</button>
        </div>
        <template v-else-if="pagina">
          <p v-if="!pagina.data.length" class="py-6 text-center text-sm text-[#6B6B6B]">
            {{ modo === 'entrada' ? 'No hay egresos disponibles.'
              : 'Este insumo no tiene movimientos registrados.' }}
          </p>
          <ul v-else-if="modo === 'entrada'" class="divide-y divide-[#E5E0DB]">
            <li v-for="egreso in pagina.data" :key="egreso.id">
              <label class="flex min-h-11 cursor-pointer items-start gap-3 py-2 text-sm">
                <input v-model="egresoSeleccionado" type="radio" name="egreso-entrada"
                  :value="egreso" :disabled="enviando" class="mt-1 accent-[#C24E12]" />
                <span class="min-w-0 break-words">
                  {{ egreso.correlativo }} · {{ egreso.proveedor }}
                  · Factura {{ egreso.numero_comprobante || 'sin número' }}
                </span>
              </label>
            </li>
          </ul>
          <div v-else class="overflow-x-auto rounded-xl border border-[#E5E0DB]">
            <table class="w-full text-left text-sm">
              <caption class="sr-only">Historial de {{ insumo.nombre }}</caption>
              <thead class="bg-[#FAF7F4] text-[#3D3D3D]">
                <tr>
                  <th scope="col" class="p-3">Fecha / usuario</th>
                  <th scope="col" class="p-3">Tipo / motivo / referencias</th>
                  <th scope="col" class="p-3 text-right">Cantidad</th>
                  <th scope="col" class="p-3 text-right">Saldo ({{ insumo.unidad_medida }})</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#E5E0DB]">
                <tr v-for="movimiento in pagina.data" :key="movimiento.id">
                  <td class="p-3 align-top">
                    <time :datetime="movimiento.created_at">
                      {{ formatearFecha(movimiento.created_at) }}
                    </time>
                    <p class="mt-1 text-[#6B6B6B]">
                      {{ movimiento.usuario?.name ?? 'Usuario no disponible' }}
                    </p>
                  </td>
                  <td class="min-w-52 break-words p-3 align-top">
                    <p class="font-bold">{{ movimiento.tipo }}</p>
                    <p class="whitespace-pre-wrap">{{ movimiento.motivo || 'Sin motivo registrado' }}</p>
                    <p v-if="movimiento.egreso" class="mt-1 text-[#6B6B6B]">
                      Egreso {{ movimiento.egreso.correlativo }} · {{ movimiento.egreso.proveedor }}
                      · Factura {{ movimiento.egreso.numero_comprobante || 'sin número' }}
                    </p>
                    <p v-if="movimiento.comanda_id">Comanda #{{ movimiento.comanda_id }}</p>
                    <p v-if="movimiento.requisicion_id">
                      Requisición #{{ movimiento.requisicion_id }}
                    </p>
                  </td>
                  <td class="p-3 text-right align-top tabular-nums">
                    {{ formatearCantidad(movimiento.cantidad) }}
                  </td>
                  <td class="p-3 text-right align-top tabular-nums">
                    {{ formatearCantidad(movimiento.stock_resultante) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
          <nav class="flex flex-wrap items-center justify-between gap-2" aria-label="Paginación">
            <button :class="claseBoton" :disabled="enviando || pagina.current_page <= 1"
              @click="cargarPagina(pagina.current_page - 1)">Anterior</button>
            <span class="text-xs text-[#6B6B6B]">
              Página {{ pagina.current_page }} de {{ pagina.last_page }} · {{ pagina.total }} registros
            </span>
            <button :class="claseBoton"
              :disabled="enviando || pagina.current_page >= pagina.last_page"
              @click="cargarPagina(pagina.current_page + 1)">Siguiente</button>
          </nav>
        </template>
      </section>

      <footer v-if="modo !== 'historial'" class="mt-5 flex flex-wrap justify-end gap-3">
        <button :class="claseBoton" :disabled="enviando" @click="cerrar">Cancelar</button>
        <button type="submit" form="form-movimiento" :disabled="enviando"
          class="min-h-11 rounded-xl bg-[#F26A21] px-5 text-sm font-bold text-white
            hover:bg-[#C24E12] focus-visible:outline-2 focus-visible:outline-[#C24E12]
            disabled:opacity-50">
          {{ enviando ? 'Registrando...' : modo === 'entrada' ? 'Registrar entrada' : 'Registrar ajuste' }}
        </button>
      </footer>
    </dialog>
  </Teleport>
</template>
