<script setup>
/**
 * Panel lateral de retiros parciales del turno para la pantalla de caja (CC-37).
 * Contiene el formulario de registro y la lista de retiros del día con su total.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
 */
import { ref, computed, watch } from 'vue';
import { Wallet } from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import FormularioRetiro from '@/features/pos/components/FormularioRetiro.vue';
import { obtenerRetirosTurno } from '@/features/pos/services/cajaService.js';

const abierto = defineModel('abierto', { type: Boolean, default: false });

const retiros = ref([]);
const cargando = ref(false);

const totalRetiros = computed(() =>
  Math.round(retiros.value.reduce((suma, r) => suma + Number(r.monto), 0) * 100) / 100
);

/**
 * Consulta en el backend los retiros del turno actual del cajero.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
 *
 * @returns {Promise<void>}
 */
async function cargarRetiros() {
  cargando.value = true;
  try {
    retiros.value = await obtenerRetirosTurno();
  } catch {
    toast.error('No se pudieron cargar los retiros del turno.');
  } finally {
    cargando.value = false;
  }
}

/**
 * Agrega el retiro recién registrado al inicio de la lista y avisa al cajero.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
 *
 * @param {Object} retiro Retiro devuelto por la API.
 */
function agregarRetiro(retiro) {
  retiros.value = [retiro, ...retiros.value];
  toast.success(`Retiro de ${formatearMoneda(retiro.monto)} registrado.`);
}

/**
 * Formatea un número como moneda en dólares.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
 *
 * @param   {number|string} valor Valor a formatear.
 * @returns {string} Cadena formateada (ej. "$12.50").
 */
function formatearMoneda(valor) {
  return `$${Number(valor).toFixed(2)}`;
}

/**
 * Devuelve la hora local (HH:MM) de una fecha ISO.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-14
 * @módulo POS – US-POS-03 / RF-POS-002 (CC-37)
 *
 * @param   {string} fechaIso Cadena de fecha ISO 8601.
 * @returns {string} Hora formateada.
 */
function formatearHora(fechaIso) {
  return new Date(fechaIso).toLocaleTimeString('es-SV', { hour: '2-digit', minute: '2-digit' });
}

watch(abierto, (estaAbierto) => {
  if (estaAbierto) cargarRetiros();
});
</script>

<template>
  <Sheet v-model:open="abierto">
    <SheetContent side="right" class="w-full sm:max-w-md p-0 flex flex-col bg-[#FAF7F4]">
      <SheetTitle class="sr-only">Retiros del turno</SheetTitle>
      <SheetDescription class="sr-only">
        Registra un retiro de efectivo y revisa los retiros del día.
      </SheetDescription>

      <div class="flex-1 overflow-y-auto p-4 space-y-5">
        <FormularioRetiro @retiro-registrado="agregarRetiro" />

        <!-- Retiros del turno -->
        <section class="bg-white rounded-2xl border border-[#E5E0DB] overflow-hidden">
          <header class="flex items-center justify-between px-4 py-3 border-b border-[#E5E0DB]">
            <div class="flex items-center gap-2">
              <Wallet :size="16" class="text-[#3D3D3D]" stroke-width="2" />
              <h3 class="text-sm font-bold text-[#3D3D3D]">Retiros de hoy</h3>
            </div>
            <span class="text-sm font-bold text-[#C62828]">
              Total: {{ formatearMoneda(totalRetiros) }}
            </span>
          </header>

          <p v-if="cargando" class="px-4 py-6 text-center text-sm text-[#6B6B6B]">
            Cargando retiros...
          </p>
          <p v-else-if="retiros.length === 0" class="px-4 py-6 text-center text-sm text-[#6B6B6B]">
            Aún no hay retiros en este turno.
          </p>
          <ul v-else class="divide-y divide-[#E5E0DB]/60">
            <li
              v-for="retiro in retiros"
              :key="retiro.id"
              class="flex items-center justify-between gap-3 px-4 py-3 text-sm"
            >
              <div class="min-w-0">
                <p class="font-medium text-[#3D3D3D] truncate">{{ retiro.motivo }}</p>
                <p class="text-xs text-[#6B6B6B]">{{ formatearHora(retiro.created_at) }}</p>
              </div>
              <span class="shrink-0 font-semibold text-[#C62828]">
                −{{ formatearMoneda(retiro.monto) }}
              </span>
            </li>
          </ul>
        </section>
      </div>
    </SheetContent>
  </Sheet>
</template>
