<script setup>
/**
 * Vista CRUD de insumos de bodega con unidad de medida (CC-45 / US-INV-01).
 * Permite listar, crear, editar y eliminar insumos del catálogo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
import { ref, onMounted } from 'vue';
import { useInventarioStore } from '@/features/inventario/stores/useInventarioStore.js';
import { Toaster, toast } from 'vue-sonner';
import {
  PackageSearch,
  Plus,
  Pencil,
  Trash2,
  TriangleAlert,
  X,
  Check,
  Search,
} from '@lucide/vue';

const store = useInventarioStore();

// ─── Estado del modal ───────────────────────────────────────────────────────
const modalAbierto = ref(false);
const modoEdicion = ref(false);
const insumoEditando = ref(null);
const busqueda = ref('');

const form = ref({
  nombre: '',
  unidad_medida: '',
  stock_actual: '',
  stock_minimo: '',
});

const UNIDADES = [
  'kg', 'g', 'lt', 'ml', 'unidad', 'docena', 'caja', 'bolsa', 'libra', 'onza',
];

onMounted(() => store.cargarInsumos());

// ─── Funciones del modal ────────────────────────────────────────────────────

/**
 * Abre el modal en modo creación con el formulario vacío.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
function abrirCrear() {
  modoEdicion.value = false;
  insumoEditando.value = null;
  form.value = { nombre: '', unidad_medida: '', stock_actual: '0', stock_minimo: '0' };
  modalAbierto.value = true;
}

/**
 * Abre el modal en modo edición prellenado con los datos del insumo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param {Object} insumo Insumo a editar.
 */
function abrirEditar(insumo) {
  modoEdicion.value = true;
  insumoEditando.value = insumo;
  form.value = {
    nombre: insumo.nombre,
    unidad_medida: insumo.unidad_medida,
    stock_actual: insumo.stock_actual,
    stock_minimo: insumo.stock_minimo,
  };
  modalAbierto.value = true;
}

function cerrarModal() {
  modalAbierto.value = false;
}

/**
 * Guarda el insumo (crear o actualizar) y muestra notificación.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
async function guardar() {
  const datos = {
    nombre: form.value.nombre.trim(),
    unidad_medida: form.value.unidad_medida.trim(),
    stock_actual: parseFloat(form.value.stock_actual) || 0,
    stock_minimo: parseFloat(form.value.stock_minimo) || 0,
  };

  let resultado;

  if (modoEdicion.value) {
    resultado = await store.editarInsumo(insumoEditando.value.id, datos);
    if (resultado) toast.success('Insumo actualizado correctamente.');
  } else {
    resultado = await store.guardarInsumo(datos);
    if (resultado) toast.success(`Insumo "${resultado.nombre}" registrado.`);
  }

  if (resultado) {
    cerrarModal();
  } else {
    const msg = typeof store.error === 'string'
      ? store.error
      : 'Error al guardar el insumo. Verifica los datos.';
    toast.error(msg);
  }
}

/**
 * Solicita confirmación y elimina el insumo seleccionado.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param {Object} insumo Insumo a eliminar.
 */
async function eliminar(insumo) {
  if (!confirm(`¿Eliminar el insumo "${insumo.nombre}"? Esta acción no se puede deshacer.`)) return;

  const ok = await store.borrarInsumo(insumo.id);

  if (ok) {
    toast.success(`Insumo "${insumo.nombre}" eliminado.`);
  } else {
    const msg = typeof store.error === 'string'
      ? store.error
      : 'No se pudo eliminar el insumo.';
    toast.error(msg);
  }
}

/**
 * Filtra los insumos por el término de búsqueda en el backend.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
async function buscar() {
  await store.cargarInsumos(busqueda.value.trim());
}

/**
 * Devuelve la clase de color del badge de stock.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param   {Object} insumo Insumo a evaluar.
 * @returns {string} Clase Tailwind de color.
 */
function claseStock(insumo) {
  const actual = parseFloat(insumo.stock_actual);
  const minimo = parseFloat(insumo.stock_minimo);
  if (actual <= 0) return 'bg-[#C62828] text-white';
  if (actual <= minimo) return 'bg-[#E0A800] text-zinc-900';
  return 'bg-[#2E7D4F] text-white';
}
</script>

<template>
  <div class="min-h-screen bg-[#FAF7F4] p-4 sm:p-6 lg:p-8" id="vista-insumos">
    <Toaster richColors position="top-center" />

    <!-- Encabezado -->
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
      <div>
        <h1 class="text-2xl font-black text-[#3D3D3D]">Catálogo de Insumos</h1>
        <p class="mt-0.5 text-sm text-[#6B6B6B]">
          Administra ingredientes de bodega con unidad de medida y stock mínimo.
        </p>
      </div>

      <button
        id="btn-nuevo-insumo"
        class="inline-flex h-11 min-w-[44px] items-center gap-2 rounded-xl bg-[#F26A21] px-5 text-sm font-bold text-white shadow-md transition-all hover:bg-[#e05e1c] hover:-translate-y-0.5 hover:shadow-lg active:scale-[0.98]"
        @click="abrirCrear"
      >
        <Plus :size="18" :stroke-width="2.5" />
        Nuevo insumo
      </button>
    </div>

    <!-- Buscador -->
    <div class="mb-4 flex gap-2">
      <div class="relative flex-1">
        <Search
          class="absolute left-3 top-1/2 -translate-y-1/2 text-[#6B6B6B]"
          :size="16"
        />
        <input
          id="input-busqueda-insumo"
          v-model="busqueda"
          type="text"
          placeholder="Buscar insumo por nombre…"
          class="h-10 w-full rounded-lg border border-[#E5E0DB] bg-white pl-9 pr-4 text-sm text-[#111111] outline-none transition-all placeholder:text-[#6B6B6B] focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
          @keyup.enter="buscar"
        />
      </div>
      <button
        class="inline-flex h-10 min-w-[44px] items-center justify-center rounded-lg border border-[#E5E0DB] bg-white px-4 text-sm font-medium text-[#3D3D3D] transition-all hover:bg-[#FAF7F4] active:scale-[0.97]"
        @click="buscar"
      >
        Buscar
      </button>
    </div>

    <!-- Alerta de insumos críticos -->
    <div
      v-if="store.insumosConAlerta.length > 0"
      class="mb-4 flex items-center gap-3 rounded-xl border border-[#E0A800]/40 bg-[#E0A800]/10 px-4 py-3"
    >
      <TriangleAlert class="shrink-0 text-[#E0A800]" :size="18" />
      <p class="text-sm text-[#3D3D3D]">
        <strong>{{ store.insumosConAlerta.length }} insumo(s)</strong> con stock por debajo
        del mínimo.
      </p>
    </div>

    <!-- Estado de carga -->
    <div v-if="store.cargando" class="flex justify-center py-16">
      <div class="flex flex-col items-center gap-3 animate-pulse">
        <PackageSearch :size="40" class="text-[#E5E0DB]" />
        <p class="text-sm text-[#6B6B6B]">Cargando insumos…</p>
      </div>
    </div>

    <!-- Estado vacío -->
    <div
      v-else-if="store.insumos.length === 0"
      class="flex flex-col items-center gap-4 py-20 text-center"
    >
      <PackageSearch :size="48" class="text-[#E5E0DB]" />
      <p class="text-base font-bold text-[#6B6B6B]">No hay insumos registrados.</p>
      <p class="text-sm text-[#6B6B6B]">
        Crea el primer insumo pulsando "Nuevo insumo".
      </p>
    </div>

    <!-- Tabla de insumos -->
    <div v-else class="overflow-hidden rounded-2xl border border-[#E5E0DB] bg-white shadow-sm">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-[#E5E0DB] bg-[#FAF7F4]">
            <th class="py-3 pl-5 pr-3 text-left font-bold text-[#3D3D3D]">Nombre</th>
            <th class="px-3 py-3 text-left font-bold text-[#3D3D3D]">Unidad</th>
            <th class="px-3 py-3 text-right font-bold text-[#3D3D3D]">Stock actual</th>
            <th class="px-3 py-3 text-right font-bold text-[#3D3D3D]">Stock mínimo</th>
            <th class="py-3 pl-3 pr-5 text-right font-bold text-[#3D3D3D]">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-[#E5E0DB]">
          <tr
            v-for="insumo in store.insumos"
            :key="insumo.id"
            class="transition-colors hover:bg-[#FAF7F4]"
          >
            <td class="py-3.5 pl-5 pr-3 font-medium text-[#111111]">
              {{ insumo.nombre }}
            </td>
            <td class="px-3 py-3.5 text-[#6B6B6B]">
              <span class="rounded-md bg-[#FAF7F4] px-2 py-0.5 text-xs font-semibold uppercase tracking-wide">
                {{ insumo.unidad_medida }}
              </span>
            </td>
            <td class="px-3 py-3.5 text-right">
              <span
                class="inline-block rounded-full px-2.5 py-0.5 text-xs font-bold"
                :class="claseStock(insumo)"
              >
                {{ parseFloat(insumo.stock_actual).toFixed(2) }}
              </span>
            </td>
            <td class="px-3 py-3.5 text-right text-[#6B6B6B]">
              {{ parseFloat(insumo.stock_minimo).toFixed(2) }}
            </td>
            <td class="py-3.5 pl-3 pr-5 text-right">
              <div class="inline-flex gap-2">
                <button
                  :id="`btn-editar-insumo-${insumo.id}`"
                  class="inline-flex h-8 min-w-[44px] items-center justify-center rounded-lg border border-[#E5E0DB] bg-white px-2 text-[#3D3D3D] transition-colors hover:bg-[#FAF7F4] hover:border-[#F26A21]"
                  title="Editar insumo"
                  @click="abrirEditar(insumo)"
                >
                  <Pencil :size="15" />
                </button>
                <button
                  :id="`btn-eliminar-insumo-${insumo.id}`"
                  class="inline-flex h-8 min-w-[44px] items-center justify-center rounded-lg border border-[#E5E0DB] bg-white px-2 text-[#C62828] transition-colors hover:bg-[#C62828]/5 hover:border-[#C62828]"
                  title="Eliminar insumo"
                  @click="eliminar(insumo)"
                >
                  <Trash2 :size="15" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- ─── Modal Crear / Editar ─────────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="modalAbierto"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="cerrarModal"
        id="modal-insumo"
      >
        <div class="relative w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
          <!-- Encabezado modal -->
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-black text-[#3D3D3D]">
              {{ modoEdicion ? 'Editar insumo' : 'Nuevo insumo' }}
            </h2>
            <button
              class="flex h-8 w-8 items-center justify-center rounded-full text-[#6B6B6B] transition-colors hover:bg-[#FAF7F4]"
              @click="cerrarModal"
            >
              <X :size="18" />
            </button>
          </div>

          <!-- Formulario -->
          <form id="form-insumo" class="flex flex-col gap-4" @submit.prevent="guardar">
            <!-- Nombre -->
            <div>
              <label for="input-nombre-insumo" class="mb-1 block text-xs font-bold text-[#3D3D3D]">
                Nombre del insumo *
              </label>
              <input
                id="input-nombre-insumo"
                v-model="form.nombre"
                type="text"
                maxlength="100"
                required
                placeholder="Ej. Harina de trigo"
                class="h-10 w-full rounded-lg border border-[#E5E0DB] bg-[#FAF7F4] px-3 text-sm text-[#111111] outline-none transition-all placeholder:text-[#6B6B6B] focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
              />
            </div>

            <!-- Unidad de medida -->
            <div>
              <label for="select-unidad-medida" class="mb-1 block text-xs font-bold text-[#3D3D3D]">
                Unidad de medida *
              </label>
              <div class="flex gap-2">
                <select
                  id="select-unidad-medida"
                  v-model="form.unidad_medida"
                  class="h-10 flex-1 rounded-lg border border-[#E5E0DB] bg-[#FAF7F4] px-3 text-sm text-[#111111] outline-none transition-all focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
                >
                  <option value="" disabled>Selecciona…</option>
                  <option v-for="u in UNIDADES" :key="u" :value="u">{{ u }}</option>
                </select>
                <input
                  id="input-unidad-personalizada"
                  v-model="form.unidad_medida"
                  type="text"
                  maxlength="20"
                  placeholder="O escribe…"
                  class="h-10 w-28 rounded-lg border border-[#E5E0DB] bg-[#FAF7F4] px-3 text-sm text-[#111111] outline-none transition-all placeholder:text-[#6B6B6B] focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
                />
              </div>
            </div>

            <!-- Stock actual y mínimo -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="input-stock-actual" class="mb-1 block text-xs font-bold text-[#3D3D3D]">
                  Stock actual
                </label>
                <input
                  id="input-stock-actual"
                  v-model="form.stock_actual"
                  type="number"
                  min="0"
                  step="0.01"
                  class="h-10 w-full rounded-lg border border-[#E5E0DB] bg-[#FAF7F4] px-3 text-sm text-[#111111] outline-none transition-all focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
                />
              </div>
              <div>
                <label for="input-stock-minimo" class="mb-1 block text-xs font-bold text-[#3D3D3D]">
                  Stock mínimo
                </label>
                <input
                  id="input-stock-minimo"
                  v-model="form.stock_minimo"
                  type="number"
                  min="0"
                  step="0.01"
                  class="h-10 w-full rounded-lg border border-[#E5E0DB] bg-[#FAF7F4] px-3 text-sm text-[#111111] outline-none transition-all focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
                />
              </div>
            </div>

            <!-- Botones modal -->
            <div class="mt-2 flex gap-3">
              <button
                type="button"
                class="h-11 flex-1 rounded-xl border border-[#E5E0DB] text-sm font-bold text-[#6B6B6B] transition-colors hover:bg-[#FAF7F4]"
                @click="cerrarModal"
              >
                Cancelar
              </button>
              <button
                id="btn-guardar-insumo"
                type="submit"
                class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-[#F26A21] text-sm font-bold text-white transition-all hover:bg-[#e05e1c] active:scale-[0.98]"
              >
                <Check :size="16" :stroke-width="2.5" />
                {{ modoEdicion ? 'Guardar cambios' : 'Registrar insumo' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
