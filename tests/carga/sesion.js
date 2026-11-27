// Utilidades compartidas de las pruebas no funcionales (Cuadrante 4) con k6.
// Se ejecutan con Docker; ver PLAN_PRUEBAS.md §8.
import http from 'k6/http';
import { check, fail } from 'k6';

export const BASE_URL = (__ENV.BASE_URL || 'http://localhost:8084').replace(/\/$/, '');
export const LIMITE_RESPUESTA_MS = 2000;
export const MARCA_PRUEBA = 'PRUEBA-CARGA';

const DOMINIO_USUARIOS = __ENV.DOMINIO_USUARIOS || 'sgco-chayito.local';

/**
 * Devuelve la contraseña de los usuarios de prueba, que llega por variable de entorno
 * para no dejarla escrita en el repositorio.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-159 (RNF rendimiento del POS)
 *
 * @returns {string} Contraseña definida en CLAVE_PRUEBA.
 */
export function obtenerClave() {
  if (!__ENV.CLAVE_PRUEBA) {
    fail('Falta la variable CLAVE_PRUEBA con la contraseña de los usuarios de prueba.');
  }

  return __ENV.CLAVE_PRUEBA;
}

/**
 * Inicia sesión con el usuario sembrado de un rol y devuelve su token de Sanctum.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-159 (RNF rendimiento del POS)
 *
 * @param   {string} rol Rol del usuario sembrado (mesero, cocinero, cajero…).
 * @returns {string} Token de sesión para el encabezado Authorization.
 */
export function iniciarSesion(rol) {
  const respuesta = http.post(
    `${BASE_URL}/api/auth/login`,
    JSON.stringify({ email: `${rol}@${DOMINIO_USUARIOS}`, password: obtenerClave() }),
    { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } },
  );

  const exito = check(respuesta, {
    'login con status ok': (r) => r.status < 300 && r.json('status') === 'ok',
  });
  if (!exito) {
    fail(`No se pudo iniciar sesión como ${rol}: HTTP ${respuesta.status}.`);
  }

  return respuesta.json('data.token');
}

/**
 * Arma los parámetros de una petición autenticada, etiquetada con el endpoint medido
 * para que k6 calcule umbrales por endpoint.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-159 (RNF rendimiento del POS)
 *
 * @param   {string} token    Token de Sanctum del usuario.
 * @param   {string} endpoint Nombre corto del endpoint (platillos, mesas, comandas…).
 * @returns {object} Parámetros para http.get / http.post.
 */
export function parametros(token, endpoint) {
  return {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
    tags: { endpoint },
  };
}

/**
 * Obtiene el primer platillo disponible del menú del día, para registrar comandas válidas.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-159 (RNF rendimiento del POS)
 *
 * @param   {string} token Token de un rol con acceso a /api/platillos.
 * @returns {number} Identificador del platillo.
 */
export function obtenerPlatilloDisponible(token) {
  const respuesta = http.get(`${BASE_URL}/api/platillos`, parametros(token, 'preparacion'));
  const platillo = (respuesta.json('data') || []).find((p) => p.disponible);

  if (!platillo) {
    fail('No hay platillos disponibles en el menú del día: no se pueden registrar comandas.');
  }

  return platillo.id;
}

/**
 * Registra una comanda para llevar marcada como prueba de carga.
 *
 * @autor  manuelmv15
 * @fecha  2026-11-25
 * @módulo Pruebas no funcionales – CC-159 (RNF rendimiento del POS)
 *
 * @param   {string} token      Token del mesero.
 * @param   {number} platilloId Platillo disponible que se pide.
 * @returns {object} Respuesta HTTP de k6.
 */
export function registrarComanda(token, platilloId) {
  const cuerpo = {
    canal: 'para_llevar',
    observaciones: MARCA_PRUEBA,
    items: [{ platillo_id: platilloId, cantidad: 1 }],
  };

  return http.post(
    `${BASE_URL}/api/comandas`,
    JSON.stringify(cuerpo),
    parametros(token, 'registrar-comanda'),
  );
}
