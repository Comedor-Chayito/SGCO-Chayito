<script setup>
/**
 * Contenedor global con navegación lateral y barra superior para usuarios autenticados.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-25
 * @módulo Core – RF-ADM-008 / Integración de navegación
 */
import { computed, ref } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';
import { ChefHat, LogOut, Menu } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from '@/components/ui/sheet';
import { obtenerNavegacionPorRol } from '@/shared/config/navegacion.js';
import { useSesionStore } from '@/stores/sesion.js';

const route = useRoute();
const router = useRouter();
const sesion = useSesionStore();
const menuMovilAbierto = ref(false);
const gruposNavegacion = computed(() => obtenerNavegacionPorRol(sesion.rol));

/**
 * Determina si el acceso corresponde a la ruta activa para comunicar contexto visual.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-25
 * @módulo Core – RF-ADM-008 / Integración de navegación
 *
 * @param {{ ruta: string }} item Acceso de navegación que se va a evaluar.
 * @returns {boolean} true cuando el acceso coincide con la ruta actual.
 */
function esRutaActiva(item) {
  return route.name === item.ruta;
}

/**
 * Cierra el panel móvil después de una navegación para liberar el contenido principal.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-25
 * @módulo Core – RF-ADM-008 / Integración de navegación
 */
function cerrarMenuMovil() {
  menuMovilAbierto.value = false;
}

/**
 * Revoca la sesión actual y dirige al usuario al formulario de acceso.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-25
 * @módulo Core – RF-ADM-008 / Integración de navegación
 *
 * @returns {Promise<void>}
 */
async function cerrarSesion() {
  await sesion.cerrarSesion();
  await router.push({ name: 'login' });
}
</script>

<template>
  <div class="min-h-[100dvh] bg-[#FAF7F4] text-[#3D3D3D]">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-72 border-r border-[#E5E0DB] bg-white lg:flex lg:flex-col">
      <div class="flex h-16 items-center gap-3 border-b border-[#E5E0DB] px-5">
        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#F26A21] text-white">
          <ChefHat :size="20" />
        </div>
        <div>
          <p class="text-sm font-black">SGCO-Chayito</p>
          <p class="text-xs text-[#6B6B6B]">Gestión operativa</p>
        </div>
      </div>

      <nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="Navegación principal">
        <section v-for="grupo in gruposNavegacion" :key="grupo.titulo" class="mb-5">
          <h2 class="px-3 text-xs font-bold text-[#6B6B6B]">{{ grupo.titulo }}</h2>
          <div class="mt-2 space-y-1">
            <RouterLink
              v-for="item in grupo.items"
              :key="item.ruta"
              :to="{ name: item.ruta }"
              class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#2B6CB0]"
              :class="esRutaActiva(item) ? 'bg-[#F26A21] text-white' : 'text-[#3D3D3D] hover:bg-[#FAF7F4]'"
            >
              <component :is="item.icono" :size="18" />
              <span>{{ item.etiqueta }}</span>
            </RouterLink>
          </div>
        </section>
      </nav>

      <div class="border-t border-[#E5E0DB] p-3">
        <div class="mb-2 rounded-xl bg-[#FAF7F4] px-3 py-2">
          <p class="truncate text-sm font-bold">{{ sesion.usuario?.name }}</p>
          <p class="text-xs capitalize text-[#6B6B6B]">{{ sesion.rol }}</p>
        </div>
        <Button variant="ghost" class="w-full justify-start text-rose-700 hover:bg-rose-50 hover:text-rose-800" @click="cerrarSesion">
          <LogOut :size="17" /> Cerrar sesión
        </Button>
      </div>
    </aside>

    <header class="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-[#E5E0DB] bg-white/95 px-4 backdrop-blur lg:pl-[19rem] lg:pr-6">
      <Sheet v-model:open="menuMovilAbierto">
        <SheetTrigger as-child>
          <Button variant="ghost" size="icon" class="lg:hidden" aria-label="Abrir navegación">
            <Menu :size="21" />
          </Button>
        </SheetTrigger>
        <SheetContent side="left" class="w-80 p-0">
          <SheetHeader class="border-b border-[#E5E0DB] p-5 text-left">
            <SheetTitle class="flex items-center gap-2 text-[#3D3D3D]"><ChefHat :size="19" /> SGCO-Chayito</SheetTitle>
            <SheetDescription>Accesos disponibles para tu rol.</SheetDescription>
          </SheetHeader>
          <nav class="p-3" aria-label="Navegación móvil">
            <section v-for="grupo in gruposNavegacion" :key="grupo.titulo" class="mb-4">
              <h2 class="px-3 text-xs font-bold text-[#6B6B6B]">{{ grupo.titulo }}</h2>
              <div class="mt-1 space-y-1">
                <RouterLink
                  v-for="item in grupo.items"
                  :key="item.ruta"
                  :to="{ name: item.ruta }"
                  class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold"
                  :class="esRutaActiva(item) ? 'bg-[#F26A21] text-white' : 'text-[#3D3D3D] hover:bg-[#FAF7F4]'"
                  @click="cerrarMenuMovil"
                >
                  <component :is="item.icono" :size="18" /> {{ item.etiqueta }}
                </RouterLink>
              </div>
            </section>
            <Button variant="ghost" class="mt-2 w-full justify-start text-rose-700" @click="cerrarSesion">
              <LogOut :size="17" /> Cerrar sesión
            </Button>
          </nav>
        </SheetContent>
      </Sheet>
      <RouterLink :to="{ name: 'inicio' }" class="text-sm font-black lg:hidden">SGCO-Chayito</RouterLink>
      <p class="hidden text-sm text-[#6B6B6B] lg:block">{{ sesion.usuario?.name }} · {{ sesion.rol }}</p>
      <Button variant="ghost" size="sm" class="lg:hidden text-rose-700" @click="cerrarSesion">
        <LogOut :size="17" />
      </Button>
    </header>

    <main class="lg:pl-72">
      <slot />
    </main>
  </div>
</template>
