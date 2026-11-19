<script setup>
/**
 * Pestaña de subcategorías de egreso del panel de configuración: listar, crear,
 * renombrar y activar o desactivar. No se borran porque los egresos las referencian.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 */
import { onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Plus, Pencil, Power } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ModalCatalogo from './ModalCatalogo.vue';
import {
  obtenerSubcategorias,
  crearSubcategoria,
  renombrarSubcategoria,
  alternarSubcategoria,
  interpretarError,
} from '../services/catalogoService.js';

const subcategorias = ref([]);
const cargando = ref(true);
const guardando = ref(false);
const modalAbierto = ref(false);
const errores = ref({});
const formulario = reactive({ id: null, nombre: '' });

onMounted(cargar);

/**
 * Trae del backend el catálogo completo de subcategorías.
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
    subcategorias.value = await obtenerSubcategorias();
  } catch (error) {
    toast.error(interpretarError(error, 'No se pudieron cargar las subcategorías.').mensaje);
  } finally {
    cargando.value = false;
  }
}

/**
 * Abre el formulario vacío para crear, o con los datos de la subcategoría para renombrar.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {object|null} subcategoria Subcategoría a renombrar, o null para crear.
 * @returns {void}
 */
function abrirFormulario(subcategoria = null) {
  formulario.id = subcategoria?.id ?? null;
  formulario.nombre = subcategoria?.nombre ?? '';
  errores.value = {};
  modalAbierto.value = true;
}

/**
 * Crea o renombra la subcategoría según el formulario y recarga la tabla.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @returns {Promise<void>}
 */
async function guardar() {
  guardando.value = true;
  errores.value = {};

  try {
    const datos = { nombre: formulario.nombre };
    const respuesta = formulario.id
      ? await renombrarSubcategoria(formulario.id, datos)
      : await crearSubcategoria(datos);

    toast.success(respuesta.message);
    modalAbierto.value = false;
    await cargar();
  } catch (error) {
    const { campos, mensaje } = interpretarError(error, 'No se pudo guardar la subcategoría.');
    errores.value = campos;
    toast.error(mensaje);
  } finally {
    guardando.value = false;
  }
}

/**
 * Activa o desactiva una subcategoría y recarga la tabla.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {object} subcategoria Subcategoría a alternar.
 * @returns {Promise<void>}
 */
async function alternar(subcategoria) {
  try {
    const respuesta = await alternarSubcategoria(subcategoria.id);
    toast.success(respuesta.message);
    await cargar();
  } catch (error) {
    toast.error(interpretarError(error, 'No se pudo cambiar el estado.').mensaje);
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-bold text-[#3D3D3D]">Subcategorías de Egreso</h2>
        <p class="text-sm text-[#6B6B6B]">
          Clasificación de las facturas que registra la contadora. Las inactivas no aparecen
          en el formulario de egresos, pero conservan su historial.
        </p>
      </div>
      <Button
        class="h-10 rounded-xl bg-[#F26A21] px-4 text-white hover:bg-[#C24E12]"
        @click="abrirFormulario()"
      >
        <Plus :size="16" />
        Nueva subcategoría
      </Button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-[#E5E0DB] bg-white">
      <p v-if="cargando" class="p-6 text-sm text-[#6B6B6B]">Cargando subcategorías…</p>
      <p v-else-if="subcategorias.length === 0" class="p-6 text-sm text-[#6B6B6B]">
        No hay subcategorías registradas.
      </p>

      <table v-else class="w-full text-left text-sm">
        <thead
          class="border-b border-[#E5E0DB] bg-[#FAF7F4]
            text-xs font-semibold uppercase text-[#6B6B6B]"
        >
          <tr>
            <th class="px-6 py-3">Nombre</th>
            <th class="px-6 py-3">Estado</th>
            <th class="px-6 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[#E5E0DB]">
          <tr v-for="subcategoria in subcategorias" :key="subcategoria.id">
            <td class="px-6 py-3 font-medium text-[#111111]">{{ subcategoria.nombre }}</td>
            <td class="px-6 py-3">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="subcategoria.activo
                  ? 'bg-[#2E7D4F]/10 text-[#2E7D4F]'
                  : 'bg-[#6B6B6B]/10 text-[#6B6B6B]'"
              >
                {{ subcategoria.activo ? 'Activa' : 'Inactiva' }}
              </span>
            </td>
            <td class="px-6 py-3">
              <div class="flex justify-end gap-2">
                <Button
                  variant="outline"
                  class="h-9 rounded-lg"
                  @click="abrirFormulario(subcategoria)"
                >
                  <Pencil :size="14" />
                  Renombrar
                </Button>
                <Button variant="outline" class="h-9 rounded-lg" @click="alternar(subcategoria)">
                  <Power :size="14" />
                  {{ subcategoria.activo ? 'Desactivar' : 'Activar' }}
                </Button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ModalCatalogo
      :abierto="modalAbierto"
      :titulo="formulario.id ? 'Renombrar subcategoría' : 'Nueva subcategoría'"
      :procesando="guardando"
      @confirmar="guardar"
      @cerrar="modalAbierto = false"
    >
      <div>
        <label
          for="subcategoria-nombre"
          class="mb-1 block text-xs font-semibold uppercase text-[#3D3D3D]"
        >
          Nombre
        </label>
        <Input
          id="subcategoria-nombre"
          v-model="formulario.nombre"
          maxlength="80"
          placeholder="Ej: Gas propano"
          class="h-10 rounded-xl"
          :aria-invalid="Boolean(errores.nombre)"
        />
        <p v-if="errores.nombre" class="mt-1 text-xs text-[#C62828]">{{ errores.nombre }}</p>
      </div>
    </ModalCatalogo>
  </div>
</template>
