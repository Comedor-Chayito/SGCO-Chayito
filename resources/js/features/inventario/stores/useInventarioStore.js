import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import {
  obtenerInsumos,
  crearInsumo,
  actualizarInsumo,
  eliminarInsumo,
} from '@/features/inventario/services/inventarioService.js';

/**
 * Store de Pinia para la gestión de insumos del módulo Inventario (CC-45 / US-INV-01).
 * Mantiene el catálogo de bodega y el estado de la interfaz (carga, error).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
export const useInventarioStore = defineStore('inventario', () => {
  // ─── Estado ────────────────────────────────────────────────────────────────
  const insumos = ref([]);
  const cargando = ref(false);
  const error = ref(null);

  // ─── Getters ───────────────────────────────────────────────────────────────

  /**
   * Insumos en alerta de quiebre de stock, según el estado_stock que evalúa el backend.
   *
   * @autor  Jeferson De La Cruz
   * @fecha  2026-10-13
   * @módulo Inventario – RF-INV-004 / CC-57
   */
  const insumosConAlerta = computed(() =>
    insumos.value.filter((i) => i.estado_stock !== 'normal')
  );

  // ─── Acciones ──────────────────────────────────────────────────────────────

  /**
   * Carga todos los insumos desde el backend, con búsqueda opcional.
   *
   * @autor  Jeferson De La Cruz
   * @fecha  2026-10-13
   * @módulo Inventario – RF-INV-003
   *
   * @param   {string} [busqueda=''] Texto de búsqueda por nombre.
   * @returns {Promise<void>}
   */
  async function cargarInsumos(busqueda = '') {
    cargando.value = true;
    error.value = null;

    try {
      insumos.value = await obtenerInsumos(busqueda);
    } catch (err) {
      error.value = err?.response?.data?.message ?? 'Error al cargar los insumos.';
    } finally {
      cargando.value = false;
    }
  }

  /**
   * Crea un nuevo insumo y lo agrega localmente al catálogo.
   *
   * @autor  Jeferson De La Cruz
   * @fecha  2026-10-13
   * @módulo Inventario – RF-INV-003
   *
   * @param   {Object} datos Datos del nuevo insumo.
   * @returns {Promise<Object|null>} Insumo creado o null si falló.
   */
  async function guardarInsumo(datos) {
    error.value = null;

    try {
      const nuevo = await crearInsumo(datos);
      insumos.value.push(nuevo);
      insumos.value.sort((a, b) => a.nombre.localeCompare(b.nombre));
      return nuevo;
    } catch (err) {
      error.value = err?.response?.data?.message
        ?? err?.response?.data?.errors
        ?? 'Error al guardar el insumo.';
      return null;
    }
  }

  /**
   * Actualiza un insumo existente y refleja los cambios en el catálogo local.
   *
   * @autor  Jeferson De La Cruz
   * @fecha  2026-10-13
   * @módulo Inventario – RF-INV-003
   *
   * @param   {number} id    Identificador del insumo.
   * @param   {Object} datos Datos actualizados.
   * @returns {Promise<Object|null>} Insumo actualizado o null si falló.
   */
  async function editarInsumo(id, datos) {
    error.value = null;

    try {
      const actualizado = await actualizarInsumo(id, datos);
      const idx = insumos.value.findIndex((i) => i.id === id);
      if (idx !== -1) insumos.value[idx] = actualizado;
      return actualizado;
    } catch (err) {
      error.value = err?.response?.data?.message
        ?? err?.response?.data?.errors
        ?? 'Error al actualizar el insumo.';
      return null;
    }
  }

  /**
   * Elimina un insumo del catálogo local y del backend.
   *
   * @autor  Jeferson De La Cruz
   * @fecha  2026-10-13
   * @módulo Inventario – RF-INV-003
   *
   * @param   {number} id Identificador del insumo a eliminar.
   * @returns {Promise<boolean>} true si se eliminó, false si falló.
   */
  async function borrarInsumo(id) {
    error.value = null;

    try {
      await eliminarInsumo(id);
      insumos.value = insumos.value.filter((i) => i.id !== id);
      return true;
    } catch (err) {
      error.value = err?.response?.data?.message
        ?? err?.response?.data?.errors?.[0]
        ?? 'Error al eliminar el insumo.';
      return false;
    }
  }

  return {
    insumos,
    cargando,
    error,
    insumosConAlerta,
    cargarInsumos,
    guardarInsumo,
    editarInsumo,
    borrarInsumo,
  };
});
