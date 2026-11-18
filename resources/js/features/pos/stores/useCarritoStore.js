import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { registrarComanda } from '@/features/pos/services/posService.js';

/**
 * Store Pinia del carrito de la comanda activa en el POS.
 * Gestiona los ítems seleccionados, el canal, la mesa y el subtotal calculado.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 */
export const useCarritoStore = defineStore('carrito', () => {
  // Estado
  const items = ref([]);
  const canal = ref('mesa');
  const mesaId = ref(null);
  const observaciones = ref('');
  const cargando = ref(false);
  const error = ref(null);
  const requiereRevision = ref(false); // true si el servidor rechazó por un precio que cambió

  // Flujo del POS
  const pasoActual = ref(1); // 1 = Configuración, 2 = Menú
  const platoEnConstruccion = ref([]); // Para el Armador de Bandejas


  // Getters
  const totalItems = computed(() =>
    items.value.reduce((acc, item) => acc + item.cantidad, 0)
  );

  const subtotal = computed(() =>
    items.value.reduce((acc, item) => acc + (item.precio_unitario * item.cantidad), 0)
  );

  const subtotalFormateado = computed(() =>
    `$${subtotal.value.toFixed(2)}`
  );

  const estaVacio = computed(() => items.value.length === 0);

  const subtotalPlato = computed(() =>
    platoEnConstruccion.value.reduce((acc, sel) => acc + (sel.platillo.precio_unitario * sel.cantidad), 0)
  );

  function agregarItem(platillo) {
    const existente = items.value.find((i) => i.tipo === 'individual' && i.platillo_id === platillo.id);

    if (existente) {
      existente.cantidad += 1;
    } else {
      items.value.push({
        id: 'ind_' + platillo.id,
        tipo: 'individual',
        platillo_id: platillo.id,
        nombre: platillo.nombre,
        precio_unitario: parseFloat(platillo.precio_unitario),
        cantidad: 1,
        observaciones: '',
      });
    }
  }

  function agregarPlato(selecciones) {
    const precio = selecciones.reduce((acc, sel) => acc + (sel.platillo.precio_unitario * sel.cantidad), 0);
    items.value.push({
      id: 'plato_' + Date.now(),
      tipo: 'plato',
      nombre: 'Bandeja Personalizada',
      precio_unitario: precio,
      cantidad: 1,
      observaciones: '',
      sub_items: [...selecciones], // Array de { platillo, cantidad }
    });
  }

  function incrementar(itemId) {
    const item = items.value.find((i) => i.id === itemId);
    if (item) item.cantidad += 1;
  }

  function decrementar(itemId) {
    const indice = items.value.findIndex((i) => i.id === itemId);
    if (indice === -1) return;

    if (items.value[indice].cantidad <= 1) {
      items.value.splice(indice, 1);
    } else {
      items.value[indice].cantidad -= 1;
    }
  }

  function actualizarObservaciones(itemId, nuevasObservaciones) {
    const item = items.value.find((i) => i.id === itemId);
    if (item) item.observaciones = nuevasObservaciones;
  }

  function toggleSeleccionPlato(platillo) {
    const index = platoEnConstruccion.value.findIndex(s => s.platillo.id === platillo.id);
    if (index !== -1) {
      platoEnConstruccion.value[index].cantidad++;
    } else {
      platoEnConstruccion.value.push({ platillo, cantidad: 1 });
    }
  }

  function quitarSeleccionPlato(index) {
    platoEnConstruccion.value.splice(index, 1);
  }

  function vaciarPlato() {
    platoEnConstruccion.value = [];
  }

  function limpiar() {
    items.value        = [];
    canal.value        = 'mesa';
    mesaId.value       = null;
    observaciones.value = '';
    error.value        = null;
    pasoActual.value   = 1;
    platoEnConstruccion.value = [];
  }

  /**
   * Reconcilia el carrito y la bandeja en curso con el menú vigente del servidor:
   * quita platillos que ya no están disponibles y actualiza los precios cambiados.
   *
   * @autor  Jeferson De La Cruz
   * @fecha  2026-11-17
   * @módulo POS – RF-POS-001 / US-ADM-03 (CC-104, CC-106)
   *
   * @param   {Array} menu Platillos disponibles devueltos por GET /platillos.
   * @returns {Array<{tipo: string, nombre: string, anterior?: number, nuevo?: number}>}
   *          Cambios aplicados; vacío si el carrito ya coincidía con el menú.
   */
  function reconciliarConMenu(menu) {
    const vigentes = new Map(menu.map((p) => [p.id, p]));
    const cambios = [];

    // Devuelve el platillo vigente (o null si ya no está) registrando el cambio de precio.
    const sincronizar = (platillo) => {
      const vigente = vigentes.get(platillo.id);

      if (!vigente) {
        cambios.push({ tipo: 'no_disponible', nombre: platillo.nombre });
        return null;
      }

      const anterior = parseFloat(platillo.precio_unitario);
      const nuevo = parseFloat(vigente.precio_unitario);
      if (anterior !== nuevo) {
        cambios.push({ tipo: 'precio', nombre: vigente.nombre, anterior, nuevo });
      }

      return vigente;
    };

    items.value = items.value.flatMap((item) => {
      if (item.tipo === 'individual') {
        const vigente = sincronizar({
          id: item.platillo_id,
          nombre: item.nombre,
          precio_unitario: item.precio_unitario,
        });
        if (!vigente) return [];
        return [{ ...item, nombre: vigente.nombre, precio_unitario: parseFloat(vigente.precio_unitario) }];
      }

      const subItems = item.sub_items
        .map((sub) => {
          const vigente = sincronizar(sub.platillo);
          return vigente ? { ...sub, platillo: vigente } : null;
        })
        .filter(Boolean);

      if (subItems.length === 0) return [];

      const precio = subItems.reduce((acc, sub) => acc + (sub.platillo.precio_unitario * sub.cantidad), 0);
      return [{ ...item, sub_items: subItems, precio_unitario: precio }];
    });

    platoEnConstruccion.value = platoEnConstruccion.value
      .map((sel) => {
        const vigente = sincronizar(sel.platillo);
        return vigente ? { ...sel, platillo: vigente } : null;
      })
      .filter(Boolean);

    return cambios;
  }

  async function enviarComanda() {
    cargando.value = true;
    error.value    = null;
    requiereRevision.value = false;

    const listaObservaciones = [];
    if (observaciones.value && observaciones.value.trim()) {
      listaObservaciones.push(observaciones.value.trim());
    }

    const payloadDetalles = [];
    items.value.forEach(i => {
      if (i.tipo === 'plato') {
        // La observación del plato se eleva a nivel general de la comanda
        if (i.observaciones && i.observaciones.trim()) {
          listaObservaciones.push(i.observaciones.trim());
        }
        i.sub_items.forEach(sub => {
          // Flatten plato sub_items para compatibilidad con el backend
          payloadDetalles.push({
            platillo_id: sub.platillo.id,
            cantidad: sub.cantidad * i.cantidad,
            observaciones: null,
            precio_esperado: parseFloat(sub.platillo.precio_unitario),
          });
        });
      } else {
        payloadDetalles.push({
          platillo_id: i.platillo_id,
          cantidad: i.cantidad,
          observaciones: i.observaciones ? i.observaciones.trim() : null,
          precio_esperado: i.precio_unitario,
        });
      }
    });

    const obsUnicas = [...new Set(listaObservaciones)];

    const payload = {
      canal:         canal.value,
      mesa_id:       canal.value === 'mesa' ? mesaId.value : null,
      observaciones: obsUnicas.length > 0 ? obsUnicas.join(' · ') : null,
      items:         payloadDetalles,
    };

    const comanda = await registrarComanda(payload).catch((err) => {
      error.value = err.response?.data?.message ?? 'Error al registrar la comanda.';

      // El servidor rechaza sin crear la comanda si el precio vigente difiere del confirmado
      const errores = err.response?.data?.errors ?? {};
      requiereRevision.value = err.response?.status === 422
        && Object.keys(errores).some((campo) => campo.endsWith('precio_esperado'));
      return null;
    });

    if (comanda) limpiar();

    cargando.value = false;

    return comanda;
  }

  return {
    // Estado
    items,
    canal,
    mesaId,
    observaciones,
    cargando,
    error,
    requiereRevision,
    pasoActual,
    platoEnConstruccion,
    // Getters

    totalItems,
    subtotal,
    subtotalFormateado,
    estaVacio,
    subtotalPlato,
    // Acciones
    agregarItem,
    agregarPlato,
    incrementar,
    decrementar,
    actualizarObservaciones,
    toggleSeleccionPlato,
    quitarSeleccionPlato,
    vaciarPlato,
    limpiar,
    reconciliarConMenu,
    enviarComanda,
  };
});
