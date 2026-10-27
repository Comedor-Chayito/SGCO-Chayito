<script setup>
/**
 * Formulario rápido de registro de facturas de proveedores como egresos.
 * La subcategoría se elige con un clic y la fecha se conserva entre registros
 * para cargar varias facturas del mismo día sin volver a escribirla.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 (CC-62)
 */
import { computed, nextTick, onMounted, ref } from 'vue';
import { FileText, Loader2, RotateCcw, Save } from '@lucide/vue';
import {
  obtenerSubcategoriasEgreso,
  registrarEgreso,
} from '@/features/contabilidad/services/egresoService.js';

const emit = defineEmits(['egreso-registrado']);

// ─── Límites alineados con la tabla egresos (DECIMAL(10,2), VARCHAR) ───
const MONTO_MAXIMO_EGRESO = 99999999.99;
const LARGO_MAXIMO_PROVEEDOR = 150;
const LARGO_MAXIMO_COMPROBANTE = 50;
const LARGO_MAXIMO_DESCRIPCION = 500;

const CAMPOS = [
  'subcategoria_egreso_id',
  'proveedor',
  'numero_comprobante',
  'fecha_comprobante',
  'monto_total',
  'descripcion',
];

/**
 * Devuelve la fecha local de hoy en formato YYYY-MM-DD.
 * No usa toISOString porque convierte a UTC y de noche adelanta un día.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 (CC-62)
 *
 * @returns {string} Fecha de hoy.
 */
function fechaHoy() {
  const hoy = new Date();
  const mes = String(hoy.getMonth() + 1).padStart(2, '0');
  const dia = String(hoy.getDate()).padStart(2, '0');

  return `${hoy.getFullYear()}-${mes}-${dia}`;
}

/**
 * Crea un objeto con una propiedad vacía por cada campo del formulario.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 (CC-62)
 *
 * @returns {Object<string, string>} Errores en blanco.
 */
function erroresVacios() {
  return Object.fromEntries(CAMPOS.map((campo) => [campo, '']));
}

// ─── Estado ───
const subcategorias = ref([]);
const cargandoSubcategorias = ref(true);
const enviando = ref(false);
const errorGeneral = ref('');
const errores = ref(erroresVacios());
const campoProveedor = ref(null);

const formulario = ref({
  subcategoria_egreso_id: null,
  proveedor: '',
  numero_comprobante: '',
  fecha_comprobante: fechaHoy(),
  monto_total: '',
  descripcion: '',
});

const formularioCompleto = computed(() =>
  formulario.value.subcategoria_egreso_id
  && formulario.value.proveedor.trim()
  && formulario.value.numero_comprobante.trim()
  && formulario.value.fecha_comprobante
  && parseFloat(formulario.value.monto_total) > 0
);

/**
 * Carga el catálogo de subcategorías activas al montar el formulario.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 (CC-62)
 *
 * @returns {Promise<void>}
 */
async function cargarSubcategorias() {
  cargandoSubcategorias.value = true;

  try {
    subcategorias.value = await obtenerSubcategoriasEgreso();
  } catch (err) {
    errorGeneral.value = err.response?.data?.message
      ?? 'No se pudieron cargar las subcategorías. Recarga la página.';
  } finally {
    cargandoSubcategorias.value = false;
  }
}

/**
 * Limpia el monto mientras se escribe: solo dígitos, un punto y dos decimales.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 (CC-62)
 *
 * @param   {InputEvent} evento Evento input del campo monto.
 * @returns {void}
 */
function formatearMonto(evento) {
  let valor = evento.target.value.replace(/[^0-9.]/g, '');
  const partes = valor.split('.');

  if (partes.length > 1) {
    valor = `${partes[0]}.${partes.slice(1).join('').slice(0, 2)}`;
  }

  // Si el valor limpio no cambia, Vue no repinta el input: se escribe directo en el DOM
  evento.target.value = valor;
  formulario.value.monto_total = valor;
}

/**
 * Valida los campos en el navegador antes de enviar al backend.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 (CC-62)
 *
 * @returns {boolean} true si todos los campos son válidos.
 */
function validarCampos() {
  const datos = formulario.value;
  const nuevos = erroresVacios();
  const monto = parseFloat(datos.monto_total);

  if (!datos.subcategoria_egreso_id) {
    nuevos.subcategoria_egreso_id = 'Elige una subcategoría.';
  }

  if (!datos.proveedor.trim()) {
    nuevos.proveedor = 'El proveedor es obligatorio.';
  }

  if (!datos.numero_comprobante.trim()) {
    nuevos.numero_comprobante = 'El número de comprobante es obligatorio.';
  }

  if (!datos.fecha_comprobante) {
    nuevos.fecha_comprobante = 'La fecha es obligatoria.';
  } else if (datos.fecha_comprobante > fechaHoy()) {
    nuevos.fecha_comprobante = 'La fecha no puede ser futura.';
  }

  if (isNaN(monto) || monto <= 0) {
    nuevos.monto_total = 'El monto debe ser mayor a $0.00.';
  } else if (monto > MONTO_MAXIMO_EGRESO) {
    nuevos.monto_total = 'El monto no puede superar $99,999,999.99.';
  }

  errores.value = nuevos;

  return Object.values(nuevos).every((mensaje) => !mensaje);
}

/**
 * Deja el formulario listo para la siguiente factura.
 * Conserva la fecha, porque normalmente se cargan varias del mismo día.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 (CC-62)
 *
 * @returns {Promise<void>}
 */
async function reiniciarFormulario() {
  formulario.value = {
    ...formulario.value,
    subcategoria_egreso_id: null,
    proveedor: '',
    numero_comprobante: '',
    monto_total: '',
    descripcion: '',
  };
  errores.value = erroresVacios();
  errorGeneral.value = '';

  await nextTick();
  campoProveedor.value?.focus();
}

/**
 * Envía la factura al backend y muestra los errores de validación por campo.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005 (CC-62)
 *
 * @returns {Promise<void>}
 */
async function manejarRegistro() {
  errorGeneral.value = '';

  if (!validarCampos()) return;

  enviando.value = true;

  try {
    const datos = formulario.value;
    const egreso = await registrarEgreso({
      subcategoria_egreso_id: datos.subcategoria_egreso_id,
      proveedor: datos.proveedor.trim(),
      numero_comprobante: datos.numero_comprobante.trim(),
      fecha_comprobante: datos.fecha_comprobante,
      monto_total: parseFloat(parseFloat(datos.monto_total).toFixed(2)),
      descripcion: datos.descripcion.trim() || null,
    });

    emit('egreso-registrado', egreso);
    await reiniciarFormulario();
  } catch (err) {
    if (err.response?.status === 422) {
      const erroresApi = err.response.data?.errors ?? {};

      errores.value = Object.fromEntries(
        CAMPOS.map((campo) => [campo, erroresApi[campo]?.[0] ?? ''])
      );

      // El mensaje general repite el primer error; solo se muestra si ninguno tiene campo propio
      const hayErrorDeCampo = Object.values(errores.value).some((mensaje) => mensaje);
      errorGeneral.value = hayErrorDeCampo ? '' : (err.response.data?.message ?? '');
    } else {
      errorGeneral.value = err.response?.data?.message
        ?? 'No se pudo registrar la factura. Intenta de nuevo.';
    }
  } finally {
    enviando.value = false;
  }
}

onMounted(cargarSubcategorias);
</script>

<template>
  <section
    id="formulario-egreso"
    class="rounded-2xl border border-[#E5E0DB] bg-white p-6 shadow-sm"
  >
    <header class="mb-5 flex items-center gap-3">
      <div
        class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#FAF7F4] text-[#C24E12]"
      >
        <FileText :size="22" aria-hidden="true" />
      </div>
      <div>
        <h2 class="text-lg font-bold text-[#3D3D3D]">Registrar factura de proveedor</h2>
        <p class="text-xs text-[#6B6B6B]">
          El correlativo del egreso se asigna automáticamente al guardar.
        </p>
      </div>
    </header>

    <div
      v-if="errorGeneral"
      class="mb-4 rounded-xl border border-[#C62828]/20 bg-[#C62828]/5 px-4 py-3
        text-sm font-medium text-[#C62828]"
      role="alert"
    >
      {{ errorGeneral }}
    </div>

    <form class="space-y-5" novalidate @submit.prevent="manejarRegistro">
      <!-- Subcategoría: un clic, sin desplegables -->
      <fieldset>
        <legend class="mb-2 text-sm font-semibold text-[#3D3D3D]">Subcategoría</legend>

        <p v-if="cargandoSubcategorias" class="flex items-center gap-2 text-sm text-[#6B6B6B]">
          <Loader2 :size="16" class="animate-spin" aria-hidden="true" />
          Cargando subcategorías…
        </p>

        <div v-else class="flex flex-wrap gap-2" role="radiogroup">
          <button
            v-for="subcategoria in subcategorias"
            :key="subcategoria.id"
            type="button"
            role="radio"
            :aria-checked="formulario.subcategoria_egreso_id === subcategoria.id"
            :disabled="enviando"
            :class="[
              'min-h-11 rounded-xl border px-4 text-sm font-medium transition-colors',
              formulario.subcategoria_egreso_id === subcategoria.id
                ? 'border-[#F26A21] bg-[#F26A21] text-white'
                : 'border-[#E5E0DB] bg-white text-[#3D3D3D] hover:border-[#FF8C42]',
            ]"
            @click="formulario.subcategoria_egreso_id = subcategoria.id"
          >
            {{ subcategoria.nombre }}
          </button>
        </div>

        <p
          v-if="errores.subcategoria_egreso_id"
          class="mt-1 text-xs font-medium text-[#C62828]"
          role="alert"
        >
          {{ errores.subcategoria_egreso_id }}
        </p>
      </fieldset>

      <div class="grid gap-4 md:grid-cols-2">
        <!-- Proveedor -->
        <div class="space-y-1">
          <label for="campo-proveedor" class="text-sm font-semibold text-[#3D3D3D]">
            Proveedor
          </label>
          <input
            id="campo-proveedor"
            ref="campoProveedor"
            v-model="formulario.proveedor"
            type="text"
            autocomplete="off"
            :maxlength="LARGO_MAXIMO_PROVEEDOR"
            :disabled="enviando"
            :class="[
              'min-h-11 w-full rounded-xl border bg-white px-3 text-sm text-[#111111]',
              'focus:border-[#F26A21] focus:outline-none',
              errores.proveedor ? 'border-[#C62828]' : 'border-[#E5E0DB]',
            ]"
          />
          <p v-if="errores.proveedor" class="text-xs font-medium text-[#C62828]" role="alert">
            {{ errores.proveedor }}
          </p>
        </div>

        <!-- Número de comprobante -->
        <div class="space-y-1">
          <label for="campo-comprobante" class="text-sm font-semibold text-[#3D3D3D]">
            N.º de factura o CCF
          </label>
          <input
            id="campo-comprobante"
            v-model="formulario.numero_comprobante"
            type="text"
            autocomplete="off"
            :maxlength="LARGO_MAXIMO_COMPROBANTE"
            :disabled="enviando"
            :class="[
              'min-h-11 w-full rounded-xl border bg-white px-3 text-sm text-[#111111]',
              'focus:border-[#F26A21] focus:outline-none',
              errores.numero_comprobante ? 'border-[#C62828]' : 'border-[#E5E0DB]',
            ]"
          />
          <p
            v-if="errores.numero_comprobante"
            class="text-xs font-medium text-[#C62828]"
            role="alert"
          >
            {{ errores.numero_comprobante }}
          </p>
        </div>

        <!-- Fecha del comprobante -->
        <div class="space-y-1">
          <label for="campo-fecha" class="text-sm font-semibold text-[#3D3D3D]">
            Fecha de la factura
          </label>
          <input
            id="campo-fecha"
            v-model="formulario.fecha_comprobante"
            type="date"
            :max="fechaHoy()"
            :disabled="enviando"
            :class="[
              'min-h-11 w-full rounded-xl border bg-white px-3 text-sm text-[#111111]',
              'focus:border-[#F26A21] focus:outline-none',
              errores.fecha_comprobante ? 'border-[#C62828]' : 'border-[#E5E0DB]',
            ]"
          />
          <p
            v-if="errores.fecha_comprobante"
            class="text-xs font-medium text-[#C62828]"
            role="alert"
          >
            {{ errores.fecha_comprobante }}
          </p>
        </div>

        <!-- Monto total -->
        <div class="space-y-1">
          <label for="campo-monto" class="text-sm font-semibold text-[#3D3D3D]">
            Monto total con IVA ($)
          </label>
          <!-- Input nativo: formatearMonto recorta decimales escribiendo directo en el DOM -->
          <input
            id="campo-monto"
            :value="formulario.monto_total"
            type="text"
            inputmode="decimal"
            placeholder="0.00"
            autocomplete="off"
            :disabled="enviando"
            :class="[
              'min-h-11 w-full rounded-xl border bg-white px-3 text-sm text-[#111111]',
              'focus:border-[#F26A21] focus:outline-none',
              errores.monto_total ? 'border-[#C62828]' : 'border-[#E5E0DB]',
            ]"
            @input="formatearMonto"
          />
          <p v-if="errores.monto_total" class="text-xs font-medium text-[#C62828]" role="alert">
            {{ errores.monto_total }}
          </p>
        </div>
      </div>

      <!-- Descripción opcional -->
      <div class="space-y-1">
        <label for="campo-descripcion" class="text-sm font-semibold text-[#3D3D3D]">
          Descripción <span class="font-normal text-[#6B6B6B]">(opcional)</span>
        </label>
        <textarea
          id="campo-descripcion"
          v-model="formulario.descripcion"
          rows="2"
          :maxlength="LARGO_MAXIMO_DESCRIPCION"
          :disabled="enviando"
          :class="[
            'w-full rounded-xl border bg-white px-3 py-2 text-sm text-[#111111]',
            'focus:border-[#F26A21] focus:outline-none',
            errores.descripcion ? 'border-[#C62828]' : 'border-[#E5E0DB]',
          ]"
        />
        <p v-if="errores.descripcion" class="text-xs font-medium text-[#C62828]" role="alert">
          {{ errores.descripcion }}
        </p>
      </div>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <button
          type="button"
          :disabled="enviando"
          class="flex min-h-11 items-center justify-center gap-2 rounded-xl border px-4
            border-[#E5E0DB] bg-white text-sm font-semibold text-[#3D3D3D]
            hover:bg-[#FAF7F4] disabled:opacity-50"
          @click="reiniciarFormulario"
        >
          <RotateCcw :size="16" aria-hidden="true" />
          Limpiar
        </button>
        <button
          id="boton-registrar-egreso"
          type="submit"
          :disabled="enviando || cargandoSubcategorias || !formularioCompleto"
          class="flex min-h-11 items-center justify-center gap-2 rounded-xl px-5
            bg-[#F26A21] text-sm font-semibold text-white
            hover:bg-[#FF8C42] disabled:cursor-not-allowed disabled:opacity-50"
        >
          <Loader2 v-if="enviando" :size="16" class="animate-spin" aria-hidden="true" />
          <Save v-else :size="16" aria-hidden="true" />
          {{ enviando ? 'Guardando…' : 'Guardar egreso' }}
        </button>
      </div>
    </form>
  </section>
</template>
