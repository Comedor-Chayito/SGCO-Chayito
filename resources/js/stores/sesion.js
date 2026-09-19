import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import api from '@/services/api.js';

/**
 * Store de sesión de usuario — autenticación y rol activo.
 * Gestiona el token de sesión que habilita/oculta vistas y botones.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-18
 * @módulo Admin – RF-ADM-008
 */
export const useSesionStore = defineStore('sesion', () => {
  const usuario = ref(null);
  const token = ref(localStorage.getItem('sgco_token') ?? null);

  const autenticado = computed(() => !!token.value);
  const rol = computed(() => usuario.value?.rol ?? null);

  /**
   * Inicia sesión con las credenciales del usuario.
   *
   * @autor  Equipo SGCO-Chayito
   * @fecha  2026-09-18
   * @módulo Admin – RF-ADM-008
   *
   * @param   {string} correo     Correo electrónico del usuario.
   * @param   {string} contrasena Contraseña en texto plano (se hashea en backend).
   * @returns {Promise<boolean>} true si el inicio de sesión fue exitoso.
   */
  async function iniciarSesion(correo, contrasena) {
    const respuesta = await api.post('/auth/login', { correo, contrasena });

    if (respuesta.data.status === 'ok') {
      token.value = respuesta.data.data.token;
      usuario.value = respuesta.data.data.usuario;
      localStorage.setItem('sgco_token', token.value);
      return true;
    }

    return false;
  }

  /**
   * Cierra la sesión del usuario actual y limpia el estado local.
   *
   * @autor  Equipo SGCO-Chayito
   * @fecha  2026-09-18
   * @módulo Admin – RF-ADM-008
   *
   * @returns {Promise<void>}
   */
  async function cerrarSesion() {
    await api.post('/auth/logout').catch(() => {});
    token.value = null;
    usuario.value = null;
    localStorage.removeItem('sgco_token');
  }

  return { usuario, token, autenticado, rol, iniciarSesion, cerrarSesion };
});
