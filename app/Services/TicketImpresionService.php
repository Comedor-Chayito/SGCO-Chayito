<?php

namespace App\Services;

use App\Models\Comanda;

/**
 * Servicio de formateo y vinculación con impresoras térmicas (US-POS-02 / RF-POS-001 / Sec. 3.1.2).
 * Formatea comandas para comanderas térmicas de 58 mm (32 columnas) y 80 mm (48 columnas).
 * Genera texto plano alineado y comandos binarios ESC/POS en Base64.
 *
 * @autor  Equipo SGCO-Chayito
 * @fecha  2026-09-20
 * @módulo POS – US-POS-02 / RF-POS-001
 */
class TicketImpresionService
{
    /**
     * Anchos de columna soportados según el ancho del rollo de papel térmico.
     */
    public const COLUMNAS_58MM = 32;
    public const COLUMNAS_80MM = 48;

    /**
     * Genera el payload estructurado del ticket de cocina para impresión térmica.
     *
     * @param  Comanda $comanda Instancia de la comanda con detalles, platillos y relaciones cargadas.
     * @param  int     $anchoMm Ancho en milímetros del papel térmico (58 u 80).
     * @return array{
     *     comanda_id: int,
     *     ancho_mm: int,
     *     columnas: int,
     *     lineas: array<int, string>,
     *     texto_plano: string,
     *     escpos_base64: string
     * }
     */
    public function generarTicketCocina(Comanda $comanda, int $anchoMm = 58): array
    {
        $comanda->loadMissing(['detalles.platillo', 'mesa', 'usuario']);

        $columnas = ($anchoMm >= 80) ? self::COLUMNAS_80MM : self::COLUMNAS_58MM;
        $lineas = [];

        // 1. Encabezado principal
        $lineas[] = $this->centrar('COMEDOR CHAYITO', $columnas);
        $lineas[] = $this->centrar('TICKET DE COCINA', $columnas);
        $lineas[] = $this->separador('=', $columnas);

        // 2. Metadatos de la orden
        $lineas[] = $this->justificar("ORDEN: #{$comanda->id}", $comanda->created_at ? $comanda->created_at->format('d/m/Y H:i') : date('d/m/Y H:i'), $columnas);

        // Canal / Mesa
        $canalTexto = match ($comanda->canal) {
            'mesa'        => $comanda->mesa ? "MESA: {$comanda->mesa->numero}" : 'MESA',
            'para_llevar' => 'CANAL: PARA LLEVAR',
            'whatsapp'    => 'CANAL: PEDIDO WHATSAPP',
            default       => 'CANAL: ' . strtoupper($comanda->canal),
        };
        $lineas[] = $this->ajustarALinea($canalTexto, $columnas);

        if ($comanda->usuario) {
            $lineas[] = $this->ajustarALinea("ATENDIO: {$comanda->usuario->name}", $columnas);
        }

        $lineas[] = $this->separador('-', $columnas);

        // 3. Encabezado de platillos
        $lineas[] = $this->justificar('CANT  PLATILLO', 'ESTADO', $columnas);
        $lineas[] = $this->separador('-', $columnas);

        // 4. Detalle de platillos y notas de preparación
        foreach ($comanda->detalles as $detalle) {
            $cant = $detalle->cantidad;
            $nombrePlatillo = $detalle->platillo ? $detalle->platillo->nombre : 'Platillo';
            $lineaPlatillo = sprintf('[%dx] %s', $cant, $nombrePlatillo);

            // Ajustar el nombre si excede el ancho disponible
            $lineasPlatillo = $this->dividirTexto($lineaPlatillo, $columnas);
            foreach ($lineasPlatillo as $lp) {
                $lineas[] = $lp;
            }

            // Observaciones específicas del platillo
            if (! empty($detalle->observaciones)) {
                $lineasObs = $this->dividirTexto("* NOTA: {$detalle->observaciones}", $columnas - 2);
                foreach ($lineasObs as $lo) {
                    $lineas[] = "  {$lo}";
                }
            }
        }

        $lineas[] = $this->separador('-', $columnas);

        // 5. Observación general de la comanda si existe
        if (! empty($comanda->observaciones)) {
            $lineas[] = 'OBSERVACIONES GENERALES:';
            $lineasObsGeneral = $this->dividirTexto($comanda->observaciones, $columnas - 2);
            foreach ($lineasObsGeneral as $log) {
                $lineas[] = "  {$log}";
            }
            $lineas[] = $this->separador('-', $columnas);
        }

        // 6. Pie del ticket
        $lineas[] = $this->centrar('*** PREPARACION FIFO ***', $columnas);
        $lineas[] = $this->centrar('SGCO-Chayito POS', $columnas);
        $lineas[] = '';

        $textoPlano = implode("\n", $lineas);
        $escposBinario = $this->generarEscposBinario($comanda, $lineas);

        return [
            'comanda_id'    => $comanda->id,
            'ancho_mm'      => ($columnas === self::COLUMNAS_80MM) ? 80 : 58,
            'columnas'      => $columnas,
            'lineas'        => $lineas,
            'texto_plano'   => $textoPlano,
            'escpos_base64' => base64_encode($escposBinario),
        ];
    }

    /**
     * Genera la secuencia binaria con comandos ESC/POS estándar para impresoras térmicas.
     *
     * @param  Comanda       $comanda
     * @param  array<string> $lineasTexto
     * @return string
     */
    protected function generarEscposBinario(Comanda $comanda, array $lineasTexto): string
    {
        $bin = '';

        // ESC @: Inicializar impresora
        $bin .= "\x1B\x40";

        // Imprimir líneas
        foreach ($lineasTexto as $linea) {
            // Si es encabezado o comanda destacada, activar negrita temporalmente
            if (str_contains($linea, 'COMEDOR CHAYITO') || str_contains($linea, 'ORDEN: #')) {
                $bin .= "\x1B\x45\x01"; // Negrita ON
                $bin .= $linea . "\n";
                $bin .= "\x1B\x45\x00"; // Negrita OFF
            } else {
                $bin .= $linea . "\n";
            }
        }

        // Avance de líneas de cortesía
        $bin .= "\n\n\n";

        // GS V 0: Corte de papel completo estándar ESC/POS
        $bin .= "\x1D\x56\x00";

        return $bin;
    }

    /**
     * Centra un texto en la línea según el ancho total de columnas.
     */
    public function centrar(string $texto, int $ancho): string
    {
        $longitud = mb_strlen($texto, 'UTF-8');
        if ($longitud >= $ancho) {
            return mb_substr($texto, 0, $ancho, 'UTF-8');
        }

        $espaciosIzq = (int) floor(($ancho - $longitud) / 2);

        return str_repeat(' ', $espaciosIzq) . $texto;
    }

    /**
     * Justifica texto con una sección a la izquierda y otra a la derecha en una sola línea.
     */
    public function justificar(string $izquierda, string $derecha, int $ancho): string
    {
        $lenIzq = mb_strlen($izquierda, 'UTF-8');
        $lenDer = mb_strlen($derecha, 'UTF-8');

        if (($lenIzq + $lenDer) >= $ancho) {
            $espacioDisponible = max(1, $ancho - $lenDer - 1);
            $izquierda = mb_substr($izquierda, 0, $espacioDisponible, 'UTF-8');
            $lenIzq = mb_strlen($izquierda, 'UTF-8');
        }

        $espacios = max(1, $ancho - ($lenIzq + $lenDer));

        return $izquierda . str_repeat(' ', $espacios) . $derecha;
    }

    /**
     * Genera una línea divisoria continua con un caracter repetido.
     */
    public function separador(string $caracter = '-', int $ancho = 32): string
    {
        return str_repeat($caracter[0] ?? '-', $ancho);
    }

    /**
     * Ajusta o recorta un texto a un máximo de columnas.
     */
    public function ajustarALinea(string $texto, int $ancho): string
    {
        if (mb_strlen($texto, 'UTF-8') > $ancho) {
            return mb_substr($texto, 0, $ancho, 'UTF-8');
        }

        return $texto;
    }

    /**
     * Divide un texto largo en múltiples líneas sin cortar palabras innecesariamente.
     *
     * @return array<int, string>
     */
    public function dividirTexto(string $texto, int $ancho): array
    {
        if (mb_strlen($texto, 'UTF-8') <= $ancho) {
            return [$texto];
        }

        $palabras = explode(' ', $texto);
        $lineas = [];
        $lineaActual = '';

        foreach ($palabras as $palabra) {
            if ($lineaActual === '') {
                $lineaActual = $palabra;
            } elseif (mb_strlen($lineaActual . ' ' . $palabra, 'UTF-8') <= $ancho) {
                $lineaActual .= ' ' . $palabra;
            } else {
                $lineas[] = $lineaActual;
                $lineaActual = $palabra;
            }
        }

        if ($lineaActual !== '') {
            $lineas[] = $lineaActual;
        }

        return $lineas;
    }
}
