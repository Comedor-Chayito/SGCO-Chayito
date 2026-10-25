<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExportarReporteRequest;
use App\Services\ExportacionReporteService;
use Illuminate\Http\Response;

/**
 * Entrega como archivo descargable el reporte consolidado de un período.
 *
 * @autor  manuelmv15
 * @fecha  2026-10-24
 * @módulo Reportes – Sec-3.1.3 / Sec-3.5.1 / CC-79
 */
class ExportacionReporteController extends Controller
{
    /**
     * Inicializa el controlador con el servicio de exportación.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79
     *
     * @param  ExportacionReporteService  $exportacionReporteService  Servicio que genera el archivo.
     */
    public function __construct(private readonly ExportacionReporteService $exportacionReporteService) {}

    /**
     * Descarga el reporte consolidado del período en el formato solicitado. Es la única
     * respuesta de la API que no sigue el contrato JSON: el cuerpo es el archivo. Los errores
     * de validación sí responden con el contrato.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Reportes – Sec-3.5.1 / CC-79
     *
     * @param  ExportarReporteRequest  $request  Período y formato ya validados.
     * @return Response Archivo CSV como adjunto.
     */
    public function exportar(ExportarReporteRequest $request): Response
    {
        $filtro = $request->validated();
        $nombreArchivo = $this->exportacionReporteService->nombreArchivo(
            $filtro['fecha_desde'],
            $filtro['fecha_hasta'],
            $filtro['formato'],
        );

        return response(
            $this->exportacionReporteService->generarCsv($filtro['fecha_desde'], $filtro['fecha_hasta']),
            200,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$nombreArchivo}\"",
            ],
        );
    }
}
