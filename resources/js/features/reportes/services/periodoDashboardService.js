/**
 * Cálculo de rangos de fechas para filtrar el dashboard gerencial.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – RF-REP-007 / CC-72
 */

export const PERIODO_PREDETERMINADO = 'mes_actual';

export const PERIODOS_PREDEFINIDOS = [
  { clave: 'mes_actual', etiqueta: 'Este mes' },
  { clave: 'mes_anterior', etiqueta: 'Mes anterior' },
  { clave: 'ultimos_tres_meses', etiqueta: 'Últimos 3 meses' },
  { clave: 'anio_actual', etiqueta: 'Este año' },
];

/**
 * Convierte una fecha local a formato ISO sin desplazarla por zona horaria.
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-23
 * @módulo Reportes – RF-REP-007 / CC-75
 *
 * @param   {Date} fecha Fecha que se desea representar.
 * @returns {string} Fecha en formato YYYY-MM-DD.
 */
export function fechaLocalIso(fecha) {
  const anio = fecha.getFullYear();
  const mes = String(fecha.getMonth() + 1).padStart(2, '0');
  const dia = String(fecha.getDate()).padStart(2, '0');

  return `${anio}-${mes}-${dia}`;
}

/**
 * Calcula el rango de fechas de un periodo predefinido. Ningún rango pasa de hoy,
 * porque el API rechaza fechas futuras.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – RF-REP-007 / CC-72
 *
 * @param   {string} clave Clave de PERIODOS_PREDEFINIDOS.
 * @param   {Date}   hoy   Fecha de referencia; por defecto, la actual.
 * @returns {{ fecha_desde: string, fecha_hasta: string }} Rango en formato ISO.
 */
export function calcularPeriodo(clave, hoy = new Date()) {
  const anio = hoy.getFullYear();
  const mes = hoy.getMonth();
  const rangos = {
    mes_actual: [new Date(anio, mes, 1), hoy],
    mes_anterior: [new Date(anio, mes - 1, 1), new Date(anio, mes, 0)],
    ultimos_tres_meses: [new Date(anio, mes - 2, 1), hoy],
    anio_actual: [new Date(anio, 0, 1), hoy],
  };
  const [desde, hasta] = rangos[clave] ?? rangos[PERIODO_PREDETERMINADO];

  return { fecha_desde: fechaLocalIso(desde), fecha_hasta: fechaLocalIso(hasta) };
}

/**
 * Identifica qué periodo predefinido coincide con el rango elegido, para resaltarlo.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – RF-REP-007 / CC-72
 *
 * @param   {{ fecha_desde: string, fecha_hasta: string }} filtros Rango actual del formulario.
 * @param   {Date} hoy Fecha de referencia; por defecto, la actual.
 * @returns {string|null} Clave del periodo coincidente, o null si el rango es personalizado.
 */
export function identificarPeriodo(filtros, hoy = new Date()) {
  const coincidente = PERIODOS_PREDEFINIDOS.find(({ clave }) => {
    const rango = calcularPeriodo(clave, hoy);
    return rango.fecha_desde === filtros.fecha_desde && rango.fecha_hasta === filtros.fecha_hasta;
  });

  return coincidente?.clave ?? null;
}
