<script setup>
/**
 * Vista de Contabilidad — egresos e IVA crédito fiscal.
 * Accesible para contadora y administrador.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Contabilidad – US-CON-01 / RF-CON-005
 */
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { Toaster, toast } from 'vue-sonner';
import { Landmark, ArrowLeft } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import FormularioEgreso from '@/features/contabilidad/components/FormularioEgreso.vue';
import ConfirmacionEgreso from '@/features/contabilidad/components/ConfirmacionEgreso.vue';
import HistorialEgresos from '@/features/contabilidad/components/HistorialEgresos.vue';
import EncabezadoOperacion from '@/shared/components/EncabezadoOperacion.vue';

const router = useRouter();
const ultimoEgreso = ref(null);
const historialEgresos = ref(null);

/**
 * Confirma el registro de un egreso con un aviso breve y la tarjeta con su correlativo,
 * y recarga el historial para que el egreso nuevo aparezca en la tabla y en los totales.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Contabilidad – RF-CON-005 (CC-64) / RF-CON-006 (CC-69)
 *
 * @param   {Object} egreso Egreso creado, con correlativo y subcategoría.
 * @returns {void}
 */
function confirmarEgreso(egreso) {
  ultimoEgreso.value = egreso;
  toast.success(`Egreso ${egreso.correlativo} registrado`);
  historialEgresos.value?.recargar();
}

</script>

<template>
  <main class="min-h-screen bg-[#FDFBF7] text-[#2D2D2D]" id="vista-contabilidad">
    <!-- Toaster de notificaciones shadcn (vue-sonner) -->
    <Toaster richColors position="top-center" />

    <EncabezadoOperacion
      titulo="Contabilidad y Finanzas"
      subtitulo="Cortes de caja, egresos e IVA crédito fiscal"
      :icono="Landmark"
      clase-icono="bg-emerald-100 text-emerald-600"
    >
      <template #navegacion>
        <Button
          variant="ghost"
          size="icon"
          class="rounded-xl hover:bg-zinc-100"
          title="Volver"
          @click="router.back()"
        >
          <ArrowLeft :size="20" />
        </Button>
      </template>
    </EncabezadoOperacion>

    <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6 lg:p-8">

      <!-- Confirmación del último egreso registrado (CC-64 / US-CON-01) -->
      <ConfirmacionEgreso
        v-if="ultimoEgreso"
        :egreso="ultimoEgreso"
        @cerrar="ultimoEgreso = null"
      />

      <!-- Carga rápida de facturas de proveedores (CC-62 / US-CON-01) -->
      <FormularioEgreso @egreso-registrado="confirmarEgreso" />

      <!-- Historial con desglose de IVA y totales del mes (CC-69 / US-CON-02) -->
      <HistorialEgresos ref="historialEgresos" />

    </div>
  </main>
</template>
