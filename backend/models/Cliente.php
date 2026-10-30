<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `cliente`: solo DNI y/o RUC.
 * Se pide uno de los dos; la factura exige RUC.
 */
class Cliente
{
    public function __construct(
        private int $idCliente,     // id_cliente INT UNSIGNED
        private ?string $dni,       // dni CHAR(8) NULL
        private ?string $ruc        // ruc CHAR(11) NULL
    ) {
        // Los mismos CHECK de la tabla
        if ($dni === null && $ruc === null) {
            throw new InvalidArgumentException('El cliente necesita DNI o RUC');
        }
        if ($dni !== null && preg_match('/^[0-9]{8}$/', $dni) !== 1) {
            throw new InvalidArgumentException('El DNI debe tener 8 digitos');
        }
        if ($ruc !== null && preg_match('/^[0-9]{11}$/', $ruc) !== 1) {
            throw new InvalidArgumentException('El RUC debe tener 11 digitos');
        }
    }

    public function getIdCliente(): int { return $this->idCliente; }
    public function getDni(): ?string { return $this->dni; }
    public function getRuc(): ?string { return $this->ruc; }

    public function puedeRecibirFactura(): bool
    {
        return $this->ruc !== null;
    }

    // Igual que la columna `cliente` de vw_comprobante (boleta.sql)
    public function documentoParaComprobante(bool $esFactura): string
    {
        if ($esFactura && $this->ruc !== null) {
            return 'RUC ' . $this->ruc;
        }
        if ($this->dni !== null) {
            return 'DNI ' . $this->dni;
        }
        return 'RUC ' . $this->ruc;
    }
}
