<script setup>
/**
 * Vista de inicio / bienvenida del sistema SGCO-Chayito.
 * Muestra el acceso dinámico a módulos según el rol del token (US-ADM-01 / RF-ADM-008).
 *
 * @autor  Jeferson De La Cruz / Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Core – Inicio
 */
import { RouterLink, useRouter } from 'vue-router';
import { ChefHat, ShieldCheck, LogOut, LogIn, Users } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useSesionStore } from '@/stores/sesion.js';
import { usePermisos } from '@/shared/composables/usePermisos.js';

const router = useRouter();
const sesion = useSesionStore();
const { puedeVer, rolActual } = usePermisos();

async function cerrarSesion() {
  await sesion.cerrarSesion();
  router.push('/login');
}
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

      <!-- Menús y botones con restricción dinámica según rol del token (RBAC) -->
      <div class="flex flex-col gap-2.5 justify-center">
        <!-- Punto de Venta (Mesero, Cajero, Admin) -->
        <RouterLink v-if="puedeVer(['mesero', 'cajero', 'administrador'])" to="/pos">
          <Button class="w-full min-h-[44px] bg-[#F26A21] hover:bg-[#FF8C42] text-white font-bold px-5 rounded-xl cursor-pointer">
            Punto de Venta (POS)
          </Button>
        </RouterLink>

        <!-- Cola de Cocina (Cocinero, Cocinera, Admin) -->
        <RouterLink v-if="puedeVer(['cocinero', 'cocinera', 'administrador'])" to="/cocina">
          <Button variant="outline" class="w-full min-h-[44px] border-[#E5E0DB] text-[#3D3D3D] hover:bg-zinc-50 font-semibold px-5 rounded-xl cursor-pointer">
            Cola de Cocina (KDS)
          </Button>
        </RouterLink>

        <!-- Panel de Administración de Usuarios (Exclusivo Administrador) -->
        <RouterLink v-if="puedeVer(['administrador'])" to="/admin">
          <Button class="w-full min-h-[44px] bg-purple-700 hover:bg-purple-800 text-white font-bold px-5 rounded-xl cursor-pointer">
            <Users :size="18" class="mr-2" />
            Administración de Usuarios
          </Button>
        </RouterLink>

        <!-- Inventario (Cocinero, Admin) -->
        <RouterLink v-if="puedeVer(['cocinero', 'cocinera', 'administrador'])" to="/inventario">
          <Button variant="outline" class="w-full min-h-[44px] border-[#E5E0DB] text-[#3D3D3D] hover:bg-zinc-50 font-semibold px-5 rounded-xl cursor-pointer">
            Inventario
          </Button>
        </RouterLink>

        <!-- Contabilidad (Contadora, Admin) -->
        <RouterLink v-if="puedeVer(['contadora', 'administrador'])" to="/contabilidad">
          <Button variant="outline" class="w-full min-h-[44px] border-[#E5E0DB] text-[#3D3D3D] hover:bg-zinc-50 font-semibold px-5 rounded-xl cursor-pointer">
            Contabilidad y Cortes
          </Button>
        </RouterLink>

        <!-- Botón de Login si no está autenticado -->
        <RouterLink v-if="!sesion.autenticado" to="/login">
          <Button variant="outline" class="w-full min-h-[44px] border-[#E5E0DB] text-[#F26A21] hover:bg-orange-50 font-semibold px-5 rounded-xl cursor-pointer">
            <LogIn :size="18" class="mr-2" />
            Iniciar Sesión
          </Button>
        </RouterLink>

        <!-- Botón de Cerrar Sesión si está autenticado -->
        <Button
          v-if="sesion.autenticado"
          variant="ghost"
          class="w-full min-h-[40px] text-zinc-500 hover:text-rose-600 hover:bg-rose-50 font-medium text-xs mt-2"
          @click="cerrarSesion"
        >
          <LogOut :size="14" class="mr-1.5" />
          Cerrar Sesión Activa
        </Button>
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
