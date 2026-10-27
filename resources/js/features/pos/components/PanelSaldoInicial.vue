<script setup>
/**
 * Panel lateral de saldo inicial y apertura de caja por denominación (CC-39).
 * Despliega el formulario en una hoja lateral (Sheet) accesible desde la barra superior de caja.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-39)
 */
import { Sheet, SheetContent, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import FormularioSaldoInicial from '@/features/pos/components/FormularioSaldoInicial.vue';

const abierto = defineModel('abierto', { type: Boolean, default: false });

const emit = defineEmits(['apertura-registrada']);

/**
 * Propaga el evento de apertura registrada hacia el componente padre.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 (CC-39)
 *
 * @param {Object} apertura Objeto de apertura devuelto por la API.
 */
function onAperturaRegistrada(apertura) {
  emit('apertura-registrada', apertura);
}
</script>

<template>
  <Sheet v-model:open="abierto">
    <SheetContent
      side="right"
      class="data-[side=right]:w-full data-[side=right]:sm:max-w-md p-0 flex flex-col bg-[#FAF7F4] border-l border-[#E5E0DB]"
    >
      <SheetTitle class="sr-only">Saldo Inicial de Caja</SheetTitle>
      <SheetDescription class="sr-only">
        Formulario para contar billetes y monedas y registrar el saldo inicial del turno.
      </SheetDescription>

      <FormularioSaldoInicial
        @apertura-registrada="onAperturaRegistrada"
        @cerrar="abierto = false"
      />
    </SheetContent>
  </Sheet>
</template>
