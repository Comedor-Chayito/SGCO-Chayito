<script setup>
/**
 * Panel de compras de bodega (CC-58 / US-INV-03).
 * Muestra la alerta roja con los insumos en quiebre de stock que deben comprarse.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-004 / CC-58
 */
import { onMounted } from 'vue';
import { PackageCheck } from '@lucide/vue';
import AlertaStock from '@/features/inventario/components/AlertaStock.vue';
import { useInventarioStore } from '@/features/inventario/stores/useInventarioStore.js';

const store = useInventarioStore();

onMounted(() => store.cargarAlertas());
</script>

<template>
  <div class="min-h-screen bg-[#FAF7F4] p-4 sm:p-6 lg:p-8" id="vista-compras">
    <div class="mb-6">
      <h1 class="text-2xl font-black text-[#3D3D3D]">Panel de compras</h1>
      <p class="mt-0.5 text-sm text-[#6B6B6B]">
        Insumos que llegaron al stock mínimo o se agotaron.
      </p>
    </div>

    <AlertaStock :alertas="store.alertas" :error="store.errorAlertas" />

    <div
      v-if="store.alertas.length === 0 && !store.errorAlertas"
      class="flex flex-col items-center gap-3 py-20 text-center"
    >
      <PackageCheck :size="48" class="text-[#2E7D4F]" />
      <p class="text-base font-bold text-[#3D3D3D]">Sin insumos por comprar.</p>
      <p class="text-sm text-[#6B6B6B]">Todo el stock está por encima del mínimo.</p>
    </div>
  </div>
</template>
