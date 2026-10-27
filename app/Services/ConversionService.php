<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Servicio de conversión de unidades de medida para la deducción automática de inventario.
 * Convierte entre unidades de la misma familia (masa, volumen, conteo) usando una base canónica.
 *
 * Tabla de equivalencias canónicas:
 *   Masa    → base: gramos (g)
 *   Volumen → base: mililitros (ml)
 *   Conteo  → base: unidades
 *
 * @autor  Jeferson De La Cruz
 *
 * @fecha  2026-10-13
 *
 * @módulo Inventario – RF-INV-003 / US-INV-01
 */
class ConversionService
{
    /**
     * Factor que expresa cuántas unidades canónicas equivale 1 unidad de la clave.
     * Masa en gramos · Volumen en mililitros · Conteo en unidades.
     */
    private const FACTOR_CANONICO = [
        // ── Masa (base: gramos) ───────────────────────────────────────────
        'kg' => 1000.0,
        'g' => 1.0,
        'libra' => 453.592,
        'onza' => 28.3495,
        'arroba' => 11500.0,   // 1 arroba ≈ 11.5 kg (uso centroamericano)

        // ── Volumen (base: mililitros) ────────────────────────────────────
        'lt' => 1000.0,
        'ml' => 1.0,

        // ── Conteo (base: unidades) ───────────────────────────────────────
        'unidad' => 1.0,
        'docena' => 12.0,
        'caja' => 1.0,   // caja y bolsa se asumen sin sub-conversión; ajustar si se necesita
        'bolsa' => 1.0,
    ];

    /**
     * Convierte una cantidad desde una unidad de origen hacia una unidad de destino.
     * Ambas unidades deben pertenecer a la misma familia (masa, volumen o conteo).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-13
     *
     * @módulo Inventario – RF-INV-003
     *
     * @param  float  $cantidad  Valor numérico a convertir.
     * @param  string  $desde  Unidad de origen (ej. 'g').
     * @param  string  $hasta  Unidad de destino (ej. 'kg').
     * @return float Valor convertido, redondeado a 6 decimales.
     */
    public static function convertir(float $cantidad, string $desde, string $hasta): float
    {
        $desde = self::normalizarUnidad($desde);
        $hasta = self::normalizarUnidad($hasta);

        if ($desde === $hasta) {
            return round($cantidad, 6);
        }

        if (! self::sonCompatibles($desde, $hasta)) {
            throw new InvalidArgumentException(
                "No es posible convertir la unidad '{$desde}' a '{$hasta}'."
            );
        }

        $factorDesde = self::FACTOR_CANONICO[$desde];
        $factorHasta = self::FACTOR_CANONICO[$hasta];

        return round(($cantidad * $factorDesde) / $factorHasta, 6);
    }

    /**
     * Normaliza alias de unidades para almacenar y convertir una representación única.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-19
     *
     * @módulo Inventario – RF-INV-003
     *
     * @param  string  $unidad  Unidad ingresada por el usuario o almacenada en bodega.
     * @return string Unidad normalizada en minúsculas.
     */
    public static function normalizarUnidad(string $unidad): string
    {
        $unidad = strtolower(trim($unidad));

        return match ($unidad) {
            'gr' => 'g',
            'lb' => 'libra',
            'oz' => 'onza',
            'l' => 'lt',
            'cc' => 'ml',
            'pieza' => 'unidad',
            default => $unidad,
        };
    }

    /**
     * Determina si dos unidades pueden convertirse entre sí sin alterar su magnitud.
     *
     * @autor  Alexis
     *
     * @fecha  2026-10-19
     *
     * @módulo Inventario – RF-INV-003
     *
     * @param  string  $unidadOrigen  Unidad de consumo.
     * @param  string  $unidadDestino  Unidad de almacenamiento en bodega.
     * @return bool True si pertenecen a la misma familia o son exactamente iguales.
     */
    public static function sonCompatibles(string $unidadOrigen, string $unidadDestino): bool
    {
        $unidadOrigen = self::normalizarUnidad($unidadOrigen);
        $unidadDestino = self::normalizarUnidad($unidadDestino);

        if ($unidadOrigen === $unidadDestino) {
            return true;
        }

        $familiaOrigen = self::familia($unidadOrigen);
        $familiaDestino = self::familia($unidadDestino);

        return $familiaOrigen !== null && $familiaOrigen === $familiaDestino;
    }

    /**
     * Devuelve la lista de unidades compatibles (misma familia) para una unidad dada.
     * Útil para poblar el selector de unidad de consumo en el frontend (fallback PHP).
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-13
     *
     * @módulo Inventario – RF-INV-003
     *
     * @param  string  $unidad  Unidad base del insumo (ej. 'kg').
     * @return array<string> Lista de unidades de la misma familia.
     */
    public static function unidadesCompatibles(string $unidad): array
    {
        $unidad = self::normalizarUnidad($unidad);
        $familia = self::familia($unidad);

        $grupos = [
            'masa' => ['kg', 'g', 'libra', 'onza', 'arroba'],
            'volumen' => ['lt', 'ml'],
            'conteo' => ['unidad', 'docena', 'caja', 'bolsa'],
        ];

        return $grupos[$familia] ?? [$unidad];
    }

    /**
     * Determina la familia de medida a la que pertenece una unidad.
     *
     * @autor  Jeferson De La Cruz
     *
     * @fecha  2026-10-13
     *
     * @módulo Inventario – RF-INV-003
     *
     * @param  string  $unidad  Unidad en minúsculas.
     * @return string|null 'masa', 'volumen', 'conteo' o null si es desconocida.
     */
    private static function familia(string $unidad): ?string
    {
        $masas = ['kg', 'g', 'libra', 'onza', 'arroba'];
        $volumenes = ['lt', 'ml'];
        $conteo = ['unidad', 'docena', 'caja', 'bolsa'];

        if (in_array($unidad, $masas, true)) {
            return 'masa';
        }

        if (in_array($unidad, $volumenes, true)) {
            return 'volumen';
        }

        if (in_array($unidad, $conteo, true)) {
            return 'conteo';
        }

        return null;
    }
}
