<?php
declare(strict_types=1);

/**
 * Una fila de la tabla `serie_comprobante`: B001, F001... por tienda,
 * con el ultimo correlativo emitido.
 */
class SerieComprobante
{
    public function __construct(
        private int $idSerie,              // id_serie SMALLINT UNSIGNED
        private string $serie,             // serie CHAR(4): B001, F001...
        private int $ultimoNumero,         // ultimo_numero INT UNSIGNED
        private int $idTienda,             // id_tienda SMALLINT UNSIGNED
        private int $idTipoComprobante     // id_tipo_comprobante: 1 BOLETA, 2 FACTURA
    ) {
        if (preg_match('/^[BF][0-9]{3}$/', $serie) !== 1) {
            throw new InvalidArgumentException('La serie debe ser B o F seguida de 3 digitos');
        }
        if ($ultimoNumero < 0 || $ultimoNumero > 99999999) {
            throw new InvalidArgumentException('ultimo_numero fuera de rango');
        }
    }

    public function getIdSerie(): int { return $this->idSerie; }
    public function getSerie(): string { return $this->serie; }
    public function getUltimoNumero(): int { return $this->ultimoNumero; }
    public function getIdTienda(): int { return $this->idTienda; }
    public function getIdTipoComprobante(): int { return $this->idTipoComprobante; }

    // tipo_comprobante.letra_serie: B = boleta, F = factura
    public function esFactura(): bool
    {
        return $this->serie[0] === 'F';
    }

    public function nombreComprobante(): string
    {
        return $this->esFactura() ? 'FACTURA DE VENTA' : 'BOLETA DE VENTA';
    }

    // Igual que CONCAT(serie, '-', LPAD(correlativo, 8, '0')) de vw_comprobante
    public function formatear(int $numeroCorrelativo): string
    {
        return $this->serie . '-' . str_pad((string) $numeroCorrelativo, 8, '0', STR_PAD_LEFT);
    }

    // Cada serie avanza de uno en uno
    public function siguienteNumero(): int
    {
        return $this->ultimoNumero + 1;
    }
}
