<script setup>
/**
 * Redirección al módulo POS completo en features/pos/views/POS.vue.
 * Este archivo se mantiene para compatibilidad con imports existentes.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 */
import { useRouter } from 'vue-router';

const router = useRouter();
router.replace({ name: 'pos' });
</script>

<template>
  <div></div>
</template>
