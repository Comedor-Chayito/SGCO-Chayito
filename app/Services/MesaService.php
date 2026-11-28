<?php

namespace App\Services;

use App\Models\Comanda;
use App\Models\Mesa;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Servicio de lógica de negocio del catálogo de mesas del comedor (US-ADM-03 / CC-107).
 *
 * @autor  manuelmv15
 *
 * @fecha  2026-11-17
 *
 * @módulo Administración – US-ADM-03 / CC-107
 */
class MesaService
{
    /**
     * Estados de comanda que mantienen a una mesa en atención.
     *
     * @var list<string>
     */
    private const ESTADOS_COMANDA_ABIERTA = ['pendiente', 'en_preparacion', 'lista'];

    /**
     * Lista todas las mesas, activas e inactivas, para el panel de configuración.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @return Collection<int, Mesa> Mesas ordenadas por número.
     */
    public function listarTodas(): Collection
    {
        return Mesa::query()
            ->orderBy('numero')
            ->get(['id', 'numero', 'capacidad', 'estado', 'activa']);
    }

    /**
     * Registra una mesa nueva, libre y activa.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @param  array{numero: int, capacidad?: int|null}  $datos  Datos validados por CrearMesaRequest.
     * @return Mesa Mesa creada.
     */
    public function crear(array $datos): Mesa
    {
        $mesa = Mesa::create([
            'numero' => $datos['numero'],
            'capacidad' => $datos['capacidad'] ?? null,
            'estado' => 'libre',
            'activa' => true,
        ]);

        return $mesa->refresh();
    }

    /**
     * Cambia el número o la capacidad de una mesa. El estado libre/ocupada lo administra el POS.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @param  Mesa  $mesa  Mesa a editar.
     * @param  array{numero: int, capacidad?: int|null}  $datos  Datos validados por ActualizarMesaRequest.
     * @return Mesa Mesa actualizada.
     */
    public function actualizar(Mesa $mesa, array $datos): Mesa
    {
        $mesa->update([
            'numero' => $datos['numero'],
            'capacidad' => $datos['capacidad'] ?? null,
        ]);

        return $mesa->refresh();
    }

    /**
     * Borra una mesa sin comandas; si tiene comandas históricas, la desactiva en su lugar.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @param  Mesa  $mesa  Mesa a retirar.
     * @return Mesa|null null si se borró; la mesa desactivada si tenía comandas.
     *
     * @throws ValidationException Si la mesa tiene comandas abiertas.
     */
    public function eliminar(Mesa $mesa): ?Mesa
    {
        $this->validarSinComandasAbiertas($mesa);

        if (! $mesa->comandas()->exists()) {
            $mesa->delete();

            return null;
        }

        $mesa->update(['activa' => false]);

        return $mesa->refresh();
    }

    /**
     * Activa o desactiva una mesa. No se puede desactivar mientras tenga comandas abiertas.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @param  Mesa  $mesa  Mesa a alternar.
     * @return Mesa Mesa con su nuevo estado.
     *
     * @throws ValidationException Si se intenta desactivar una mesa con comandas abiertas.
     */
    public function alternarActivo(Mesa $mesa): Mesa
    {
        if ($mesa->activa) {
            $this->validarSinComandasAbiertas($mesa);
        }

        $mesa->update(['activa' => ! $mesa->activa]);

        return $mesa->refresh();
    }

    /**
     * Impide retirar una mesa que todavía tiene comandas pendientes, en preparación o listas.
     * Se revisan las comandas y no la columna estado, que puede quedar desfasada.
     *
     * @autor  manuelmv15
     *
     * @fecha  2026-11-17
     *
     * @módulo Administración – US-ADM-03 / CC-107
     *
     * @param  Mesa  $mesa  Mesa a revisar.
     * @return void
     *
     * @throws ValidationException Si la mesa tiene comandas abiertas.
     */
    private function validarSinComandasAbiertas(Mesa $mesa): void
    {
        $tieneComandasAbiertas = Comanda::query()
            ->where('mesa_id', $mesa->id)
            ->whereIn('estado', self::ESTADOS_COMANDA_ABIERTA)
            ->exists();

        if ($tieneComandasAbiertas) {
            throw ValidationException::withMessages([
                'mesa' => 'La mesa tiene comandas abiertas; retírala cuando se hayan cobrado o cancelado.',
            ]);
        }
    }
}
