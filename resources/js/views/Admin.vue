<script setup>
/**
 * Vista de Administración — Gestión de Usuarios y Roles RBAC (US-ADM-01 / CC-84).
 * Permite listar, registrar, editar y desactivar cuentas de usuarios con control de acceso.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Admin – US-ADM-01 / RF-ADM-008
 */
import { ref, reactive, computed, onMounted } from 'vue';
import { useRouter } from 'vue-router';
import { useSesionStore } from '@/stores/sesion.js';
import { usePermisos } from '@/shared/composables/usePermisos.js';
import {
  obtenerUsuarios,
  crearUsuario,
  actualizarUsuario,
  toggleActivoUsuario,
  obtenerRoles,
  eliminarUsuario,
} from '@/features/admin/services/usuarioService.js';
import {
  Users,
  UserPlus,
  ShieldCheck,
  CheckCircle2,
  XCircle,
  Search,
  Edit2,
  Power,
  ArrowLeft,
  RefreshCw,
  Lock,
  Mail,
  User as UserIcon,
  LogOut,
  Trash2,
  AlertTriangle,
} from '@lucide/vue';
import { toast, Toaster } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';

const router = useRouter();
const sesion = useSesionStore();
const { puedeVer } = usePermisos();

// Estado reactivo
const usuarios = ref([]);
const rolesDisponibles = ref([]);
const cargando = ref(true);
const filtroBusqueda = ref('');
const filtroRol = ref('');
const filtroEstado = ref('');

// Modales y formularios
const modalCrearAbierto = ref(false);
const modalEditarAbierto = ref(false);
const guardando = ref(false);

const formCrear = reactive({
  name: '',
  email: '',
  password: '',
  rol: 'mesero',
  activo: true,
});

const formEditar = reactive({
  id: null,
  name: '',
  email: '',
  password: '',
  rol: 'mesero',
  activo: true,
});

// Carga inicial de datos
onMounted(async () => {
  await recargarDatos();
});

async function recargarDatos() {
  cargando.value = true;
  try {
    const [listaUsuarios, listaRoles] = await Promise.all([
      obtenerUsuarios(),
      obtenerRoles(),
    ]);
    usuarios.value = listaUsuarios;
    rolesDisponibles.value = listaRoles.filter((r) => r !== 'cocinera'); // cocinero cubre ambos
  } catch (error) {
    if (error.response?.status === 403) {
      toast.error('Acceso restringido: Se requieren permisos de Administrador.');
      router.push('/pos');
      return;
    }
    toast.error('Error al cargar la lista de usuarios y roles.');
  } finally {
    cargando.value = false;
  }
}

async function cerrarSesion() {
  await sesion.cerrarSesion();
  router.push('/login');
}

// Usuarios filtrados en tiempo real
const usuariosFiltrados = computed(() => {
  return usuarios.value.filter((u) => {
    const coincideTexto =
      !filtroBusqueda.value ||
      u.name.toLowerCase().includes(filtroBusqueda.value.toLowerCase()) ||
      u.email.toLowerCase().includes(filtroBusqueda.value.toLowerCase());

    const coincideRol =
      !filtroRol.value ||
      u.rol === filtroRol.value ||
      (u.roles && u.roles.some((r) => r.name === filtroRol.value));

    const coincideEstado =
      filtroEstado.value === '' ||
      (filtroEstado.value === 'activos' && u.activo) ||
      (filtroEstado.value === 'inactivos' && !u.activo);

    return coincideTexto && coincideRol && coincideEstado;
  });
});

// Métricas de resumen
const totalUsuarios = computed(() => usuarios.value.length);
const totalActivos = computed(() => usuarios.value.filter((u) => u.activo).length);
const totalInactivos = computed(() => usuarios.value.filter((u) => !u.activo).length);

// Clases visuales para badges por rol
function obtenerColorRol(rol) {
  switch (rol) {
    case 'administrador':
      return 'bg-purple-100 text-purple-800 border-purple-200';
    case 'cajero':
      return 'bg-blue-100 text-blue-800 border-blue-200';
    case 'mesero':
      return 'bg-emerald-100 text-emerald-800 border-emerald-200';
    case 'cocinero':
    case 'cocinera':
      return 'bg-amber-100 text-amber-800 border-amber-200';
    case 'contadora':
      return 'bg-rose-100 text-rose-800 border-rose-200';
    default:
      return 'bg-zinc-100 text-zinc-800 border-zinc-200';
  }
}

// Abrir modal de creación
function abrirModalCrear() {
  formCrear.name = '';
  formCrear.email = '';
  formCrear.password = '';
  formCrear.rol = 'mesero';
  formCrear.activo = true;
  modalCrearAbierto.value = true;
}

// Abrir modal de edición
function abrirModalEditar(usuario) {
  formEditar.id = usuario.id;
  formEditar.name = usuario.name;
  formEditar.email = usuario.email;
  formEditar.password = '';
  formEditar.rol = usuario.rol || (usuario.roles?.[0]?.name ?? 'mesero');
  formEditar.activo = usuario.activo;
  modalEditarAbierto.value = true;
}

// Guardar nuevo usuario
async function guardarUsuarioNuevo() {
  guardando.value = true;
  try {
    await crearUsuario({
      name: formCrear.name,
      email: formCrear.email,
      password: formCrear.password,
      rol: formCrear.rol,
      activo: formCrear.activo,
    });
    toast.success('Usuario registrado exitosamente', {
      description: `Se asignó el rol de ${formCrear.rol}.`,
    });
    modalCrearAbierto.value = false;
    await recargarDatos();
  } catch (error) {
    const mensaje =
      error.response?.data?.message ||
      'No se pudo registrar el usuario. Verifica los campos.';
    toast.error(mensaje);
  } finally {
    guardando.value = false;
  }
}

// Actualizar usuario existente
async function guardarUsuarioModificado() {
  guardando.value = true;
  try {
    const payload = {
      name: formEditar.name,
      email: formEditar.email,
      rol: formEditar.rol,
      activo: formEditar.activo,
    };
    if (formEditar.password && formEditar.password.trim() !== '') {
      payload.password = formEditar.password;
    }

    await actualizarUsuario(formEditar.id, payload);
    toast.success('Usuario actualizado correctamente');
    modalEditarAbierto.value = false;
    await recargarDatos();
  } catch (error) {
    const mensaje =
      error.response?.data?.message ||
      'No se pudo actualizar el usuario.';
    toast.error(mensaje);
  } finally {
    guardando.value = false;
  }
}

// Alternar estado activo / desactivado
async function toggleEstado(usuario) {
  try {
    const res = await toggleActivoUsuario(usuario.id);
    const estadoTexto = res.activo ? 'activada' : 'desactivada';
    toast.success(`Cuenta ${estadoTexto} correctamente`, {
      description: res.activo
        ? 'El usuario ahora puede iniciar sesión.'
        : 'Se revocaron todos los accesos del usuario.',
    });
    await recargarDatos();
  } catch (error) {
    const mensaje =
      error.response?.data?.message ||
      'No se pudo modificar el estado de la cuenta.';
    toast.error(mensaje);
  }
}

// Modal y proceso de eliminación permanente
const modalEliminarAbierto = ref(false);
const usuarioAEliminar = ref(null);
const eliminando = ref(false);

function abrirModalEliminar(usuario) {
  usuarioAEliminar.value = usuario;
  modalEliminarAbierto.value = true;
}

async function ejecutarEliminarUsuario() {
  if (!usuarioAEliminar.value) return;
  eliminando.value = true;
  try {
    await eliminarUsuario(usuarioAEliminar.value.id);
    toast.success('Usuario eliminado permanentemente', {
      description: `La cuenta de ${usuarioAEliminar.value.name} ha sido eliminada.`,
    });
    modalEliminarAbierto.value = false;
    usuarioAEliminar.value = null;
    await recargarDatos();
  } catch (error) {
    const mensaje =
      error.response?.data?.message ||
      error.response?.data?.errors?.usuario?.[0] ||
      'No se pudo eliminar el usuario.';
    toast.error(mensaje);
  } finally {
    eliminando.value = false;
  }
}
</script>

<template>
  <Toaster position="top-right" richColors />
  <main class="min-h-screen bg-[#FDFBF7] text-[#2D2D2D] p-4 sm:p-6 lg:p-8" id="vista-admin">
    <div class="max-w-7xl mx-auto space-y-6">

      <!-- Cabecera Principal -->
      <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-[#EBE6DF] shadow-sm">
        <div class="flex items-center gap-4">
          <Button
            variant="ghost"
            size="icon"
            class="rounded-xl hover:bg-zinc-100"
            @click="router.push('/pos')"
            title="Volver al Punto de Venta (POS)"
          >
            <ArrowLeft :size="20" />
          </Button>
          <div>
            <div class="flex items-center gap-2">
              <h1 class="text-2xl font-bold text-[#1A1A1A]">Administración de Usuarios y Roles</h1>
              <Badge variant="outline" class="bg-orange-50 text-[#F26A21] border-[#F26A21]/30 font-semibold">
                RBAC
              </Badge>
            </div>
            <p class="text-sm text-[#737373]">
              Control de acceso basado en roles oficiales: Mesero, Cajero, Cocinero, Contadora y Administrador.
            </p>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <Button
            variant="outline"
            class="rounded-xl border-[#E5E0DB] text-zinc-700 hover:bg-zinc-50 font-medium"
            :disabled="cargando"
            @click="recargarDatos"
          >
            <RefreshCw :size="16" class="mr-2" :class="{ 'animate-spin': cargando }" />
            Refrescar
          </Button>

          <Button
            class="rounded-xl bg-[#F26A21] hover:bg-[#FF8C42] text-white font-semibold shadow-sm"
            @click="abrirModalCrear"
          >
            <UserPlus :size="18" class="mr-2" />
            Nuevo Usuario
          </Button>

          <Button
            variant="outline"
            class="rounded-xl border-rose-200 text-rose-600 hover:bg-rose-50 hover:border-rose-300 font-medium"
            @click="cerrarSesion"
            title="Cerrar sesión actual"
          >
            <LogOut :size="16" class="mr-2" />
            Cerrar Sesión
          </Button>
        </div>
      </header>

      <!-- Tarjetas Métricas -->
      <section class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-[#EBE6DF] shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs uppercase tracking-wider text-zinc-500 font-semibold">Total Cuentas</p>
            <p class="text-3xl font-extrabold text-[#1A1A1A] mt-1">{{ totalUsuarios }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-orange-100 text-[#F26A21] flex items-center justify-center">
            <Users :size="24" />
          </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-[#EBE6DF] shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs uppercase tracking-wider text-zinc-500 font-semibold">Cuentas Activas</p>
            <p class="text-3xl font-extrabold text-emerald-600 mt-1">{{ totalActivos }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
            <CheckCircle2 :size="24" />
          </div>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-[#EBE6DF] shadow-sm flex items-center justify-between">
          <div>
            <p class="text-xs uppercase tracking-wider text-zinc-500 font-semibold">Desactivadas</p>
            <p class="text-3xl font-extrabold text-zinc-400 mt-1">{{ totalInactivos }}</p>
          </div>
          <div class="w-12 h-12 rounded-xl bg-zinc-100 text-zinc-400 flex items-center justify-center">
            <XCircle :size="24" />
          </div>
        </div>
      </section>

      <!-- Barra de Filtros y Búsqueda -->
      <section class="bg-white p-4 rounded-2xl border border-[#EBE6DF] shadow-sm flex flex-col md:flex-row gap-3 items-center justify-between">
        <div class="relative w-full md:w-96">
          <Search :size="18" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
          <Input
            v-model="filtroBusqueda"
            type="text"
            placeholder="Buscar por nombre o correo..."
            class="pl-10 rounded-xl border-[#E5E0DB] focus-visible:ring-[#F26A21]"
          />
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
          <!-- Filtro por Rol -->
          <select
            v-model="filtroRol"
            class="px-3 py-2 bg-white border border-[#E5E0DB] rounded-xl text-sm font-medium text-zinc-700 focus:outline-none focus:ring-2 focus:ring-[#F26A21]"
          >
            <option value="">Todos los Roles</option>
            <option v-for="rol in rolesDisponibles" :key="rol" :value="rol">
              {{ rol.charAt(0).toUpperCase() + rol.slice(1) }}
            </option>
          </select>

          <!-- Filtro por Estado -->
          <select
            v-model="filtroEstado"
            class="px-3 py-2 bg-white border border-[#E5E0DB] rounded-xl text-sm font-medium text-zinc-700 focus:outline-none focus:ring-2 focus:ring-[#F26A21]"
          >
            <option value="">Todos los Estados</option>
            <option value="activos">Solo Activos</option>
            <option value="inactivos">Solo Inactivos</option>
          </select>
        </div>
      </section>

      <!-- Tabla de Usuarios -->
      <section class="bg-white rounded-2xl border border-[#EBE6DF] shadow-sm overflow-hidden">
        <div v-if="cargando" class="p-12 text-center text-zinc-500">
          <RefreshCw :size="32" class="animate-spin mx-auto mb-3 text-[#F26A21]" />
          <p class="font-medium">Cargando cuentas de usuario...</p>
        </div>

        <div v-else-if="usuariosFiltrados.length === 0" class="p-12 text-center text-zinc-500">
          <Users :size="40" class="mx-auto mb-3 text-zinc-300" />
          <p class="text-base font-semibold text-zinc-700">No se encontraron usuarios</p>
          <p class="text-sm text-zinc-400 mt-1">Prueba cambiando los filtros de búsqueda o registra una nueva cuenta.</p>
        </div>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="bg-[#FAF8F5] text-zinc-500 border-b border-[#EBE6DF] uppercase tracking-wider text-xs font-semibold">
              <tr>
                <th class="py-3.5 px-6">Empleado</th>
                <th class="py-3.5 px-6">Correo Corporativo</th>
                <th class="py-3.5 px-6">Rol Asignado</th>
                <th class="py-3.5 px-6">Estado</th>
                <th class="py-3.5 px-6 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[#F0ECE6]">
              <tr
                v-for="usuario in usuariosFiltrados"
                :key="usuario.id"
                class="hover:bg-zinc-50/80 transition-colors"
              >
                <!-- Nombre y Avatar -->
                <td class="py-4 px-6">
                  <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-orange-100 text-[#F26A21] flex items-center justify-center font-bold text-sm">
                      {{ usuario.name.charAt(0).toUpperCase() }}
                    </div>
                    <div>
                      <p class="font-semibold text-zinc-900">{{ usuario.name }}</p>
                      <p class="text-xs text-zinc-400">ID: #{{ usuario.id }}</p>
                    </div>
                  </div>
                </td>

                <!-- Correo -->
                <td class="py-4 px-6 text-zinc-600 font-mono text-xs">
                  {{ usuario.email }}
                </td>

                <!-- Rol -->
                <td class="py-4 px-6">
                  <span
                    :class="[
                      'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border',
                      obtenerColorRol(usuario.rol || usuario.roles?.[0]?.name)
                    ]"
                  >
                    <ShieldCheck :size="12" class="mr-1" />
                    {{ (usuario.rol || usuario.roles?.[0]?.name || 'Sin rol').toUpperCase() }}
                  </span>
                </td>

                <!-- Estado -->
                <td class="py-4 px-6">
                  <span
                    v-if="usuario.activo"
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    Activo
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-zinc-100 text-zinc-500 border border-zinc-200"
                  >
                    <span class="w-1.5 h-1.5 rounded-full bg-zinc-400"></span>
                    Inactivo
                  </span>
                </td>

                <!-- Acciones -->
                <td class="py-4 px-6 text-right">
                  <div class="flex items-center justify-end gap-2">
                    <Button
                      variant="ghost"
                      size="sm"
                      class="rounded-lg text-zinc-600 hover:text-zinc-900 hover:bg-zinc-100"
                      @click="abrirModalEditar(usuario)"
                      title="Editar usuario"
                    >
                      <Edit2 :size="16" />
                    </Button>

                    <Button
                      variant="ghost"
                      size="sm"
                      :class="[
                        'rounded-lg',
                        usuario.activo
                          ? 'text-rose-600 hover:bg-rose-50'
                          : 'text-emerald-600 hover:bg-emerald-50'
                      ]"
                      @click="toggleEstado(usuario)"
                      :title="usuario.activo ? 'Desactivar cuenta' : 'Activar cuenta'"
                    >
                      <Power :size="16" />
                    </Button>

                    <Button
                      variant="ghost"
                      size="sm"
                      class="rounded-lg text-rose-500 hover:text-rose-700 hover:bg-rose-50"
                      @click="abrirModalEliminar(usuario)"
                      title="Eliminar usuario permanentemente"
                    >
                      <Trash2 :size="16" />
                    </Button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Modal Crear Usuario -->
      <div
        v-if="modalCrearAbierto"
        class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4"
      >
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-zinc-200 space-y-5 animate-in fade-in zoom-in-95 duration-150">
          <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
            <h2 class="text-lg font-bold text-zinc-900 flex items-center gap-2">
              <UserPlus :size="20" class="text-[#F26A21]" />
              Crear Nuevo Usuario
            </h2>
            <button
              class="text-zinc-400 hover:text-zinc-600 text-xl font-bold"
              @click="modalCrearAbierto = false"
            >
              &times;
            </button>
          </div>

          <form @submit.prevent="guardarUsuarioNuevo" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-zinc-700 uppercase mb-1">Nombre Completo</label>
              <div class="relative">
                <UserIcon :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
                <Input
                  v-model="formCrear.name"
                  type="text"
                  placeholder="Ej: Juan Pérez"
                  required
                  class="pl-9 rounded-xl"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-zinc-700 uppercase mb-1">Correo Corporativo</label>
              <div class="relative">
                <Mail :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
                <Input
                  v-model="formCrear.email"
                  type="email"
                  placeholder="juan@sgco-chayito.local"
                  required
                  class="pl-9 rounded-xl"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-zinc-700 uppercase mb-1">Contraseña (mínimo 8 caracteres)</label>
              <div class="relative">
                <Lock :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-zinc-400" />
                <Input
                  v-model="formCrear.password"
                  type="password"
                  placeholder="••••••••"
                  minlength="8"
                  required
                  class="pl-9 rounded-xl"
                />
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-zinc-700 uppercase mb-1">Rol en el Sistema</label>
              <select
                v-model="formCrear.rol"
                required
                class="w-full px-3 py-2.5 bg-white border border-[#E5E0DB] rounded-xl text-sm font-medium text-zinc-800 focus:outline-none focus:ring-2 focus:ring-[#F26A21]"
              >
                <option v-for="rol in rolesDisponibles" :key="rol" :value="rol">
                  {{ rol.toUpperCase() }}
                </option>
              </select>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3 border-t border-zinc-100">
              <Button
                type="button"
                variant="outline"
                class="rounded-xl"
                @click="modalCrearAbierto = false"
              >
                Cancelar
              </Button>
              <Button
                type="submit"
                class="rounded-xl bg-[#F26A21] hover:bg-[#FF8C42] text-white font-semibold"
                :disabled="guardando"
              >
                {{ guardando ? 'Guardando...' : 'Crear Usuario' }}
              </Button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Editar Usuario -->
      <div
        v-if="modalEditarAbierto"
        class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4"
      >
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-zinc-200 space-y-5 animate-in fade-in zoom-in-95 duration-150">
          <div class="flex items-center justify-between pb-3 border-b border-zinc-100">
            <h2 class="text-lg font-bold text-zinc-900 flex items-center gap-2">
              <Edit2 :size="20" class="text-[#F26A21]" />
              Modificar Usuario
            </h2>
            <button
              class="text-zinc-400 hover:text-zinc-600 text-xl font-bold"
              @click="modalEditarAbierto = false"
            >
              &times;
            </button>
          </div>

          <form @submit.prevent="guardarUsuarioModificado" class="space-y-4">
            <div>
              <label class="block text-xs font-semibold text-zinc-700 uppercase mb-1">Nombre Completo</label>
              <Input
                v-model="formEditar.name"
                type="text"
                required
                class="rounded-xl"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-zinc-700 uppercase mb-1">Correo Corporativo</label>
              <Input
                v-model="formEditar.email"
                type="email"
                required
                class="rounded-xl"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-zinc-700 uppercase mb-1">
                Nueva Contraseña <span class="text-zinc-400 font-normal">(dejar en blanco para no cambiar)</span>
              </label>
              <Input
                v-model="formEditar.password"
                type="password"
                placeholder="Solo si deseas cambiarla..."
                minlength="8"
                class="rounded-xl"
              />
            </div>

            <div>
              <label class="block text-xs font-semibold text-zinc-700 uppercase mb-1">Rol Asignado</label>
              <select
                v-model="formEditar.rol"
                required
                class="w-full px-3 py-2.5 bg-white border border-[#E5E0DB] rounded-xl text-sm font-medium text-zinc-800 focus:outline-none focus:ring-2 focus:ring-[#F26A21]"
              >
                <option v-for="rol in rolesDisponibles" :key="rol" :value="rol">
                  {{ rol.toUpperCase() }}
                </option>
              </select>
            </div>

            <div class="flex items-center gap-2 pt-1">
              <input
                id="check-activo"
                v-model="formEditar.activo"
                type="checkbox"
                class="rounded border-zinc-300 text-[#F26A21] focus:ring-[#F26A21] h-4 w-4"
              />
              <label for="check-activo" class="text-sm font-medium text-zinc-700">Cuenta activa</label>
            </div>

            <div class="pt-2 flex items-center justify-end gap-3 border-t border-zinc-100">
              <Button
                type="button"
                variant="outline"
                class="rounded-xl"
                @click="modalEditarAbierto = false"
              >
                Cancelar
              </Button>
              <Button
                type="submit"
                class="rounded-xl bg-[#F26A21] hover:bg-[#FF8C42] text-white font-semibold"
                :disabled="guardando"
              >
                {{ guardando ? 'Guardando...' : 'Guardar Cambios' }}
              </Button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal Confirmar Eliminación -->
      <div
        v-if="modalEliminarAbierto"
        class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4"
      >
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-xl border border-zinc-200 space-y-4 animate-in fade-in zoom-in-95 duration-150">
          <div class="flex items-center gap-3 text-rose-600">
            <div class="w-10 h-10 rounded-xl bg-rose-100 flex items-center justify-center shrink-0">
              <AlertTriangle :size="22" />
            </div>
            <div>
              <h2 class="text-base font-bold text-zinc-900">¿Eliminar usuario permanentemente?</h2>
              <p class="text-xs text-zinc-500">Esta acción no se puede revertir</p>
            </div>
          </div>

          <p class="text-sm text-zinc-600">
            Estás a punto de eliminar al usuario <strong class="text-zinc-900">{{ usuarioAEliminar?.name }}</strong> (<span class="font-mono text-xs">{{ usuarioAEliminar?.email }}</span>).
          </p>

          <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl text-xs text-amber-800 space-y-1">
            <p class="font-semibold">Protección de integridad contable:</p>
            <p>Si el usuario tiene comandas, ventas o retiros vinculados, el sistema bloqueará la eliminación para no alterar los cortes. En ese caso, desactiva la cuenta en su lugar.</p>
          </div>

          <div class="flex justify-end gap-3 pt-2">
            <Button
              variant="outline"
              type="button"
              class="rounded-xl border-zinc-200"
              @click="modalEliminarAbierto = false"
              :disabled="eliminando"
            >
              Cancelar
            </Button>
            <Button
              type="button"
              class="bg-rose-600 hover:bg-rose-700 text-white rounded-xl flex items-center gap-1.5 font-semibold"
              @click="ejecutarEliminarUsuario"
              :disabled="eliminando"
            >
              <Trash2 :size="16" v-if="!eliminando" />
              <RefreshCw :size="16" class="animate-spin" v-else />
              {{ eliminando ? 'Eliminando...' : 'Eliminar Permanentemente' }}
            </Button>
          </div>
        </div>
      </div>

    </div>
  </main>
</template>
