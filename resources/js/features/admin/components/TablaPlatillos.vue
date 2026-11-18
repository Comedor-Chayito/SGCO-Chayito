<script setup>
/**
 * Pestaña de platillos y precios del panel de configuración: listar, buscar, crear,
 * editar precio, sacar o meter del menú del día y eliminar los que no tienen historial.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 */
import { computed, onMounted, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Plus, Pencil, Power, Search, Trash2 } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import ModalCatalogo from './ModalCatalogo.vue';
import {
  obtenerPlatillos,
  crearPlatillo,
  actualizarPlatillo,
  cambiarDisponibilidadPlatillo,
  eliminarPlatillo,
  interpretarError,
  formatearPrecio,
} from '../services/catalogoService.js';

const platillos = ref([]);
const cargando = ref(true);
const procesando = ref(false);
const busqueda = ref('');
const modalFormulario = ref(false);
const platilloPorEliminar = ref(null);
const errores = ref({});
const formulario = reactive({ id: null, nombre: '', descripcion: '', precio_unitario: '' });

const platillosFiltrados = computed(() => {
  const texto = busqueda.value.trim().toLowerCase();

  return texto
    ? platillos.value.filter((platillo) => platillo.nombre.toLowerCase().includes(texto))
    : platillos.value;
});

onMounted(cargar);

/**
 * Trae del backend el catálogo completo de platillos.
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
    platillos.value = await obtenerPlatillos();
  } catch (error) {
    toast.error(interpretarError(error, 'No se pudieron cargar los platillos.').mensaje);
  } finally {
    cargando.value = false;
  }
}

/**
 * Abre el formulario vacío para crear, o con los datos del platillo para editar.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {object|null} platillo Platillo a editar, o null para crear.
 * @returns {void}
 */
function abrirFormulario(platillo = null) {
  formulario.id = platillo?.id ?? null;
  formulario.nombre = platillo?.nombre ?? '';
  formulario.descripcion = platillo?.descripcion ?? '';
  formulario.precio_unitario = platillo?.precio_unitario ?? '';
  errores.value = {};
  modalFormulario.value = true;
}

/**
 * Crea o edita el platillo según el formulario y recarga la tabla.
 * Las comandas ya registradas conservan su precio original (lo garantiza el backend).
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
      nombre: formulario.nombre,
      descripcion: formulario.descripcion || null,
      precio_unitario: formulario.precio_unitario,
    };
    const respuesta = formulario.id
      ? await actualizarPlatillo(formulario.id, datos)
      : await crearPlatillo(datos);

    toast.success(respuesta.message);
    modalFormulario.value = false;
    await cargar();
  } catch (error) {
    const { campos, mensaje } = interpretarError(error, 'No se pudo guardar el platillo.');
    errores.value = campos;
    toast.error(mensaje);
  } finally {
    procesando.value = false;
  }
}

/**
 * Saca o mete un platillo del menú del día y recarga la tabla.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 *
 * @param   {object} platillo Platillo a alternar.
 * @returns {Promise<void>}
 */
async function alternarMenu(platillo) {
  try {
    const respuesta = await cambiarDisponibilidadPlatillo(platillo.id, !platillo.disponible);
    toast.success(respuesta.message);
    await cargar();
  } catch (error) {
    toast.error(interpretarError(error, 'No se pudo cambiar la disponibilidad.').mensaje);
  }
}

/**
 * Elimina el platillo confirmado. Si tiene comandas o receta, el backend lo rechaza
 * y se muestra su mensaje para que se desactive del menú en su lugar.
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
    const respuesta = await eliminarPlatillo(platilloPorEliminar.value.id);
    toast.success(respuesta.message);
    platilloPorEliminar.value = null;
    await cargar();
  } catch (error) {
    toast.error(interpretarError(error, 'No se pudo eliminar el platillo.').mensaje);
    platilloPorEliminar.value = null;
  } finally {
    procesando.value = false;
  }
}
</script>

<template>
  <div class="space-y-4">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h2 class="text-lg font-bold text-[#3D3D3D]">Platillos y Menú del Día</h2>
        <p class="text-sm text-[#6B6B6B]">
          Un cambio de precio se refleja en el punto de venta y no altera las comandas ya
          registradas. Los platillos fuera de menú no se pueden vender.
        </p>
      </div>
      <Button
        class="h-10 rounded-xl bg-[#F26A21] px-4 text-white hover:bg-[#C24E12]"
        @click="abrirFormulario()"
      >
        <Plus :size="16" />
        Nuevo platillo
      </Button>
    </div>

    <div class="relative max-w-sm">
      <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-[#6B6B6B]" />
      <Input
        v-model="busqueda"
        placeholder="Buscar platillo por nombre"
        aria-label="Buscar platillo por nombre"
        class="h-10 rounded-xl bg-white pl-9"
      />
    </div>

    <div class="overflow-hidden rounded-2xl border border-[#E5E0DB] bg-white">
      <p v-if="cargando" class="p-6 text-sm text-[#6B6B6B]">Cargando platillos…</p>
      <p v-else-if="platillosFiltrados.length === 0" class="p-6 text-sm text-[#6B6B6B]">
        {{ busqueda
          ? 'Ningún platillo coincide con la búsqueda.'
          : 'No hay platillos registrados.' }}
      </p>

      <table v-else class="w-full text-left text-sm">
        <thead
          class="border-b border-[#E5E0DB] bg-[#FAF7F4]
            text-xs font-semibold uppercase text-[#6B6B6B]"
        >
          <tr>
            <th class="px-6 py-3">Platillo</th>
            <th class="px-6 py-3 text-right">Precio</th>
            <th class="px-6 py-3">Menú del día</th>
            <th class="px-6 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[#E5E0DB]">
          <tr v-for="platillo in platillosFiltrados" :key="platillo.id">
            <td class="px-6 py-3">
              <p class="font-medium text-[#111111]">{{ platillo.nombre }}</p>
              <p v-if="platillo.descripcion" class="text-xs text-[#6B6B6B]">
                {{ platillo.descripcion }}
              </p>
            </td>
            <td class="px-6 py-3 text-right font-semibold tabular-nums text-[#111111]">
              {{ formatearPrecio(platillo.precio_unitario) }}
            </td>
            <td class="px-6 py-3">
              <span
                class="rounded-full px-2.5 py-1 text-xs font-semibold"
                :class="platillo.disponible
                  ? 'bg-[#2E7D4F]/10 text-[#2E7D4F]'
                  : 'bg-[#6B6B6B]/10 text-[#6B6B6B]'"
              >
                {{ platillo.disponible ? 'En menú' : 'Fuera de menú' }}
              </span>
            </td>
            <td class="px-6 py-3">
              <div class="flex justify-end gap-2">
                <Button variant="outline" class="h-9 rounded-lg" @click="abrirFormulario(platillo)">
                  <Pencil :size="14" />
                  Editar
                </Button>
                <Button variant="outline" class="h-9 rounded-lg" @click="alternarMenu(platillo)">
                  <Power :size="14" />
                  {{ platillo.disponible ? 'Sacar del menú' : 'Agregar al menú' }}
                </Button>
                <Button
                  variant="outline"
                  class="h-9 rounded-lg text-[#C62828] hover:bg-[#C62828]/10"
                  @click="platilloPorEliminar = platillo"
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
      :titulo="formulario.id ? 'Editar platillo' : 'Nuevo platillo'"
      :procesando="procesando"
      @confirmar="guardar"
      @cerrar="modalFormulario = false"
    >
      <div class="space-y-4">
        <div>
          <label
            for="platillo-nombre"
            class="mb-1 block text-xs font-semibold uppercase text-[#3D3D3D]"
          >
            Nombre
          </label>
          <Input
            id="platillo-nombre"
            v-model="formulario.nombre"
            maxlength="100"
            placeholder="Ej: Pupusa revuelta"
            class="h-10 rounded-xl"
            :aria-invalid="Boolean(errores.nombre)"
          />
          <p v-if="errores.nombre" class="mt-1 text-xs text-[#C62828]">{{ errores.nombre }}</p>
        </div>
        <div>
          <label
            for="platillo-descripcion"
            class="mb-1 block text-xs font-semibold uppercase text-[#3D3D3D]"
          >
            Descripción (opcional)
          </label>
          <Input
            id="platillo-descripcion"
            v-model="formulario.descripcion"
            maxlength="1000"
            placeholder="Ej: Chicharrón con queso"
            class="h-10 rounded-xl"
            :aria-invalid="Boolean(errores.descripcion)"
          />
          <p v-if="errores.descripcion" class="mt-1 text-xs text-[#C62828]">
            {{ errores.descripcion }}
          </p>
        </div>
        <div>
          <label
            for="platillo-precio"
            class="mb-1 block text-xs font-semibold uppercase text-[#3D3D3D]"
          >
            Precio unitario ($)
          </label>
          <Input
            id="platillo-precio"
            v-model="formulario.precio_unitario"
            type="number"
            min="0.01"
            step="0.01"
            placeholder="Ej: 1.25"
            class="h-10 rounded-xl"
            :aria-invalid="Boolean(errores.precio_unitario)"
          />
          <p v-if="errores.precio_unitario" class="mt-1 text-xs text-[#C62828]">
            {{ errores.precio_unitario }}
          </p>
        </div>
      </div>
    </ModalCatalogo>

    <ModalCatalogo
      :abierto="platilloPorEliminar !== null"
      titulo="Eliminar platillo"
      texto-confirmar="Eliminar"
      peligro
      :procesando="procesando"
      @confirmar="confirmarEliminacion"
      @cerrar="platilloPorEliminar = null"
    >
      <p class="text-sm text-[#3D3D3D]">
        ¿Eliminar «{{ platilloPorEliminar?.nombre }}»? Solo se puede borrar si nunca se vendió y
        no tiene receta; si no, sácalo del menú para conservar el historial.
      </p>
    </ModalCatalogo>
  </div>
</template>
