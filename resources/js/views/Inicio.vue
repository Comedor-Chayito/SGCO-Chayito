<script setup>
/**
 * Vista de inicio / bienvenida del sistema SGCO-Chayito.
 * Muestra el logo, nombre del sistema y acceso al login.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Core – Inicio
 */
import { RouterLink } from 'vue-router';
import { ChefHat } from '@lucide/vue';
import { Button } from '@/components/ui/button';
</script>

<template>
  <main class="inicio-contenedor" id="vista-inicio">
    <div class="inicio-tarjeta">
      <div class="inicio-logo">
        <span class="inicio-logo-icono text-[#F26A21] flex justify-center mb-2">
          <ChefHat :size="48" stroke-width="2" />
        </span>
      </div>
      <h1 class="inicio-titulo">SGCO-Chayito</h1>
      <p class="inicio-subtitulo">
        Sistema de Gestión Contable y Operativa
      </p>
      <p class="inicio-descripcion">
        Comedor Chayito · UES-FMO
      </p>
      <div class="inicio-estado" id="estado-sistema">
        <span class="estado-punto"></span>
        Sistema operativo
      </div>
    </div>
  </main>
</template>

<style scoped>
.inicio-contenedor {
  min-height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--color-fondo-secundario);
}

.inicio-tarjeta {
  background: var(--color-fondo-base);
  border: 1px solid var(--color-borde);
  border-radius: 1.5rem;
  padding: 3rem 2.5rem;
  text-align: center;
  max-width: 420px;
  width: 90%;
  box-shadow: 0 4px 32px 0 rgb(242 106 33 / 8%);
}

.inicio-logo {
  font-size: 4rem;
  margin-bottom: 1rem;
}

.inicio-titulo {
  font-size: 2rem;
  font-weight: 700;
  color: var(--color-carbon);
  margin: 0 0 0.5rem;
}

.inicio-subtitulo {
  font-size: 1rem;
  color: var(--color-texto-secundario);
  margin: 0 0 0.25rem;
}

.inicio-descripcion {
  font-size: 0.875rem;
  color: var(--color-naranja-oscuro);
  font-weight: 600;
  margin: 0 0 2rem;
}

.inicio-estado {
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;
  background: rgb(46 125 79 / 10%);
  color: var(--color-exito);
  border-radius: 2rem;
  padding: 0.375rem 1rem;
  font-size: 0.875rem;
  font-weight: 600;
}

.estado-punto {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--color-exito);
  animation: pulso 2s infinite;
}

@keyframes pulso {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.4; }
}
</style>
