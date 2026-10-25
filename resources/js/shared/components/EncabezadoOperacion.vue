<script setup>
/**
 * Encabezado unificado para las pantallas operativas autenticadas.
 * Presenta contexto de la vista y conserva las acciones propias de cada módulo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-25
 * @módulo Core – Integración de navegación
 */
defineProps({
  titulo: {
    type: String,
    required: true,
  },
  subtitulo: {
    type: String,
    default: '',
  },
  icono: {
    type: [Object, Function],
    default: null,
  },
  claseIcono: {
    type: String,
    default: 'bg-orange-100 text-[#F26A21]',
  },
});
</script>

<template>
  <header
    class="sticky top-0 z-30 flex min-h-16 w-full items-center border-b border-[#E5E0DB] bg-white/95 px-4 py-3 shadow-xs backdrop-blur-md sm:px-6"
  >
    <div class="flex w-full flex-wrap items-center justify-between gap-3">
      <div class="flex min-w-0 items-center gap-3">
        <slot name="navegacion" />
        <div
          v-if="icono"
          class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl shadow-xs"
          :class="claseIcono"
        >
          <component :is="icono" :size="20" stroke-width="2" />
        </div>
        <div class="min-w-0">
          <h1 class="truncate text-lg font-bold leading-tight tracking-tight text-[#3D3D3D]">
            {{ titulo }}
          </h1>
          <p v-if="subtitulo" class="truncate text-xs text-[#6B6B6B]">
            {{ subtitulo }}
          </p>
        </div>
      </div>
      <div v-if="$slots.acciones" class="flex flex-wrap items-center gap-2">
        <slot name="acciones" />
      </div>
    </div>
  </header>
</template>
