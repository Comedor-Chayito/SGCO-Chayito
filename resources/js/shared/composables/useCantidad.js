/**
 * Formateo de cantidades físicas (stock, porciones) con la precisión de la BD: 3 decimales.
 * No aplica a dinero; los montos siempre van con 2 decimales.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-003 (CC-46)
 *
 * @returns {{ formatearCantidad: (cantidad: string|number) => string }} API del composable.
 */
export function useCantidad() {
  /**
   * Formatea una cantidad con hasta 3 decimales, sin ceros sobrantes (10.500 → 10.5).
   *
   * @autor  manuelmv15
   * @fecha  2026-10-19
   * @módulo Inventario – RF-INV-003 (CC-46)
   *
   * @param   {string|number} cantidad Cantidad devuelta por la API.
   * @returns {string} Cantidad formateada, o '—' si no es un número.
   */
  function formatearCantidad(cantidad) {
    const numero = Number(cantidad);

    if (cantidad === null || cantidad === '' || Number.isNaN(numero)) return '—';

    return String(Number(numero.toFixed(3)));
  }

  return { formatearCantidad };
}
