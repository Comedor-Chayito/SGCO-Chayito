<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\Egreso;
use App\Models\ExistenciaAreaOperativa;
use App\Models\Insumo;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Arma el reporte consolidado de un período y lo convierte al formato de archivo solicitado.
 * El reporte se divide en secciones: una hoja por sección en Excel, bloques sucesivos en CSV.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.1.3 / Sec-3.5.1 / CC-79 / CC-80 / CC-81
 */
class ExportacionReporteService
{
    // Marca de orden de bytes: sin ella, Excel abre el CSV en ANSI y rompe tildes y eñes.
    private const BOM_UTF8 = "\xEF\xBB\xBF";

    private const TIPOS_MIME = [
        'csv' => 'text/csv; charset=UTF-8',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    // Tipos de columna: deciden decimales en CSV y formato numérico en Excel.
    private const TIPO_TEXTO = 'texto';

    private const TIPO_MONTO = 'monto';

    private const TIPO_CANTIDAD = 'cantidad';

    private const TIPO_ENTERO = 'entero';

    private const TIPO_PORCENTAJE = 'porcentaje';

    private const DECIMALES_POR_TIPO = [
        self::TIPO_MONTO => 2,
        self::TIPO_CANTIDAD => 3,
        self::TIPO_PORCENTAJE => 2,
    ];

    private const FORMATOS_EXCEL = [
        self::TIPO_MONTO => '#,##0.00',
        self::TIPO_CANTIDAD => '#,##0.000',
        self::TIPO_PORCENTAJE => '0.00',
    ];

    private const ETIQUETAS_ESTADO_STOCK = [
        InsumoService::ESTADO_STOCK_NORMAL => 'Normal',
        InsumoService::ESTADO_STOCK_BAJO => 'Bajo',
        InsumoService::ESTADO_STOCK_AGOTADO => 'Agotado',
    ];

    /**
     * Inicializa el servicio con las fuentes de los indicadores y del estado de stock.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79 / CC-81
     *
     * @param  DashboardGerencialService  $dashboardGerencialService  Servicio de indicadores financieros.
     * @param  InsumoService  $insumoService  Servicio que evalúa existencias contra el stock mínimo.
     */
    public function __construct(
        private readonly DashboardGerencialService $dashboardGerencialService,
        private readonly InsumoService $insumoService,
    ) {}

    /**
     * Obtiene todas las secciones del reporte consolidado del período. Cada sección declara
     * el tipo de sus columnas; los valores numéricos van como float o int.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79 / CC-81
     *
     * @param  string  $fechaDesde  Inicio inclusivo del período (YYYY-MM-DD).
     * @param  string  $fechaHasta  Fin inclusivo del período (YYYY-MM-DD).
     * @return array<int, array{titulo: string, columnas: array<int, array{0: string, 1: string}>, filas: array<int, array<int, string|int|float>>, total: array<int, string|int|float>|null}> Secciones.
     */
    public function obtenerSecciones(string $fechaDesde, string $fechaHasta): array
    {
        $dashboard = $this->dashboardGerencialService->obtenerKpis($fechaDesde, $fechaHasta);

        return [
            $this->seccionResumen($fechaDesde, $fechaHasta, $dashboard['kpis']),
            $this->seccionEgresos($fechaDesde, $fechaHasta),
            $this->seccionGastosPorSubcategoria($dashboard['graficos']['gastos_por_subcategoria']),
            $this->seccionCompras($fechaDesde, $fechaHasta),
            $this->seccionInventario(),
            $this->seccionExistenciasPorArea(),
        ];
    }

    /**
     * Genera el contenido CSV del reporte: cada sección con su título, encabezados y filas,
     * separada de la siguiente por una línea en blanco.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79 / CC-81
     *
     * @param  string  $fechaDesde  Inicio inclusivo del período (YYYY-MM-DD).
     * @param  string  $fechaHasta  Fin inclusivo del período (YYYY-MM-DD).
     * @return string Contenido del archivo en UTF-8 con BOM.
     */
    public function generarCsv(string $fechaDesde, string $fechaHasta): string
    {
        $archivo = fopen('php://temp', 'r+');
        fwrite($archivo, self::BOM_UTF8);

        foreach ($this->obtenerSecciones($fechaDesde, $fechaHasta) as $indice => $seccion) {
            if ($indice > 0) {
                fwrite($archivo, PHP_EOL);
            }

            $tipos = array_column($seccion['columnas'], 1);
            fputcsv($archivo, [$seccion['titulo']], escape: '');
            fputcsv($archivo, array_column($seccion['columnas'], 0), escape: '');

            foreach ($this->filasConTotal($seccion) as $fila) {
                fputcsv($archivo, $this->formatearFilaCsv($fila, $tipos), escape: '');
            }
        }

        rewind($archivo);
        $contenido = stream_get_contents($archivo);
        fclose($archivo);

        return $contenido;
    }

    /**
     * Genera el contenido Excel (.xlsx) del reporte con una hoja por sección. Los montos y
     * cantidades se escriben como números para que se puedan sumar en la hoja.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-80 / CC-81
     *
     * @param  string  $fechaDesde  Inicio inclusivo del período (YYYY-MM-DD).
     * @param  string  $fechaHasta  Fin inclusivo del período (YYYY-MM-DD).
     * @return string Contenido binario del libro de Excel.
     */
    public function generarXlsx(string $fechaDesde, string $fechaHasta): string
    {
        $rutaTemporal = tempnam(sys_get_temp_dir(), 'reporte_');
        $escritor = new Writer;
        $escritor->openToFile($rutaTemporal);

        foreach ($this->obtenerSecciones($fechaDesde, $fechaHasta) as $indice => $seccion) {
            $hoja = $indice === 0 ? $escritor->getCurrentSheet() : $escritor->addNewSheetAndMakeItCurrent();
            $hoja->setName($seccion['titulo']);
            $hoja->setColumnWidthForRange(20, 1, count($seccion['columnas']));

            $this->escribirSeccionXlsx($escritor, $seccion);
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
     * Arma la sección de resumen financiero del período con los KPIs del dashboard. La columna
     * Valor mezcla fechas, montos y conteos; el formato de monto solo se aplica a los float.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79 / CC-81
     *
     * @param  string  $fechaDesde  Inicio inclusivo del período.
     * @param  string  $fechaHasta  Fin inclusivo del período.
     * @param  array<string, float|int>  $kpis  KPIs calculados por DashboardGerencialService.
     * @return array<string, mixed> Sección del reporte.
     */
    private function seccionResumen(string $fechaDesde, string $fechaHasta, array $kpis): array
    {
        return [
            'titulo' => 'Resumen',
            'columnas' => [['Concepto', self::TIPO_TEXTO], ['Valor', self::TIPO_MONTO]],
            'filas' => [
                ['Fecha desde', $fechaDesde],
                ['Fecha hasta', $fechaHasta],
                ['Inventario al', now()->format('Y-m-d H:i')],
                ['Ingresos por ventas', (float) $kpis['ingresos']],
                ['Gastos netos', (float) $kpis['gastos_netos']],
                ['IVA crédito fiscal', (float) $kpis['iva_credito_fiscal']],
                ['Utilidad bruta', (float) $kpis['utilidad_bruta']],
                ['Margen bruto (%)', (float) $kpis['margen_bruto_porcentaje']],
                ['Ventas registradas', (int) $kpis['ventas_registradas']],
                ['Egresos registrados', (int) $kpis['egresos_registrados']],
            ],
            'total' => null,
        ];
    }

    /**
     * Arma el detalle de egresos del período por fecha de comprobante, base del libro de compras.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-81
     *
     * @param  string  $fechaDesde  Inicio inclusivo del período.
     * @param  string  $fechaHasta  Fin inclusivo del período.
     * @return array<string, mixed> Sección del reporte con fila de total.
     */
    private function seccionEgresos(string $fechaDesde, string $fechaHasta): array
    {
        $egresos = Egreso::query()
            ->with('subcategoria:id,nombre')
            ->whereBetween('fecha_comprobante', [$fechaDesde, $fechaHasta])
            ->orderBy('fecha_comprobante')
            ->orderBy('correlativo')
            ->get();

        return [
            'titulo' => 'Egresos',
            'columnas' => [
                ['Correlativo', self::TIPO_TEXTO],
                ['Fecha de comprobante', self::TIPO_TEXTO],
                ['Proveedor', self::TIPO_TEXTO],
                ['N.º de comprobante', self::TIPO_TEXTO],
                ['Subcategoría', self::TIPO_TEXTO],
                ['Descripción', self::TIPO_TEXTO],
                ['Monto total', self::TIPO_MONTO],
                ['Costo neto', self::TIPO_MONTO],
                ['IVA crédito fiscal', self::TIPO_MONTO],
            ],
            'filas' => $egresos->map(fn (Egreso $egreso): array => [
                (string) $egreso->correlativo,
                $egreso->fecha_comprobante->format('Y-m-d'),
                (string) $egreso->proveedor,
                (string) $egreso->numero_comprobante,
                (string) $egreso->subcategoria?->nombre,
                (string) $egreso->descripcion,
                (float) $egreso->monto_total,
                (float) $egreso->costo_neto,
                (float) $egreso->iva_credito_fiscal,
            ])->all(),
            'total' => [
                'Total', '', '', '', '', '',
                round((float) $egresos->sum('monto_total'), 2),
                round((float) $egresos->sum('costo_neto'), 2),
                round((float) $egresos->sum('iva_credito_fiscal'), 2),
            ],
        ];
    }

    /**
     * Arma los totales de gasto neto por subcategoría de egreso del período.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-81
     *
     * @param  array<int, array{nombre: string, egresos: int, gasto_neto: float, porcentaje: float}>  $gastos  Gasto por subcategoría del dashboard.
     * @return array<string, mixed> Sección del reporte.
     */
    private function seccionGastosPorSubcategoria(array $gastos): array
    {
        return [
            'titulo' => 'Gastos por subcategoría',
            'columnas' => [
                ['Subcategoría', self::TIPO_TEXTO],
                ['Egresos', self::TIPO_ENTERO],
                ['Gasto neto', self::TIPO_MONTO],
                ['Porcentaje (%)', self::TIPO_PORCENTAJE],
            ],
            'filas' => array_map(fn (array $gasto): array => [
                $gasto['nombre'],
                (int) $gasto['egresos'],
                (float) $gasto['gasto_neto'],
                (float) $gasto['porcentaje'],
            ], $gastos),
            'total' => null,
        ];
    }

    /**
     * Arma el detalle de comprobantes de compra del período con su desglose de IVA.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-81
     *
     * @param  string  $fechaDesde  Inicio inclusivo del período.
     * @param  string  $fechaHasta  Fin inclusivo del período.
     * @return array<string, mixed> Sección del reporte con fila de total.
     */
    private function seccionCompras(string $fechaDesde, string $fechaHasta): array
    {
        $compras = Compra::query()
            ->whereBetween('fecha_compra', [$fechaDesde, $fechaHasta])
            ->orderBy('fecha_compra')
            ->orderBy('id')
            ->get();

        return [
            'titulo' => 'Compras',
            'columnas' => [
                ['Fecha de compra', self::TIPO_TEXTO],
                ['Monto total', self::TIPO_MONTO],
                ['Costo neto', self::TIPO_MONTO],
                ['IVA crédito fiscal', self::TIPO_MONTO],
            ],
            'filas' => $compras->map(fn (Compra $compra): array => [
                $compra->fecha_compra->format('Y-m-d'),
                (float) $compra->monto_total,
                (float) $compra->costo_neto,
                (float) $compra->iva_credito_fiscal,
            ])->all(),
            'total' => [
                'Total',
                round((float) $compras->sum('monto_total'), 2),
                round((float) $compras->sum('costo_neto'), 2),
                round((float) $compras->sum('iva_credito_fiscal'), 2),
            ],
        ];
    }

    /**
     * Arma las existencias actuales de bodega por insumo. Es una foto al momento de exportar:
     * el inventario no guarda histórico por fecha.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-81
     *
     * @return array<string, mixed> Sección del reporte.
     */
    private function seccionInventario(): array
    {
        return [
            'titulo' => 'Inventario',
            'columnas' => [
                ['Insumo', self::TIPO_TEXTO],
                ['Unidad', self::TIPO_TEXTO],
                ['Stock en bodega', self::TIPO_CANTIDAD],
                ['Stock mínimo', self::TIPO_CANTIDAD],
                ['Estado', self::TIPO_TEXTO],
            ],
            'filas' => Insumo::query()
                ->orderBy('nombre')
                ->get()
                ->map(fn (Insumo $insumo): array => [
                    $insumo->nombre,
                    $insumo->unidad_medida,
                    (float) $insumo->stock_actual,
                    (float) $insumo->stock_minimo,
                    self::ETIQUETAS_ESTADO_STOCK[$this->insumoService->evaluarExistencias($insumo)],
                ])
                ->all(),
            'total' => null,
        ];
    }

    /**
     * Arma las existencias actuales en las áreas operativas (insumos ya transferidos desde bodega).
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-81
     *
     * @return array<string, mixed> Sección del reporte.
     */
    private function seccionExistenciasPorArea(): array
    {
        return [
            'titulo' => 'Existencias por área',
            'columnas' => [
                ['Área', self::TIPO_TEXTO],
                ['Insumo', self::TIPO_TEXTO],
                ['Unidad', self::TIPO_TEXTO],
                ['Stock', self::TIPO_CANTIDAD],
            ],
            'filas' => ExistenciaAreaOperativa::query()
                ->join('insumos', 'insumos.id', '=', 'existencias_area_operativa.insumo_id')
                ->orderBy('existencias_area_operativa.area')
                ->orderBy('insumos.nombre')
                ->get([
                    'existencias_area_operativa.area',
                    'existencias_area_operativa.stock_actual',
                    'insumos.nombre',
                    'insumos.unidad_medida',
                ])
                ->map(fn (ExistenciaAreaOperativa $existencia): array => [
                    ucfirst($existencia->area),
                    $existencia->nombre,
                    $existencia->unidad_medida,
                    (float) $existencia->stock_actual,
                ])
                ->all(),
            'total' => null,
        ];
    }

    /**
     * Escribe en la hoja actual del libro los encabezados, filas y total de una sección.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-81
     *
     * @param  Writer  $escritor  Libro de Excel abierto.
     * @param  array<string, mixed>  $seccion  Sección del reporte.
     */
    private function escribirSeccionXlsx(Writer $escritor, array $seccion): void
    {
        $estiloNegrita = (new Style)->withFontBold(true);
        $tipos = array_column($seccion['columnas'], 1);

        $escritor->addRow(Row::fromValuesWithStyle(array_column($seccion['columnas'], 0), $estiloNegrita));

        foreach ($seccion['filas'] as $fila) {
            $escritor->addRow($this->crearFilaXlsx($fila, $tipos, false));
        }

        if ($seccion['total'] !== null) {
            $escritor->addRow($this->crearFilaXlsx($seccion['total'], $tipos, true));
        }
    }

    /**
     * Crea una fila de Excel aplicando a cada número decimal el formato de su columna.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-81
     *
     * @param  array<int, string|int|float>  $fila  Valores de la fila.
     * @param  array<int, string>  $tipos  Tipo de cada columna.
     * @param  bool  $negrita  true para la fila de total.
     * @return Row Fila lista para escribirse.
     */
    private function crearFilaXlsx(array $fila, array $tipos, bool $negrita): Row
    {
        $celdas = [];

        foreach ($fila as $indice => $valor) {
            $estilo = (new Style)->withFontBold($negrita);
            $formato = is_float($valor) ? (self::FORMATOS_EXCEL[$tipos[$indice]] ?? null) : null;

            $celdas[] = Cell::fromValue($valor, $formato ? $estilo->withFormat($formato) : $estilo);
        }

        return new Row($celdas);
    }

    /**
     * Devuelve las filas de una sección con su fila de total al final, si la tiene.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-81
     *
     * @param  array<string, mixed>  $seccion  Sección del reporte.
     * @return array<int, array<int, string|int|float>> Filas a escribir.
     */
    private function filasConTotal(array $seccion): array
    {
        return $seccion['total'] === null
            ? $seccion['filas']
            : [...$seccion['filas'], $seccion['total']];
    }

    /**
     * Convierte una fila a texto CSV con los decimales que corresponden al tipo de cada columna.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79 / CC-81
     *
     * @param  array<int, string|int|float>  $fila  Valores de la fila.
     * @param  array<int, string>  $tipos  Tipo de cada columna.
     * @return array<int, string> Valores listos para el CSV.
     */
    private function formatearFilaCsv(array $fila, array $tipos): array
    {
        return array_map(
            fn (string|int|float $valor, string $tipo): string => is_float($valor)
                ? $this->formatearDecimal($valor, self::DECIMALES_POR_TIPO[$tipo] ?? 2)
                : (string) $valor,
            $fila,
            $tipos,
        );
    }

    /**
     * Escribe un número con punto decimal, sin separador de miles.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79 / CC-81
     *
     * @param  float  $valor  Número a escribir.
     * @param  int  $decimales  Cantidad de decimales.
     * @return string Número formateado.
     */
    private function formatearDecimal(float $valor, int $decimales): string
    {
        return number_format($valor, $decimales, '.', '');
    }
}
