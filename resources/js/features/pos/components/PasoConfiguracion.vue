<script setup>
/**
 * Paso 1 del Wizard POS: Selección de Canal y Mesa.
 * Utiliza Cards de shadcn para canales y Buttons para mesas.
 * Avanza automáticamente al Paso 2 al seleccionar "Para Llevar/WhatsApp" o al tocar una Mesa.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-18
 * @módulo POS – RF-POS-001
 */
import { useCarritoStore } from '@/features/pos/stores/useCarritoStore.js';
import { useSesionStore } from '@/stores/sesion.js';
import { usePermisos } from '@/shared/composables/usePermisos.js';
import { useRouter, RouterLink } from 'vue-router';
import { Card, CardContent } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Store, ShoppingBag, MessageCircle, ChefHat, ShieldCheck, LogOut } from '@lucide/vue';
import { markRaw } from 'vue';

const props = defineProps({
  mesas: {
    type: Array,
    required: true,
  }
});

const router = useRouter();
const sesion = useSesionStore();
const { puedeVer, rolActual } = usePermisos();
const carrito = useCarritoStore();

const CANALES = [
  { valor: 'mesa',       etiqueta: 'En Mesa',       icono: markRaw(Store),         desc: 'Comer en el local' },
  { valor: 'para_llevar', etiqueta: 'Para Llevar', icono: markRaw(ShoppingBag),   desc: 'Para retiro' },
  { valor: 'whatsapp',   etiqueta: 'WhatsApp',    icono: markRaw(MessageCircle), desc: 'Pedido por chat' },
];

function seleccionarCanal(valor) {
  carrito.canal = valor;
  if (valor !== 'mesa') {
    carrito.mesaId = null;
    carrito.pasoActual = 2; // Avanza automático
  }
}

function seleccionarMesa(id) {
  carrito.mesaId = id;
  carrito.pasoActual = 2; // Avanza automático
}

async function salir() {
  await sesion.cerrarSesion();
  router.push('/login');
}
</script>

<template>
  <div class="max-w-4xl mx-auto p-4 md:p-6 space-y-8 animate-in fade-in zoom-in-95 duration-300">
    
    <!-- Barra superior de navegación rápida con RBAC -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#E5E0DB]">
      <div class="flex items-center gap-3">
        <div>
          <h1 class="text-xl font-extrabold text-zinc-900 tracking-tight">Punto de Venta</h1>
          <p class="text-xs text-zinc-500">
            Registro de comanda
            <span v-if="sesion.usuario" class="font-medium text-zinc-700">· {{ sesion.usuario.name }}</span>
            <span v-if="rolActual" class="ml-1 font-bold text-[#F26A21]">({{ rolActual.toUpperCase() }})</span>
          </p>
        </div>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <!-- Botón Panel Admin (Solo visible para rol administrador) -->
        <RouterLink
          v-if="puedeVer(['administrador'])"
          to="/admin"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-700 hover:bg-purple-800 text-xs font-bold text-white shadow-xs active:scale-95 transition-all"
          title="Panel de Administración de Usuarios y Roles"
        >
          <ShieldCheck :size="15" />
          <span>Panel Admin</span>
        </RouterLink>

        <!-- Botón Cola de Cocina (Cocinero, Cajero, Admin) -->
        <RouterLink
          v-if="puedeVer(['cocinero', 'cajero', 'administrador'])"
          to="/cocina"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-[#E5E0DB] bg-white hover:bg-zinc-50 text-xs font-bold text-[#3D3D3D] shadow-xs active:scale-95 transition-all"
          title="Ver pantalla de cocina"
        >
          <ChefHat :size="15" class="text-[#F26A21]" />
          <span>Cola de Cocina</span>
        </RouterLink>

        <!-- Botón Cerrar Sesión -->
        <Button
          variant="ghost"
          size="sm"
          class="h-8 rounded-xl px-2.5 text-zinc-500 hover:text-rose-600 hover:bg-rose-50 text-xs font-semibold"
          @click="salir"
          title="Cerrar sesión actual"
        >
          <LogOut :size="14" class="mr-1" />
          Salir
        </Button>
      </div>
    </div>

    <!-- Sección de Canales -->
    <section>
      <h2 class="text-2xl font-extrabold tracking-tight mb-4 text-zinc-900">¿Dónde comerá el cliente?</h2>
      
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <Card 
          v-for="c in CANALES" :key="c.valor"
          class="group cursor-pointer transition-all duration-200 hover:border-[#F26A21] hover:shadow-md"
          :class="{ 'border-[#F26A21] ring-2 ring-[#F26A21]/20 bg-[#F26A21]/5': carrito.canal === c.valor }"
          @click="seleccionarCanal(c.valor)"
        >
          <CardContent class="p-6 flex flex-row sm:flex-col items-center sm:text-center gap-4 sm:gap-2">
            <component 
              :is="c.icono" 
              class="w-10 h-10 shrink-0 transition-colors duration-200"
              :class="carrito.canal === c.valor ? 'text-[#F26A21]' : 'text-zinc-400 group-hover:text-[#F26A21]'" 
            />
            <div class="flex flex-col">
              <h3 class="font-bold text-lg text-zinc-800">{{ c.etiqueta }}</h3>
              <p class="text-sm text-zinc-500">{{ c.desc }}</p>
            </div>
          </CardContent>
        </Card>
      </div>
    </section>

    <!-- Sección de Mesas -->
    <section v-if="carrito.canal === 'mesa'" class="animate-in fade-in slide-in-from-top-4 duration-300">
      <div class="flex items-baseline justify-between mb-4">
        <h2 class="text-2xl font-extrabold tracking-tight text-zinc-900">Selecciona la Mesa</h2>
        <span class="text-sm text-zinc-500 font-medium px-3 py-1 bg-zinc-100 rounded-full">
          {{ mesas.filter(m => m.estado === 'libre').length }} libres
        </span>
      </div>

      <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 gap-3 md:gap-4">
        <Button
          v-for="mesa in mesas"
          :key="mesa.id"
          variant="outline"
          class="h-24 text-2xl font-black rounded-2xl border-2 transition-all"
          :class="[
            mesa.estado === 'ocupada' 
              ? 'opacity-40 cursor-not-allowed bg-zinc-50' 
              : 'hover:border-[#F26A21] hover:text-[#F26A21] hover:bg-[#F26A21]/5',
            carrito.mesaId === mesa.id ? 'bg-[#F26A21] text-white border-[#F26A21] hover:bg-[#F26A21] hover:text-white' : ''
          ]"
          :disabled="mesa.estado === 'ocupada'"
          @click="seleccionarMesa(mesa.id)"
        >
          {{ mesa.numero }}
        </Button>
      </div>
    </section>
  </div>
</template>
