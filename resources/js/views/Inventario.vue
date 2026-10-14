<script setup>
/**
 * Vista de Inventario — deducción automática y alertas de stock.
 * Accesible para cocinero y administrador.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Inventario – US-INV-01 / RF-INV-003
 */
import { useRouter } from 'vue-router';
import { useSesionStore } from '@/stores/sesion.js';
import { Package, ArrowLeft, LogOut, ShieldCheck, Clock } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

const router = useRouter();
const sesion = useSesionStore();

async function cerrarSesion() {
  await sesion.cerrarSesion();
  router.push('/login');
}
</script>

<template>
  <main class="min-h-screen bg-[#FDFBF7] text-[#2D2D2D] p-4 sm:p-6 lg:p-8" id="vista-inventario">
    <div class="max-w-6xl mx-auto space-y-6">

      <!-- Cabecera Superior -->
      <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-[#EBE6DF] shadow-sm">
        <div class="flex items-center gap-3">
          <Button
            variant="ghost"
            size="icon"
            class="rounded-xl hover:bg-zinc-100"
            @click="router.back()"
            title="Volver"
          >
            <ArrowLeft :size="20" />
          </Button>

          <div class="flex items-center gap-2.5">
            <div class="w-10 h-10 rounded-xl bg-orange-100 text-[#F26A21] flex items-center justify-center font-bold">
              <Package :size="22" />
            </div>
            <div>
              <h1 class="text-lg font-bold text-zinc-900 leading-tight">Inventario de Ingredientes</h1>
              <p class="text-xs text-zinc-500">Comedor Chayito · Control de insumos y stock</p>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <!-- Usuario y Rol -->
          <div class="flex items-center gap-2 px-3 py-1.5 bg-zinc-50 border border-zinc-200/80 rounded-xl text-xs">
            <span class="font-medium text-zinc-700">{{ sesion.usuario?.name || 'Usuario' }}</span>
            <Badge variant="outline" class="bg-white text-orange-600 border-orange-200 text-[10px] font-bold">
              <ShieldCheck :size="10" class="mr-1" />
              {{ (sesion.rol || 'Cocinero').toUpperCase() }}
            </Badge>
          </div>

          <!-- Botón Cerrar Sesión -->
          <Button
            variant="ghost"
            class="text-rose-600 hover:text-rose-700 hover:bg-rose-50 rounded-xl flex items-center gap-1.5 text-xs font-semibold"
            @click="cerrarSesion"
            title="Cerrar sesión activa"
          >
            <LogOut :size="16" />
            <span>Cerrar Sesión</span>
          </Button>
        </div>
      </header>

      <!-- Contenido en construcción -->
      <section class="bg-white rounded-2xl border border-[#EBE6DF] p-8 sm:p-12 text-center shadow-sm space-y-4">
        <div class="w-16 h-16 rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center mx-auto">
          <Clock :size="32" />
        </div>
        <h2 class="text-xl font-bold text-zinc-900">Módulo de Inventario en Construcción</h2>
        <p class="text-sm text-zinc-500 max-w-md mx-auto">
          El control de ingredientes con deducción automática al registrar comandas y notificaciones de stock crítico se integrará en la siguiente fase (US-INV-01 / RF-INV-003).
        </p>
      </section>

    </div>
  </main>
</template>
