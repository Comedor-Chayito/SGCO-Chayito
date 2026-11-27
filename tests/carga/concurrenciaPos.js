// CP-Q4-02 — Carga: 5 usuarios concurrentes sin degradación (respuestas ≤ 2.0 s y sin errores).
// CC-160 · RNF de CLAUDE.md §11. Simula un turno: 3 meseros, 1 cocinero y 1 cajero a la vez.
import http from 'k6/http';
import { check, sleep } from 'k6';
import {
  BASE_URL,
  LIMITE_RESPUESTA_MS,
  iniciarSesion,
  obtenerPlatilloDisponible,
  parametros,
  registrarComanda,
} from './sesion.js';

const DURACION = __ENV.DURACION || '2m';
const UMBRAL = [`p(95)<${LIMITE_RESPUESTA_MS}`];

export const options = {
  scenarios: {
    meseros: { executor: 'constant-vus', vus: 3, duration: DURACION, exec: 'flujoMesero' },
    cocina: { executor: 'constant-vus', vus: 1, duration: DURACION, exec: 'flujoCocina' },
    caja: { executor: 'constant-vus', vus: 1, duration: DURACION, exec: 'flujoCaja' },
  },
  thresholds: {
    http_req_duration: UMBRAL,
    'http_req_duration{endpoint:platillos}': UMBRAL,
    'http_req_duration{endpoint:mesas}': UMBRAL,
    'http_req_duration{endpoint:registrar-comanda}': UMBRAL,
    'http_req_duration{endpoint:cola-cocina}': UMBRAL,
    'http_req_duration{endpoint:comandas-caja}': UMBRAL,
    http_req_failed: ['rate<0.01'],
    checks: ['rate>0.99'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'max'],
};

/**
 * Inicia sesión una vez por rol y elige un platillo disponible; los usuarios virtuales
 * del mismo rol comparten el token, como varios dispositivos con la misma cuenta.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-160 (RNF 5 usuarios concurrentes)
 *
 * @returns {object} Tokens por rol y platillo para registrar comandas.
 */
export function setup() {
  const tokenMesero = iniciarSesion('mesero');

  return {
    tokenMesero,
    tokenCocina: iniciarSesion('cocinero'),
    tokenCaja: iniciarSesion('cajero'),
    platilloId: obtenerPlatilloDisponible(tokenMesero),
  };
}

/**
 * Espera entre 2 y 4 segundos, el tiempo de un usuario entre una acción y la siguiente.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-160 (RNF 5 usuarios concurrentes)
 *
 * @returns {void}
 */
function pausaDeUsuario() {
  sleep(2 + Math.random() * 2);
}

/**
 * Mesero: consulta menú y mesas, y registra una comanda.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-160 (RNF 5 usuarios concurrentes)
 *
 * @param   {object} datos Datos devueltos por setup().
 * @returns {void}
 */
export function flujoMesero(datos) {
  const platillos = http.get(
    `${BASE_URL}/api/platillos`,
    parametros(datos.tokenMesero, 'platillos'),
  );
  check(platillos, { 'platillos responde 200': (r) => r.status === 200 });

  const mesas = http.get(`${BASE_URL}/api/mesas`, parametros(datos.tokenMesero, 'mesas'));
  check(mesas, { 'mesas responde 200': (r) => r.status === 200 });

  const comanda = registrarComanda(datos.tokenMesero, datos.platilloId);
  check(comanda, { 'comanda con status ok': (r) => r.status < 300 && r.json('status') === 'ok' });

  pausaDeUsuario();
}

/**
 * Cocinero: refresca la cola de comandas pendientes.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-160 (RNF 5 usuarios concurrentes)
 *
 * @param   {object} datos Datos devueltos por setup().
 * @returns {void}
 */
export function flujoCocina(datos) {
  const cola = http.get(
    `${BASE_URL}/api/comandas/cocina`,
    parametros(datos.tokenCocina, 'cola-cocina'),
  );
  check(cola, { 'cola de cocina responde 200': (r) => r.status === 200 });

  pausaDeUsuario();
}

/**
 * Cajero: consulta el listado de comandas para cobrar.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-160 (RNF 5 usuarios concurrentes)
 *
 * @param   {object} datos Datos devueltos por setup().
 * @returns {void}
 */
export function flujoCaja(datos) {
  const comandas = http.get(
    `${BASE_URL}/api/comandas`,
    parametros(datos.tokenCaja, 'comandas-caja'),
  );
  check(comandas, { 'comandas de caja responde 200': (r) => r.status === 200 });

  pausaDeUsuario();
}
