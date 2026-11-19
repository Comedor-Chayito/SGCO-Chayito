const formatoCantidad = new Intl.NumberFormat('es-SV', { maximumFractionDigits: 6 });

/**
 * Presenta cantidades de inventario conservando hasta seis decimales.
 * @autor Equipo de desarrollo
 * @fecha 2026-11-17
 * @módulo Inventario RF-INV-003 / CC-113
 * @param {string|number} cantidad Cantidad recibida de la API.
 * @returns {string} Cantidad sin ceros decimales innecesarios.
 */
export function formatearCantidad(cantidad) {
  return formatoCantidad.format(Number(cantidad));
}
