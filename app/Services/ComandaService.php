<?php

namespace App\Services;

use App\Models\Comanda;
use App\Models\Platillo;
use Illuminate\Support\Facades\DB;

class ComandaService
{
    /**
     * Registra una comanda junto con su detalle de platillos dentro de una transacción.
     * El precio de cada renglón se toma del platillo en base de datos, nunca del valor
     * enviado por el cliente, y el subtotal se calcula como la suma de cantidad × precio.
     *
     * @autor  manuelmv15
     * @fecha  2026-09-18
     * @módulo POS – RF-POS-001
     *
     * @param  array $datos Datos validados: mesa_id, usuario_id, canal, observaciones, detalles[].
     * @return Comanda Comanda creada con su detalle, mesa y usuario cargados.
     */
    public function registrar(array $datos): Comanda
    {
        return DB::transaction(function () use ($datos) {
            $comanda = Comanda::create([
                'mesa_id' => $datos['mesa_id'] ?? null,
                'usuario_id' => $datos['usuario_id'],
                'canal' => $datos['canal'],
                'estado' => 'pendiente',
                'observaciones' => $datos['observaciones'] ?? null,
                'subtotal' => 0,
            ]);

            $subtotal = 0;

            foreach ($datos['detalles'] as $detalle) {
                $platillo = Platillo::findOrFail($detalle['platillo_id']);

                $comanda->detalles()->create([
                    'platillo_id' => $platillo->id,
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $platillo->precio_unitario,
                    'observaciones' => $detalle['observaciones'] ?? null,
                ]);

                $subtotal += $platillo->precio_unitario * $detalle['cantidad'];
            }

            $comanda->update(['subtotal' => round($subtotal, 2)]);

            return $comanda->load('detalles.platillo', 'mesa', 'usuario');
        });
    }
}
