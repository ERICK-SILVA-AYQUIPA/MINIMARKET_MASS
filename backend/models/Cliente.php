<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `cliente`: DNI y/o RUC, y sus datos de contacto (migracion 026).
 * Se pide uno de los dos documentos; la factura exige RUC. Nombre, direccion y telefono son opcionales.
 */
class Cliente
{
    public function __construct(
        private int $idCliente,     // id_cliente INT UNSIGNED
        private ?string $dni,       // dni CHAR(8) NULL
        private ?string $ruc,       // ruc CHAR(11) NULL
        private ?string $nombre = null,    // nombre VARCHAR(100) NULL
        private ?string $direccion = null, // direccion VARCHAR(120) NULL
        private ?string $telefono = null   // telefono VARCHAR(9) NULL, solo digitos
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
    public function getNombre(): ?string { return $this->nombre; }
    public function getDireccion(): ?string { return $this->direccion; }
    public function getTelefono(): ?string { return $this->telefono; }

    // Como se muestra en las listas: su nombre si lo tiene; si no, "Cliente 12"
    public function nombreParaMostrar(): string
    {
        return $this->nombre ?? 'Cliente ' . $this->idCliente;
    }

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
