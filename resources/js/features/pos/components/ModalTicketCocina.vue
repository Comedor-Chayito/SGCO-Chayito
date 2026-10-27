<script setup>
/**
 * Modal de previsualización e impresión de ticket térmico de comanda para cocina.
 * Soporta anchos de 58 mm (32 columnas) y 80 mm (48 columnas) con formato continuo.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo POS – US-POS-02 / RF-POS-001 / Sec. 3.1.2
 */
import { ref, watch } from 'vue';
import { Printer, Copy, Check, X, RefreshCw, FileText } from '@lucide/vue';
import { obtenerTicketImpresion } from '@/features/pos/services/posService.js';
import { Button } from '@/components/ui/button';
import { toast } from 'vue-sonner';

const props = defineProps({
  abierto: {
    type: Boolean,
    default: false,
  },
  comandaId: {
    type: Number,
    default: null,
  },
});

const emit = defineEmits(['cerrar']);

const anchoMm = ref(58);
const cargando = ref(false);
const ticketData = ref(null);
const copiado = ref(false);

/**
 * Carga los datos del ticket desde el backend con el ancho seleccionado.
 */
async function cargarTicket() {
  if (!props.comandaId) return;
  cargando.value = true;
  try {
    ticketData.value = await obtenerTicketImpresion(props.comandaId, anchoMm.value);
  } catch (error) {
    toast.error('No se pudo generar el formato del ticket térmico.');
  } finally {
    cargando.value = false;
  }
}

watch(
  () => [props.abierto, props.comandaId, anchoMm.value],
  ([abierto, id]) => {
    if (abierto && id) {
      cargarTicket();
    }
  },
  { immediate: true }
);

/**
 * Copia el texto plano del ticket al portapapeles.
 */
async function copiarTextoPlano() {
  if (!ticketData.value?.texto_plano) return;
  try {
    await navigator.clipboard.writeText(ticketData.value.texto_plano);
    copiado.value = true;
    toast.success('Texto del ticket copiado al portapapeles');
    setTimeout(() => {
      copiado.value = false;
    }, 2000);
  } catch (e) {
    toast.error('No se pudo copiar el texto');
  }
}

/**
 * Dispara la impresión térmica nativa mediante ventana emergente aislada sin márgenes de navegador.
 */
function imprimirNativo() {
  if (!ticketData.value?.texto_plano) return;

  const anchoCss = anchoMm.value === 80 ? '72mm' : '52mm';
  const ventanaImpresion = window.open('', '_blank', 'width=350,height=600');

  if (!ventanaImpresion) {
    toast.error('Por favor permite las ventanas emergentes para imprimir.');
    return;
  }

  const html = `
    <!DOCTYPE html>
    <html>
      <head>
        <meta charset="utf-8">
        <title>Ticket Cocina #${props.comandaId}</title>
        <style>
          @page {
            size: ${anchoMm.value}mm auto;
            margin: 0;
          }
          @media print {
            body {
              width: ${anchoCss};
              margin: 0;
              padding: 2mm;
            }
          }
          body {
            font-family: 'Courier New', Courier, monospace;
            font-size: ${anchoMm.value === 80 ? '12px' : '11px'};
            line-height: 1.25;
            color: #000;
            white-space: pre-wrap;
            word-break: break-all;
            margin: 0;
            padding: 4mm;
            background: #fff;
          }
        </style>
      </head>
      <body>${ticketData.value.texto_plano}</body>
    </html>
  `;

  ventanaImpresion.document.open();
  ventanaImpresion.document.write(html);
  ventanaImpresion.document.close();

  ventanaImpresion.focus();
  setTimeout(() => {
    ventanaImpresion.print();
    ventanaImpresion.close();
  }, 250);
}
</script>

<template>
  <div
    v-if="abierto"
    class="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex items-center justify-center p-3 sm:p-4 animate-in fade-in duration-150"
    id="modal-ticket-cocina"
  >
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-zinc-200 flex flex-col max-h-[90vh] overflow-hidden animate-in zoom-in-95 duration-150">
      
      <!-- Cabecera del Modal -->
      <div class="flex items-center justify-between p-4 border-b border-zinc-100 bg-zinc-50/60">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-lg bg-orange-100 text-[#F26A21] flex items-center justify-center">
            <Printer :size="18" />
          </div>
          <div>
            <h3 class="text-sm font-bold text-zinc-900 leading-tight">
              Impresión Térmica · Orden #{{ comandaId }}
            </h3>
            <p class="text-[11px] text-zinc-500">
              Formato comanda de preparación para cocina
            </p>
          </div>
        </div>

        <button
          type="button"
          @click="emit('cerrar')"
          class="text-zinc-400 hover:text-zinc-700 p-1.5 rounded-lg hover:bg-zinc-100 transition-colors"
          title="Cerrar modal"
        >
          <X :size="18" />
        </button>
      </div>

      <!-- Selector de Ancho de Papel Térmico (58 mm / 80 mm) -->
      <div class="px-4 py-2.5 bg-zinc-100/70 border-b border-zinc-200/80 flex items-center justify-between gap-2">
        <span class="text-xs font-semibold text-zinc-600">Ancho de papel:</span>
        <div class="inline-flex rounded-lg bg-zinc-200/80 p-0.5 text-xs font-semibold">
          <button
            type="button"
            @click="anchoMm = 58"
            class="px-3 py-1 rounded-md transition-all cursor-pointer"
            :class="anchoMm === 58 ? 'bg-white text-zinc-900 shadow-xs' : 'text-zinc-500 hover:text-zinc-800'"
          >
            58 mm (32 cols)
          </button>
          <button
            type="button"
            @click="anchoMm = 80"
            class="px-3 py-1 rounded-md transition-all cursor-pointer"
            :class="anchoMm === 80 ? 'bg-white text-zinc-900 shadow-xs' : 'text-zinc-500 hover:text-zinc-800'"
          >
            80 mm (48 cols)
          </button>
        </div>
      </div>

      <!-- Vista Previa de Ticket Térmico -->
      <div class="p-4 flex-1 overflow-y-auto bg-zinc-100/50 flex justify-center">
        <!-- Indicador de carga -->
        <div v-if="cargando" class="py-16 flex flex-col items-center justify-center gap-2 text-zinc-400">
          <RefreshCw :size="24" class="animate-spin text-[#F26A21]" />
          <span class="text-xs">Generando ticket térmico...</span>
        </div>

        <!-- Papel térmico renderizado -->
        <div
          v-else
          class="bg-white p-4 rounded-lg shadow-md border border-zinc-300/80 font-mono text-xs leading-relaxed text-zinc-900 select-all transition-all"
          :class="anchoMm === 80 ? 'w-full max-w-[340px]' : 'w-full max-w-[270px]'"
          style="box-shadow: 0 4px 20px -2px rgba(0,0,0,0.08);"
        >
          <pre class="whitespace-pre-wrap break-words font-mono text-[11px] leading-[1.3] text-zinc-900 m-0">{{ ticketData?.texto_plano }}</pre>

          <!-- Efecto de corte de papel dentado -->
          <div class="mt-4 pt-2 border-t border-dashed border-zinc-300 text-center text-[10px] text-zinc-400 font-sans">
            ✄ Corte de papel térmico (ESC/POS)
          </div>
        </div>
      </div>

      <!-- Acciones Inferiores -->
      <div class="p-4 border-t border-zinc-100 bg-white flex items-center justify-between gap-2">
        <Button
          type="button"
          variant="outline"
          size="sm"
          class="rounded-xl text-zinc-600 border-zinc-200 hover:bg-zinc-50 flex items-center gap-1.5 text-xs"
          @click="copiarTextoPlano"
          :disabled="cargando || !ticketData"
          title="Copiar texto plano al portapapeles"
        >
          <Check v-if="copiado" :size="14" class="text-emerald-600" />
          <Copy v-else :size="14" />
          <span>{{ copiado ? 'Copiado' : 'Copiar' }}</span>
        </Button>

        <div class="flex items-center gap-2">
          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="rounded-xl text-zinc-500 hover:bg-zinc-100 text-xs"
            @click="emit('cerrar')"
          >
            Cerrar
          </Button>

          <Button
            type="button"
            size="sm"
            class="rounded-xl bg-[#F26A21] hover:bg-[#FF8C42] text-white flex items-center gap-2 font-semibold text-xs px-4"
            @click="imprimirNativo"
            :disabled="cargando || !ticketData"
          >
            <Printer :size="15" />
            <span>Imprimir Ticket</span>
          </Button>
        </div>
      </div>

    </div>
  </div>
</template>

