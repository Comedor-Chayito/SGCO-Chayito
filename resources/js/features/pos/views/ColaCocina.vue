<script setup>
/**
 * Pantalla de Cocina / KDS (Kitchen Display System) para el área de cocina y caja.
 * Muestra las órdenes en cola en riguroso orden de llegada (FIFO), con tarjetas animadas
 * deslizantes al entrar/salir, temporizador reactivo, alertas auditivas y sondeo periódico.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – US-POS-02 / RF-POS-001
 */
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { RouterLink } from 'vue-router';
import { usePermisos } from '@/shared/composables/usePermisos.js';
import {
  ChefHat,
  RefreshCw,
  CheckCircle2,
  Volume2,
  VolumeX,
  Receipt,
} from '@lucide/vue';
import { Toaster, toast } from 'vue-sonner';
import { obtenerColaCocina, actualizarEstadoComanda } from '@/features/pos/services/posService.js';
import ComandaCard from '@/features/pos/components/ComandaCard.vue';
import ComandaCompletadaItem from '@/features/pos/components/ComandaCompletadaItem.vue';
import EncabezadoOperacion from '@/shared/components/EncabezadoOperacion.vue';

const { puedeVer } = usePermisos();

// Estado de comandas
const comandas = ref([]);
const completadas = ref([]);
const cargando = ref(true);
const procesandoId = ref(null);
const sonidoHabilitado = ref(true);
const autoRefresco = ref(true);

// Estado del arrastre entre columnas del tablero (drag & drop nativo)
const arrastrandoId = ref(null);
const columnaDestino = ref(null);

/**
 * Columnas del tablero Kanban de cocina, en el orden del flujo de la comanda.
 * Cada columna define los estados que agrupa y el estado que asigna al soltar una tarjeta.
 */
const COLUMNAS = [
  {
    id: 'pendiente',
    label: 'Pendientes',
    estadoDestino: 'pendiente',
    punto: 'bg-[#2B6CB0]',
    badge: 'bg-blue-100 text-[#2B6CB0]',
  },
  {
    id: 'en_preparacion',
    label: 'En preparación',
    estadoDestino: 'en_preparacion',
    punto: 'bg-[#E0A800]',
    badge: 'bg-amber-100 text-amber-800',
  },
  {
    id: 'lista',
    label: 'Listas para cobro',
    estadoDestino: 'lista',
    punto: 'bg-[#2E7D4F]',
    badge: 'bg-emerald-100 text-[#2E7D4F]',
  },
];

// Control de polling (sondeo cada 3 segundos)
const INTERVALO_POLLING_MS = 3000;
let pollingTimer = null;
const ultimoRefresco = ref(new Date());

/**
 * Emite un sonido agradable de campanilla utilizando Web Audio API sin dependencias externas.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – US-POS-02 / RF-POS-001
 */
function reproducirCampanilla() {
  if (!sonidoHabilitado.value) return;

  try {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext;
    if (!AudioContextClass) return;

    const ctx = new AudioContextClass();
    const osc = ctx.createOscillator();
    const gain = ctx.createGain();

    osc.type = 'sine';
    osc.frequency.setValueAtTime(587.33, ctx.currentTime); // Nota D5
    osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // Nota A5

    gain.gain.setValueAtTime(0.2, ctx.currentTime);
    gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.6);

    osc.connect(gain);
    gain.connect(ctx.destination);

    osc.start();
    osc.stop(ctx.currentTime + 0.6);
  } catch (e) {
    // Los navegadores pueden bloquear audio sin interacción previa
  }
}

/**
 * Consulta la lista de comandas activas y completadas recientes desde el backend.
 * Compara con las existentes para detectar si entró una orden nueva y emitir alerta.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – US-POS-02 / RF-POS-001
 *
 * @param {boolean} esSilencioso Si es true, no muestra indicador de carga completa.
 */
async function cargarComandas(esSilencioso = false) {
  if (!esSilencioso) cargando.value = true;

  try {
    const datos = await obtenerColaCocina();
    
    let activas = [];
    let nuevasCompletadas = [];

    if (datos && typeof datos === 'object' && !Array.isArray(datos)) {
      activas = datos.activas || [];
      nuevasCompletadas = datos.completadas || [];
    } else if (Array.isArray(datos)) {
      activas = datos;
    }

    // Detectar si hay órdenes nuevas que no estaban antes
    if (comandas.value.length > 0 && activas.length > 0) {
      const idsActuales = new Set(comandas.value.map(c => c.id));
      const nuevasOrdenes = activas.filter(c => !idsActuales.has(c.id));

      if (nuevasOrdenes.length > 0) {
        reproducirCampanilla();
        nuevasOrdenes.forEach(nueva => {
          const mesaTexto = nueva.mesa ? `Mesa ${nueva.mesa.numero}` : nueva.canal;
          toast.info(`¡Nueva orden recibida! #${nueva.id}`, {
            description: `${mesaTexto} · ${nueva.detalles?.length || 0} platillo(s)`,
            duration: 5000,
          });
        });
      }
    }

    comandas.value = activas;
    completadas.value = nuevasCompletadas;
    ultimoRefresco.value = new Date();
  } catch (error) {
    if (!esSilencioso) {
      toast.error('Error al cargar la cola de cocina.');
    }
  } finally {
    cargando.value = false;
  }
}

/**
 * Reactiva una orden completada regresándola a pendiente en la cola de cocina.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – US-POS-02 / RF-POS-001
 *
 * @param {number} id Identificador de la comanda a reactivar.
 */
async function manejarReactivarComanda(id) {
  procesandoId.value = id;
  try {
    await actualizarEstadoComanda(id, 'pendiente');
    toast.success(`Orden #${id} reactivada en la cola de cocina.`);
    await cargarComandas(true);
  } catch (error) {
    toast.error('No se pudo reactivar la comanda.');
  } finally {
    procesandoId.value = null;
  }
}

/**
 * Maneja la transición de estado de una comanda y la persiste en la BD:
 * pendiente ↔ en_preparacion → lista. El paso a "pagada" lo hace caja al cobrar.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-12
 * @módulo POS – US-POS-02 / RF-POS-001
 *
 * @param {{ id: number, nuevoEstado: string }} evento Payload del evento emitido por la tarjeta.
 */
async function manejarCambioEstado({ id, nuevoEstado }) {
  procesandoId.value = id;
  try {
    await actualizarEstadoComanda(id, nuevoEstado);

    if (nuevoEstado === 'en_preparacion') {
      toast.success(`Orden #${id} en preparación.`);
    } else if (nuevoEstado === 'lista') {
      toast.success(`Orden #${id} lista. Pasa a Caja para cobro.`, {
        description: 'La comanda está en espera de cobro.',
        duration: 6000,
      });
    } else {
      toast.info(`Orden #${id} actualizada a ${nuevoEstado}.`);
    }

    // Refrescar lista para reorganizar el flujo animado
    await cargarComandas(true);
  } catch (error) {
    toast.error('No se pudo actualizar el estado de la comanda.');
  } finally {
    procesandoId.value = null;
  }
}

// Tarjetas agrupadas por columna; la columna de listas incluye también las ya pagadas
const tarjetasPorColumna = computed(() => ({
  pendiente: comandas.value.filter(c => c.estado === 'pendiente'),
  en_preparacion: comandas.value.filter(c => c.estado === 'en_preparacion'),
  lista: completadas.value,
}));

/**
 * Busca una comanda en las listas activas y completadas por su identificador.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-02 / RF-POS-001
 *
 * @param   {number} id Identificador de la comanda.
 * @returns {Object|undefined} La comanda encontrada o undefined.
 */
function buscarComanda(id) {
  return comandas.value.find(c => c.id === id) || completadas.value.find(c => c.id === id);
}

/**
 * Inicia el arrastre de una tarjeta. Las comandas pagadas ya salieron de cocina y no se mueven.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-02 / RF-POS-001
 *
 * @param {DragEvent} evento  Evento nativo de arrastre.
 * @param {Object}    comanda Comanda que se arrastra.
 */
function iniciarArrastre(evento, comanda) {
  if (comanda.estado === 'pagada' || procesandoId.value) {
    evento.preventDefault();
    return;
  }

  arrastrandoId.value = comanda.id;
  evento.dataTransfer.effectAllowed = 'move';
  evento.dataTransfer.setData('text/plain', String(comanda.id));
}

/**
 * Limpia el estado visual del arrastre al soltar o cancelar.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-02 / RF-POS-001
 */
function terminarArrastre() {
  arrastrandoId.value = null;
  columnaDestino.value = null;
}

/**
 * Suelta una tarjeta sobre una columna y persiste el nuevo estado si cambió.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-02 / RF-POS-001
 *
 * @param {Object} columna Columna del tablero donde se soltó la tarjeta.
 */
async function soltarEnColumna(columna) {
  const comanda = buscarComanda(arrastrandoId.value);
  terminarArrastre();

  if (!comanda || comanda.estado === columna.estadoDestino) return;

  await manejarCambioEstado({ id: comanda.id, nuevoEstado: columna.estadoDestino });
}

const totalListasCobro = computed(() =>
  completadas.value.filter(c => c.estado === 'lista').length
);

// Iniciar y pausar el temporizador de polling
onMounted(() => {
  cargarComandas();
  pollingTimer = setInterval(() => {
    if (autoRefresco.value) {
      cargarComandas(true);
    }
  }, INTERVALO_POLLING_MS);
});

onUnmounted(() => {
  if (pollingTimer) clearInterval(pollingTimer);
});
</script>

<template>
  <div class="flex min-h-[100dvh] flex-col bg-[#FAF7F4]" id="vista-cola-cocina">
    <!-- Toaster de notificaciones shadcn -->
    <Toaster richColors position="top-center" />

    <EncabezadoOperacion
      titulo="Cola de Cocina"
      subtitulo="Comedor Chayito · Órdenes en preparación"
      :icono="ChefHat"
    >
      <template #acciones>
        <!-- Alternar sonido de nuevas comandas -->
        <button
          type="button"
          @click="sonidoHabilitado = !sonidoHabilitado"
          class="flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] transition-all cursor-pointer"
          :class="sonidoHabilitado ? 'bg-amber-50 border-amber-200 text-[#C24E12]' : 'bg-white text-zinc-400'"
          :title="sonidoHabilitado ? 'Sonido de nuevas órdenes activado' : 'Sonido silenciado'"
        >
          <Volume2 v-if="sonidoHabilitado" :size="20" stroke-width="2" />
          <VolumeX v-else :size="20" stroke-width="2" />
        </button>

        <!-- Botón de acceso rápido a Caja con badge de comandas listas -->
        <RouterLink
          v-if="puedeVer(['cajero', 'administrador', 'socio'])"
          to="/caja"
          class="relative flex h-11 items-center gap-2 rounded-xl border border-[#E5E0DB] bg-white px-3.5 text-sm font-semibold text-[#3D3D3D] hover:bg-zinc-50 active:scale-95 transition-all cursor-pointer shadow-xs"
          :class="totalListasCobro > 0 ? 'border-[#2E7D4F] bg-emerald-50 text-[#2E7D4F]' : ''"
          title="Ir a Caja"
        >
          <Receipt :size="16" stroke-width="2.2" />
          <span class="hidden sm:inline">Caja</span>
          <!-- Badge con cantidad de comandas listas -->
          <span
            v-if="totalListasCobro > 0"
            class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-[#2E7D4F] text-white text-[10px] font-black"
          >
            {{ totalListasCobro }}
          </span>
        </RouterLink>

        <!-- Botón de refresco manual con spinner -->
        <button
          type="button"
          :disabled="cargando"
          @click="cargarComandas(false)"
          class="flex h-11 items-center gap-2 rounded-xl border border-[#E5E0DB] bg-white px-3.5 text-sm font-semibold text-[#3D3D3D] hover:bg-zinc-50 active:scale-95 transition-all disabled:opacity-50 cursor-pointer shadow-xs"
          title="Refrescar ahora"
        >
          <RefreshCw :size="16" stroke-width="2.2" :class="{ 'animate-spin': cargando }" />
          <span class="hidden sm:inline">Refrescar</span>
        </button>
      </template>
    </EncabezadoOperacion>

    <!-- Tablero Kanban tipo Jira: una columna por estado de la comanda -->
    <main class="flex-1 overflow-x-auto p-4 md:p-6">
      <div
        class="flex h-full snap-x snap-mandatory gap-4 md:grid md:snap-none md:grid-cols-3"
      >
        <section
          v-for="columna in COLUMNAS"
          :key="columna.id"
          :id="`columna-${columna.id}`"
          class="flex w-[85vw] max-w-sm shrink-0 snap-start flex-col rounded-2xl md:w-auto md:max-w-none"
          :class="columnaDestino === columna.id
            ? 'bg-orange-50 ring-2 ring-[#F26A21]/60'
            : 'bg-[#F0ECE8]'"
          @dragover.prevent="columnaDestino = columna.id"
          @dragleave.self="columnaDestino = null"
          @drop.prevent="soltarEnColumna(columna)"
        >
          <!-- Encabezado de la columna con contador -->
          <header class="flex items-center justify-between px-3.5 pt-3.5 pb-2">
            <div class="flex items-center gap-2">
              <span class="h-2.5 w-2.5 rounded-full" :class="columna.punto"></span>
              <h2 class="text-xs font-bold uppercase tracking-wider text-[#6B6B6B]">
                {{ columna.label }}
              </h2>
            </div>
            <span class="rounded-md px-2 py-0.5 text-xs font-bold" :class="columna.badge">
              {{ tarjetasPorColumna[columna.id].length }}
            </span>
          </header>

          <!-- Skeleton de carga inicial -->
          <div v-if="cargando && comandas.length === 0" class="space-y-3 p-2.5 animate-pulse">
            <div v-for="n in 2" :key="n" class="h-40 rounded-xl bg-zinc-200/80"></div>
          </div>

          <!-- Tarjetas de la columna -->
          <TransitionGroup
            v-else
            name="cola-comandas"
            tag="div"
            class="relative flex min-h-[8rem] flex-1 flex-col gap-3 p-2.5"
          >
            <template v-if="columna.id === 'lista'">
              <div
                v-for="comp in tarjetasPorColumna.lista"
                :key="comp.id"
                :draggable="comp.estado !== 'pagada'"
                :class="{ 'opacity-40': arrastrandoId === comp.id }"
                class="cursor-grab active:cursor-grabbing"
                @dragstart="iniciarArrastre($event, comp)"
                @dragend="terminarArrastre"
              >
                <ComandaCompletadaItem
                  :comanda="comp"
                  :procesando="procesandoId === comp.id"
                  @reactivar="manejarReactivarComanda"
                />
              </div>
            </template>

            <template v-else>
              <div
                v-for="(comanda, index) in tarjetasPorColumna[columna.id]"
                :key="comanda.id"
                draggable="true"
                :class="{ 'opacity-40': arrastrandoId === comanda.id }"
                class="cursor-grab active:cursor-grabbing"
                @dragstart="iniciarArrastre($event, comanda)"
                @dragend="terminarArrastre"
              >
                <ComandaCard
                  :comanda="comanda"
                  :posicion="index + 1"
                  :procesando="procesandoId === comanda.id"
                  @cambiar-estado="manejarCambioEstado"
                />
              </div>
            </template>

            <!-- Columna vacía -->
            <div
              v-if="tarjetasPorColumna[columna.id].length === 0"
              key="vacia"
              class="flex flex-1 flex-col items-center justify-center gap-1.5 rounded-xl border-2 border-dashed border-[#E5E0DB] p-6 text-center text-xs text-[#6B6B6B]"
            >
              <CheckCircle2 :size="22" class="text-zinc-300" />
              <span>Sin comandas</span>
            </div>
          </TransitionGroup>
        </section>
      </div>
    </main>
  </div>
</template>

<style scoped>
/* Animaciones de entrada, salida y reposicionamiento fluido de tarjetas */
.cola-comandas-move,
.cola-comandas-enter-active,
.cola-comandas-leave-active {
  transition: all 0.45s cubic-bezier(0.25, 1, 0.5, 1);
}

.cola-comandas-enter-from {
  opacity: 0;
  transform: translateY(-28px) scale(0.94);
}

.cola-comandas-leave-to {
  opacity: 0;
  transform: translateY(28px) scale(0.92);
}

.cola-comandas-leave-active {
  position: absolute;
}
</style>
