<?php
declare(strict_types=1);

/**
 * Subtotal (operacion gravada), IGV y total de un comprobante, en centimos enteros.
 *
 * En la tienda el precio de venta YA incluye el IGV. Por cada linea:
 *   valor de venta = importe / (1 + tasa), redondeado al centimo;  IGV = importe - valor de venta.
 * Se calcula linea por linea (como lo pide el formato de SUNAT) y se suma: asi el ticket y lo que se
 * enviaria al proveedor OSE/PSE dan los mismos centimos, y gravada + IGV siempre es igual al total.
 *
 * [Contador] Hoy todo se trata como gravado al 18 % (IGV 16 % + IPM 2 %). Los productos exonerados
 * (por ejemplo, frutas y verduras frescas del Apendice I de la Ley del IGV) necesitarian una marca
 * por producto: confirmarlo con el contador antes de emitir comprobantes electronicos.
 */
final class DesgloseIgv
{
    public const TASA_PORCENTAJE = 18;

    private function __construct(
        private int $gravada,
        private int $igv,
        private int $total
    ) {
    }

    /** @param int[] $importesCentimos importe de cada linea, con IGV incluido */
    public static function deLineas(array $importesCentimos, int $tasaPorcentaje = self::TASA_PORCENTAJE): self
    {
        $gravada = 0;
        $total = 0;
        foreach ($importesCentimos as $importe) {
            if ($importe < 0) {
                throw new InvalidArgumentException('El importe de una linea no puede ser negativo');
            }
            $gravada += self::valorSinIgv($importe, $tasaPorcentaje);
            $total += $importe;
        }
        return new self($gravada, $total - $gravada, $total);
    }

    // Importe con IGV -> valor sin IGV, redondeado al centimo (mitad hacia arriba) sin pasar por float
    public static function valorSinIgv(int $importeCentimos, int $tasaPorcentaje = self::TASA_PORCENTAJE): int
    {
        $divisor = 100 + $tasaPorcentaje;
        return intdiv($importeCentimos * 100 * 2 + $divisor, 2 * $divisor);
    }

    public function getGravada(): int { return $this->gravada; }
    public function getIgv(): int { return $this->igv; }
    public function getTotal(): int { return $this->total; }
}
