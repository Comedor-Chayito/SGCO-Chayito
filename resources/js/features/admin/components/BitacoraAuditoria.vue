<script setup>
/**
 * Vista de la Bitácora de Auditoría del Sistema (US-ADM-04 / CC-125).
 * Permite consultar de forma inalterable y en modo de solo lectura el registro
 * histórico de acciones, cambios y asignaciones sobre usuarios y roles.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Administración y Seguridad – US-ADM-04 (CC-125) / RF-ADM-008
 */
import { ref, reactive, computed, onMounted } from 'vue';
import {
  History,
  Search,
  RefreshCw,
  Eye,
  ShieldCheck,
  Calendar,
  User as UserIcon,
  Filter,
  X,
  XCircle,
  ChevronLeft,
  ChevronRight,
  Lock,
  ArrowRight,
  Clock,
  Layers,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { obtenerAuditorias } from '@/features/admin/services/auditoriaService.js';
import { obtenerUsuarios } from '@/features/admin/services/usuarioService.js';

// Estado reactivo
const cargando = ref(true);
const auditorias = ref([]);
const usuariosDisponibles = ref([]);
const totalRegistros = ref(0);
const paginaActual = ref(1);
const ultimaPagina = ref(1);
const porPagina = ref(15);

// Modal de detalle de cambios
const modalDetalleAbierto = ref(false);
const registroSeleccionado = ref(null);

// Filtros
const filtros = reactive({
  usuario_id: '',
  accion: '',
  entidad: '',
  desde: '',
  hasta: '',
});
const busquedaTexto = ref('');

const ACCIONES_DISPONIBLES = [
  { clave: 'creado', etiqueta: 'Creación' },
  { clave: 'actualizado', etiqueta: 'Actualización' },
  { clave: 'desactivado', etiqueta: 'Desactivación' },
  { clave: 'reactivado', etiqueta: 'Reactivación' },
  { clave: 'eliminado', etiqueta: 'Eliminación' },
  { clave: 'rol_asignado', etiqueta: 'Rol Asignado' },
  { clave: 'rol_removido', etiqueta: 'Rol Removido' },
];

/**
 * Consulta la bitácora paginada desde el servidor aplicando los filtros activos.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-125
 *
 * @param   {number} [pagina=1] Número de página requerida.
 * @returns {Promise<void>}
 */
async function cargarAuditorias(pagina = 1) {
  cargando.value = true;
  try {
    const params = {
      page: pagina,
      por_pagina: porPagina.value,
    };

    if (filtros.usuario_id) params.usuario_id = filtros.usuario_id;
    if (filtros.accion) params.accion = filtros.accion;
    if (filtros.entidad) params.entidad = filtros.entidad;
    if (filtros.desde) params.desde = filtros.desde;
    if (filtros.hasta) params.hasta = filtros.hasta;

    const respuesta = await obtenerAuditorias(params);
    auditorias.value = respuesta.data || [];
    paginaActual.value = respuesta.current_page || 1;
    ultimaPagina.value = respuesta.last_page || 1;
    totalRegistros.value = respuesta.total || 0;
  } catch (error) {
    console.error('Error al consultar la bitácora de auditoría:', error);
    toast.error('No se pudo cargar la bitácora de auditoría.');
  } finally {
    cargando.value = false;
  }
}

/**
 * Carga el catálogo de usuarios para el filtro de responsables.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-125
 *
 * @returns {Promise<void>}
 */
async function cargarUsuariosFiltro() {
  try {
    usuariosDisponibles.value = await obtenerUsuarios();
  } catch {
    usuariosDisponibles.value = [];
  }
}

onMounted(async () => {
  await Promise.all([
    cargarAuditorias(1),
    cargarUsuariosFiltro(),
  ]);
});

defineExpose({
  cargarAuditorias,
  cargando,
});

/**
 * Filtra localmente por texto de búsqueda sobre la página actual (nombre de usuario o ID).
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-125
 *
 * @type {import('vue').ComputedRef<Array<object>>}
 */
const auditoriasFiltradas = computed(() => {
  if (!busquedaTexto.value.trim()) {
    return auditorias.value;
  }
  const termino = busquedaTexto.value.trim().toLowerCase();
  return auditorias.value.filter((item) => {
    const nombreUsuario = (item.usuario?.name || '').toLowerCase();
    const accion = (item.accion || '').toLowerCase();
    const entidad = (item.entidad || '').toLowerCase();
    const id = String(item.id);
    return (
      nombreUsuario.includes(termino) ||
      accion.includes(termino) ||
      entidad.includes(termino) ||
      id.includes(termino)
    );
  });
});

/**
 * Resetea todos los filtros y recarga la bitácora desde la primera página.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-125
 *
 * @returns {Promise<void>}
 */
async function limpiarFiltros() {
  filtros.usuario_id = '';
  filtros.accion = '';
  filtros.entidad = '';
  filtros.desde = '';
  filtros.hasta = '';
  busquedaTexto.value = '';
  await cargarAuditorias(1);
}

/**
 * Abre el modal con el detalle completo de cambios (Antes / Después) del registro seleccionado.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-125
 *
 * @param {object} registro Registro de auditoría a inspeccionar.
 */
function abrirDetalle(registro) {
  registroSeleccionado.value = registro;
  modalDetalleAbierto.value = true;
}

/**
 * Formatea una fecha ISO a una cadena legible en la zona horaria local.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-125
 *
 * @param   {string} fechaStr Fecha en formato ISO.
 * @returns {string} Fecha formateada.
 */
function formatearFecha(fechaStr) {
  if (!fechaStr) return '—';
  const fecha = new Date(fechaStr);
  return new Intl.DateTimeFormat('es-SV', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
  }).format(fecha);
}

/**
 * Devuelve el estilo CSS (Tailwind) correspondiente a la acción registrada.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-125
 *
 * @param   {string} accion Acción efectuada.
 * @returns {string} Clases de badge.
 */
function estiloBadgeAccion(accion) {
  switch (accion) {
    case 'creado':
      return 'bg-emerald-50 text-emerald-800 border-emerald-200';
    case 'actualizado':
      return 'bg-blue-50 text-blue-800 border-blue-200';
    case 'desactivado':
      return 'bg-rose-50 text-rose-800 border-rose-200';
    case 'reactivado':
      return 'bg-amber-50 text-amber-800 border-amber-200';
    case 'eliminado':
      return 'bg-red-100 text-red-900 border-red-300';
    case 'rol_asignado':
    case 'rol_removido':
      return 'bg-purple-50 text-purple-800 border-purple-200';
    default:
      return 'bg-zinc-100 text-zinc-800 border-zinc-200';
  }
}

/**
 * Obtiene las llaves únicas combinadas de datos anteriores y nuevos para comparación.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-125
 *
 * @param   {object} registro Registro seleccionado.
 * @returns {Array<string>} Lista de campos alterados.
 */
function obtenerCamposAlterados(registro) {
  if (!registro) return [];
  const antes = registro.datos_anteriores || {};
  const despues = registro.datos_nuevos || {};
  const setCampos = new Set([...Object.keys(antes), ...Object.keys(despues)]);
  return Array.from(setCampos);
}

/**
 * Formatea valores de atributos (objetos, booleanos, cadenas) para visualización amigable.
 *
 * @autor  kristiankovic
 * @fecha  2026-11-18
 * @módulo Admin – US-ADM-04 / CC-125
 *
 * @param   {*} valor Valor del atributo.
 * @returns {string} Representación textual.
 */
function formatearValor(valor) {
  if (valor === null || valor === undefined) return '—';
  if (typeof valor === 'boolean') return valor ? 'Activo (true)' : 'Inactivo (false)';
  if (typeof valor === 'object') return JSON.stringify(valor, null, 2);
  return String(valor);
}
</script>

<template>
  <div class="space-y-6" id="seccion-bitacora-auditoria">

    <!-- Tarjetas de Métricas de Auditoría -->
    <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-white p-5 rounded-2xl border border-[#E5E0DB] shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs uppercase tracking-wider text-zinc-500 font-semibold">Total de Eventos</p>
          <p class="text-2xl font-extrabold text-[#3D3D3D] mt-1 h-8 flex items-center">{{ totalRegistros }}</p>
          <p class="text-xs text-zinc-500 mt-0.5">Acciones registradas</p>
        </div>
        <div class="w-11 h-11 rounded-xl bg-orange-50 text-[#F26A21] flex items-center justify-center">
          <History :size="22" />
        </div>
      </div>

      <div class="bg-white p-5 rounded-2xl border border-[#E5E0DB] shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs uppercase tracking-wider text-zinc-500 font-semibold">Modo de Registro</p>
          <p class="text-lg sm:text-xl font-extrabold text-[#2E7D4F] mt-1 h-8 flex items-center gap-1.5">
            <Lock :size="17" class="text-[#2E7D4F]" /> Solo Lectura
          </p>
          <p class="text-xs text-zinc-500 mt-0.5">Inalterable e inmutable</p>
        </div>
        <div class="w-11 h-11 rounded-xl bg-emerald-50 text-[#2E7D4F] flex items-center justify-center">
          <ShieldCheck :size="22" />
        </div>
      </div>

      <div class="bg-white p-5 rounded-2xl border border-[#E5E0DB] shadow-sm flex items-center justify-between">
        <div>
          <p class="text-xs uppercase tracking-wider text-zinc-500 font-semibold">Alcance Auditado</p>
          <p class="text-lg sm:text-xl font-extrabold text-blue-700 mt-1 h-8 flex items-center gap-1.5">
            <Layers :size="17" class="text-blue-700" /> Usuarios y Roles
          </p>
          <p class="text-xs text-zinc-500 mt-0.5">Trazabilidad completa</p>
        </div>
        <div class="w-11 h-11 rounded-xl bg-blue-50 text-blue-700 flex items-center justify-center">
          <Layers :size="22" />
        </div>
      </div>
    </section>

    <!-- Barra de Filtros y Búsqueda (mismo flujo que Cuentas de Usuarios y Matriz) -->
    <section class="bg-white p-4 rounded-2xl border border-[#E5E0DB] shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">
      <!-- Búsqueda rápida a la izquierda -->
      <div class="relative w-full md:w-96">
        <Search :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
        <Input
          v-model="busquedaTexto"
          type="text"
          placeholder="Buscar por usuario o acción..."
          class="pl-10 rounded-xl border-[#E5E0DB] focus-visible:ring-[#F26A21]"
        />
      </div>

      <!-- Desplegables alineados a la derecha -->
      <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto justify-end">
        <!-- Filtro por Acción -->
        <select
          v-model="filtros.accion"
          aria-label="Filtrar por acción"
          class="w-full sm:w-auto px-3 py-2 bg-white border border-[#E5E0DB] rounded-xl text-sm font-medium text-zinc-700 focus:outline-none focus:ring-2 focus:ring-[#F26A21]"
          @change="cargarAuditorias(1)"
        >
          <option value="">Todas las Acciones</option>
          <option v-for="accion in ACCIONES_DISPONIBLES" :key="accion.clave" :value="accion.clave">
            {{ accion.etiqueta }}
          </option>
        </select>

        <!-- Filtro por Usuario Responsable -->
        <select
          v-model="filtros.usuario_id"
          aria-label="Filtrar por usuario"
          class="w-full sm:w-auto px-3 py-2 bg-white border border-[#E5E0DB] rounded-xl text-sm font-medium text-zinc-700 focus:outline-none focus:ring-2 focus:ring-[#F26A21]"
          @change="cargarAuditorias(1)"
        >
          <option value="">Todos los Usuarios</option>
          <option v-for="user in usuariosDisponibles" :key="user.id" :value="user.id">
            {{ user.name }}
          </option>
        </select>

        <!-- Botón Limpiar (si hay filtros) -->
        <Button
          v-if="filtros.accion || filtros.usuario_id || busquedaTexto"
          variant="ghost"
          size="sm"
          class="text-xs text-zinc-500 hover:text-zinc-800"
          @click="limpiarFiltros"
        >
          Limpiar
        </Button>
      </div>
    </section>

    <!-- Tabla de Registros de Auditoría -->
    <section class="bg-white rounded-2xl border border-[#E5E0DB] shadow-sm overflow-hidden">
      <!-- Indicador de carga -->
      <div v-if="cargando" class="p-12 text-center text-zinc-500 min-h-[420px] flex flex-col items-center justify-center">
        <RefreshCw :size="32" class="animate-spin mx-auto mb-3 text-[#F26A21]" />
        <p class="font-medium text-zinc-700">Consultando bitácora de auditoría inalterable...</p>
      </div>

      <!-- Tabla con datos -->
      <div v-else class="overflow-x-auto">
        <table class="w-full text-left text-sm border-collapse" id="tabla-auditoria">
          <thead class="bg-[#FAF7F4] border-b border-[#E5E0DB] text-xs uppercase tracking-wide text-zinc-600 font-bold">
            <tr>
              <th scope="col" class="py-3.5 px-4 sm:px-6">Fecha y Hora</th>
              <th scope="col" class="py-3.5 px-4">Usuario Responsable</th>
              <th scope="col" class="py-3.5 px-4">Acción Realizada</th>
              <th scope="col" class="py-3.5 px-4">Entidad Afectada</th>
              <th scope="col" class="py-3.5 px-4 text-center">Detalle de Cambios</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-zinc-100">
            <tr
              v-for="registro in auditoriasFiltradas"
              :key="registro.id"
              class="hover:bg-orange-50/20 transition-colors"
            >
              <!-- Fecha y hora -->
              <td class="py-3 px-4 sm:px-6 whitespace-nowrap text-xs sm:text-sm font-medium text-zinc-700">
                <div class="flex items-center gap-2">
                  <Clock :size="15" class="text-zinc-400 shrink-0" />
                  <span>{{ formatearFecha(registro.created_at) }}</span>
                </div>
              </td>

              <!-- Usuario Responsable -->
              <td class="py-3 px-4">
                <div class="flex items-center gap-2">
                  <div class="w-7 h-7 rounded-full bg-orange-100 text-[#C24E12] flex items-center justify-center text-xs font-bold shrink-0">
                    {{ registro.usuario?.name?.charAt(0) || 'S' }}
                  </div>
                  <div>
                    <p class="font-semibold text-zinc-900 leading-tight">
                      {{ registro.usuario?.name || 'Sistema / Seeder' }}
                    </p>
                    <p v-if="registro.usuario_id" class="text-[11px] text-zinc-400">
                      ID #{{ registro.usuario_id }}
                    </p>
                  </div>
                </div>
              </td>

              <!-- Acción -->
              <td class="py-3 px-4">
                <span
                  class="inline-block px-2.5 py-0.5 rounded-full text-xs font-bold border capitalize"
                  :class="estiloBadgeAccion(registro.accion)"
                >
                  {{ registro.accion.replace('_', ' ') }}
                </span>
              </td>

              <!-- Entidad Afectada -->
              <td class="py-3 px-4 font-mono text-xs text-zinc-700">
                <span class="bg-zinc-100 px-2 py-0.5 rounded border border-zinc-200">
                  {{ registro.entidad || 'Entidad' }} #{{ registro.entidad_id }}
                </span>
              </td>

              <!-- Botón Ver Detalle -->
              <td class="py-3 px-4 text-center">
                <Button
                  variant="ghost"
                  size="sm"
                  class="rounded-xl text-[#F26A21] hover:text-[#C24E12] hover:bg-orange-50 text-xs font-semibold gap-1.5"
                  @click="abrirDetalle(registro)"
                >
                  <Eye :size="15" />
                  <span>Ver Cambios</span>
                </Button>
              </td>
            </tr>

            <!-- Sin resultados -->
            <tr v-if="auditoriasFiltradas.length === 0">
              <td colspan="5" class="py-12 text-center text-zinc-500">
                <History :size="36" class="mx-auto text-zinc-300 mb-2" />
                <p class="text-sm font-semibold text-zinc-700">No se encontraron eventos en la bitácora.</p>
                <p class="text-xs text-zinc-400 mt-1">Prueba cambiando los filtros o la búsqueda.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Paginación -->
      <div class="p-4 bg-[#FAF7F4] border-t border-[#E5E0DB] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-zinc-600">
        <p>
          Mostrando página <span class="font-bold text-zinc-800">{{ paginaActual }}</span> de <span class="font-bold text-zinc-800">{{ ultimaPagina }}</span> (Total: {{ totalRegistros }} registros)
        </p>

        <div class="flex items-center gap-1.5">
          <Button
            variant="outline"
            size="sm"
            class="rounded-lg h-8 px-2.5 border-[#E5E0DB] text-zinc-700 hover:bg-white"
            :disabled="paginaActual <= 1 || cargando"
            @click="cargarAuditorias(paginaActual - 1)"
          >
            <ChevronLeft :size="15" class="mr-1" /> Anterior
          </Button>

          <Button
            variant="outline"
            size="sm"
            class="rounded-lg h-8 px-2.5 border-[#E5E0DB] text-zinc-700 hover:bg-white"
            :disabled="paginaActual >= ultimaPagina || cargando"
            @click="cargarAuditorias(paginaActual + 1)"
          >
            Siguiente <ChevronRight :size="15" class="ml-1" />
          </Button>
        </div>
      </div>
    </section>

    <!-- Modal de Inspección de Cambios -->
    <div
      v-if="modalDetalleAbierto && registroSeleccionado"
      class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4"
    >
      <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-xl border border-zinc-200 space-y-5 animate-in fade-in zoom-in-95 duration-150 max-h-[90vh] flex flex-col">
        <!-- Encabezado del modal -->
        <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
          <div>
            <h2 class="text-lg font-bold text-zinc-900 flex items-center gap-2">
              <History :size="20" class="text-[#F26A21]" />
              Detalle del Evento #{{ registroSeleccionado.id }}
            </h2>
            <p class="text-xs text-zinc-500 mt-0.5">
              Registrado el {{ formatearFecha(registroSeleccionado.created_at) }}
            </p>
          </div>
          <button
            type="button"
            class="text-zinc-400 hover:text-zinc-600 text-xl font-bold p-1 rounded-lg hover:bg-zinc-100"
            @click="modalDetalleAbierto = false"
          >
            &times;
          </button>
        </div>

        <!-- Metadatos rápidos -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 p-3 rounded-xl bg-[#FAF7F4] border border-[#E5E0DB] text-xs">
          <div>
            <p class="text-zinc-500 uppercase font-semibold text-[10px]">Responsable</p>
            <p class="font-bold text-zinc-800 mt-0.5">{{ registroSeleccionado.usuario?.name || 'Sistema' }}</p>
          </div>
          <div>
            <p class="text-zinc-500 uppercase font-semibold text-[10px]">Acción</p>
            <span
              class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[11px] font-bold border capitalize"
              :class="estiloBadgeAccion(registroSeleccionado.accion)"
            >
              {{ registroSeleccionado.accion.replace('_', ' ') }}
            </span>
          </div>
          <div>
            <p class="text-zinc-500 uppercase font-semibold text-[10px]">Entidad</p>
            <p class="font-mono text-zinc-800 mt-0.5">{{ registroSeleccionado.entidad }} #{{ registroSeleccionado.entidad_id }}</p>
          </div>
        </div>

        <!-- Comparativa Antes / Después -->
        <div class="flex-1 overflow-y-auto space-y-3 pr-1">
          <p class="text-xs font-bold text-zinc-700 uppercase tracking-wider">
            Comparativa de Estados (Auditoría inalterable)
          </p>

          <div
            v-if="obtenerCamposAlterados(registroSeleccionado).length === 0"
            class="text-center py-8 text-zinc-400 text-sm"
          >
            No se registraron cambios en atributos específicos para esta acción.
          </div>

          <div
            v-else
            class="rounded-xl border border-[#E5E0DB] overflow-hidden divide-y divide-zinc-100 text-xs"
          >
            <!-- Cabecera de comparación -->
            <div class="grid grid-cols-12 bg-zinc-50 py-2 px-3 font-bold text-zinc-600 text-[11px] uppercase tracking-wide">
              <div class="col-span-4">Atributo</div>
              <div class="col-span-4">Estado Anterior</div>
              <div class="col-span-4 text-[#2E7D4F]">Estado Nuevo</div>
            </div>

            <!-- Fila por cada campo alterado -->
            <div
              v-for="campo in obtenerCamposAlterados(registroSeleccionado)"
              :key="campo"
              class="grid grid-cols-12 py-2.5 px-3 hover:bg-orange-50/20 transition-colors items-start"
            >
              <div class="col-span-4 font-mono font-semibold text-zinc-800 truncate" :title="campo">
                {{ campo }}
              </div>
              <div class="col-span-4 text-zinc-500 break-words pr-2">
                {{ formatearValor(registroSeleccionado.datos_anteriores?.[campo]) }}
              </div>
              <div class="col-span-4 font-medium text-emerald-700 break-words">
                {{ formatearValor(registroSeleccionado.datos_nuevos?.[campo]) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Botón Cerrar -->
        <div class="pt-3 border-t border-zinc-100 flex justify-end">
          <Button
            type="button"
            variant="outline"
            class="rounded-xl border-zinc-200"
            @click="modalDetalleAbierto = false"
          >
            Cerrar Detalle
          </Button>
        </div>
      </div>
    </div>

  </div>
</template>
