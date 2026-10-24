/**
 * Cálculos de nivel de stock de un insumo contra su mínimo, para el panel de compras.
 * Opera en milésimas (precisión de la BD) para no arrastrar errores de punto flotante.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-004 / CC-58
 */

/**
 * Convierte una cantidad de la API a milésimas enteras.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-004 / CC-58
 *
 * @param   {string|number} cantidad Cantidad con hasta 3 decimales.
 * @returns {number} Cantidad en milésimas.
 */
function aMilesimas(cantidad) {
  return Math.round(Number(cantidad) * 1000);
}

/**
 * Calcula cuánto falta para llegar al stock mínimo y qué porcentaje del mínimo hay.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-19
 * @módulo Inventario – RF-INV-004 / CC-58
 *
 * @param   {{ stock_actual: string|number, stock_minimo: string|number }} insumo Insumo de la API.
 * @returns {{ faltante: number, porcentaje: number }} Faltante (≥ 0) y porcentaje (0–100).
 */
export function calcularNivelStock(insumo) {
  const actual = aMilesimas(insumo.stock_actual);
  const minimo = aMilesimas(insumo.stock_minimo);

  const faltante = Math.max(minimo - actual, 0) / 1000;
  const porcentaje = minimo > 0 ? Math.min(Math.round((actual / minimo) * 100), 100) : 0;

  return { faltante, porcentaje };
}
