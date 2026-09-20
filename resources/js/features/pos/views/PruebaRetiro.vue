<script setup>
/**
 * Vista temporal de pruebas para el componente FormularioRetiro.
 * ELIMINAR al integrar en la vista Caja.vue definitiva.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002 (CC-37) — TEMPORAL
 */
import { ref } from 'vue'
import FormularioRetiro from
  '@/features/pos/components/FormularioRetiro.vue'
import { Toaster, toast } from 'vue-sonner'

const retiros = ref([])

/**
 * Maneja el evento de retiro registrado exitosamente.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-09-19
 * @módulo POS – RF-POS-002 (CC-37) — TEMPORAL
 *
 * @param   {Object} resultado Datos del retiro registrado.
 * @returns {void}
 */
function onRetiroRegistrado(resultado) {
  retiros.value.unshift({
    id: Date.now(),
    ...resultado,
    created_at: new Date().toLocaleTimeString('es-SV'),
  })
  toast.success('Retiro registrado (mock)', {
    description: `$${resultado.monto} — ${resultado.detalle}`,
    duration: 3000,
  })
}
</script>

<template>
  <div class="prueba-contenedor">
    <Toaster richColors position="top-center" />

    <header class="prueba-header">
      <h1 class="prueba-titulo">
        🧪 Prueba: FormularioRetiro
      </h1>
      <p class="prueba-nota">
        Vista temporal — CC-37 · US-POS-03
      </p>
    </header>

    <div class="prueba-grid">
      <!-- Formulario -->
      <div class="prueba-col">
        <FormularioRetiro
          @retiro-registrado="onRetiroRegistrado"
        />
      </div>

      <!-- Log de retiros registrados -->
      <div class="prueba-col">
        <div class="prueba-log">
          <h2 class="prueba-log-titulo">
            Retiros registrados ({{ retiros.length }})
          </h2>

          <p
            v-if="retiros.length === 0"
            class="prueba-log-vacio"
          >
            Ningún retiro registrado aún.
            Usa el formulario para probar.
          </p>

          <ul v-else class="prueba-log-lista">
            <li
              v-for="r in retiros"
              :key="r.id"
              class="prueba-log-item"
            >
              <div class="prueba-log-fila">
                <span
                  class="prueba-log-tipo"
                  :class="
                    r.tipo === 'egreso'
                      ? 'prueba-log-egreso'
                      : 'prueba-log-ingreso'
                  "
                >
                  {{ r.tipo === 'egreso' ? '↓' : '↑' }}
                  {{ r.tipo }}
                </span>
                <span class="prueba-log-monto">
                  ${{ parseFloat(r.monto).toFixed(2) }}
                </span>
              </div>
              <div class="prueba-log-detalle">
                {{ r.detalle }}
              </div>
              <div class="prueba-log-hora">
                {{ r.created_at }}
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.prueba-contenedor {
  min-height: 100dvh;
  padding: 1.5rem;
  background: var(--color-fondo-secundario);
}

.prueba-header {
  text-align: center;
  margin-bottom: 2rem;
}

.prueba-titulo {
  font-size: 1.5rem;
  font-weight: 700;
  color: var(--color-carbon);
  margin: 0 0 0.25rem;
}

.prueba-nota {
  font-size: 0.8125rem;
  color: var(--color-texto-secundario);
  margin: 0;
  background: rgb(242 106 33 / 10%);
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 2rem;
  font-weight: 600;
  color: var(--color-naranja-oscuro);
}

.prueba-grid {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 1.5rem;
  max-width: 960px;
  margin: 0 auto;
}

@media (min-width: 768px) {
  .prueba-grid {
    flex-direction: row;
    align-items: flex-start;
  }
}

.prueba-col {
  flex: 1;
  width: 100%;
  display: flex;
  justify-content: center;
}

.prueba-log {
  background: var(--color-fondo-base);
  border: 1px solid var(--color-borde);
  border-radius: 1.25rem;
  padding: 1.5rem;
  width: 100%;
  max-width: 480px;
}

.prueba-log-titulo {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-carbon);
  margin: 0 0 1rem;
}

.prueba-log-vacio {
  font-size: 0.875rem;
  color: var(--color-texto-secundario);
  text-align: center;
  padding: 2rem 0;
}

.prueba-log-lista {
  list-style: none;
  padding: 0;
  margin: 0;
  display: flex;
  flex-direction: column;
  gap: 0.75rem;
}

.prueba-log-item {
  padding: 0.75rem;
  border-radius: 0.75rem;
  background: var(--color-fondo-secundario);
  border: 1px solid var(--color-borde);
}

.prueba-log-fila {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.25rem;
}

.prueba-log-tipo {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 0.125rem 0.5rem;
  border-radius: 0.375rem;
}

.prueba-log-egreso {
  background: rgb(198 40 40 / 10%);
  color: var(--color-peligro);
}

.prueba-log-ingreso {
  background: rgb(46 125 79 / 10%);
  color: var(--color-exito);
}

.prueba-log-monto {
  font-size: 1rem;
  font-weight: 700;
  color: var(--color-carbon);
}

.prueba-log-detalle {
  font-size: 0.8125rem;
  color: var(--color-texto-secundario);
}

.prueba-log-hora {
  font-size: 0.6875rem;
  color: var(--color-texto-secundario);
  margin-top: 0.25rem;
  opacity: 0.7;
}
</style>
