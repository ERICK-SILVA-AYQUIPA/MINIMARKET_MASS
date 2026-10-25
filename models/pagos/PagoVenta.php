<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `pago_venta`. Es abstract porque cada pago es de un subtipo:
 * efectivo, tarjeta o billetera digital Sip (tablas con la misma llave id_pago).
 */
abstract class PagoVenta
{
    public function __construct(
        private int $idPago,           // id_pago BIGINT UNSIGNED
        private float $montoPagado,    // monto_pagado DECIMAL(10,2)
        private string $fechaHora,     // fecha_hora DATETIME
        private string $estado,        // estado: 'VALIDO' o 'ANULADO'
        private int $idVenta           // id_venta BIGINT UNSIGNED
    ) {
        if ($montoPagado <= 0) {
            throw new InvalidArgumentException('monto_pagado debe ser mayor que 0');
        }
        if (!in_array($estado, ['VALIDO', 'ANULADO'], true)) {
            throw new InvalidArgumentException("estado debe ser 'VALIDO' o 'ANULADO'");
        }
    }

    // id_metodo_pago y nombre_metodo de la tabla metodo_pago
    abstract public function getIdMetodoPago(): int;
    abstract public function getNombreMetodo(): string;

    // El texto de "detalle_del_pago" de sp_boleta (boleta.sql)
    abstract public function detalle(): string;

    public function getIdPago(): int { return $this->idPago; }
    public function getMontoPagado(): float { return $this->montoPagado; }
    public function getFechaHora(): string { return $this->fechaHora; }
    public function getEstado(): string { return $this->estado; }
    public function getIdVenta(): int { return $this->idVenta; }

    public function esValido(): bool
    {
        return $this->estado === 'VALIDO';
    }
}
