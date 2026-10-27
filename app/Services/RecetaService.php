<?php

namespace App\Services;

use App\Models\Platillo;
use App\Models\Receta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de lógica de negocio para vincular ingredientes (insumos) a platillos
 * mediante la tabla de recetas estándar. Soporta conversión de unidades de consumo
 * distintas a la unidad de almacenamiento en bodega (CC-46 / US-INV-01).
 *
 * Ejemplo: insumo registrado en 'kg', receta define consumo en 'g' (200 g/platillo).
 * La porción se almacena en la unidad de consumo tal como el usuario la ingresó.
 * La deducción real de bodega usa ConversionService para convertir al momento del descuento.
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-10-13
 *
 * @módulo Inventario – RF-INV-003
 */
class RecetaService
{
    /**
     * Lista todos los renglones de receta de un platillo con sus insumos cargados.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-13
     *
     * @módulo Inventario – RF-INV-003
     *
     * @param  Platillo  $platillo  Platillo del cual se obtienen las recetas.
     * @return Collection<int, Receta>
     */
    public function listarPorPlatillo(Platillo $platillo): Collection
    {
        return $platillo
            ->recetas()
            ->with('insumo:id,nombre,unidad_medida,stock_actual')
            ->get();
    }

    /**
     * Agrega un insumo (con su porción y unidad de consumo) a la receta de un platillo.
     * Valida que no exista ya el mismo insumo en el mismo platillo.
     * La cantidad se almacena en la unidad de consumo indicada; la conversión a la unidad
     * base del insumo ocurre en el momento de la deducción de bodega.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-13
     *
     * @módulo Inventario – RF-INV-003
     *
     * @param  Platillo  $platillo  Platillo al que se le agrega el insumo.
     * @param  array<string, mixed>  $datos  Datos validados: insumo_id, cantidad_por_porcion, unidad_consumo.
     *
     * @throws ValidationException Si el insumo ya está en la receta del platillo.
     */
    public function agregar(Platillo $platillo, array $datos): Receta
    {
        $yaExiste = $platillo->recetas()
            ->where('insumo_id', $datos['insumo_id'])
            ->exists();

        if ($yaExiste) {
            throw ValidationException::withMessages([
                'insumo_id' => 'Este insumo ya está en la receta de este platillo. '
                    .'Si deseas cambiar la porción, edita el renglón existente.',
            ]);
        }

        $receta = Receta::create([
            'platillo_id' => $platillo->id,
            'insumo_id' => $datos['insumo_id'],
            'cantidad_por_porcion' => $datos['cantidad_por_porcion'],
            'unidad_consumo' => ConversionService::normalizarUnidad($datos['unidad_consumo']),
        ]);

        return $receta->load('insumo:id,nombre,unidad_medida,stock_actual');
    }

    /**
     * Actualiza la porción y unidad de consumo de un renglón de receta existente.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-13
     *
     * @módulo Inventario – RF-INV-003
     *
     * @param  Receta  $receta  Renglón de receta a modificar.
     * @param  array<string, mixed>  $datos  Datos validados: cantidad_por_porcion, unidad_consumo.
     */
    public function actualizar(Receta $receta, array $datos): Receta
    {
        $receta->update([
            'cantidad_por_porcion' => $datos['cantidad_por_porcion'],
            'unidad_consumo' => ConversionService::normalizarUnidad($datos['unidad_consumo']),
        ]);

        return $receta->load('insumo:id,nombre,unidad_medida,stock_actual');
    }

    /**
     * Elimina un renglón de receta (desvincula un insumo de un platillo).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-13
     *
     * @módulo Inventario – RF-INV-003
     *
     * @param  Receta  $receta  Renglón de receta a eliminar.
     */
    public function eliminar(Receta $receta): bool
    {
        return (bool) $receta->delete();
    }

    /**
     * Calcula la cantidad de un insumo a descontar de bodega al vender N platillos,
     * convirtiendo de la unidad de consumo registrada a la unidad base del insumo.
     *
     * Ejemplo: receta define 200 g/platillo, insumo almacenado en kg.
     *   descuentoEnBase(renglon, 3) → 0.600 kg
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-13
     *
     * @módulo Inventario – RF-INV-003 / US-INV-01
     *
     * @param  Receta  $receta  Renglón de receta con su insumo cargado.
     * @param  int  $cantidadVendida  Número de porciones vendidas.
     * @return float Cantidad a descontar en la unidad de almacenamiento del insumo.
     */
    public function descuentoEnBase(Receta $receta, int $cantidadVendida): float
    {
        $cantidadConsumo = (float) $receta->cantidad_por_porcion * $cantidadVendida;

        return ConversionService::convertir(
            $cantidadConsumo,
            $receta->unidad_consumo,
            $receta->insumo->unidad_medida
        );
    }
}
