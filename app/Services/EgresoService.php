<?php

namespace App\Services;

use App\Models\Egreso;
use App\Models\User;
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
