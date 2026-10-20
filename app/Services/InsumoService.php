<?php

namespace App\Services;

use App\Models\Insumo;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de lógica de negocio para la gestión de insumos de bodega (CC-45 / US-INV-01).
 *
 * @autor  Jeferson De La Cruz
 * @fecha  2026-10-13
 * @módulo Inventario – RF-INV-003
 */
class InsumoService
{
    public const ESTADO_STOCK_NORMAL = 'normal';
    public const ESTADO_STOCK_BAJO = 'bajo';
    public const ESTADO_STOCK_AGOTADO = 'agotado';

    /**
     * Evalúa las existencias de un insumo contra su stock mínimo.
     * Un stock igual al mínimo ya se considera en alerta.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-004 / CC-57
     *
     * @param  Insumo $insumo Insumo a evaluar.
     * @return string Uno de los estados ESTADO_STOCK_*.
     */
    public function evaluarExistencias(Insumo $insumo): string
    {
        // Comparación en centavos para no arrastrar errores de punto flotante.
        $actual = (int) round((float) $insumo->stock_actual * 100);
        $minimo = (int) round((float) $insumo->stock_minimo * 100);

        if ($actual <= 0) {
            return self::ESTADO_STOCK_AGOTADO;
        }

        if ($actual <= $minimo) {
            return self::ESTADO_STOCK_BAJO;
        }

        return self::ESTADO_STOCK_NORMAL;
    }

    /**
     * Lista los insumos en alerta (stock en o por debajo del mínimo), agotados primero.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-004 / CC-57
     *
     * @return Collection<int, Insumo> Insumos en alerta con el atributo estado_stock.
     */
    public function listarAlertas(): Collection
    {
        $insumos = Insumo::query()
            ->whereColumn('stock_actual', '<=', 'stock_minimo')
            ->orWhere('stock_actual', '<=', 0)
            ->orderByRaw('stock_actual <= 0 DESC')
            ->orderBy('nombre', 'asc')
            ->get();

        return $insumos->each(fn (Insumo $insumo) => $this->adjuntarEstadoStock($insumo));
    }

    /**
     * Agrega al insumo el atributo calculado estado_stock para la respuesta de la API.
     *
     * @autor  manuelmv15
     * @fecha  2026-10-19
     * @módulo Inventario – RF-INV-004 / CC-57
     *
     * @param  Insumo $insumo Insumo a etiquetar.
     * @return Insumo El mismo insumo con estado_stock.
     */
    private function adjuntarEstadoStock(Insumo $insumo): Insumo
    {
        return $insumo->setAttribute('estado_stock', $this->evaluarExistencias($insumo));
    }

    /**
     * Lista todos los insumos, con filtro opcional por nombre.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  array<string, mixed> $filtros Filtros opcionales: busqueda.
     * @return Collection<int, Insumo>
     */
    public function listar(array $filtros = []): Collection
    {
        $query = Insumo::query()->orderBy('nombre', 'asc');

        if (! empty($filtros['busqueda'])) {
            $termino = '%' . trim($filtros['busqueda']) . '%';
            $query->where('nombre', 'like', $termino);
        }

        return $query->get()->each(fn (Insumo $insumo) => $this->adjuntarEstadoStock($insumo));
    }

    /**
     * Crea un nuevo insumo en el catálogo de bodega.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  array<string, mixed> $datos Datos validados del insumo.
     * @return Insumo
     */
    public function crear(array $datos): Insumo
    {
        $insumo = Insumo::create([
            'nombre'        => trim($datos['nombre']),
            'unidad_medida' => trim($datos['unidad_medida']),
            'stock_actual'  => $datos['stock_actual'] ?? 0,
            'stock_minimo'  => $datos['stock_minimo'] ?? 0,
        ]);

        return $this->adjuntarEstadoStock($insumo);
    }

    /**
     * Actualiza los datos de un insumo existente.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Insumo               $insumo Instancia del insumo a modificar.
     * @param  array<string, mixed> $datos  Datos validados a actualizar.
     * @return Insumo
     */
    public function actualizar(Insumo $insumo, array $datos): Insumo
    {
        $insumo->update([
            'nombre'        => trim($datos['nombre']),
            'unidad_medida' => trim($datos['unidad_medida']),
            'stock_actual'  => $datos['stock_actual'],
            'stock_minimo'  => $datos['stock_minimo'],
        ]);

        return $this->adjuntarEstadoStock($insumo->fresh());
    }

    /**
     * Elimina un insumo del catálogo si no está vinculado a ninguna receta activa.
     *
     * @autor  Jeferson De La Cruz
     * @fecha  2026-10-13
     * @módulo Inventario – RF-INV-003
     *
     * @param  Insumo $insumo Insumo a eliminar.
     * @return bool
     * @throws ValidationException Si el insumo posee recetas o requisiciones vinculadas.
     */
    public function eliminar(Insumo $insumo): bool
    {
        if ($insumo->recetas()->exists()) {
            throw ValidationException::withMessages([
                'insumo' => 'No se puede eliminar el insumo porque está vinculado a recetas de platillos. '
                    . 'Primero elimina las relaciones en las recetas.',
            ]);
        }

        if ($insumo->requisicionDetalles()->exists()) {
            throw ValidationException::withMessages([
                'insumo' => 'No se puede eliminar el insumo porque aparece en requisiciones registradas.',
            ]);
        }

        return (bool) $insumo->delete();
    }
}
