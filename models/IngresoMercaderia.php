<?php
declare(strict_types=1);

/**
 * Cabecera de `ingreso_mercaderia` (database/ingresos.sql): mercaderia recibida en una tienda
 * con el documento del emisor. Las cantidades y costos estan en `detalle_ingreso`.
 */
class IngresoMercaderia
{
    public const TIPOS_DOCUMENTO = ['GUIA', 'FACTURA', 'BOLETA'];

    public function __construct(
        private int $idIngreso,            // id_ingreso INT UNSIGNED
        private string $fechaHora,         // fecha_hora DATETIME: cuando se registro
        private string $tipoDocumento,     // tipo_documento VARCHAR(7)
        private string $numeroDocumento,   // numero_documento VARCHAR(13), ej. F001-00012345
        private string $fechaDocumento,    // fecha_documento DATE
        private string $rucEmisor,         // ruc_emisor CHAR(11)
        private string $razonSocial,       // razon_social VARCHAR(100)
        private float $total,              // total DECIMAL(10,2)
        private ?string $observacion,      // observacion VARCHAR(200) NULL
        private int $idTienda,
        private int $idEmpleado
    ) {
        if (!in_array($tipoDocumento, self::TIPOS_DOCUMENTO, true)) {
            throw new InvalidArgumentException('Tipo de documento no valido');
        }
        if (preg_match('/^[0-9]{11}$/', $rucEmisor) !== 1) {
            throw new InvalidArgumentException('RUC del emisor no valido');
        }
        if ($total < 0) {
            throw new InvalidArgumentException('Total negativo');
        }
    }

    public function getIdIngreso(): int { return $this->idIngreso; }
    public function getFechaHora(): string { return $this->fechaHora; }
    public function getTipoDocumento(): string { return $this->tipoDocumento; }
    public function getNumeroDocumento(): string { return $this->numeroDocumento; }
    public function getFechaDocumento(): string { return $this->fechaDocumento; }
    public function getRucEmisor(): string { return $this->rucEmisor; }
    public function getRazonSocial(): string { return $this->razonSocial; }
    public function getTotal(): float { return $this->total; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function getIdTienda(): int { return $this->idTienda; }
    public function getIdEmpleado(): int { return $this->idEmpleado; }

    // "FACTURA F001-00012345"
    public function documento(): string
    {
        return $this->tipoDocumento . ' ' . $this->numeroDocumento;
    }
}
