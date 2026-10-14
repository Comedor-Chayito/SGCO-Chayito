<script setup>
/**
 * Formulario de registro de retiros parciales de efectivo asociados al turno.
 * Permite al cajero registrar un retiro con monto y motivo.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002 (CC-37)
 */
import { ref, computed } from 'vue'
import {
  ArrowDownCircle,
  DollarSign,
  FileText,
  Loader2,
  CheckCircle2,
} from '@lucide/vue'
import { registrarRetiro } from
  '@/features/pos/services/cajaService.js'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'

const emit = defineEmits(['retiro-registrado'])

// ─── Límites alineados con RegistrarRetiroRequest y la tabla retiros_parciales ───
const MONTO_MAXIMO_RETIRO = 99999.99
const LARGO_MAXIMO_MOTIVO = 150

// ─── Estado del formulario ───
const monto = ref('')
const motivo = ref('')
const motivoPersonalizado = ref('')
const cargando = ref(false)
const error = ref('')
const exito = ref(false)
const erroresValidacion = ref({
  monto: '',
  motivo: '',
})

// ─── Motivos predefinidos de retiro ───
const MOTIVOS_COMUNES = [
  { valor: 'Pago a proveedor', etiqueta: 'Pago a proveedor' },
  { valor: 'Cambio de billetes', etiqueta: 'Cambio de billetes' },
  { valor: 'Gastos menores', etiqueta: 'Gastos menores' },
  { valor: 'Compra de insumos', etiqueta: 'Compra de insumos' },
  { valor: 'otro', etiqueta: 'Otro (especificar)' },
]

const esMotivoPersonalizado = computed(() =>
  motivo.value === 'otro'
)

const motivoDefinitivo = computed(() =>
  esMotivoPersonalizado.value
    ? motivoPersonalizado.value.trim()
    : motivo.value
)

/**
 * Valida los campos del formulario antes de enviar.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002 (CC-37)
 *
 * @returns {boolean} true si la validación pasa.
 */
function validarCampos() {
  erroresValidacion.value = {
    monto: '',
    motivo: '',
  }
  let valido = true

  const montoNumerico = parseFloat(monto.value)

  if (!monto.value) {
    erroresValidacion.value.monto =
      'El monto es obligatorio.'
    valido = false
  } else if (isNaN(montoNumerico) || montoNumerico <= 0) {
    erroresValidacion.value.monto =
      'El monto debe ser mayor a $0.00.'
    valido = false
  } else if (montoNumerico > MONTO_MAXIMO_RETIRO) {
    erroresValidacion.value.monto =
      'El monto no puede superar $99,999.99.'
    valido = false
  }

  if (!motivoDefinitivo.value) {
    erroresValidacion.value.motivo =
      'El motivo es obligatorio.'
    valido = false
  } else if (motivoDefinitivo.value.length < 3) {
    erroresValidacion.value.motivo =
      'El motivo debe tener al menos 3 caracteres.'
    valido = false
  }

  return valido
}

/**
 * Reinicia el formulario a sus valores por defecto.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002 (CC-37)
 *
 * @returns {void}
 */
function reiniciarFormulario() {
  monto.value = ''
  motivo.value = ''
  motivoPersonalizado.value = ''
  error.value = ''
  erroresValidacion.value = {
    monto: '',
    motivo: '',
  }
}

/**
 * Procesa el envío del formulario de retiro.
 * Delega el registro al servicio de caja.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002 (CC-37)
 *
 * @returns {Promise<void>}
 */
async function manejarRegistro() {
  error.value = ''
  exito.value = false

  if (!validarCampos()) return

  cargando.value = true

  try {
    const resultado = await registrarRetiro({
      monto: parseFloat(
        parseFloat(monto.value).toFixed(2)
      ),
      motivo: motivoDefinitivo.value,
    })

    exito.value = true
    emit('retiro-registrado', resultado)

    setTimeout(() => {
      exito.value = false
      reiniciarFormulario()
    }, 2000)
  } catch (err) {
    if (err.response?.status === 422) {
      const errores = err.response.data?.errors ?? {}

      erroresValidacion.value.monto =
        errores.monto?.[0] ?? ''
      erroresValidacion.value.motivo =
        errores.motivo?.[0] ?? ''
      // Regla de negocio sin campo propio: la caja del día ya está cerrada
      error.value = errores.caja?.[0] ?? ''
    } else {
      error.value =
        err.response?.data?.message
        ?? 'Error al registrar el retiro. '
          + 'Intenta de nuevo.'
    }
  } finally {
    cargando.value = false
  }
}

/**
 * Formatea un valor numérico como moneda a 2 decimales.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002 (CC-37)
 *
 * @param   {string} valor Valor del input a formatear.
 * @returns {void}
 */
function formatearMonto(evento) {
  let valor = evento.target.value

  // Solo permitir dígitos y un punto decimal
  valor = valor.replace(/[^0-9.]/g, '')

  // Solo un punto decimal
  const partes = valor.split('.')

  if (partes.length > 2) {
    valor = partes[0] + '.' + partes.slice(1).join('')
  }

  // Máximo 2 decimales
  if (partes.length === 2 && partes[1].length > 2) {
    valor = partes[0] + '.' + partes[1].slice(0, 2)
  }

  monto.value = valor
}

const formularioValido = computed(() =>
  monto.value
  && parseFloat(monto.value) > 0
  && motivoDefinitivo.value.length >= 3
)
</script>

<template>
  <div
    class="formulario-retiro-contenedor"
    id="formulario-retiro"
  >
    <!-- Encabezado -->
    <div class="formulario-retiro-encabezado">
      <div class="formulario-retiro-icono-envoltorio">
        <ArrowDownCircle
          :size="24"
          class="text-red-600"
        />
      </div>
      <div>
        <h2 class="formulario-retiro-titulo">
          Registrar retiro de efectivo
        </h2>
        <p class="formulario-retiro-subtitulo">
          Se descuenta del efectivo del turno
        </p>
      </div>
    </div>

    <!-- Error general -->
    <div
      v-if="error"
      class="formulario-retiro-error-general"
      role="alert"
    >
      <span class="formulario-retiro-error-icono">!</span>
      {{ error }}
    </div>

    <!-- Mensaje de éxito -->
    <div
      v-if="exito"
      class="formulario-retiro-exito"
      role="status"
    >
      <CheckCircle2 :size="20" />
      Retiro registrado correctamente
    </div>

    <!-- Formulario -->
    <form
      class="formulario-retiro-form"
      id="form-retiro"
      @submit.prevent="manejarRegistro"
      novalidate
    >
      <!-- Monto -->
      <div class="formulario-retiro-campo">
        <Label
          for="campo-monto"
          class="formulario-retiro-etiqueta"
        >
          Monto ($)
        </Label>
        <div class="formulario-retiro-input-envoltorio">
          <DollarSign
            :size="18"
            class="formulario-retiro-input-icono"
            aria-hidden="true"
          />
          <Input
            id="campo-monto"
            :model-value="monto"
            type="text"
            inputmode="decimal"
            placeholder="0.00"
            autocomplete="off"
            :disabled="cargando"
            :class="[
              'formulario-retiro-input',
              'formulario-retiro-input-con-icono',
              {
                'formulario-retiro-input-error':
                  erroresValidacion.monto,
              },
            ]"
            @input="formatearMonto"
          />
        </div>
        <p
          v-if="erroresValidacion.monto"
          class="formulario-retiro-error-campo"
          role="alert"
        >
          {{ erroresValidacion.monto }}
        </p>
      </div>

      <!-- Motivo -->
      <div class="formulario-retiro-campo">
        <Label
          for="campo-motivo"
          class="formulario-retiro-etiqueta"
        >
          Motivo
        </Label>
        <Select
          v-model="motivo"
          :disabled="cargando"
        >
          <SelectTrigger
            id="campo-motivo"
            :class="[
              'formulario-retiro-select-trigger',
              {
                'formulario-retiro-input-error':
                  erroresValidacion.motivo
                  && !esMotivoPersonalizado,
              },
            ]"
          >
            <div class="flex items-center gap-2">
              <FileText
                :size="16"
                class="formulario-retiro-select-icono"
                aria-hidden="true"
              />
              <SelectValue
                placeholder="Selecciona un motivo"
              />
            </div>
          </SelectTrigger>
          <SelectContent>
            <SelectItem
              v-for="m in MOTIVOS_COMUNES"
              :key="m.valor"
              :value="m.valor"
            >
              {{ m.etiqueta }}
            </SelectItem>
          </SelectContent>
        </Select>

        <!-- Campo personalizado si eligió "Otro" -->
        <div
          v-if="esMotivoPersonalizado"
          class="formulario-retiro-motivo-custom"
        >
          <Input
            id="campo-motivo-personalizado"
            v-model="motivoPersonalizado"
            type="text"
            placeholder="Describe el motivo del retiro…"
            :maxlength="LARGO_MAXIMO_MOTIVO"
            autocomplete="off"
            :disabled="cargando"
            :class="[
              'formulario-retiro-input',
              {
                'formulario-retiro-input-error':
                  erroresValidacion.motivo,
              },
            ]"
          />
        </div>
        <p
          v-if="erroresValidacion.motivo"
          class="formulario-retiro-error-campo"
          role="alert"
        >
          {{ erroresValidacion.motivo }}
        </p>
      </div>

      <!-- Resumen previo al envío -->
      <div
        v-if="formularioValido"
        class="formulario-retiro-resumen"
      >
        <div class="formulario-retiro-resumen-fila">
          <span class="formulario-retiro-resumen-label">
            Monto
          </span>
          <span
            class="formulario-retiro-resumen-valor
              formulario-retiro-resumen-monto"
          >
            ${{ parseFloat(monto).toFixed(2) }}
          </span>
        </div>
        <div class="formulario-retiro-resumen-fila">
          <span class="formulario-retiro-resumen-label">
            Motivo
          </span>
          <span class="formulario-retiro-resumen-valor">
            {{ motivoDefinitivo }}
          </span>
        </div>
      </div>

      <!-- Botón de registro -->
      <Button
        type="submit"
        id="boton-registrar-retiro"
        class="formulario-retiro-boton"
        :disabled="cargando || !formularioValido || exito"
      >
        <Loader2
          v-if="cargando"
          :size="20"
          class="formulario-retiro-spinner"
        />
        <CheckCircle2 v-else-if="exito" :size="20" />
        <ArrowDownCircle v-else :size="20" />
        {{
          cargando
            ? 'Registrando…'
            : exito
              ? '¡Registrado!'
              : 'Registrar retiro'
        }}
      </Button>
    </form>
  </div>
</template>

<style scoped>
/* ─── Contenedor principal ─── */
.formulario-retiro-contenedor {
  background: var(--color-fondo-base);
  border: 1px solid var(--color-borde);
  border-radius: 1.25rem;
  padding: 1.5rem;
  width: 100%;
  max-width: 480px;
  box-shadow:
    0 4px 24px 0 rgb(0 0 0 / 4%),
    0 1px 3px 0 rgb(0 0 0 / 3%);
  animation: retiro-aparecer 0.35s ease-out;
}

@keyframes retiro-aparecer {
  from {
    opacity: 0;
    transform: translateY(8px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ─── Encabezado ─── */
.formulario-retiro-encabezado {
  display: flex;
  align-items: center;
  gap: 0.875rem;
  margin-bottom: 1.5rem;
}

.formulario-retiro-icono-envoltorio {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 48px;
  height: 48px;
  border-radius: 0.75rem;
  background: rgb(242 106 33 / 8%);
  flex-shrink: 0;
}

.formulario-retiro-titulo {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-carbon);
  margin: 0 0 0.125rem;
  line-height: 1.3;
}

.formulario-retiro-subtitulo {
  font-size: 0.8125rem;
  color: var(--color-texto-secundario);
  margin: 0;
}

/* ─── Error general ─── */
.formulario-retiro-error-general {
  display: flex;
  align-items: flex-start;
  gap: 0.625rem;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  background: rgb(198 40 40 / 6%);
  border: 1px solid rgb(198 40 40 / 15%);
  color: var(--color-peligro);
  font-size: 0.8125rem;
  font-weight: 500;
  line-height: 1.4;
  margin-bottom: 1.25rem;
  animation: retiro-aparecer 0.25s ease-out;
}

.formulario-retiro-error-icono {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: var(--color-peligro);
  color: white;
  font-size: 0.6875rem;
  font-weight: 700;
}

/* ─── Éxito ─── */
.formulario-retiro-exito {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  background: rgb(46 125 79 / 8%);
  border: 1px solid rgb(46 125 79 / 15%);
  color: var(--color-exito);
  font-size: 0.8125rem;
  font-weight: 600;
  margin-bottom: 1.25rem;
  animation: retiro-aparecer 0.25s ease-out;
}

/* ─── Formulario ─── */
.formulario-retiro-form {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.formulario-retiro-campo {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.formulario-retiro-etiqueta {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-carbon);
}

/* ─── Inputs ─── */
.formulario-retiro-input-envoltorio {
  position: relative;
  display: flex;
  align-items: center;
}

.formulario-retiro-input-icono {
  position: absolute;
  left: 0.875rem;
  color: var(--color-texto-secundario);
  pointer-events: none;
  z-index: 1;
  transition: color 0.2s ease;
}

.formulario-retiro-input-envoltorio:focus-within
  .formulario-retiro-input-icono {
  color: var(--color-naranja-primario);
}

.formulario-retiro-input {
  width: 100%;
  min-height: 44px;
  padding: 0.625rem 0.875rem;
  font-size: 0.9375rem;
  border: 1.5px solid var(--color-borde);
  border-radius: 0.75rem;
  background: var(--color-fondo-base);
  color: var(--color-negro-trazo);
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.formulario-retiro-input:focus {
  border-color: var(--color-naranja-primario);
  box-shadow: 0 0 0 3px rgb(242 106 33 / 12%);
  outline: none;
}

.formulario-retiro-input::placeholder {
  color: var(--color-texto-secundario);
  opacity: 0.6;
}

.formulario-retiro-input-con-icono {
  padding-left: 2.75rem;
}

.formulario-retiro-input-error {
  border-color: var(--color-peligro);
}

.formulario-retiro-input-error:focus {
  box-shadow: 0 0 0 3px rgb(198 40 40 / 12%);
}

/* ─── Select ─── */
.formulario-retiro-select-trigger {
  min-height: 44px;
  border: 1.5px solid var(--color-borde);
  border-radius: 0.75rem;
  font-size: 0.9375rem;
  transition:
    border-color 0.2s ease,
    box-shadow 0.2s ease;
}

.formulario-retiro-select-trigger:focus {
  border-color: var(--color-naranja-primario);
  box-shadow: 0 0 0 3px rgb(242 106 33 / 12%);
}

.formulario-retiro-select-icono {
  color: var(--color-texto-secundario);
  flex-shrink: 0;
}

/* ─── Motivo personalizado ─── */
.formulario-retiro-motivo-custom {
  margin-top: 0.5rem;
  animation: retiro-aparecer 0.2s ease-out;
}

/* ─── Error de campo ─── */
.formulario-retiro-error-campo {
  font-size: 0.75rem;
  color: var(--color-peligro);
  margin: 0.125rem 0 0;
  font-weight: 500;
  animation: retiro-aparecer 0.2s ease-out;
}

/* ─── Resumen previo ─── */
.formulario-retiro-resumen {
  padding: 1rem;
  border-radius: 0.75rem;
  background: var(--color-fondo-secundario);
  border: 1px solid var(--color-borde);
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  animation: retiro-aparecer 0.25s ease-out;
}

.formulario-retiro-resumen-fila {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 0.5rem;
}

.formulario-retiro-resumen-label {
  font-size: 0.8125rem;
  color: var(--color-texto-secundario);
  font-weight: 500;
}

.formulario-retiro-resumen-valor {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-carbon);
  text-align: right;
}

.formulario-retiro-resumen-monto {
  font-size: 1.125rem;
  font-weight: 700;
  color: var(--color-naranja-oscuro);
}

/* ─── Botón de registro ─── */
.formulario-retiro-boton {
  width: 100%;
  min-height: 48px;
  gap: 0.5rem;
  margin-top: 0.25rem;
  font-size: 0.9375rem;
  font-weight: 600;
  border-radius: 0.75rem;
  background: var(--color-naranja-primario);
  color: white;
  border: none;
  cursor: pointer;
  transition:
    background 0.2s ease,
    transform 0.1s ease,
    box-shadow 0.2s ease;
}

.formulario-retiro-boton:hover:not(:disabled) {
  background: var(--color-naranja-claro);
  box-shadow: 0 4px 16px rgb(242 106 33 / 25%);
}

.formulario-retiro-boton:active:not(:disabled) {
  transform: translateY(1px);
}

.formulario-retiro-boton:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

/* ─── Spinner ─── */
.formulario-retiro-spinner {
  animation: retiro-girar 1s linear infinite;
}

@keyframes retiro-girar {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* ─── Responsive ─── */
@media (min-width: 480px) {
  .formulario-retiro-contenedor {
    padding: 2rem;
  }

  .formulario-retiro-titulo {
    font-size: 1.25rem;
  }
}
</style>
