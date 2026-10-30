<?php
declare(strict_types=1);

require_once __DIR__ . '/Dinero.php';
require_once __DIR__ . '/DetalleVenta.php';
require_once __DIR__ . '/pagos/PagoVenta.php';

/**
 * Una fila de la tabla `venta`: cabecera del comprobante.
 * De la serie salen la tienda y el tipo; del turno salen la caja y el cajero.
 */
class Venta
{
    public function __construct(
        private int $idVenta,              // id_venta BIGINT UNSIGNED
        private int $numeroCorrelativo,    // numero_correlativo INT UNSIGNED, unico por serie
        private string $fechaHora,         // fecha_hora DATETIME
        private float $total,              // total DECIMAL(10,2)
        private string $estado,            // estado: 'EMITIDA' o 'ANULADA'
        private int $idSerie,              // id_serie SMALLINT UNSIGNED
        private int $idCajaTurno,          // id_caja_turno INT UNSIGNED
        private ?int $idCliente            // id_cliente INT UNSIGNED NULL: boleta sin cliente
    ) {
        if ($numeroCorrelativo < 1 || $numeroCorrelativo > 99999999) {
            throw new InvalidArgumentException('numero_correlativo fuera de rango');
        }
        if ($total <= 0) {
            throw new InvalidArgumentException('total debe ser mayor que 0');
        }
        if (!in_array($estado, ['EMITIDA', 'ANULADA'], true)) {
            throw new InvalidArgumentException("estado debe ser 'EMITIDA' o 'ANULADA'");
        }
    }

    public function getIdVenta(): int { return $this->idVenta; }
    public function getNumeroCorrelativo(): int { return $this->numeroCorrelativo; }
    public function getFechaHora(): string { return $this->fechaHora; }
    public function getTotal(): float { return $this->total; }
    public function getEstado(): string { return $this->estado; }
    public function getIdSerie(): int { return $this->idSerie; }
    public function getIdCajaTurno(): int { return $this->idCajaTurno; }
    public function getIdCliente(): ?int { return $this->idCliente; }

    public function estaAnulada(): bool
    {
        return $this->estado === 'ANULADA';
    }

    /** La suma de los importes del detalle es el total. @param DetalleVenta[] $detalles */
    public function detalleCuadra(array $detalles): bool
    {
        $suma = 0;
        foreach ($detalles as $detalle) {
            $suma += Dinero::centimos($detalle->getImporte());
        }
        return $suma === Dinero::centimos($this->total);
    }

    /**
     * Los pagos validos suman el total. Si la venta esta anulada,
     * todos sus pagos deben estar ANULADOS.
     * @param PagoVenta[] $pagos
     */
    public function pagosCuadran(array $pagos): bool
    {
        if ($this->estaAnulada()) {
            foreach ($pagos as $pago) {
                if ($pago->esValido()) {
                    return false;
                }
            }
            return true;
        }

        $suma = 0;
        foreach ($pagos as $pago) {
            if ($pago->esValido()) {
                $suma += Dinero::centimos($pago->getMontoPagado());
            }
        }
        return $suma === Dinero::centimos($this->total);
    }
}
