<?php

namespace App\Services;

use App\Models\Egreso;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de lógica de negocio para el registro de egresos (CC-13 / US-CON-01).
 *
 * @autor  manuelmv15
 * @fecha  2026-10-23
 * @módulo Contabilidad – RF-CON-005
 */
class EgresoService
{
    public const PREFIJO_CORRELATIVO = 'EG-';
    public const DIGITOS_CORRELATIVO = 6;
    public const EGRESOS_POR_PAGINA = 15;
    private const INTENTOS_CORRELATIVO = 3;

    /**
     * Inicializa el servicio con el registro de auditoría y el calculador de IVA inyectados.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Contabilidad – RF-CON-005 (CC-63) / RF-CON-006 (CC-68)
     *
     * @param  AuditoriaService        $auditoriaService        Servicio de bitácora de auditoría.
     * @param  IvaCreditoFiscalService $ivaCreditoFiscalService Calculador del desglose de IVA crédito fiscal.
     */
    public function __construct(
        private readonly AuditoriaService $auditoriaService,
        private readonly IvaCreditoFiscalService $ivaCreditoFiscalService,
    ) {
    }

    /**
     * Registra la factura de un proveedor como egreso y le asigna el siguiente correlativo.
     * Si dos registros simultáneos chocan por el mismo correlativo, se reintenta con el siguiente.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005 (CC-63)
     *
     * @param  array $datos   Datos validados de la factura.
     * @param  User  $usuario Contadora o administrador que registra el egreso.
     * @return Egreso Egreso creado con su subcategoría cargada.
     *
     * @throws ValidationException Si el comprobante ya fue registrado por otra petición simultánea.
     * @throws \Throwable          Si falla la transacción en base de datos.
     */
    public function registrar(array $datos, User $usuario): Egreso
    {
        for ($intento = 1; ; $intento++) {
            try {
                return DB::transaction(fn () => $this->crear($datos, $usuario));
            } catch (UniqueConstraintViolationException $e) {
                $chocoCorrelativo = str_contains($e->getMessage(), 'egresos_correlativo_unique');

                if (! $chocoCorrelativo) {
                    // El Form Request ya revisa duplicados; esto solo pasa si otra petición ganó la carrera.
                    throw ValidationException::withMessages([
                        'numero_comprobante' => 'Esta factura de este proveedor ya fue registrada.',
                    ]);
                }

                if ($intento >= self::INTENTOS_CORRELATIVO) {
                    throw $e;
                }
            }
        }
    }

    /**
     * Lista el historial de egresos paginado, del más reciente al más antiguo, junto con los
     * totales de todo el filtro (no solo de la página) para el reporte mensual.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Contabilidad – RF-CON-006 (CC-69)
     *
     * @param  array $filtros Filtros validados: 'desde', 'hasta' y 'subcategoria_egreso_id', todos opcionales.
     * @return array Arreglo con 'egresos' (paginado) y 'totales' (cantidad, monto total, costo neto e IVA).
     */
    public function listar(array $filtros): array
    {
        $totales = $this->consultaFiltrada($filtros)
            ->toBase()
            ->selectRaw('COUNT(*) AS cantidad')
            ->selectRaw('COALESCE(SUM(monto_total), 0) AS monto_total')
            ->selectRaw('COALESCE(SUM(costo_neto), 0) AS costo_neto')
            ->selectRaw('COALESCE(SUM(iva_credito_fiscal), 0) AS iva_credito_fiscal')
            ->first();

        $egresos = $this->consultaFiltrada($filtros)
            ->with('subcategoria:id,nombre')
            ->orderByDesc('fecha_comprobante')
            ->orderByDesc('id')
            ->paginate(self::EGRESOS_POR_PAGINA, [
                'id',
                'correlativo',
                'subcategoria_egreso_id',
                'proveedor',
                'numero_comprobante',
                'fecha_comprobante',
                'monto_total',
                'costo_neto',
                'iva_credito_fiscal',
            ]);

        return [
            'egresos' => $egresos,
            'totales' => [
                'cantidad'           => (int) $totales->cantidad,
                'monto_total'        => round((float) $totales->monto_total, 2),
                'costo_neto'         => round((float) $totales->costo_neto, 2),
                'iva_credito_fiscal' => round((float) $totales->iva_credito_fiscal, 2),
            ],
        ];
    }

    /**
     * Arma la consulta de egresos con los filtros opcionales del historial.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Contabilidad – RF-CON-006 (CC-69)
     *
     * @param  array $filtros Filtros validados del historial.
     * @return Builder<Egreso> Consulta sin ordenar ni paginar.
     */
    private function consultaFiltrada(array $filtros): Builder
    {
        return Egreso::query()
            ->when(
                $filtros['desde'] ?? null,
                fn (Builder $q, string $desde) => $q->where('fecha_comprobante', '>=', $desde)
            )
            ->when(
                $filtros['hasta'] ?? null,
                fn (Builder $q, string $hasta) => $q->where('fecha_comprobante', '<=', $hasta)
            )
            ->when(
                $filtros['subcategoria_egreso_id'] ?? null,
                fn (Builder $q, int|string $id) => $q->where('subcategoria_egreso_id', $id)
            );
    }

    /**
     * Inserta el egreso con el siguiente correlativo y su desglose de IVA, y deja registro de auditoría.
     * Debe ejecutarse dentro de una transacción para que el bloqueo tenga efecto.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-24
     * @módulo Contabilidad – RF-CON-005 (CC-63) / RF-CON-006 (CC-68)
     *
     * @param  array $datos   Datos validados de la factura.
     * @param  User  $usuario Usuario que registra el egreso.
     * @return Egreso Egreso creado.
     */
    private function crear(array $datos, User $usuario): Egreso
    {
        $montoTotal = round((float) $datos['monto_total'], 2);
        $desglose = $this->ivaCreditoFiscalService->calcular($montoTotal);

        $egreso = Egreso::create([
            'correlativo'            => $this->siguienteCorrelativo(),
            'subcategoria_egreso_id' => $datos['subcategoria_egreso_id'],
            'usuario_id'             => $usuario->id,
            'proveedor'              => trim($datos['proveedor']),
            'numero_comprobante'     => trim($datos['numero_comprobante']),
            'fecha_comprobante'      => $datos['fecha_comprobante'],
            'monto_total'            => $montoTotal,
            'costo_neto'             => $desglose['costo_neto'],
            'iva_credito_fiscal'     => $desglose['iva_credito_fiscal'],
            'descripcion'            => $datos['descripcion'] ?? null,
        ]);

        $this->auditoriaService->registrar('egreso_registrado', $egreso, null, $egreso->toArray());

        return $egreso->load('subcategoria:id,nombre');
    }

    /**
     * Calcula el siguiente correlativo (EG-000001, EG-000002, …) bloqueando el último egreso.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-23
     * @módulo Contabilidad – RF-CON-005 (CC-63)
     *
     * @return string Correlativo con prefijo y ceros a la izquierda.
     */
    private function siguienteCorrelativo(): string
    {
        $ultimo = Egreso::query()
            ->orderByDesc('id')
            ->lockForUpdate()
            ->value('correlativo');

        $numero = $ultimo ? (int) substr($ultimo, strlen(self::PREFIJO_CORRELATIVO)) + 1 : 1;

        return self::PREFIJO_CORRELATIVO . str_pad((string) $numero, self::DIGITOS_CORRELATIVO, '0', STR_PAD_LEFT);
    }
}
