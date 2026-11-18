<script setup>
/**
 * Ventana modal reutilizable del panel de catálogos: título, contenido libre y
 * botones de confirmar y cancelar. Sirve para formularios y para confirmaciones.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-03 / CC-110
 */
import { Button } from '@/components/ui/button';

defineProps({
  abierto: { type: Boolean, required: true },
  titulo: { type: String, required: true },
  textoConfirmar: { type: String, default: 'Guardar' },
  procesando: { type: Boolean, default: false },
  peligro: { type: Boolean, default: false },
});

const emit = defineEmits(['confirmar', 'cerrar']);
</script>

<template>
  <div
    v-if="abierto"
    class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4 backdrop-blur-sm"
    @keydown.esc="emit('cerrar')"
  >
    <form
      class="w-full max-w-md space-y-5 rounded-2xl border border-[#E5E0DB] bg-white p-6 shadow-xl"
      role="dialog"
      aria-modal="true"
      :aria-label="titulo"
      @submit.prevent="emit('confirmar')"
    >
      <div class="flex items-center justify-between border-b border-[#E5E0DB] pb-3">
        <h2 class="text-lg font-bold text-[#3D3D3D]">{{ titulo }}</h2>
        <button
          type="button"
          class="text-xl font-bold text-[#6B6B6B] hover:text-[#111111]"
          aria-label="Cerrar"
          @click="emit('cerrar')"
        >
          &times;
        </button>
      </div>

      <slot />

      <div class="flex justify-end gap-2 pt-2">
        <Button
          type="button"
          variant="outline"
          class="h-10 rounded-xl px-4"
          :disabled="procesando"
          @click="emit('cerrar')"
        >
          Cancelar
        </Button>
        <Button
          type="submit"
          class="h-10 rounded-xl px-4 text-white"
          :class="peligro ? 'bg-[#C62828] hover:bg-[#A61F1F]' : 'bg-[#F26A21] hover:bg-[#C24E12]'"
          :disabled="procesando"
        >
          {{ procesando ? 'Procesando…' : textoConfirmar }}
        </Button>
      </div>
    </form>
  </div>
</template>
