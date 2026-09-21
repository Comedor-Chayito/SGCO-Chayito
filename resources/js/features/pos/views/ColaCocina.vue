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
import { RouterLink, useRouter } from 'vue-router';
import { useSesionStore } from '@/stores/sesion.js';
import {
  ChefHat,
  RefreshCw,
  Flame,
  Clock,
  CheckCircle2,
  Volume2,
  VolumeX,
  ArrowLeft,
  ChevronDown,
  LogOut,
} from '@lucide/vue';
import { Toaster, toast } from 'vue-sonner';
import { obtenerColaCocina, actualizarEstadoComanda } from '@/features/pos/services/posService.js';
import ComandaCard from '@/features/pos/components/ComandaCard.vue';
import ComandaCompletadaItem from '@/features/pos/components/ComandaCompletadaItem.vue';

// Enrutador y Sesión activa
const router = useRouter();
const sesion = useSesionStore();

async function manejarCerrarSesion() {
  await sesion.cerrarSesion();
  router.push('/login');
}

// Estado de comandas
const comandas = ref([]);
const completadas = ref([]);
const cargando = ref(true);
const procesandoId = ref(null);
const filtroEstado = ref('todas'); // 'todas', 'pendiente', 'en_cocina', 'completadas'
const sonidoHabilitado = ref(true);
const autoRefresco = ref(true);

// Control del collapsible de pestañas en móvil (< md)
const tabsAbiertas = ref(false);

/**
 * Definición de las pestañas para reutilizar en el collapsible y la barra desktop.
 * Cada pestaña tiene: id, etiqueta, icono (componente Lucide), color activo y color badge.
 */
const TABS = [
  {
    id: 'todas',
    label: 'Todas activas',
    icon: null,
    colorActivo: 'text-[#F26A21]',
    badgeActivo: 'bg-orange-100 text-[#C24E12]',
    count: () => comandas.value.length,
  },
  {
    id: 'pendiente',
    label: 'Pendientes',
    icon: 'Clock',
    colorActivo: 'text-[#2B6CB0]',
    badgeActivo: 'bg-blue-100 text-[#2B6CB0]',
    count: () => totalPendientes.value,
  },
  {
    id: 'en_cocina',
    label: 'En Preparación',
    icon: 'Flame',
    colorActivo: 'text-amber-700',
    badgeActivo: 'bg-amber-100 text-amber-800',
    count: () => totalEnCocina.value,
  },
  {
    id: 'completadas',
    label: 'Completadas',
    icon: 'CheckCircle2',
    colorActivo: 'text-[#2E7D4F]',
    badgeActivo: 'bg-emerald-100 text-[#2E7D4F]',
    count: () => completadas.value.length,
  },
];

/**
 * Cambia la pestaña activa y cierra el collapsible móvil al seleccionar.
 *
 * @param {string} tabId Identificador de la pestaña seleccionada.
 */
function seleccionarTab(tabId) {
  filtroEstado.value = tabId;
  tabsAbiertas.value = false;
}

// Pestaña activa actual (objeto completo)
const tabActiva = computed(() => TABS.find(t => t.id === filtroEstado.value));

// Control de polling (sondeo cada 6 segundos)
const INTERVALO_POLLING_MS = 6000;
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
 * Maneja la transición de estado de una comanda (ej. pendiente -> en_cocina).
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-19
 * @módulo POS – US-POS-02 / RF-POS-001
 *
 * @param {{ id: number, nuevoEstado: string }} evento Payload del evento emitido por la tarjeta.
 */
async function manejarCambioEstado({ id, nuevoEstado }) {
  procesandoId.value = id;
  try {
    await actualizarEstadoComanda(id, nuevoEstado);

    // Mensaje de éxito según el nuevo estado
    if (nuevoEstado === 'en_cocina') {
      toast.success(`Orden #${id} en preparación.`);
    } else if (nuevoEstado === 'pagada') {
      toast.success(`Orden #${id} marcada como lista y despachada.`);
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

// Comandas filtradas según la pestaña seleccionada
const comandasFiltradas = computed(() => {
  if (filtroEstado.value === 'todas') return comandas.value;
  return comandas.value.filter(c => c.estado === filtroEstado.value);
});

// Contadores para las insignias de pestañas
const totalPendientes = computed(() =>
  comandas.value.filter(c => c.estado === 'pendiente').length
);
const totalEnCocina = computed(() =>
  comandas.value.filter(c => c.estado === 'en_cocina').length
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

    <!-- Barra superior de navegación y control KDS -->
    <header class="sticky top-0 z-30 flex flex-wrap items-center justify-between gap-3 border-b border-[#E5E0DB] bg-white/95 px-4 py-3 backdrop-blur-md shadow-xs">
      <div class="flex items-center gap-3">
        <RouterLink
          to="/pos"
          class="flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] text-[#3D3D3D] hover:bg-zinc-100 active:scale-95 transition-all"
          title="Regresar al Punto de Venta"
        >
          <ArrowLeft :size="20" stroke-width="2" />
        </RouterLink>

        <div class="flex items-center gap-2.5">
          <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-[#F26A21] text-white shadow-xs">
            <ChefHat :size="22" stroke-width="2" />
          </div>
          <div>
            <h1 class="text-lg font-bold leading-tight text-[#3D3D3D] tracking-tight">
              Cola de Cocina
            </h1>
            <p class="text-xs text-[#6B6B6B]">
              Comedor Chayito · Pedidos en tiempo real (FIFO)
            </p>
          </div>
        </div>
      </div>

      <!-- Controles de la pantalla: Sonido, Auto-refresco, Refrescar ahora -->
      <div class="flex items-center gap-2">
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

        <!-- Botón de Cerrar Sesión -->
        <button
          type="button"
          @click="manejarCerrarSesion"
          class="flex h-11 items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 text-sm font-semibold text-rose-600 hover:bg-rose-100 active:scale-95 transition-all cursor-pointer shadow-xs"
          title="Cerrar sesión"
        >
          <LogOut :size="16" />
          <span class="hidden sm:inline">Salir</span>
        </button>
      </div>
    </header>

    <!-- Barra de pestañas y contadores -->
    <div class="border-b border-[#E5E0DB] bg-white">

      <!-- ══════════════════════════════════════════
           MÓVIL (< md): Collapsible — muestra solo la sección activa
           y despliega las demás opciones al tocar.
      ══════════════════════════════════════════ -->
      <div class="md:hidden">
        <!-- Trigger: sección activa + chevron -->
        <button
          type="button"
          id="btn-tabs-collapsible"
          class="w-full flex items-center justify-between px-4 py-3 gap-3 cursor-pointer"
          :aria-expanded="tabsAbiertas"
          aria-controls="panel-tabs-movil"
          @click="tabsAbiertas = !tabsAbiertas"
        >
          <!-- Sección activa actual -->
          <div class="flex items-center gap-2">
            <span
              class="text-xs font-bold px-3 py-1.5 rounded-lg flex items-center gap-2 bg-[#FAF7F4] border border-[#E5E0DB]/80"
              :class="tabActiva?.colorActivo"
            >
              <Clock v-if="tabActiva?.icon === 'Clock'" :size="14" stroke-width="2.2" />
              <Flame v-else-if="tabActiva?.icon === 'Flame'" :size="14" stroke-width="2.2" />
              <CheckCircle2 v-else-if="tabActiva?.icon === 'CheckCircle2'" :size="14" stroke-width="2.2" />
              {{ tabActiva?.label }}
              <span
                class="px-1.5 py-0.5 rounded-md text-[11px] font-bold"
                :class="tabActiva?.badgeActivo"
              >
                {{ tabActiva?.count() }}
              </span>
            </span>
            <span class="text-[10px] text-[#6B6B6B] font-medium">· Toca para cambiar</span>
          </div>

          <!-- Chevron animado -->
          <ChevronDown
            :size="18"
            stroke-width="2.2"
            class="text-[#6B6B6B] transition-transform duration-300 shrink-0"
            :class="{ 'rotate-180': tabsAbiertas }"
          />
        </button>

        <!-- Panel desplegable con las otras pestañas -->
        <Transition
          enter-active-class="transition-all duration-300 ease-out"
          enter-from-class="opacity-0 -translate-y-2"
          enter-to-class="opacity-100 translate-y-0"
          leave-active-class="transition-all duration-200 ease-in"
          leave-from-class="opacity-100 translate-y-0"
          leave-to-class="opacity-0 -translate-y-2"
        >
          <div
            v-if="tabsAbiertas"
            id="panel-tabs-movil"
            class="border-t border-[#E5E0DB] px-4 pb-3 pt-2 space-y-1 bg-[#FAF7F4]"
          >
            <button
              v-for="tab in TABS"
              :key="tab.id"
              type="button"
              class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer"
              :class="filtroEstado === tab.id
                ? `bg-white border border-[#E5E0DB] shadow-xs ${tab.colorActivo}`
                : 'text-[#6B6B6B] hover:bg-white/70 hover:text-[#3D3D3D]'"
              @click="seleccionarTab(tab.id)"
            >
              <span class="flex items-center gap-2">
                <Clock v-if="tab.icon === 'Clock'" :size="14" stroke-width="2.2" />
                <Flame v-else-if="tab.icon === 'Flame'" :size="14" stroke-width="2.2" />
                <CheckCircle2 v-else-if="tab.icon === 'CheckCircle2'" :size="14" stroke-width="2.2" />
                {{ tab.label }}
              </span>
              <span
                class="px-1.5 py-0.5 rounded-md text-[11px] font-bold"
                :class="filtroEstado === tab.id ? tab.badgeActivo : 'bg-zinc-200 text-zinc-600'"
              >
                {{ tab.count() }}
              </span>
            </button>
          </div>
        </Transition>
      </div>

      <!-- ══════════════════════════════════════════
           DESKTOP (md+): Barra horizontal de pestañas
      ══════════════════════════════════════════ -->
      <div class="hidden md:flex items-center px-4 py-2.5">
        <div class="flex items-center gap-1.5 p-1 bg-[#FAF7F4] rounded-xl border border-[#E5E0DB]/80">
          <button
            v-for="tab in TABS"
            :key="tab.id"
            type="button"
            class="min-h-[38px] px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer flex items-center gap-2"
            :class="filtroEstado === tab.id
              ? `bg-white shadow-xs ${tab.colorActivo}`
              : 'text-[#6B6B6B] hover:text-[#3D3D3D]'"
            @click="filtroEstado = tab.id"
          >
            <Clock v-if="tab.icon === 'Clock'" :size="14" stroke-width="2.2" />
            <Flame v-else-if="tab.icon === 'Flame'" :size="14" stroke-width="2.2" />
            <CheckCircle2 v-else-if="tab.icon === 'CheckCircle2'" :size="14" stroke-width="2.2" />
            <span>{{ tab.label }}</span>
            <span
              class="px-1.5 py-0.5 rounded-md text-[11px] font-bold"
              :class="filtroEstado === tab.id ? tab.badgeActivo : 'bg-zinc-200 text-zinc-600'"
            >
              {{ tab.count() }}
            </span>
          </button>
        </div>
      </div>

    </div>

    <!-- Área principal de contenido -->
    <main class="flex-1 p-4 md:p-6 overflow-y-auto">
      <!-- VISTA A: Pestaña de Órdenes Completadas (1 línea compacta + desplegable tipo card simple) -->
      <div v-if="filtroEstado === 'completadas'" class="max-w-4xl mx-auto space-y-3">
        <div class="flex items-center justify-between pb-2 border-b border-[#E5E0DB]">
          <div class="flex items-center gap-2">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-100 text-[#2E7D4F]">
              <CheckCircle2 :size="16" stroke-width="2.5" />
            </span>
            <div>
              <h2 class="font-bold text-sm text-[#3D3D3D]">Historial de Comandas Completadas</h2>
              <p class="text-[11px] text-[#6B6B6B]">Pulsa "Info" para desplegar la información completa de cada orden</p>
            </div>
          </div>
          <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-[#2E7D4F]">
            {{ completadas.length }} completada(s)
          </span>
        </div>

        <!-- Skeleton de completadas -->
        <div v-if="cargando && completadas.length === 0" class="space-y-2 animate-pulse">
          <div v-for="n in 4" :key="n" class="h-12 bg-zinc-200 rounded-xl"></div>
        </div>

        <!-- Estado vacío si no hay completadas -->
        <div
          v-else-if="completadas.length === 0"
          class="text-center py-16 text-zinc-400 text-xs flex flex-col items-center gap-2 bg-white rounded-2xl border border-[#E5E0DB] p-8"
        >
          <CheckCircle2 :size="36" class="text-zinc-300" />
          <span class="text-sm font-semibold text-zinc-600">Aún no hay órdenes completadas recientemente</span>
          <p class="text-zinc-400 max-w-xs">A medida que marques comandas como listas en la cocina, se registrarán aquí.</p>
        </div>

        <!-- Lista en 1 sola línea por comanda con despliegue de card simple -->
        <div v-else class="space-y-2">
          <ComandaCompletadaItem
            v-for="comp in completadas"
            :key="comp.id"
            :comanda="comp"
            :procesando="procesandoId === comp.id"
            @reactivar="manejarReactivarComanda"
          />
        </div>
      </div>

      <!-- VISTA B: Pestañas de Comandas Activas (Todas, Pendientes, En Preparación) -->
      <template v-else>
        <!-- Skeleton de carga inicial -->
        <div v-if="cargando && comandas.length === 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 animate-pulse">
          <div v-for="n in 3" :key="n" class="h-64 rounded-2xl bg-zinc-200/80 border border-zinc-300/60"></div>
        </div>

        <!-- Estado vacío: Sin órdenes pendientes -->
        <div
          v-else-if="comandasFiltradas.length === 0"
          class="flex min-h-[50vh] flex-col items-center justify-center text-center p-8"
        >
          <div class="flex h-20 w-20 items-center justify-center rounded-3xl bg-white border border-[#E5E0DB] shadow-xs text-[#2E7D4F] mb-4">
            <CheckCircle2 :size="42" stroke-width="1.8" />
          </div>
          <h3 class="text-xl font-bold text-[#3D3D3D] tracking-tight">
            ¡Cocina al día!
          </h3>
          <p class="text-sm text-[#6B6B6B] max-w-sm mt-1">
            No hay órdenes pendientes en este momento. Las nuevas comandas enviadas desde las mesas aparecerán aquí automáticamente.
          </p>
        </div>

        <!-- Grid con animación deslizante (TransitionGroup) -->
        <TransitionGroup
          v-else
          name="cola-comandas"
          tag="div"
          class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5 items-start"
        >
          <ComandaCard
            v-for="(comanda, index) in comandasFiltradas"
            :key="comanda.id"
            :comanda="comanda"
            :posicion="index + 1"
            :procesando="procesandoId === comanda.id"
            @cambiar-estado="manejarCambioEstado"
          />
        </TransitionGroup>
      </template>
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
