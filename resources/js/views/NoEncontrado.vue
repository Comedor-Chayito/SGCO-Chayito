<script setup>
/**
 * Vista 404 — ruta no encontrada.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Core
 */
</script>

<template>
  <main class="no-encontrado" id="vista-no-encontrado">
    <h1>404</h1>
    <p>Página no encontrada.</p>
    <RouterLink to="/">Volver al inicio</RouterLink>
  </main>
</template>

<style scoped>
.no-encontrado {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 1rem;
  color: var(--color-carbon);
}

.no-encontrado h1 {
  font-size: 6rem;
  font-weight: 800;
  color: var(--color-naranja-primario);
  margin: 0;
}

.no-encontrado a {
  color: var(--color-naranja-primario);
  font-weight: 600;
  text-decoration: none;
}

.no-encontrado a:hover {
  color: var(--color-naranja-oscuro);
  text-decoration: underline;
}
</style>
