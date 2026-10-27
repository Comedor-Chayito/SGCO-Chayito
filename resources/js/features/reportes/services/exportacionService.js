import api from '@/services/api.js';
import { fechaLocalIso } from '@/features/reportes/services/periodoDashboardService.js';

/**
 * Selección de período y formato para exportar reportes consolidados.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.1.3 / Sec-3.5.1 / CC-78
 */

export const FORMATO_PREDETERMINADO = 'csv';

export const FORMATOS_EXPORTACION = [
  { clave: 'csv', etiqueta: 'CSV', descripcion: 'Texto separado por comas, abre en cualquier hoja de cálculo.' },
  { clave: 'xlsx', etiqueta: 'Excel (.xlsx)', descripcion: 'Libro de Excel listo para el llenado manual.' },
];

/**
 * Revisa que el período elegido sea completo, ordenado y no llegue a fechas futuras.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.5.1 / CC-78
 *
 * @param   {{ fecha_desde: string, fecha_hasta: string }} filtros Rango del formulario.
 * @param   {Date} hoy Fecha de referencia; por defecto, la actual.
 * @returns {string|null} Mensaje del primer problema encontrado, o null si el período es válido.
 */
export function validarPeriodoExportacion(filtros, hoy = new Date()) {
  if (!filtros.fecha_desde || !filtros.fecha_hasta) {
    return 'Selecciona la fecha inicial y la fecha final del período.';
  }

  if (filtros.fecha_desde > filtros.fecha_hasta) {
    return 'La fecha inicial no puede ser posterior a la fecha final.';
  }

  if (filtros.fecha_hasta > fechaLocalIso(hoy)) {
    return 'El período no puede incluir fechas futuras.';
  }

  return null;
}

/**
 * Solicita al API el reporte del período en el formato elegido y lo descarga en el navegador.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.5.1 / CC-78
 *
 * @param   {{ fecha_desde: string, fecha_hasta: string }} filtros Período a exportar.
 * @param   {string} formato Clave de FORMATOS_EXPORTACION.
 * @returns {Promise<string>} Nombre del archivo descargado.
 */
export async function descargarExportacion(filtros, formato) {
  const respuesta = await api.get('/reportes/exportar', {
    params: { ...filtros, formato },
    responseType: 'blob',
  });

  const nombreArchivo = obtenerNombreArchivo(
    respuesta.headers['content-disposition'],
    `reporte-${filtros.fecha_desde}_${filtros.fecha_hasta}.${formato}`,
  );
  const enlace = document.createElement('a');

  enlace.href = URL.createObjectURL(respuesta.data);
  enlace.download = nombreArchivo;
  enlace.click();

  // Firefox cancela la descarga si la URL se revoca en el mismo ciclo del clic.
  setTimeout(() => URL.revokeObjectURL(enlace.href), 0);

  return nombreArchivo;
}

/**
 * Extrae el nombre del archivo de la cabecera Content-Disposition del servidor.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.5.1 / CC-78
 *
 * @param   {string|undefined} cabecera     Valor de Content-Disposition.
 * @param   {string}           predeterminado Nombre a usar si la cabecera no trae uno.
 * @returns {string} Nombre del archivo.
 */
function obtenerNombreArchivo(cabecera, predeterminado) {
  const coincidencia = cabecera?.match(/filename="?([^";]+)"?/);
  return coincidencia?.[1] ?? predeterminado;
}

/**
 * Obtiene el mensaje de error de una descarga fallida. Con responseType blob, el JSON
 * del contrato llega como Blob y hay que leerlo como texto.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.5.1 / CC-78
 *
 * @param   {unknown} error                 Error recibido en la petición HTTP.
 * @param   {string}  mensajePredeterminado Texto para fallos sin detalle.
 * @returns {Promise<string>} Mensaje seguro para mostrar en la interfaz.
 */
export async function obtenerMensajeErrorExportacion(error, mensajePredeterminado) {
  const cuerpo = error?.response?.data;

  if (!(cuerpo instanceof Blob)) return mensajePredeterminado;

  try {
    const contenido = JSON.parse(await cuerpo.text());
    const errores = contenido.errors ? Object.values(contenido.errors).flat() : [];
    return errores[0] || contenido.message || mensajePredeterminado;
  } catch {
    return mensajePredeterminado;
  }
}
