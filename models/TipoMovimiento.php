<?php
declare(strict_types=1);

/** Una fila de `tipo_movimiento`: como afecta cada movimiento del kardex al stock. */
final class TipoMovimiento
{
    // id_tipo_movimiento de datos.sql; las operaciones del kardex los usan por nombre
    public const VENTA = 1;
    public const ANULACION_VENTA = 2;
    public const TRASLADO_SALIDA = 3;
    public const TRASLADO_ENTRADA = 4;
    public const MERMA = 5;
    public const AJUSTE_ENTRADA = 6;   // ajustes.sql
    public const AJUSTE_SALIDA = 7;
    public const INGRESO = 8;          // ingresos.sql
    public const DEVOLUCION = 9;       // operaciones.sql

    public function __construct(
        private int $idTipoMovimiento,      // id_tipo_movimiento TINYINT UNSIGNED
        private string $nombreMovimiento,   // nombre_movimiento VARCHAR(30)
        private string $efecto              // efecto CHAR(1): 'E' entrada, 'S' salida
    ) {
        if (!in_array($efecto, ['E', 'S'], true)) {
            throw new InvalidArgumentException("efecto debe ser 'E' o 'S'");
        }
    }

    public function getIdTipoMovimiento(): int { return $this->idTipoMovimiento; }
    public function getNombreMovimiento(): string { return $this->nombreMovimiento; }
    public function esEntrada(): bool { return $this->efecto === 'E'; }
}
