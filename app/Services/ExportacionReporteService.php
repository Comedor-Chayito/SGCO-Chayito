<?php

namespace App\Services;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Arma el reporte consolidado de un período y lo convierte al formato de archivo solicitado.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.1.3 / Sec-3.5.1 / CC-79 / CC-80
 */
class ExportacionReporteService
{
    // Marca de orden de bytes: sin ella, Excel abre el CSV en ANSI y rompe tildes y eñes.
    private const BOM_UTF8 = "\xEF\xBB\xBF";

    private const ENCABEZADOS = ['Sección', 'Concepto', 'Valor'];

    private const TIPOS_MIME = [
        'csv' => 'text/csv; charset=UTF-8',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    private const FORMATO_MONTO_EXCEL = '#,##0.00';

    /**
     * Inicializa el servicio con la fuente de los indicadores consolidados.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79
     *
     * @param  DashboardGerencialService  $dashboardGerencialService  Servicio de indicadores financieros.
     */
    public function __construct(private readonly DashboardGerencialService $dashboardGerencialService) {}

    /**
     * Obtiene las filas del resumen consolidado del período, listas para escribirse en un archivo.
     * Los montos van como float y los conteos como int; cada formato decide cómo escribirlos.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79 / CC-80
     *
     * @param  string  $fechaDesde  Inicio inclusivo del período (YYYY-MM-DD).
     * @param  string  $fechaHasta  Fin inclusivo del período (YYYY-MM-DD).
     * @return array<int, array{0: string, 1: string, 2: string|int|float}> Filas sección, concepto y valor.
     */
    public function obtenerFilas(string $fechaDesde, string $fechaHasta): array
    {
        $kpis = $this->dashboardGerencialService->obtenerKpis($fechaDesde, $fechaHasta)['kpis'];

        return [
            ['Período', 'Fecha desde', $fechaDesde],
            ['Período', 'Fecha hasta', $fechaHasta],
            ['Resumen financiero', 'Ingresos por ventas', (float) $kpis['ingresos']],
            ['Resumen financiero', 'Gastos netos', (float) $kpis['gastos_netos']],
            ['Resumen financiero', 'IVA crédito fiscal', (float) $kpis['iva_credito_fiscal']],
            ['Resumen financiero', 'Utilidad bruta', (float) $kpis['utilidad_bruta']],
            ['Resumen financiero', 'Margen bruto (%)', (float) $kpis['margen_bruto_porcentaje']],
            ['Resumen financiero', 'Ventas registradas', (int) $kpis['ventas_registradas']],
            ['Resumen financiero', 'Egresos registrados', (int) $kpis['egresos_registrados']],
        ];
    }

    /**
     * Genera el contenido CSV del reporte consolidado del período.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79
     *
     * @param  string  $fechaDesde  Inicio inclusivo del período (YYYY-MM-DD).
     * @param  string  $fechaHasta  Fin inclusivo del período (YYYY-MM-DD).
     * @return string Contenido del archivo en UTF-8 con BOM.
     */
    public function generarCsv(string $fechaDesde, string $fechaHasta): string
    {
        $archivo = fopen('php://temp', 'r+');

        fwrite($archivo, self::BOM_UTF8);
        fputcsv($archivo, self::ENCABEZADOS, escape: '');

        foreach ($this->obtenerFilas($fechaDesde, $fechaHasta) as $fila) {
            fputcsv($archivo, array_map($this->formatearValorCsv(...), $fila), escape: '');
        }

        rewind($archivo);
        $contenido = stream_get_contents($archivo);
        fclose($archivo);

        return $contenido;
    }

    /**
     * Genera el contenido Excel (.xlsx) del reporte consolidado del período. Los montos se
     * escriben como números con dos decimales para que se puedan sumar en la hoja.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-80
     *
     * @param  string  $fechaDesde  Inicio inclusivo del período (YYYY-MM-DD).
     * @param  string  $fechaHasta  Fin inclusivo del período (YYYY-MM-DD).
     * @return string Contenido binario del libro de Excel.
     */
    public function generarXlsx(string $fechaDesde, string $fechaHasta): string
    {
        $rutaTemporal = tempnam(sys_get_temp_dir(), 'reporte_');
        $estiloEncabezado = (new Style)->withFontBold(true);
        $estiloMonto = (new Style)->withFormat(self::FORMATO_MONTO_EXCEL);
        $escritor = new Writer;

        $escritor->getOptions()->setColumnWidth(22, 1, 2);
        $escritor->getOptions()->setColumnWidth(16, 3);
        $escritor->openToFile($rutaTemporal);
        $escritor->addRow(Row::fromValuesWithStyle(self::ENCABEZADOS, $estiloEncabezado));

        foreach ($this->obtenerFilas($fechaDesde, $fechaHasta) as $fila) {
            $escritor->addRow(new Row(array_map(
                fn (string|int|float $valor): Cell => Cell::fromValue(
                    $valor,
                    is_float($valor) ? $estiloMonto : null,
                ),
                $fila,
            )));
        }

        $escritor->close();
        $contenido = file_get_contents($rutaTemporal);
        unlink($rutaTemporal);

        return $contenido;
    }

    /**
     * Genera el archivo del reporte en el formato indicado.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-80
     *
     * @param  string  $formato  Formato validado: csv o xlsx.
     * @param  string  $fechaDesde  Inicio inclusivo del período (YYYY-MM-DD).
     * @param  string  $fechaHasta  Fin inclusivo del período (YYYY-MM-DD).
     * @return string Contenido del archivo.
     */
    public function generarArchivo(string $formato, string $fechaDesde, string $fechaHasta): string
    {
        return $formato === 'xlsx'
            ? $this->generarXlsx($fechaDesde, $fechaHasta)
            : $this->generarCsv($fechaDesde, $fechaHasta);
    }

    /**
     * Devuelve el tipo MIME con que se descarga cada formato.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-80
     *
     * @param  string  $formato  Formato validado: csv o xlsx.
     * @return string Valor de la cabecera Content-Type.
     */
    public function tipoMime(string $formato): string
    {
        return self::TIPOS_MIME[$formato];
    }

    /**
     * Construye el nombre del archivo descargable a partir del período y el formato.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79
     *
     * @param  string  $fechaDesde  Inicio del período (YYYY-MM-DD).
     * @param  string  $fechaHasta  Fin del período (YYYY-MM-DD).
     * @param  string  $formato  Extensión del archivo.
     * @return string Nombre del archivo.
     */
    public function nombreArchivo(string $fechaDesde, string $fechaHasta, string $formato): string
    {
        return "reporte-consolidado_{$fechaDesde}_{$fechaHasta}.{$formato}";
    }

    /**
     * Escribe un valor del reporte como texto CSV; los montos llevan punto decimal y dos
     * decimales, sin separador de miles.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79 / CC-80
     *
     * @param  string|int|float  $valor  Valor de una celda del reporte.
     * @return string Valor listo para el CSV.
     */
    private function formatearValorCsv(string|int|float $valor): string
    {
        return is_float($valor) ? number_format($valor, 2, '.', '') : (string) $valor;
    }
}
