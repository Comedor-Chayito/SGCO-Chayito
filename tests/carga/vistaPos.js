// CP-Q4-01 (parte visual) — La vista del POS queda lista para tomar pedidos en ≤ 2.0 s.
// CC-159 · RNF de CLAUDE.md §11. Usa el navegador Chromium integrado en k6.
import { browser } from 'k6/browser';
import { check } from 'k6';
import { Trend } from 'k6/metrics';
import { BASE_URL, LIMITE_RESPUESTA_MS, obtenerClave } from './sesion.js';

const tiempoVistaPos = new Trend('tiempo_vista_pos', true);

export const options = {
  scenarios: {
    vistaPos: {
      executor: 'per-vu-iterations',
      vus: 1,
      iterations: Number(__ENV.ITERACIONES || 10),
      maxDuration: '10m',
      options: { browser: { type: 'chromium' } },
    },
  },
  thresholds: {
    tiempo_vista_pos: [`p(95)<${LIMITE_RESPUESTA_MS}`],
    checks: ['rate==1'],
  },
  summaryTrendStats: ['avg', 'min', 'med', 'p(90)', 'p(95)', 'max'],
};

/**
 * Inicia sesión como mesero en un navegador limpio y mide cuánto tarda la vista del POS
 * en mostrar el selector de mesas, con todas sus peticiones terminadas.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-159 (RNF rendimiento del POS)
 *
 * @returns {Promise<void>}
 */
export default async function () {
  const correo = `mesero@${__ENV.DOMINIO_USUARIOS || 'sgco-chayito.local'}`;
  const contexto = await browser.newContext();
  const pagina = await contexto.newPage();

  try {
    await pagina.goto(`${BASE_URL}/login`, { waitUntil: 'networkidle' });
    await pagina.locator('#campo-correo').fill(correo);
    await pagina.locator('#campo-contrasena').fill(obtenerClave());
    await Promise.all([
      pagina.waitForNavigation({ waitUntil: 'networkidle' }),
      pagina.locator('#boton-login').click(),
    ]);

    const inicio = Date.now();
    await pagina.goto(`${BASE_URL}/pos`, { waitUntil: 'networkidle' });
    await pagina.locator('#vista-pos').waitFor({ state: 'visible' });
    await pagina.getByRole('heading', { name: 'Selecciona la Mesa' }).waitFor({ state: 'visible' });
    const duracion = Date.now() - inicio;

    tiempoVistaPos.add(duracion);
    check(duracion, { 'vista del POS lista en ≤ 2.0 s': (ms) => ms <= LIMITE_RESPUESTA_MS });
  } finally {
    await pagina.close();
    await contexto.close();
  }
}
