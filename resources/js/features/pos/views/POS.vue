<script setup>
/**
 * Vista principal del Punto de Venta (POS) — Pantalla táctil.
 * Orquesta el Wizard de pasos (Configuración -> Menú) con soporte Mobile-First
 * y componentes premium shadcn-vue.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-18
 * @módulo POS – US-POS-01 / RF-POS-001
 */
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { useCarritoStore } from '@/features/pos/stores/useCarritoStore.js';
import { obtenerPlatillos, obtenerMesas } from '@/features/pos/services/posService.js';
import { ChefHat, ShoppingCart, Check } from '@lucide/vue';
import { Toaster, toast } from 'vue-sonner';

// Componentes UI
import PasoConfiguracion from '@/features/pos/components/PasoConfiguracion.vue';
import PasoMenu from '@/features/pos/components/PasoMenu.vue';
import CarritoContenido from '@/features/pos/components/CarritoContenido.vue';
import { Sheet, SheetContent, SheetTrigger, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

const carrito = useCarritoStore();

const platillos = ref([]);
const mesas = ref([]);
const cargandoDatos = ref(true);
const sheetAbierto = ref(false);

// Intervalo (ms) con el que se revisa el menú mientras la vista está abierta.
const INTERVALO_REFRESCO_MENU = 60000;
let temporizadorMenu = null;

/**
 * Vuelve a pedir el menú vigente y reconcilia el carrito con él. Avisa al operador de
 * cualquier precio cambiado o platillo retirado para que no confirme un importe obsoleto.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-17
 * @módulo POS – RF-POS-001 / US-ADM-03 (CC-104, CC-106)
 *
 * @returns {Promise<boolean|null>} true si hubo cambios en el carrito, false si no,
 *                                  null si no se pudo consultar el menú.
 */
async function sincronizarMenu() {
  let menu;
  try {
    menu = await obtenerPlatillos();
  } catch (error) {
    return null;
  }

  platillos.value = menu;
  const cambios = carrito.reconciliarConMenu(menu);

  cambios.forEach((cambio) => {
    if (cambio.tipo === 'precio') {
      toast.warning(`El precio de ${cambio.nombre} cambió`, {
        description: `Antes $${cambio.anterior.toFixed(2)}, ahora $${cambio.nuevo.toFixed(2)}.`,
        duration: 6000,
      });
    } else {
      toast.warning(`${cambio.nombre} ya no está disponible`, {
        description: 'Se quitó de la orden actual.',
        duration: 6000,
      });
    }
  });

  return cambios.length > 0;
}

function alVolverAVerLaPantalla() {
  if (document.visibilityState === 'visible') sincronizarMenu();
}

onMounted(async () => {
  try {
    [platillos.value, mesas.value] = await Promise.all([
      obtenerPlatillos(),
      obtenerMesas(),
    ]);
  } catch (error) {
    toast.error('Error al cargar datos iniciales del servidor.');
  } finally {
    cargandoDatos.value = false;
  }

  temporizadorMenu = setInterval(sincronizarMenu, INTERVALO_REFRESCO_MENU);
  document.addEventListener('visibilitychange', alVolverAVerLaPantalla);
});

onUnmounted(() => {
  clearInterval(temporizadorMenu);
  document.removeEventListener('visibilitychange', alVolverAVerLaPantalla);
});

// Al iniciar una orden nueva (paso 2) se parte siempre del menú vigente
watch(() => carrito.pasoActual, (paso) => {
  if (paso === 2) sincronizarMenu();
});

// Resetea mesaId si se cambia el canal de forma retroactiva
watch(() => carrito.canal, (nuevoCanal) => {
  if (nuevoCanal !== 'mesa') carrito.mesaId = null;
});

// Exclusión de reentrada: se activa antes del primer await y se libera en finally,
// para que un doble clic (o el botón del carrito móvil y el de escritorio) registre una sola comanda.
let confirmando = false;

/**
 * Confirma la orden: verifica el menú vigente, registra la comanda enviando el precio esperado
 * de cada ítem y, si el servidor detecta que un precio cambió, refresca el carrito sin registrar.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-11-17
 * @módulo POS – RF-POS-001 / US-ADM-03 (CC-104, CC-106)
 *
 * @returns {Promise<void>}
 */
async function confirmarComanda() {
  if (confirmando) return;
  confirmando = true;
  carrito.cargando = true;

  try {
    // Antes de registrar se verifica el menú: si cambió un precio o un platillo se retiró,
    // el operador revisa la orden actualizada en lugar de confirmar un importe obsoleto.
    const huboCambios = await sincronizarMenu();

    if (huboCambios === null) {
      toast.error('No se pudo verificar el menú vigente. Revisa la conexión e intenta de nuevo.');
      return;
    }

    if (huboCambios) {
      toast.info('Revisa la orden actualizada y vuelve a confirmarla.', { duration: 6000 });
      return;
    }

    const comanda = await carrito.enviarComanda();

    if (comanda) {
      sheetAbierto.value = false; // Cierra el sheet si estaba abierto en móvil
      toast.success(`Comanda #${comanda.id} registrada`, {
        description: 'La orden ya está en la cola de cocina.',
        duration: 4000,
      });
    } else if (carrito.requiereRevision) {
      // El precio cambió entre la verificación y el registro: el servidor no creó la comanda.
      await sincronizarMenu();
      toast.warning('Un precio cambió mientras se registraba la orden.', {
        description: 'Revisa el ticket actualizado y vuelve a confirmarla.',
        duration: 6000,
      });
    } else if (carrito.error) {
      toast.error(carrito.error);
    }
  } finally {
    carrito.cargando = false;
    confirmando = false;
  }
}

function agregarPlatoAlTicket() {
  if (carrito.platoEnConstruccion.length === 0) return;
  carrito.agregarPlato(carrito.platoEnConstruccion);
  carrito.vaciarPlato();
  toast.success('Plato agregado al ticket', { duration: 2000 });
}
</script>

<template>
  <div class="flex min-h-[100dvh] flex-col bg-[#FAF7F4]" id="vista-pos">
    <!-- Toaster de notificaciones shadcn (vue-sonner) -->
    <Toaster richColors position="top-center" />

    <!-- Skeleton Base de Carga Inicial -->
    <div v-if="cargandoDatos" class="flex min-h-[100dvh] items-center justify-center bg-[#FAF7F4]">
      <div class="flex flex-col items-center gap-4 animate-pulse">
        <div class="w-16 h-16 bg-zinc-200 text-zinc-500 rounded-full flex items-center justify-center">
          <ChefHat :size="32" stroke-width="1.5" />
        </div>
        <div class="h-5 w-32 bg-zinc-200 rounded-md"></div>
      </div>
    </div>

    <!-- Wizard Orquestador -->
    <template v-else>
      
      <!-- ── PASO 1: Configuración (Canal/Mesa) ── -->
      <PasoConfiguracion 
        v-if="carrito.pasoActual === 1" 
        :mesas="mesas" 
      />

      <!-- ── PASO 2: Menú y Carrito ── -->
      <div v-else-if="carrito.pasoActual === 2" class="flex h-[calc(100dvh-var(--alto-shell,0px))] min-h-0 flex-1 overflow-hidden">
        
        <!-- Zona Menú (Izquierda) -->
        <div class="relative min-w-0 flex-1 overflow-hidden">
          <PasoMenu :platillos="platillos" :mesas="mesas" />
          
          <!-- Dock de acciones contenido dentro del área de menú -->
          <div class="pointer-events-none absolute inset-x-3 bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-30 flex items-end justify-end gap-2 sm:inset-x-4">
            
            <!-- Botón Confirmar Plato (Visible solo si hay ítems armándose) -->
            <div 
              class="pointer-events-auto min-w-0 flex-1 transition-[transform,opacity] duration-200 md:flex-none"
              :class="carrito.platoEnConstruccion.length > 0 ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0 pointer-events-none'"
            >
              <Button
                data-testid="agregar-plato"
                class="h-14 w-full rounded-xl bg-[#C24E12] px-4 text-base font-black text-white shadow-[0_12px_30px_-14px_rgba(194,78,18,0.8)] transition-[transform,background-color,box-shadow] hover:bg-[#A9400E] hover:shadow-[0_14px_34px_-16px_rgba(194,78,18,0.9)] active:scale-[0.98] md:w-auto md:px-5"
                @click="agregarPlatoAlTicket"
              >
                <Check class="size-5 shrink-0" :stroke-width="2.5" />
                <span class="truncate">Agregar plato</span>
                <span class="ml-1 shrink-0 border-l border-white/25 pl-3 tabular-nums">
                  ${{ carrito.subtotalPlato.toFixed(2) }}
                </span>
              </Button>
            </div>

            <!-- Botón Flotante Carrito (FAB) (Solo Móvil) -->
            <div class="md:hidden pointer-events-auto">
              <Sheet v-model:open="sheetAbierto">
                <SheetTrigger as-child>
                  <Button
                    data-testid="abrir-ticket"
                    aria-label="Abrir ticket actual"
                    class="relative flex size-14 items-center justify-center rounded-xl bg-zinc-900 text-white shadow-[0_12px_30px_-14px_rgba(24,24,27,0.85)] transition-[transform,background-color] hover:bg-zinc-800 active:scale-[0.98]"
                  >
                    <ShoppingCart class="size-5 text-white" :stroke-width="2.25" />
                    <Badge v-if="carrito.totalItems > 0" class="absolute -right-1.5 -top-1.5 min-w-6 rounded-lg border-2 border-zinc-900 bg-[#F26A21] px-1.5 py-0.5 text-center text-[11px] font-black text-white hover:bg-[#F26A21]">
                      {{ carrito.totalItems }}
                    </Badge>
                  </Button>
                </SheetTrigger>
                <SheetContent side="bottom" class="h-[85vh] p-0 flex flex-col rounded-t-[2rem]">
                  <SheetTitle class="sr-only">Carrito de Compras</SheetTitle>
                  <SheetDescription class="sr-only">Revisa y confirma tu orden actual.</SheetDescription>
                  <CarritoContenido @confirmar="confirmarComanda" />
                </SheetContent>
              </Sheet>
            </div>
            
          </div>
        </div>

        <!-- Zona Carrito (Derecha, Solo Desktop) -->
        <aside class="z-20 hidden w-[340px] shrink-0 flex-col border-l border-[#E5E0DB] bg-white shadow-[-4px_0_24px_-12px_rgba(61,61,61,0.14)] md:flex lg:w-[400px] xl:w-[420px]">
          <CarritoContenido @confirmar="confirmarComanda" />
        </aside>

      </div>
    </template>
  </div>
</template>
