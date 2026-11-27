<?php

namespace Tests\Concerns;

use App\Models\AperturaCaja;
use App\Models\User;

trait AbreCajaDelDia
{
    /**
     * Registra la apertura de caja de hoy para que las pruebas puedan cobrar y retirar.
     *
     * @autor  manuelmv15
     * @fecha  2026-11-18
     * @módulo POS – RF-POS-002 / US-POS-04
     *
     * @param  User   $usuario      Cajero o administrador que abre la caja.
     * @param  float  $saldoInicial Saldo inicial de la gaveta.
     * @return AperturaCaja Apertura creada.
     */
    protected function abrirCajaDelDia(User $usuario, float $saldoInicial = 0.00): AperturaCaja
    {
        return AperturaCaja::create([
            'usuario_id'     => $usuario->id,
            'fecha'          => today()->toDateString(),
            'saldo_inicial'  => $saldoInicial,
            'denominaciones' => [],
        ]);
    }
}
