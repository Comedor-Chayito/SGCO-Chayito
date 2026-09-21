<script setup>
/**
 * Vista de Contabilidad — egresos e IVA crédito fiscal.
 * Accesible para contadora y administrador.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Contabilidad – US-CON-01 / RF-CON-005
 */
import { useRouter } from 'vue-router';
import { useSesionStore } from '@/stores/sesion.js';
import { Landmark, ArrowLeft, LogOut, ShieldCheck, Clock } from '@lucide/vue';
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
  <main class="min-h-screen bg-[#FDFBF7] text-[#2D2D2D] p-4 sm:p-6 lg:p-8" id="vista-contabilidad">
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
            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center font-bold">
              <Landmark :size="22" />
            </div>
            <div>
              <h1 class="text-lg font-bold text-zinc-900 leading-tight">Contabilidad y Finanzas</h1>
              <p class="text-xs text-zinc-500">Comedor Chayito · Cortes de caja, egresos e IVA crédito fiscal</p>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <!-- Usuario y Rol -->
          <div class="flex items-center gap-2 px-3 py-1.5 bg-zinc-50 border border-zinc-200/80 rounded-xl text-xs">
            <span class="font-medium text-zinc-700">{{ sesion.usuario?.name || 'Usuario' }}</span>
            <Badge variant="outline" class="bg-white text-emerald-600 border-emerald-200 text-[10px] font-bold">
              <ShieldCheck :size="10" class="mr-1" />
              {{ (sesion.rol || 'Contadora').toUpperCase() }}
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
        <div class="w-16 h-16 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-600 flex items-center justify-center mx-auto">
          <Clock :size="32" />
        </div>
        <h2 class="text-xl font-bold text-zinc-900">Módulo de Contabilidad en Construcción</h2>
        <p class="text-sm text-zinc-500 max-w-md mx-auto">
          El registro de facturas de proveedores, cálculo de IVA crédito fiscal y cortes de caja Z se implementará en la siguiente fase (US-CON-01 / RF-CON-005).
        </p>
      </section>

    </div>
  </main>
</template>
