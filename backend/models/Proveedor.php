<?php
declare(strict_types=1);

/**
 * Una fila de `proveedor` (migraciones/015): a quien le compra la cadena.
 * El RUC es unico; uno INACTIVO no recibe ordenes nuevas, pero sus ingresos y ordenes quedan.
 */
class Proveedor
{
    public const ESTADOS = ['ACTIVO', 'INACTIVO'];

    public function __construct(
        private int $idProveedor,
        private string $ruc,               // ruc CHAR(11)
        private string $razonSocial,       // razon_social VARCHAR(100)
        private ?string $contacto,         // contacto VARCHAR(80) NULL
        private ?string $telefono,         // telefono VARCHAR(15) NULL
        private ?string $correo,           // correo VARCHAR(100) NULL
        private ?int $diasEntrega,         // dias_entrega TINYINT NULL: cuanto tarda un pedido
        private string $estado
    ) {
        if (preg_match('/^[0-9]{11}$/', $ruc) !== 1) {
            throw new InvalidArgumentException('RUC del proveedor no valido');
        }
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado del proveedor no valido');
        }
    }

    public function getIdProveedor(): int { return $this->idProveedor; }
    public function getRuc(): string { return $this->ruc; }
    public function getRazonSocial(): string { return $this->razonSocial; }
    public function getContacto(): ?string { return $this->contacto; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function getCorreo(): ?string { return $this->correo; }
    public function getDiasEntrega(): ?int { return $this->diasEntrega; }
    public function getEstado(): string { return $this->estado; }

    public function estaActivo(): bool
    {
        return $this->estado === 'ACTIVO';
    }

    // La fecha en que deberia llegar un pedido hecho hoy (null si no se sabe cuanto tarda)
    public function entregaEstimada(string $hoy): ?string
    {
        return $this->diasEntrega === null ? null
            : (new DateTimeImmutable($hoy))->modify('+' . $this->diasEntrega . ' day')->format('Y-m-d');
    }
}
