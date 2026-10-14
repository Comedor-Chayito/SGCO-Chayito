<script setup>
/**
 * Vista para vincular ingredientes (insumos + porción base) a un platillo (CC-46 / US-INV-01).
 * Permite ver, agregar, editar y eliminar los renglones de receta de cada platillo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
import { ref, onMounted, computed } from 'vue';
import { Toaster, toast } from 'vue-sonner';
import {
  obtenerInsumos,
  obtenerRecetaPlatillo,
  agregarIngrediente,
  actualizarIngrediente,
  eliminarIngrediente,
} from '@/features/inventario/services/inventarioService.js';
import api from '@/services/api.js';
import {
  ChefHat,
  Plus,
  Pencil,
  Trash2,
  X,
  Check,
  ArrowLeft,
  FlaskConical,
} from '@lucide/vue';

// ─── Estado general ──────────────────────────────────────────────────────────
const platillos = ref([]);
const platilloSeleccionado = ref(null);
const receta = ref([]);
const insumosCatalogo = ref([]);
const cargando = ref(false);
const cargandoReceta = ref(false);

// ─── Estado modal ────────────────────────────────────────────────────────────
const modalAbierto = ref(false);
const modoEdicion = ref(false);
const renglonEditando = ref(null);

const form = ref({
  insumo_id: '',
  cantidad_por_porcion: '',
});

// ─── Computed ────────────────────────────────────────────────────────────────

/**
 * Insumos disponibles para agregar (que no estén ya en la receta del platillo seleccionado).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
const insumosDisponibles = computed(() => {
  if (modoEdicion.value) return insumosCatalogo.value;
  const enReceta = new Set(receta.value.map((r) => r.insumo_id));
  return insumosCatalogo.value.filter((i) => !enReceta.has(i.id));
});

// ─── Ciclo de vida ───────────────────────────────────────────────────────────

onMounted(async () => {
  cargando.value = true;
  try {
    const [resPlatillos, resInsumos] = await Promise.all([
      api.get('/platillos'),
      obtenerInsumos(),
    ]);
    platillos.value = resPlatillos.data.data ?? [];
    insumosCatalogo.value = resInsumos;
  } catch {
    toast.error('Error al cargar los datos iniciales.');
  } finally {
    cargando.value = false;
  }
});

// ─── Funciones ───────────────────────────────────────────────────────────────

/**
 * Selecciona un platillo y carga su receta desde el backend.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param {Object} platillo Platillo seleccionado.
 */
async function seleccionarPlatillo(platillo) {
  platilloSeleccionado.value = platillo;
  cargandoReceta.value = true;
  try {
    receta.value = await obtenerRecetaPlatillo(platillo.id);
  } catch {
    toast.error('Error al cargar la receta del platillo.');
  } finally {
    cargandoReceta.value = false;
  }
}

function volverListaPlatillos() {
  platilloSeleccionado.value = null;
  receta.value = [];
}

/**
 * Abre el modal para agregar un nuevo ingrediente.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
function abrirAgregar() {
  modoEdicion.value = false;
  renglonEditando.value = null;
  form.value = { insumo_id: '', cantidad_por_porcion: '' };
  modalAbierto.value = true;
}

/**
 * Abre el modal en modo edición con los datos del renglón a modificar.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param {Object} renglon Renglón de receta a editar.
 */
function abrirEditar(renglon) {
  modoEdicion.value = true;
  renglonEditando.value = renglon;
  form.value = {
    insumo_id: renglon.insumo_id,
    cantidad_por_porcion: renglon.cantidad_por_porcion,
  };
  modalAbierto.value = true;
}

function cerrarModal() {
  modalAbierto.value = false;
}

/**
 * Guarda el ingrediente (crear o actualizar) y refresca la receta.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
async function guardar() {
  try {
    if (modoEdicion.value) {
      const actualizado = await actualizarIngrediente(
        platilloSeleccionado.value.id,
        renglonEditando.value.id,
        { cantidad_por_porcion: parseFloat(form.value.cantidad_por_porcion) }
      );
      const idx = receta.value.findIndex((r) => r.id === renglonEditando.value.id);
      if (idx !== -1) receta.value[idx] = actualizado;
      toast.success('Porción actualizada correctamente.');
    } else {
      const nuevo = await agregarIngrediente(platilloSeleccionado.value.id, {
        insumo_id: parseInt(form.value.insumo_id),
        cantidad_por_porcion: parseFloat(form.value.cantidad_por_porcion),
      });
      receta.value.push(nuevo);
      toast.success('Ingrediente agregado a la receta.');
    }
    cerrarModal();
  } catch (err) {
    const msg = err?.response?.data?.message
      ?? err?.response?.data?.errors?.[Object.keys(err?.response?.data?.errors ?? {})[0]]?.[0]
      ?? 'Error al guardar el ingrediente.';
    toast.error(msg);
  }
}

/**
 * Elimina un ingrediente de la receta previa confirmación.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param {Object} renglon Renglón de receta a eliminar.
 */
async function eliminar(renglon) {
  if (!confirm(`¿Eliminar "${renglon.insumo?.nombre}" de la receta?`)) return;
  try {
    await eliminarIngrediente(platilloSeleccionado.value.id, renglon.id);
    receta.value = receta.value.filter((r) => r.id !== renglon.id);
    toast.success('Ingrediente eliminado de la receta.');
  } catch (err) {
    const msg = err?.response?.data?.message ?? 'Error al eliminar el ingrediente.';
    toast.error(msg);
  }
}

/**
 * Devuelve el nombre del insumo por su ID para el modal en modo edición.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 *
 * @param   {number} id Identificador del insumo.
 * @returns {string} Nombre del insumo.
 */
function nombreInsumo(id) {
  return insumosCatalogo.value.find((i) => i.id === id)?.nombre ?? `Insumo #${id}`;
}
</script>

<template>
  <div class="min-h-screen bg-[#FAF7F4] p-4 sm:p-6 lg:p-8" id="vista-receta-platillo">
    <Toaster richColors position="top-center" />

    <!-- ── Vista A: Lista de platillos ──────────────────────────────────── -->
    <template v-if="!platilloSeleccionado">
      <div class="mb-6">
        <h1 class="text-2xl font-black text-[#3D3D3D]">Recetas por Platillo</h1>
        <p class="mt-0.5 text-sm text-[#6B6B6B]">
          Selecciona un platillo para ver y editar sus ingredientes con porción base.
        </p>
      </div>

      <!-- Cargando -->
      <div v-if="cargando" class="flex justify-center py-16">
        <div class="flex flex-col items-center gap-3 animate-pulse">
          <ChefHat :size="40" class="text-[#E5E0DB]" />
          <p class="text-sm text-[#6B6B6B]">Cargando platillos…</p>
        </div>
      </div>

      <!-- Grid de platillos -->
      <div
        v-else
        class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
      >
        <button
          v-for="platillo in platillos"
          :id="`btn-platillo-${platillo.id}`"
          :key="platillo.id"
          class="group flex flex-col gap-2 rounded-2xl border border-[#E5E0DB] bg-white p-4 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:border-[#F26A21]/40 hover:shadow-md active:scale-[0.98]"
          @click="seleccionarPlatillo(platillo)"
        >
          <div class="flex items-center justify-between">
            <span
              class="inline-flex items-center justify-center rounded-xl bg-[#F26A21]/10 p-2"
            >
              <ChefHat :size="20" class="text-[#F26A21]" />
            </span>
            <span
              class="rounded-md px-2 py-0.5 text-xs font-semibold"
              :class="platillo.disponible
                ? 'bg-[#2E7D4F]/10 text-[#2E7D4F]'
                : 'bg-[#6B6B6B]/10 text-[#6B6B6B]'"
            >
              {{ platillo.disponible ? 'Disponible' : 'No disponible' }}
            </span>
          </div>
          <div>
            <p class="font-bold text-[#111111] leading-tight">{{ platillo.nombre }}</p>
            <p class="mt-0.5 text-xs text-[#6B6B6B]">
              ${{ parseFloat(platillo.precio_unitario).toFixed(2) }}
            </p>
          </div>
          <div class="flex items-center gap-1 text-xs text-[#F26A21] font-semibold mt-1">
            <FlaskConical :size="13" />
            Ver receta
          </div>
        </button>
      </div>
    </template>

    <!-- ── Vista B: Receta del platillo seleccionado ──────────────────── -->
    <template v-else>
      <!-- Encabezado con volver -->
      <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3">
          <button
            id="btn-volver-platillos"
            class="flex h-9 w-9 items-center justify-center rounded-xl border border-[#E5E0DB] bg-white text-[#3D3D3D] transition-colors hover:bg-[#FAF7F4]"
            @click="volverListaPlatillos"
            title="Volver a la lista de platillos"
          >
            <ArrowLeft :size="18" />
          </button>
          <div>
            <h1 class="text-xl font-black text-[#3D3D3D]">
              Receta: {{ platilloSeleccionado.nombre }}
            </h1>
            <p class="text-xs text-[#6B6B6B]">
              Ingredientes y porciones base por unidad vendida.
            </p>
          </div>
        </div>

        <button
          id="btn-agregar-ingrediente"
          class="inline-flex h-11 min-w-[44px] items-center gap-2 rounded-xl bg-[#F26A21] px-5 text-sm font-bold text-white shadow-md transition-all hover:bg-[#e05e1c] hover:-translate-y-0.5 hover:shadow-lg active:scale-[0.98]"
          @click="abrirAgregar"
          :disabled="insumosDisponibles.length === 0"
        >
          <Plus :size="18" :stroke-width="2.5" />
          Agregar ingrediente
        </button>
      </div>

      <!-- Cargando receta -->
      <div v-if="cargandoReceta" class="flex justify-center py-12">
        <div class="flex flex-col items-center gap-3 animate-pulse">
          <FlaskConical :size="36" class="text-[#E5E0DB]" />
          <p class="text-sm text-[#6B6B6B]">Cargando receta…</p>
        </div>
      </div>

      <!-- Receta vacía -->
      <div
        v-else-if="receta.length === 0"
        class="flex flex-col items-center gap-4 py-16 text-center"
      >
        <FlaskConical :size="48" class="text-[#E5E0DB]" />
        <p class="font-bold text-[#6B6B6B]">Este platillo no tiene receta configurada.</p>
        <p class="text-sm text-[#6B6B6B]">
          Agrega ingredientes con su porción base para activar la deducción automática de bodega.
        </p>
      </div>

      <!-- Tabla de receta -->
      <div v-else class="overflow-hidden rounded-2xl border border-[#E5E0DB] bg-white shadow-sm">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-[#E5E0DB] bg-[#FAF7F4]">
              <th class="py-3 pl-5 pr-3 text-left font-bold text-[#3D3D3D]">Ingrediente</th>
              <th class="px-3 py-3 text-left font-bold text-[#3D3D3D]">Unidad</th>
              <th class="px-3 py-3 text-right font-bold text-[#3D3D3D]">
                Cantidad / porción
              </th>
              <th class="px-3 py-3 text-right font-bold text-[#3D3D3D]">Stock actual</th>
              <th class="py-3 pl-3 pr-5 text-right font-bold text-[#3D3D3D]">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-[#E5E0DB]">
            <tr
              v-for="renglon in receta"
              :key="renglon.id"
              class="transition-colors hover:bg-[#FAF7F4]"
            >
              <td class="py-3.5 pl-5 pr-3 font-medium text-[#111111]">
                {{ renglon.insumo?.nombre ?? `Insumo #${renglon.insumo_id}` }}
              </td>
              <td class="px-3 py-3.5">
                <span class="rounded-md bg-[#FAF7F4] px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-[#6B6B6B]">
                  {{ renglon.insumo?.unidad_medida }}
                </span>
              </td>
              <td class="px-3 py-3.5 text-right font-mono font-semibold text-[#C24E12]">
                {{ parseFloat(renglon.cantidad_por_porcion).toFixed(3) }}
              </td>
              <td class="px-3 py-3.5 text-right text-[#6B6B6B]">
                {{ renglon.insumo?.stock_actual ?? '—' }}
              </td>
              <td class="py-3.5 pl-3 pr-5 text-right">
                <div class="inline-flex gap-2">
                  <button
                    :id="`btn-editar-receta-${renglon.id}`"
                    class="inline-flex h-8 min-w-[44px] items-center justify-center rounded-lg border border-[#E5E0DB] bg-white px-2 text-[#3D3D3D] transition-colors hover:bg-[#FAF7F4] hover:border-[#F26A21]"
                    title="Editar porción"
                    @click="abrirEditar(renglon)"
                  >
                    <Pencil :size="15" />
                  </button>
                  <button
                    :id="`btn-eliminar-receta-${renglon.id}`"
                    class="inline-flex h-8 min-w-[44px] items-center justify-center rounded-lg border border-[#E5E0DB] bg-white px-2 text-[#C62828] transition-colors hover:bg-[#C62828]/5 hover:border-[#C62828]"
                    title="Eliminar ingrediente"
                    @click="eliminar(renglon)"
                  >
                    <Trash2 :size="15" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>

    <!-- ─── Modal Agregar / Editar ingrediente ─────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="modalAbierto"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="cerrarModal"
        id="modal-ingrediente"
      >
        <div class="relative w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl">
          <div class="mb-5 flex items-center justify-between">
            <h2 class="text-lg font-black text-[#3D3D3D]">
              {{ modoEdicion ? 'Editar porción' : 'Agregar ingrediente' }}
            </h2>
            <button
              class="flex h-8 w-8 items-center justify-center rounded-full text-[#6B6B6B] transition-colors hover:bg-[#FAF7F4]"
              @click="cerrarModal"
            >
              <X :size="18" />
            </button>
          </div>

          <form id="form-ingrediente" class="flex flex-col gap-4" @submit.prevent="guardar">
            <!-- Insumo (solo en modo crear) -->
            <div v-if="!modoEdicion">
              <label for="select-insumo" class="mb-1 block text-xs font-bold text-[#3D3D3D]">
                Insumo *
              </label>
              <select
                id="select-insumo"
                v-model="form.insumo_id"
                required
                class="h-10 w-full rounded-lg border border-[#E5E0DB] bg-[#FAF7F4] px-3 text-sm text-[#111111] outline-none transition-all focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
              >
                <option value="" disabled>Selecciona un insumo…</option>
                <option
                  v-for="insumo in insumosDisponibles"
                  :key="insumo.id"
                  :value="insumo.id"
                >
                  {{ insumo.nombre }} ({{ insumo.unidad_medida }})
                </option>
              </select>
            </div>

            <!-- Insumo en modo edición (solo lectura) -->
            <div v-else>
              <p class="mb-1 text-xs font-bold text-[#3D3D3D]">Ingrediente</p>
              <p class="rounded-lg border border-[#E5E0DB] bg-[#FAF7F4] px-3 py-2 text-sm font-medium text-[#3D3D3D]">
                {{ nombreInsumo(form.insumo_id) }}
              </p>
            </div>

            <!-- Cantidad por porción -->
            <div>
              <label for="input-cantidad-porcion" class="mb-1 block text-xs font-bold text-[#3D3D3D]">
                Cantidad por porción *
              </label>
              <input
                id="input-cantidad-porcion"
                v-model="form.cantidad_por_porcion"
                type="number"
                min="0.001"
                step="0.001"
                required
                placeholder="Ej. 0.250"
                class="h-10 w-full rounded-lg border border-[#E5E0DB] bg-[#FAF7F4] px-3 text-sm text-[#111111] outline-none transition-all placeholder:text-[#6B6B6B] focus:border-[#F26A21] focus:ring-2 focus:ring-[#F26A21]/20"
              />
              <p class="mt-1 text-xs text-[#6B6B6B]">
                Cantidad del insumo que se consume por unidad vendida del platillo.
              </p>
            </div>

            <!-- Botones -->
            <div class="mt-2 flex gap-3">
              <button
                type="button"
                class="h-11 flex-1 rounded-xl border border-[#E5E0DB] text-sm font-bold text-[#6B6B6B] transition-colors hover:bg-[#FAF7F4]"
                @click="cerrarModal"
              >
                Cancelar
              </button>
              <button
                id="btn-guardar-ingrediente"
                type="submit"
                class="inline-flex h-11 flex-1 items-center justify-center gap-2 rounded-xl bg-[#F26A21] text-sm font-bold text-white transition-all hover:bg-[#e05e1c] active:scale-[0.98]"
              >
                <Check :size="16" :stroke-width="2.5" />
                {{ modoEdicion ? 'Guardar cambios' : 'Agregar' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </div>
</template>
