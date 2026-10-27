<script setup>
/**
 * Vista de Apertura de Caja y Saldo Inicial por Denominación (CC-39).
 * Permite al cajero o administrador contar el efectivo inicial del turno
 * en pantalla completa o desde dispositivos móviles.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 / RF-POS-002 (CC-39)
 */
import { ArrowLeft } from '@lucide/vue';
import { useRouter } from 'vue-router';
import { Toaster } from 'vue-sonner';
import FormularioSaldoInicial from '@/features/pos/components/FormularioSaldoInicial.vue';

const router = useRouter();

/**
 * Regresa a la pantalla de cobro.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-10-15
 * @módulo POS – US-POS-04 (CC-39)
 */
function volverACaja() {
  router.push('/caja');
}
</script>

<template>
  <div class="min-h-[100dvh] flex flex-col bg-[#FAF7F4]" id="vista-apertura-caja">
    <Toaster richColors position="top-center" />

    <!-- Barra superior de navegación -->
    <header class="sticky top-[var(--alto-shell,0px)] z-10 flex items-center justify-between border-b border-[#E5E0DB] bg-white px-4 py-3 shadow-xs">
      <div class="flex items-center gap-3">
        <button
          type="button"
          id="btn-regresar-caja"
          @click="volverACaja"
          class="flex h-11 w-11 items-center justify-center rounded-xl border border-[#E5E0DB] bg-[#FAF7F4] text-[#3D3D3D] hover:bg-zinc-100 active:scale-95 transition-all cursor-pointer"
          title="Regresar a Caja"
        >
          <ArrowLeft :size="20" stroke-width="2" />
        </button>
        <div>
          <h1 class="text-lg font-bold leading-tight text-[#3D3D3D]">Apertura de Caja</h1>
          <p class="text-xs text-[#6B6B6B]">Comedor Chayito · Saldo Inicial</p>
        </div>
      </div>
    </header>

    <!-- Contenido principal -->
    <main class="flex-1 flex justify-center p-0 sm:p-4 md:p-6 overflow-y-auto">
      <div class="w-full max-w-xl bg-white sm:rounded-2xl sm:border border-[#E5E0DB] sm:shadow-xs overflow-hidden flex flex-col">
        <FormularioSaldoInicial @apertura-registrada="volverACaja" @cerrar="volverACaja" />
      </div>
    </main>
  </div>
</template>
