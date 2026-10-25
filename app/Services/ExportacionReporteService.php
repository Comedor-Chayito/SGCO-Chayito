<?php

namespace App\Services;

/**
 * Arma el reporte consolidado de un período y lo convierte al formato de archivo solicitado.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.1.3 / Sec-3.5.1 / CC-79
 */
class ExportacionReporteService
{
    // Marca de orden de bytes: sin ella, Excel abre el CSV en ANSI y rompe tildes y eñes.
    private const BOM_UTF8 = "\xEF\xBB\xBF";

    private const ENCABEZADOS = ['Sección', 'Concepto', 'Valor'];

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
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79
     *
     * @param  string  $fechaDesde  Inicio inclusivo del período (YYYY-MM-DD).
     * @param  string  $fechaHasta  Fin inclusivo del período (YYYY-MM-DD).
     * @return array<int, array{0: string, 1: string, 2: string}> Filas sección, concepto y valor.
     */
    public function obtenerFilas(string $fechaDesde, string $fechaHasta): array
    {
        $kpis = $this->dashboardGerencialService->obtenerKpis($fechaDesde, $fechaHasta)['kpis'];

        return [
            ['Período', 'Fecha desde', $fechaDesde],
            ['Período', 'Fecha hasta', $fechaHasta],
            ['Resumen financiero', 'Ingresos por ventas', $this->formatearMonto($kpis['ingresos'])],
            ['Resumen financiero', 'Gastos netos', $this->formatearMonto($kpis['gastos_netos'])],
            ['Resumen financiero', 'IVA crédito fiscal', $this->formatearMonto($kpis['iva_credito_fiscal'])],
            ['Resumen financiero', 'Utilidad bruta', $this->formatearMonto($kpis['utilidad_bruta'])],
            ['Resumen financiero', 'Margen bruto (%)', $this->formatearMonto($kpis['margen_bruto_porcentaje'])],
            ['Resumen financiero', 'Ventas registradas', (string) $kpis['ventas_registradas']],
            ['Resumen financiero', 'Egresos registrados', (string) $kpis['egresos_registrados']],
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
            fputcsv($archivo, $fila, escape: '');
        }

        rewind($archivo);
        $contenido = stream_get_contents($archivo);
        fclose($archivo);

        return $contenido;
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
     * Escribe un monto con punto decimal y dos decimales, sin separador de miles.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79
     *
     * @param  float  $monto  Importe a escribir.
     * @return string Monto con dos decimales.
     */
    private function formatearMonto(float $monto): string
    {
        return number_format($monto, 2, '.', '');
    }
}
