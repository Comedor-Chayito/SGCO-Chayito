<script setup>
/**
 * Componente / Vista de la Matriz de Permisos por Rol RBAC (US-ADM-04 / CC-123).
 * Visualiza la relación bidimensional entre roles oficiales y permisos del sistema,
 * agrupados por módulo funcional con filtros de búsqueda y métricas clave.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Administración y Seguridad – US-ADM-04 (CC-123) / RF-ADM-008
 */
import { ref, computed, onMounted } from 'vue';
import {
  ShieldCheck,
  Search,
  RefreshCw,
  CheckCircle2,
  XCircle,
  AlertTriangle,
  Layers,
  Lock,
  Filter,
  Users,
  Eye,
  Info,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { obtenerMatrizPermisos } from '@/features/admin/services/matrizPermisoService.js';

// Estado reactivo
const cargando = ref(true);
const errorCarga = ref(null);
const datosMatriz = ref({
  roles: [],
  permisos: [],
  modulos: [],
  matriz: {},
  total_roles: 0,
  total_permisos: 0,
});

// Filtros
const filtroBusqueda = ref('');
const filtroModulo = ref('');
const filtroRol = ref('');
const vistaModo = ref('tabla'); // 'tabla' | 'tarjetas'

/**
 * Carga los datos de la matriz de roles y permisos desde el backend (CC-121).
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-123
 *
 * @returns {Promise<void>}
 */
async function cargarMatriz() {
  cargando.value = true;
  errorCarga.value = null;

  try {
    const data = await obtenerMatrizPermisos();
    datosMatriz.value = data;
  } catch (error) {
    console.error('Error al cargar la matriz de permisos:', error);
    errorCarga.value = 'No se pudo cargar la matriz de permisos. Verifica la conexión o tus credenciales.';
    toast.error('Error al sincronizar la matriz de permisos.');
  } finally {
    cargando.value = false;
  }
}

onMounted(() => {
  cargarMatriz();
});

defineExpose({
  cargarMatriz,
  cargando,
});

/**
 * Retorna la lista de permisos filtrados por búsqueda de texto y por módulo.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-123
 *
 * @type {import('vue').ComputedRef<Array<object>>}
 */
const permisosFiltrados = computed(() => {
  let resultado = datosMatriz.value.permisos || [];

  if (filtroModulo.value) {
    resultado = resultado.filter((p) => p.modulo === filtroModulo.value);
  }

  if (filtroBusqueda.value.trim()) {
    const termino = filtroBusqueda.value.trim().toLowerCase();
    resultado = resultado.filter(
      (p) =>
        p.name.toLowerCase().includes(termino) ||
        p.descripcion.toLowerCase().includes(termino) ||
        p.modulo.toLowerCase().includes(termino)
    );
  }

  return resultado;
});

/**
 * Agrupa los permisos filtrados por su módulo correspondiente para renderizar secciones.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-123
 *
 * @type {import('vue').ComputedRef<Array<{ clave: string, nombre: string, permisos: Array<object> }>>}
 */
const modulosConPermisosFiltrados = computed(() => {
  const modulosMap = {};

  (datosMatriz.value.modulos || []).forEach((mod) => {
    modulosMap[mod.clave] = {
      ...mod,
      permisos: [],
    };
  });

  permisosFiltrados.value.forEach((permiso) => {
    if (!modulosMap[permiso.modulo]) {
      modulosMap[permiso.modulo] = {
        clave: permiso.modulo,
        nombre: permiso.modulo.charAt(0).toUpperCase() + permiso.modulo.slice(1),
        permisos: [],
      };
    }
    modulosMap[permiso.modulo].permisos.push(permiso);
  });

  return Object.values(modulosMap).filter((mod) => mod.permisos.length > 0);
});

/**
 * Roles que se deben mostrar en las columnas, considerando el filtro de rol si está activo.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-123
 *
 * @type {import('vue').ComputedRef<Array<object>>}
 */
const rolesVisibles = computed(() => {
  const roles = datosMatriz.value.roles || [];
  if (!filtroRol.value) return roles;
  return roles.filter((r) => r.name === filtroRol.value);
});

/**
 * Verifica si un rol específico tiene concedido un permiso determinado.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-123
 *
 * @param   {string} nombreRol     Nombre del rol evaluado.
 * @param   {string} nombrePermiso Nombre del permiso evaluado.
 * @returns {boolean} true si el rol posee el permiso asignado.
 */
function tienePermiso(nombreRol, nombrePermiso) {
  const filaRol = datosMatriz.value.matriz?.[nombreRol];
  if (!filaRol) return false;
  return Boolean(filaRol[nombrePermiso]);
}

/**
 * Retorna las clases Tailwind de estilo para el badge de cada rol oficial.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-123
 *
 * @param   {string} rol Nombre del rol.
 * @returns {string} Clases CSS.
 */
function obtenerEstiloBadgeRol(rol) {
  switch (rol?.toLowerCase()) {
    case 'administrador':
      return 'bg-amber-100 text-amber-900 border-amber-300';
    case 'socio':
      return 'bg-purple-100 text-purple-900 border-purple-300';
    case 'cajero':
      return 'bg-blue-100 text-blue-900 border-blue-300';
    case 'mesero':
      return 'bg-orange-100 text-[#C24E12] border-orange-300';
    case 'cocinero':
    case 'cocinera':
      return 'bg-emerald-100 text-emerald-900 border-emerald-300';
    case 'contadora':
      return 'bg-teal-100 text-teal-900 border-teal-300';
    default:
      return 'bg-zinc-100 text-zinc-800 border-zinc-200';
  }
}
</script>

<template>
  <div class="space-y-6" id="seccion-matriz-permisos">

    <!-- Tarjetas de Métricas RBAC -->
    <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white p-5 rounded-2xl border border-[#E5E0DB] shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs uppercase tracking-wider text-zinc-500 font-semibold">Roles Oficiales</p>
          <p class="text-2xl font-extrabold text-[#3D3D3D] mt-1 h-8 flex items-center">{{ datosMatriz.total_roles || 7 }}</p>
          <p class="text-xs text-zinc-500 mt-0.5">Control de acceso RBAC</p>
        </div>
        <div class="w-11 h-11 rounded-xl bg-orange-50 text-[#F26A21] flex items-center justify-center">
          <Users :size="22" />
        </div>
      </div>

      <div class="bg-white p-5 rounded-2xl border border-[#E5E0DB] shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs uppercase tracking-wider text-zinc-500 font-semibold">Permisos Granulares</p>
          <p class="text-2xl font-extrabold text-[#2E7D4F] mt-1 h-8 flex items-center">{{ datosMatriz.total_permisos || 12 }}</p>
          <p class="text-xs text-zinc-500 mt-0.5">Asignaciones por función</p>
        </div>
        <div class="w-11 h-11 rounded-xl bg-emerald-50 text-[#2E7D4F] flex items-center justify-center">
          <ShieldCheck :size="22" />
        </div>
      </div>

      <div class="bg-white p-5 rounded-2xl border border-[#E5E0DB] shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs uppercase tracking-wider text-zinc-500 font-semibold">Módulos del Sistema</p>
          <p class="text-2xl font-extrabold text-blue-700 mt-1 h-8 flex items-center">{{ datosMatriz.modulos?.length || 6 }}</p>
          <p class="text-xs text-zinc-500 mt-0.5">Alcance de negocio</p>
        </div>
        <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
          <Layers :size="22" />
        </div>
      </div>

      <div class="bg-white p-5 rounded-2xl border border-[#E5E0DB] shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs uppercase tracking-wider text-zinc-500 font-semibold">Nivel de Seguridad</p>
          <p class="text-lg sm:text-xl font-extrabold text-amber-800 mt-1 h-8 flex items-center gap-1.5">
            <Lock :size="17" class="text-amber-700 shrink-0" /> RBAC Estricto
          </p>
          <p class="text-xs text-zinc-500 mt-0.5">Mínimo privilegio</p>
        </div>
        <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center">
          <Lock :size="22" />
        </div>
      </div>
    </section>

    <!-- Barra de Filtros y Búsqueda (mismo flujo y responsive que Cuentas de Usuarios) -->
    <section class="bg-white p-4 rounded-2xl border border-[#E5E0DB] shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">
      <div class="relative w-full md:w-96">
        <Search :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
        <Input
          v-model="filtroBusqueda"
          type="text"
          placeholder="Buscar permiso o descripción..."
          class="pl-10 rounded-xl border-[#E5E0DB] focus-visible:ring-[#F26A21]"
        />
      </div>

      <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto justify-end">
        <!-- Filtro por Módulo -->
        <select
          v-model="filtroModulo"
          aria-label="Filtrar por módulo"
          class="w-full sm:w-auto px-3 py-2 bg-white border border-[#E5E0DB] rounded-xl text-sm font-medium text-zinc-700 focus:outline-none focus:ring-2 focus:ring-[#F26A21]"
        >
          <option value="">Todos los Módulos</option>
          <option v-for="mod in datosMatriz.modulos" :key="mod.clave" :value="mod.clave">
            {{ mod.nombre }}
          </option>
        </select>

        <!-- Filtro por Rol -->
        <select
          v-model="filtroRol"
          aria-label="Filtrar por rol"
          class="w-full sm:w-auto px-3 py-2 bg-white border border-[#E5E0DB] rounded-xl text-sm font-medium text-zinc-700 focus:outline-none focus:ring-2 focus:ring-[#F26A21]"
        >
          <option value="">Todos los Roles ({{ datosMatriz.roles?.length || 0 }})</option>
          <option v-for="rol in datosMatriz.roles" :key="rol.name" :value="rol.name">
            {{ rol.name.charAt(0).toUpperCase() + rol.name.slice(1) }}
          </option>
        </select>
      </div>
    </section>

    <!-- Alerta de Política de Seguridad (US-ADM-04) -->
    <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-amber-900 flex items-start gap-3">
      <AlertTriangle :size="20" class="text-amber-700 shrink-0 mt-0.5" />
      <div class="text-xs sm:text-sm space-y-1">
        <p class="font-bold text-amber-950">Política de integridad de la matriz de permisos (US-ADM-04):</p>
        <p class="text-amber-800 leading-relaxed">
          Solo el <span class="font-semibold">administrador</span> tiene facultad para modificar la asignación de permisos por rol.
          Toda modificación queda registrada de forma inalterable en la <span class="font-semibold">bitácora de auditoría</span>.
          El administrador no puede revocar a su propio perfil el acceso de gestión.
        </p>
      </div>
    </div>

    <!-- Estado de Carga -->
    <div v-if="cargando" class="bg-white p-12 rounded-2xl border border-[#E5E0DB] text-center space-y-3 min-h-[420px] flex flex-col items-center justify-center">
      <RefreshCw :size="32" class="animate-spin mx-auto text-[#F26A21]" />
      <p class="text-sm font-semibold text-zinc-700">Cargando matriz de roles y permisos oficiales...</p>
    </div>

    <!-- Estado de Error -->
    <div v-else-if="errorCarga" class="bg-white p-8 rounded-2xl border border-rose-200 text-center space-y-3">
      <XCircle :size="36" class="text-rose-600 mx-auto" />
      <p class="text-sm font-bold text-rose-900">{{ errorCarga }}</p>
      <Button variant="outline" class="rounded-xl" @click="cargarMatriz">Reintentar</Button>
    </div>

    <!-- Tabla Bidimensional Rol × Permiso (CC-123) -->
    <div v-else class="bg-white rounded-2xl border border-[#E5E0DB] shadow-sm overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse" id="tabla-matriz-permisos">

          <!-- Encabezado de la tabla con roles -->
          <thead class="bg-[#FAF7F4] border-b border-[#E5E0DB]">
            <tr>
              <th scope="col" class="py-4 px-4 sm:px-6 font-bold text-[#3D3D3D] min-w-[280px]">
                <div class="flex items-center gap-2">
                  <ShieldCheck :size="18" class="text-[#F26A21]" />
                  <span>Módulo / Permiso</span>
                </div>
              </th>

              <!-- Columnas de Roles -->
              <th
                v-for="rol in rolesVisibles"
                :key="rol.name"
                scope="col"
                class="py-4 px-3 text-center font-bold min-w-[120px]"
              >
                <div class="flex flex-col items-center gap-1">
                  <span
                    class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold border capitalize"
                    :class="obtenerEstiloBadgeRol(rol.name)"
                  >
                    {{ rol.name }}
                  </span>
                  <span class="text-[11px] font-medium text-zinc-500">
                    {{ rol.total_permisos }}/{{ datosMatriz.total_permisos }}
                  </span>
                </div>
              </th>
            </tr>
          </thead>

          <!-- Cuerpo agrupado por Módulo -->
          <tbody class="divide-y divide-zinc-100">
            <template v-for="modulo in modulosConPermisosFiltrados" :key="modulo.clave">

              <!-- Fila separadora de módulo -->
              <tr class="bg-zinc-50/80 font-bold text-xs uppercase tracking-wider text-zinc-600">
                <td
                  :colspan="rolesVisibles.length + 1"
                  class="py-2.5 px-4 sm:px-6 border-y border-zinc-200"
                >
                  <div class="flex items-center gap-2">
                    <Layers :size="14" class="text-[#F26A21]" />
                    <span>{{ modulo.nombre }}</span>
                    <span class="text-[11px] font-normal normal-case text-zinc-500">
                      ({{ modulo.permisos.length }} {{ modulo.permisos.length === 1 ? 'permiso' : 'permisos' }})
                    </span>
                  </div>
                </td>
              </tr>

              <!-- Filas de permisos individuales -->
              <tr
                v-for="permiso in modulo.permisos"
                :key="permiso.name"
                class="hover:bg-orange-50/30 transition-colors"
              >
                <!-- Datos del permiso -->
                <td class="py-3 px-4 sm:px-6">
                  <div class="space-y-0.5">
                    <div class="flex items-center gap-2">
                      <span class="font-mono text-xs font-bold text-zinc-900 bg-zinc-100 px-2 py-0.5 rounded">
                        {{ permiso.name }}
                      </span>
                    </div>
                    <p class="text-xs text-zinc-600 line-clamp-2">
                      {{ permiso.descripcion }}
                    </p>
                  </div>
                </td>

                <!-- Celdas booleanas por rol -->
                <td
                  v-for="rol in rolesVisibles"
                  :key="`${rol.name}-${permiso.name}`"
                  class="py-3 px-3 text-center align-middle"
                >
                  <div class="flex items-center justify-center">
                    <span
                      v-if="tienePermiso(rol.name, permiso.name)"
                      class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-emerald-100 text-[#2E7D4F]"
                      :title="`${rol.name} TIENE asignado ${permiso.name}`"
                    >
                      <CheckCircle2 :size="17" />
                    </span>
                    <span
                      v-else
                      class="inline-flex items-center justify-center w-7 h-7 rounded-full bg-zinc-100 text-zinc-300"
                      :title="`${rol.name} NO posee ${permiso.name}`"
                    >
                      <XCircle :size="16" />
                    </span>
                  </div>
                </td>
              </tr>

            </template>

            <!-- Sin resultados de filtro -->
            <tr v-if="modulosConPermisosFiltrados.length === 0">
              <td :colspan="rolesVisibles.length + 1" class="py-12 text-center text-zinc-500">
                <Search :size="32" class="mx-auto text-zinc-300 mb-2" />
                <p class="text-sm font-semibold">No se encontraron permisos que coincidan con los filtros aplicados.</p>
                <p class="text-xs text-zinc-400 mt-1">Prueba limpiando el término de búsqueda o seleccionando otro módulo.</p>
              </td>
            </tr>
          </tbody>

        </table>
      </div>

      <!-- Pie de leyenda -->
      <div class="p-4 bg-[#FAF7F4] border-t border-[#E5E0DB] flex flex-wrap items-center justify-between gap-3 text-xs text-zinc-600">
        <div class="flex items-center gap-4">
          <span class="font-semibold text-zinc-700">Leyenda:</span>
          <div class="flex items-center gap-1.5">
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-emerald-100 text-[#2E7D4F]">
              <CheckCircle2 :size="13" />
            </span>
            <span>Permiso Concedido</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-zinc-100 text-zinc-400">
              <XCircle :size="13" />
            </span>
            <span>Permiso Denegado</span>
          </div>
        </div>

        <div>
          <span>Mostrando {{ permisosFiltrados.length }} de {{ datosMatriz.total_permisos || 12 }} permisos</span>
        </div>
      </div>
    </div>

  </div>
</template>
