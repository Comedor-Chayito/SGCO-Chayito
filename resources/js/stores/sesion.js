import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api.js';

/**
 * Store de sesión de usuario — autenticación y rol activo.
 * Gestiona el token de sesión que habilita/oculta vistas y botones (US-ADM-01 / US-ADM-02).
 *
 * @autor  Jeferson De La Cruz / Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo Admin – RF-ADM-008
 */
export const useSesionStore = defineStore('sesion', () => {
  const token = ref(localStorage.getItem('sgco_token') ?? null);

  // Recuperar usuario persistido para no perder rol ni estado al recargar (F5)
  const usuarioGuardado = localStorage.getItem('sgco_usuario');
  let usuarioInicial = null;
  try {
    usuarioInicial = usuarioGuardado ? JSON.parse(usuarioGuardado) : null;
  } catch {
    usuarioInicial = null;
  }

  const usuario = ref(usuarioInicial);

  const autenticado = computed(() => !!token.value);
  const rol = computed(() => {
    return usuario.value?.rol || (usuario.value?.roles?.[0]?.name ?? null);
  });

  /**
   * Inicia sesión con las credenciales del usuario y persiste token y datos del usuario.
   *
   * @param   {string} correo     Correo electrónico del usuario.
   * @param   {string} contrasena Contraseña en texto plano.
   * @returns {Promise<boolean>} true si el inicio de sesión fue exitoso.
   */
  async function iniciarSesion(correo, contrasena) {
    const respuesta = await api.post('/auth/login', { correo, contrasena });

    if (respuesta.data.status === 'ok') {
      token.value = respuesta.data.data.token;
      usuario.value = respuesta.data.data.usuario;
      localStorage.setItem('sgco_token', token.value);
      localStorage.setItem('sgco_usuario', JSON.stringify(usuario.value));
      return true;
    }

    return false;
  }

  /**
   * Refresca los datos del usuario activo desde el servidor si hay token.
   *
   * @returns {Promise<object|null>}
   */
  async function cargarUsuario() {
    if (!token.value) return null;

    try {
      const respuesta = await api.get('/auth/usuario');
      if (respuesta.data.status === 'ok') {
        usuario.value = respuesta.data.data;
        localStorage.setItem('sgco_usuario', JSON.stringify(usuario.value));
        return usuario.value;
      }
    } catch {
      // Si el token expiró o fue revocado, cerrar sesión
      await cerrarSesion();
    }

    return null;
  }

  /**
   * Cierra la sesión del usuario actual y limpia el estado local y tokens.
   *
   * @returns {Promise<void>}
   */
  async function cerrarSesion() {
    try {
      if (token.value) {
        await api.post('/auth/logout').catch(() => {});
      }
    } finally {
      token.value = null;
      usuario.value = null;
      localStorage.removeItem('sgco_token');
      localStorage.removeItem('sgco_usuario');
    }
  }

  return { usuario, token, autenticado, rol, iniciarSesion, cerrarSesion, cargarUsuario };
});
