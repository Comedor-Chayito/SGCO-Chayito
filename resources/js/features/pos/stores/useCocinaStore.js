import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

/**
 * Store Pinia de la cola de cocina.
 * Gestiona el estado local (en memoria) de las comandas marcadas como
 * "listas para cobro" por el cocinero, sin tocar la base de datos.
 * El cobro real (POST /api/cobros) lo ejecuta la pantalla de caja,
 * momento en que la comanda pasa a "pagada" en la BD.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-07
 * @módulo POS – US-POS-03 / RF-POS-002
 */
export const useCocinaStore = defineStore('cocina', () => {
  /**
   * Mapa de comandas listas para cobro: id → objeto comanda completo.
   * Se usa Map para actualización reactiva eficiente sin romper la referencia.
   */
  const _listasCobro = ref(new Map());

  /**
   * Registra una comanda como "lista para cobro" en el estado local.
   * No realiza ninguna llamada al backend.
   *
   * @autor  Equipo SGCO-Chayito
   * @fecha  2026-10-07
   * @módulo POS – US-POS-03 / RF-POS-002
   *
   * @param {Object} comanda Objeto completo de la comanda con detalles y mesa.
   */
  function marcarComoLista(comanda) {
    const nuevoMapa = new Map(_listasCobro.value);
    nuevoMapa.set(comanda.id, { ...comanda, _estadoLocal: 'lista' });
    _listasCobro.value = nuevoMapa;
  }

  /**
   * Elimina una comanda del estado local de "listas para cobro".
   * Se llama después de que el cajero procese el cobro exitosamente.
   *
   * @autor  Equipo SGCO-Chayito
   * @fecha  2026-10-07
   * @módulo POS – US-POS-03 / RF-POS-002
   *
   * @param {number} id Identificador de la comanda a retirar.
   */
  function retirarCompletada(id) {
    const nuevoMapa = new Map(_listasCobro.value);
    nuevoMapa.delete(id);
    _listasCobro.value = nuevoMapa;
  }

  /**
   * Verifica si una comanda ya fue marcada como lista localmente.
   *
   * @autor  Equipo SGCO-Chayito
   * @fecha  2026-10-07
   * @módulo POS – US-POS-03 / RF-POS-002
   *
   * @param {number} id Identificador de la comanda.
   * @returns {boolean} true si ya está marcada como lista.
   */
  function esLista(id) {
    return _listasCobro.value.has(id);
  }

  // Lista reactiva de comandas listas para cobro (para la pantalla de caja)
  const comandasListasCobro = computed(() => [..._listasCobro.value.values()]);

  // Cantidad de comandas esperando cobro (para badge en el menú)
  const totalListasCobro = computed(() => _listasCobro.value.size);

  return {
    comandasListasCobro,
    totalListasCobro,
    marcarComoLista,
    retirarCompletada,
    esLista,
  };
});
