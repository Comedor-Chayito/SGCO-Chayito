<script setup>
/**
 * Pantalla de inicio de sesión por correo corporativo.
 * Permite al usuario autenticarse con correo y contraseña cifrada (Bcrypt).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo Admin – Sec. 3.5.1 (CC-89)
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { ChefHat, Mail, Eye, EyeOff, LogIn, Loader2 } from '@lucide/vue'
import { useSesionStore } from '@/stores/sesion.js'
import { obtenerRutaInicioPorRol } from '@/router/index.js'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const router = useRouter()
const sesion = useSesionStore()

const correo = ref('')
const contrasena = ref('')
const mostrarContrasena = ref(false)
const cargando = ref(false)
const error = ref('')
const erroresValidacion = ref({ correo: '', contrasena: '' })

/**
 * Valida los campos del formulario antes de enviar.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo Admin – Sec. 3.5.1 (CC-89)
 *
 * @returns {boolean} true si la validación pasa.
 */
function validarCampos() {
  erroresValidacion.value = { correo: '', contrasena: '' }
  let valido = true

  if (!correo.value.trim()) {
    erroresValidacion.value.correo = 'El correo es obligatorio.'
    valido = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(correo.value)) {
    erroresValidacion.value.correo =
      'Ingresa un correo electrónico válido.'
    valido = false
  }

  if (!contrasena.value) {
    erroresValidacion.value.contrasena =
      'La contraseña es obligatoria.'
    valido = false
  } else if (contrasena.value.length < 8) {
    erroresValidacion.value.contrasena =
      'La contraseña debe tener al menos 8 caracteres.'
    valido = false
  }

  return valido
}

/**
 * Procesa el envío del formulario de login.
 * Delega la autenticación al store de sesión.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo Admin – Sec. 3.5.1 (CC-89)
 *
 * @returns {Promise<void>}
 */
async function manejarLogin() {
  error.value = ''

  if (!validarCampos()) return

  cargando.value = true

  try {
    const exito = await sesion.iniciarSesion(
      correo.value,
      contrasena.value
    )

    if (exito) {
      router.push(obtenerRutaInicioPorRol(sesion.rol))
    } else {
      error.value =
        'Correo o contraseña incorrectos. Verifica tus datos.'
    }
  } catch (err) {
    if (err.response?.status === 422) {
      const errores = err.response.data?.errors ?? {}

      erroresValidacion.value.correo =
        errores.correo?.[0] ?? ''
      erroresValidacion.value.contrasena =
        errores.contrasena?.[0] ?? ''
    } else if (err.response?.status === 401) {
      error.value =
        'Correo o contraseña incorrectos. Verifica tus datos.'
    } else {
      error.value =
        'Error de conexión. Intenta de nuevo más tarde.'
    }
  } finally {
    cargando.value = false
  }
}

const formularioValido = computed(() =>
  correo.value.trim() && contrasena.value
)
</script>

<template>
  <main class="login-contenedor" id="vista-login">
    <div class="login-tarjeta">
      <!-- Encabezado con logo -->
      <div class="login-encabezado">
        <div class="login-logo-envoltorio">
          <ChefHat :size="40" stroke-width="1.8" />
        </div>
        <h1 class="login-titulo">SGCO-Chayito</h1>
        <p class="login-subtitulo">
          Inicia sesión para continuar
        </p>
      </div>

      <!-- Error general -->
      <div
        v-if="error"
        class="login-error-general"
        id="error-login"
        role="alert"
      >
        <span class="login-error-icono">!</span>
        {{ error }}
      </div>

      <!-- Formulario -->
      <form
        class="login-formulario"
        id="formulario-login"
        @submit.prevent="manejarLogin"
        novalidate
      >
        <!-- Campo correo -->
        <div class="login-campo">
          <Label
            for="campo-correo"
            class="login-etiqueta"
          >
            Correo corporativo
          </Label>
          <div class="login-input-envoltorio">
            <Mail
              :size="18"
              class="login-input-icono"
              aria-hidden="true"
            />
            <Input
              id="campo-correo"
              v-model="correo"
              type="email"
              placeholder="usuario@comedorchayito.edu.sv"
              autocomplete="email"
              :disabled="cargando"
              :class="[
                'login-input login-input-con-icono',
                { 'login-input-error': erroresValidacion.correo }
              ]"
              @keydown.enter="manejarLogin"
            />
          </div>
          <p
            v-if="erroresValidacion.correo"
            class="login-error-campo"
            role="alert"
          >
            {{ erroresValidacion.correo }}
          </p>
        </div>

        <!-- Campo contraseña -->
        <div class="login-campo">
          <Label
            for="campo-contrasena"
            class="login-etiqueta"
          >
            Contraseña
          </Label>
          <div class="login-input-envoltorio">
            <button
              type="button"
              class="login-toggle-contrasena"
              :aria-label="
                mostrarContrasena
                  ? 'Ocultar contraseña'
                  : 'Mostrar contraseña'
              "
              @click="mostrarContrasena = !mostrarContrasena"
              tabindex="-1"
            >
              <Eye
                v-if="!mostrarContrasena"
                :size="18"
              />
              <EyeOff v-else :size="18" />
            </button>
            <Input
              id="campo-contrasena"
              v-model="contrasena"
              :type="mostrarContrasena ? 'text' : 'password'"
              placeholder="••••••••"
              autocomplete="current-password"
              :disabled="cargando"
              :class="[
                'login-input login-input-con-toggle',
                {
                  'login-input-error':
                    erroresValidacion.contrasena
                }
              ]"
              @keydown.enter="manejarLogin"
            />
          </div>
          <p
            v-if="erroresValidacion.contrasena"
            class="login-error-campo"
            role="alert"
          >
            {{ erroresValidacion.contrasena }}
          </p>
        </div>

        <!-- Botón de login -->
        <Button
          type="submit"
          id="boton-login"
          class="login-boton"
          :disabled="cargando || !formularioValido"
        >
          <Loader2
            v-if="cargando"
            :size="20"
            class="login-spinner"
          />
          <LogIn v-else :size="20" />
          {{ cargando ? 'Ingresando…' : 'Iniciar sesión' }}
        </Button>
      </form>

      <!-- Pie -->
      <p class="login-pie">
        Comedor Chayito · UES-FMO
      </p>
    </div>
  </main>
</template>

<style scoped>
/* ─── Contenedor principal ─── */
.login-contenedor {
  min-height: 100dvh;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 1rem;
  background:
    radial-gradient(
      ellipse at 30% 20%,
      rgb(242 106 33 / 6%) 0%,
      transparent 60%
    ),
    radial-gradient(
      ellipse at 70% 80%,
      rgb(242 106 33 / 4%) 0%,
      transparent 50%
    ),
    var(--color-fondo-secundario);
}

/* ─── Tarjeta central ─── */
.login-tarjeta {
  background: var(--color-fondo-base);
  border: 1px solid var(--color-borde);
  border-radius: 1.5rem;
  padding: 2.5rem 2rem;
  width: 100%;
  max-width: 420px;
  box-shadow:
    0 4px 32px 0 rgb(242 106 33 / 8%),
    0 1px 3px 0 rgb(0 0 0 / 4%);
  animation: login-aparecer 0.4s ease-out;
}

@keyframes login-aparecer {
  from {
    opacity: 0;
    transform: translateY(12px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

/* ─── Encabezado ─── */
.login-encabezado {
  text-align: center;
  margin-bottom: 2rem;
}

.login-logo-envoltorio {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 64px;
  height: 64px;
  border-radius: 1rem;
  background: rgb(242 106 33 / 10%);
  color: var(--color-naranja-primario);
  margin-bottom: 1rem;
  transition: transform 0.2s ease;
}

.login-logo-envoltorio:hover {
  transform: scale(1.05);
}

.login-titulo {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-carbon);
  margin: 0 0 0.375rem;
  letter-spacing: -0.02em;
}

.login-subtitulo {
  font-size: 0.875rem;
  color: var(--color-texto-secundario);
  margin: 0;
}

/* ─── Error general ─── */
.login-error-general {
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
  margin-bottom: 1.5rem;
  animation: login-aparecer 0.25s ease-out;
}

.login-error-icono {
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

/* ─── Formulario ─── */
.login-formulario {
  display: flex;
  flex-direction: column;
  gap: 1.25rem;
}

.login-campo {
  display: flex;
  flex-direction: column;
  gap: 0.375rem;
}

.login-etiqueta {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--color-carbon);
}

/* ─── Input con icono ─── */
.login-input-envoltorio {
  position: relative;
  display: flex;
  align-items: center;
}

.login-input-icono {
  position: absolute;
  left: 0.875rem;
  color: var(--color-texto-secundario);
  pointer-events: none;
  z-index: 1;
  transition: color 0.2s ease;
}

.login-input-envoltorio:focus-within .login-input-icono {
  color: var(--color-naranja-primario);
}

.login-input {
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

.login-input:focus {
  border-color: var(--color-naranja-primario);
  box-shadow: 0 0 0 3px rgb(242 106 33 / 12%);
  outline: none;
}

.login-input::placeholder {
  color: var(--color-texto-secundario);
  opacity: 0.6;
}

.login-input-con-icono {
  padding-left: 2.75rem;
}

.login-input-con-toggle {
  padding-right: 2.75rem;
}

.login-input-error {
  border-color: var(--color-peligro);
}

.login-input-error:focus {
  box-shadow: 0 0 0 3px rgb(198 40 40 / 12%);
}

/* ─── Toggle contraseña ─── */
.login-toggle-contrasena {
  position: absolute;
  right: 0.625rem;
  z-index: 1;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 0.5rem;
  background: transparent;
  color: var(--color-texto-secundario);
  cursor: pointer;
  transition:
    color 0.2s ease,
    background 0.15s ease;
}

.login-toggle-contrasena:hover {
  color: var(--color-naranja-primario);
  background: rgb(242 106 33 / 8%);
}

/* ─── Error de campo ─── */
.login-error-campo {
  font-size: 0.75rem;
  color: var(--color-peligro);
  margin: 0.125rem 0 0;
  font-weight: 500;
  animation: login-aparecer 0.2s ease-out;
}

/* ─── Botón login ─── */
.login-boton {
  width: 100%;
  min-height: 44px;
  gap: 0.5rem;
  margin-top: 0.5rem;
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

.login-boton:hover:not(:disabled) {
  background: var(--color-naranja-claro);
  box-shadow: 0 4px 16px rgb(242 106 33 / 25%);
}

.login-boton:active:not(:disabled) {
  transform: translateY(1px);
}

.login-boton:disabled {
  opacity: 0.55;
  cursor: not-allowed;
}

/* ─── Spinner ─── */
.login-spinner {
  animation: login-girar 1s linear infinite;
}

@keyframes login-girar {
  from { transform: rotate(0deg); }
  to { transform: rotate(360deg); }
}

/* ─── Pie ─── */
.login-pie {
  text-align: center;
  font-size: 0.75rem;
  color: var(--color-texto-secundario);
  margin: 2rem 0 0;
  letter-spacing: 0.01em;
}

/* ─── Responsive ─── */
@media (min-width: 480px) {
  .login-tarjeta {
    padding: 3rem 2.5rem;
  }

  .login-titulo {
    font-size: 1.75rem;
  }
}
</style>
