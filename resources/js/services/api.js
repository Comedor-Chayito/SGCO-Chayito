import axios from 'axios';
import { useSesionStore } from '@/stores/sesion.js';

/**
 * Cliente HTTP centralizado para todas las llamadas a la API REST.
 * Toda llamada HTTP debe pasar por este módulo, nunca directamente desde componentes.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Core – Contrato de API (sección 5 CLAUDE.md)
 */
const api = axios.create({
  baseURL: '/api',
  headers: {
    'Content-Type': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

/**
 * Interceptor de solicitud: adjunta el token Bearer si existe en el store de sesión.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Core – RF-ADM-008
 */
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('sgco_token');

  if (token) {
    config.headers['Authorization'] = `Bearer ${token}`;
  }

  return config;
});

/**
 * Interceptor de respuesta: maneja errores 401 cerrando la sesión automáticamente.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Core – RF-ADM-008
 */
api.interceptors.response.use(
  (respuesta) => respuesta,
  (error) => {
    if (error.response?.status === 401) {
      const sesion = useSesionStore();
      sesion.cerrarSesion();
    }

    return Promise.reject(error);
  }
);

export default api;
