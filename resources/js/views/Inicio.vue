<script setup>
/**
 * Vista de inicio / bienvenida del sistema SGCO-Chayito.
 * Sin sesión ofrece el acceso al login; con sesión lleva a la vista principal del rol.
 *
 * @autor  Jeferson De La Cruz / Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Core – Inicio / RF-ADM-008 (CC-86)
 */
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import { ChefHat, ShieldCheck, LogIn, ArrowRight } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { obtenerRutaInicioPorRol } from '@/router/index.js';
import { useSesionStore } from '@/stores/sesion.js';
import { usePermisos } from '@/shared/composables/usePermisos.js';

const sesion = useSesionStore();
const { rolActual } = usePermisos();
const rutaInicio = computed(() => obtenerRutaInicioPorRol(rolActual.value));
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

      <!-- Estado de sesión y usuario autenticado -->
      <div v-if="sesion.autenticado" class="mb-5 p-3 rounded-xl bg-orange-50/70 border border-orange-100 flex flex-col items-center gap-1.5">
        <div class="flex items-center gap-2">
          <span class="text-xs text-zinc-500">Sesión activa:</span>
          <span class="text-xs font-bold text-zinc-800">{{ sesion.usuario?.name }}</span>
        </div>
        <Badge variant="outline" class="bg-white text-[#F26A21] border-[#F26A21]/30 font-semibold text-xs">
          <ShieldCheck :size="12" class="mr-1" />
          ROL: {{ (rolActual || 'Usuario').toUpperCase() }}
        </Badge>
      </div>

      <div v-else class="inicio-estado mb-6" id="estado-sistema">
        <span class="estado-punto"></span>
        Sistema operativo
      </div>

      <!-- Con sesión: la navegación vive en el menú lateral; aquí solo se lleva a la vista del rol -->
      <RouterLink v-if="sesion.autenticado" :to="rutaInicio" class="block">
        <Button class="w-full min-h-[44px] bg-[#F26A21] hover:bg-[#FF8C42] text-white font-bold px-5 rounded-xl cursor-pointer">
          Ir a mi módulo
          <ArrowRight :size="18" class="ml-2" />
        </Button>
      </RouterLink>

      <!-- Acceso público al formulario de autenticación -->
      <RouterLink v-else to="/login" class="block">
        <Button class="w-full min-h-[44px] bg-[#F26A21] hover:bg-[#FF8C42] text-white font-bold px-5 rounded-xl cursor-pointer">
          <LogIn :size="18" class="mr-2" />
          Iniciar sesión
        </Button>
      </RouterLink>
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
  padding: 2.5rem 2rem;
  text-align: center;
  max-width: 440px;
  width: 90%;
  box-shadow: 0 4px 32px 0 rgb(242 106 33 / 8%);
}

.inicio-logo {
  font-size: 4rem;
  margin-bottom: 0.5rem;
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
  margin: 0 0 1.5rem;
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
