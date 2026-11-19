<script setup>
/**
 * Pestaña de mesas del panel de configuración: listar, crear, editar, activar o
 * desactivar y eliminar. Una mesa con historial se desactiva en lugar de borrarse.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 */
import { onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Plus, Pencil, Power, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ModalCatalogo from './ModalCatalogo.vue';
import {
  obtenerMesas,
  crearMesa,
  actualizarMesa,
  alternarMesa,
  eliminarMesa,
  interpretarError,
} from '../services/catalogoService.js';

const mesas = ref([]);
const cargando = ref(true);
const procesando = ref(false);
const modalFormulario = ref(false);
const mesaPorEliminar = ref(null);
const errores = ref({});
const formulario = reactive({ id: null, numero: '', capacidad: '' });

onMounted(cargar);

/**
 * Trae del backend todas las mesas, activas e inactivas.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @returns {Promise<void>}
 */
async function cargar() {
  cargando.value = true;

  try {
    mesas.value = await obtenerMesas();
  } catch (error) {
    toast.error(interpretarError(error, 'No se pudieron cargar las mesas.').mensaje);
  } finally {
    cargando.value = false;
  }
}

/**
 * Abre el formulario vacío para crear, o con los datos de la mesa para editar.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {object|null} mesa Mesa a editar, o null para crear.
 * @returns {void}
 */
function abrirFormulario(mesa = null) {
  formulario.id = mesa?.id ?? null;
  formulario.numero = mesa?.numero ?? '';
  formulario.capacidad = mesa?.capacidad ?? '';
  errores.value = {};
  modalFormulario.value = true;
}

/**
 * Crea o edita la mesa según el formulario y recarga la tabla.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @returns {Promise<void>}
 */
async function guardar() {
  procesando.value = true;
  errores.value = {};

  try {
    const datos = {
      numero: formulario.numero === '' ? null : Number(formulario.numero),
      capacidad: formulario.capacidad === '' ? null : Number(formulario.capacidad),
    };
    const respuesta = formulario.id
      ? await actualizarMesa(formulario.id, datos)
      : await crearMesa(datos);

    toast.success(respuesta.message);
    modalFormulario.value = false;
    await cargar();
  } catch (error) {
    const { campos, mensaje } = interpretarError(error, 'No se pudo guardar la mesa.');
    errores.value = campos;
    toast.error(mensaje);
  } finally {
    procesando.value = false;
  }
}

/**
 * Activa o desactiva una mesa. El backend lo rechaza si tiene comandas abiertas.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {object} mesa Mesa a alternar.
 * @returns {Promise<void>}
 */
async function alternar(mesa) {
  try {
    const respuesta = await alternarMesa(mesa.id);
    toast.success(respuesta.message);
    await cargar();
  } catch (error) {
    toast.error(interpretarError(error, 'No se pudo cambiar el estado de la mesa.').mensaje);
  }
}

/**
 * Elimina la mesa confirmada; si tiene historial el backend la desactiva y lo informa.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @returns {Promise<void>}
 */
async function confirmarEliminacion() {
  procesando.value = true;

  try {
    const respuesta = await eliminarMesa(mesaPorEliminar.value.id);
    toast.success(respuesta.message);
    mesaPorEliminar.value = null;
    await cargar();
  } catch (error) {
    toast.error(interpretarError(error, 'No se pudo eliminar la mesa.').mensaje);
  } finally {
    procesando.value = false;
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-bold text-[#3D3D3D]">Catálogo de Mesas</h2>
        <p class="text-sm text-[#6B6B6B]">
          Las mesas inactivas no aparecen en el punto de venta. Una mesa con comandas abiertas
          no se puede desactivar.
        </p>
      </div>
      <Button
        class="h-10 rounded-xl bg-[#F26A21] px-4 text-white hover:bg-[#C24E12]"
        @click="abrirFormulario()"
      >
        <Plus :size="16" />
        Nueva mesa
      </Button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-[#E5E0DB] bg-white">
      <p v-if="cargando" class="p-6 text-sm text-[#6B6B6B]">Cargando mesas…</p>
      <p v-else-if="mesas.length === 0" class="p-6 text-sm text-[#6B6B6B]">
        No hay mesas registradas.
      </p>

      <table v-else class="w-full text-left text-sm">
        <thead
          class="border-b border-[#E5E0DB] bg-[#FAF7F4]
            text-xs font-semibold uppercase text-[#6B6B6B]"
        >
          <tr>
            <th class="px-6 py-3">Mesa</th>
            <th class="px-6 py-3">Capacidad</th>
            <th class="px-6 py-3">Ocupación</th>
            <th class="px-6 py-3">Estado</th>
            <th class="px-6 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[#E5E0DB]">
          <tr v-for="mesa in mesas" :key="mesa.id">
            <td class="px-6 py-3 font-medium text-[#111111]">Mesa {{ mesa.numero }}</td>
            <td class="px-6 py-3 text-[#3D3D3D]">
              {{ mesa.capacidad ? `${mesa.capacidad} personas` : 'Sin definir' }}
            </td>
            <td class="px-6 py-3">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="mesa.estado === 'ocupada'
                  ? 'bg-[#2B6CB0]/10 text-[#2B6CB0]'
                  : 'bg-[#6B6B6B]/10 text-[#6B6B6B]'"
              >
                {{ mesa.estado === 'ocupada' ? 'Ocupada' : 'Libre' }}
              </span>
            </td>
            <td class="px-6 py-3">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="mesa.activa
                  ? 'bg-[#2E7D4F]/10 text-[#2E7D4F]'
                  : 'bg-[#6B6B6B]/10 text-[#6B6B6B]'"
              >
                {{ mesa.activa ? 'Activa' : 'Inactiva' }}
              </span>
            </td>
            <td class="px-6 py-3">
              <div class="flex justify-end gap-2">
                <Button variant="outline" class="h-9 rounded-lg" @click="abrirFormulario(mesa)">
                  <Pencil :size="14" />
                  Editar
                </Button>
                <Button variant="outline" class="h-9 rounded-lg" @click="alternar(mesa)">
                  <Power :size="14" />
                  {{ mesa.activa ? 'Desactivar' : 'Activar' }}
                </Button>
                <Button
                  variant="outline"
                  class="h-9 rounded-lg text-[#C62828] hover:bg-[#C62828]/10"
                  @click="mesaPorEliminar = mesa"
                >
                  <Trash2 :size="14" />
                  Eliminar
                </Button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ModalCatalogo
      :abierto="modalFormulario"
      :titulo="formulario.id ? 'Editar mesa' : 'Nueva mesa'"
      :procesando="procesando"
      @confirmar="guardar"
      @cerrar="modalFormulario = false"
    >
      <div class="space-y-4">
        <div>
          <label
            for="mesa-numero"
            class="mb-1 block text-xs font-semibold uppercase text-[#3D3D3D]"
          >
            Número de mesa
          </label>
          <Input
            id="mesa-numero"
            v-model="formulario.numero"
            type="number"
            min="1"
            placeholder="Ej: 7"
            class="h-10 rounded-xl"
            :aria-invalid="Boolean(errores.numero)"
          />
          <p v-if="errores.numero" class="mt-1 text-xs text-[#C62828]">{{ errores.numero }}</p>
        </div>
        <div>
          <label
            for="mesa-capacidad"
            class="mb-1 block text-xs font-semibold uppercase text-[#3D3D3D]"
          >
            Capacidad (opcional)
          </label>
          <Input
            id="mesa-capacidad"
            v-model="formulario.capacidad"
            type="number"
            min="1"
            max="255"
            placeholder="Ej: 4"
            class="h-10 rounded-xl"
            :aria-invalid="Boolean(errores.capacidad)"
          />
          <p v-if="errores.capacidad" class="mt-1 text-xs text-[#C62828]">
            {{ errores.capacidad }}
          </p>
        </div>
      </div>
    </ModalCatalogo>

    <ModalCatalogo
      :abierto="mesaPorEliminar !== null"
      titulo="Eliminar mesa"
      texto-confirmar="Eliminar"
      peligro
      :procesando="procesando"
      @confirmar="confirmarEliminacion"
      @cerrar="mesaPorEliminar = null"
    >
      <p class="text-sm text-[#3D3D3D]">
        ¿Eliminar la mesa {{ mesaPorEliminar?.numero }}? Si ya tiene comandas registradas,
        se desactivará en lugar de borrarse para conservar el historial.
      </p>
    </ModalCatalogo>
  </div>
</template>
