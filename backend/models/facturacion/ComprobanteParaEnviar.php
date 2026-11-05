<?php
declare(strict_types=1);

/**
 * Lo que necesita un proveedor OSE/PSE para emitir una boleta o factura electronica.
 * Montos en centimos (con IGV incluido) y cantidades en milesimas, como el resto del sistema.
 */
final class ComprobanteParaEnviar
{
    /**
     * @param array<int, array{codigo: string, descripcion: string, alPeso: bool, milesimas: int, precioCentimos: int, importeCentimos: int}> $lineas
     */
    public function __construct(
        public readonly bool $esFactura,
        public readonly string $serie,             // B001, F001
        public readonly int $numero,
        public readonly string $fechaEmision,      // 2026-11-05 o 2026-11-05 10:30:00
        public readonly string $tipoDocumentoCliente, // '-' sin documento, '1' DNI, '6' RUC (catalogo 06 de SUNAT)
        public readonly string $numeroDocumentoCliente,
        public readonly string $nombreCliente,
        public readonly array $lineas
    ) {
        if ($lineas === []) {
            throw new InvalidArgumentException('Un comprobante necesita al menos una linea');
        }
        if ($esFactura && $tipoDocumentoCliente !== '6') {
            throw new InvalidArgumentException('Una factura necesita el RUC del cliente');
        }
    }

    public function desglose(): DesgloseIgv
    {
        return DesgloseIgv::deLineas(array_column($this->lineas, 'importeCentimos'));
    }
}
