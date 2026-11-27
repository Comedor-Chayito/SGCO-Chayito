// CP-Q4-01 — Rendimiento del POS: cada respuesta del flujo de toma de pedidos en ≤ 2.0 s.
// CC-159 · RNF de CLAUDE.md §11. Un solo usuario recorre el flujo varias veces.
import http from 'k6/http';
import { check, group, sleep } from 'k6';
import {
  BASE_URL,
  LIMITE_RESPUESTA_MS,
  iniciarSesion,
  obtenerPlatilloDisponible,
  parametros,
  registrarComanda,
} from './sesion.js';

const UMBRAL = [`p(95)<${LIMITE_RESPUESTA_MS}`, `max<${LIMITE_RESPUESTA_MS * 2}`];

export const options = {
  scenarios: {
    flujoPos: {
      executor: 'per-vu-iterations',
      vus: 1,
      iterations: Number(__ENV.ITERACIONES || 30),
      maxDuration: '10m',
    },
  },
  thresholds: {
    'http_req_duration{endpoint:platillos}': UMBRAL,
    'http_req_duration{endpoint:mesas}': UMBRAL,
    'http_req_duration{endpoint:registrar-comanda}': UMBRAL,
    'http_req_duration{endpoint:cola-cocina}': UMBRAL,
    http_req_failed: ['rate==0'],
    checks: ['rate==1'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'max'],
};

/**
 * Inicia sesión como mesero y como cocinero, y elige un platillo disponible.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-159 (RNF rendimiento del POS)
 *
 * @returns {{tokenMesero: string, tokenCocina: string, platilloId: number}} Datos del flujo.
 */
export function setup() {
  const tokenMesero = iniciarSesion('mesero');
  const tokenCocina = iniciarSesion('cocinero');

  return { tokenMesero, tokenCocina, platilloId: obtenerPlatilloDisponible(tokenMesero) };
}

/**
 * Recorre una vez el flujo del POS: menú, mesas, registro de la comanda y cola de cocina.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-159 (RNF rendimiento del POS)
 *
 * @param   {object} datos Tokens y platillo devueltos por setup().
 * @returns {void}
 */
export default function (datos) {
  group('mesero toma el pedido', () => {
    const platillos = http.get(
      `${BASE_URL}/api/platillos`,
      parametros(datos.tokenMesero, 'platillos'),
    );
    check(platillos, { 'platillos responde 200': (r) => r.status === 200 });

    const mesas = http.get(`${BASE_URL}/api/mesas`, parametros(datos.tokenMesero, 'mesas'));
    check(mesas, { 'mesas responde 200': (r) => r.status === 200 });

    const comanda = registrarComanda(datos.tokenMesero, datos.platilloId);
    check(comanda, {
      'comanda responde 201 o 200': (r) => r.status === 201 || r.status === 200,
      'comanda con status ok': (r) => r.json('status') === 'ok',
    });
  });

  group('cocina ve la comanda', () => {
    const cola = http.get(
      `${BASE_URL}/api/comandas/cocina`,
      parametros(datos.tokenCocina, 'cola-cocina'),
    );
    check(cola, { 'cola de cocina responde 200': (r) => r.status === 200 });
  });

  sleep(1);
}
