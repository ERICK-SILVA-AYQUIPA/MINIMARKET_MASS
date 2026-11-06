<?php
declare(strict_types=1);

require_once __DIR__ . '/Dinero.php';
require_once __DIR__ . '/Cantidad.php';

/**
 * Una fila de la tabla `promocion` (migracion 020). La crea el administrador y se aplica sola al vender.
 * Montos en centimos y porcentajes en centesimas (1500 = 15.00 %), como Dinero: sin errores de redondeo.
 *   PORCENTAJE  un producto con X % menos
 *   PRECIO      un producto a precio de oferta (por unidad o por kg)
 *   PACK        N unidades de un producto por un precio fijo (3x2, 3 por S/ 10...)
 *   VENTA       X % a toda la compra desde un monto minimo
 */
class Promocion
{
    public const TIPOS = ['PORCENTAJE', 'PRECIO', 'PACK', 'VENTA'];
    public const PORCENTAJE_MAXIMO = 9000; // 90.00 %: una promocion nunca regala el producto

    public function __construct(
        private int $idPromocion,
        private string $nombre,
        private string $tipo,
        private ?int $idProducto,      // NULL solo en VENTA
        private ?int $idTienda,        // NULL = toda la cadena
        private ?int $porcentaje,      // centesimas: PORCENTAJE y VENTA
        private ?int $precio,          // centimos: PRECIO (por unidad o kg) y PACK (el pack entero)
        private ?int $cantidadPack,    // PACK
        private ?int $montoMinimo,     // centimos: VENTA
        private string $fechaInicio,   // AAAA-MM-DD
        private string $fechaFin,      // AAAA-MM-DD (inclusive)
        private string $estado,        // ACTIVA o PAUSADA
        private int $idEmpleado = 0,
        private ?string $fechaRegistro = null
    ) {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new InvalidArgumentException('Tipo de promoción desconocido: ' . $tipo);
        }
        if (!in_array($estado, ['ACTIVA', 'PAUSADA'], true)) {
            throw new InvalidArgumentException("estado debe ser 'ACTIVA' o 'PAUSADA'");
        }
        $conPorcentaje = $porcentaje !== null && $porcentaje > 0 && $porcentaje <= self::PORCENTAJE_MAXIMO;
        $valida = match ($tipo) {
            'PORCENTAJE' => $idProducto !== null && $conPorcentaje,
            'PRECIO'     => $idProducto !== null && $precio !== null && $precio > 0,
            'PACK'       => $idProducto !== null && $precio !== null && $precio > 0 && $cantidadPack !== null && $cantidadPack >= 2 && $cantidadPack <= 99,
            'VENTA'      => $idProducto === null && $conPorcentaje && $montoMinimo !== null && $montoMinimo >= 0,
        };
        if (!$valida) {
            throw new InvalidArgumentException('Los datos no corresponden a una promoción ' . $tipo);
        }
        if ($fechaFin < $fechaInicio) {
            throw new InvalidArgumentException('La promoción termina antes de empezar');
        }
    }

    public function getIdPromocion(): int { return $this->idPromocion; }
    public function getNombre(): string { return $this->nombre; }
    public function getTipo(): string { return $this->tipo; }
    public function getIdProducto(): ?int { return $this->idProducto; }
    public function getIdTienda(): ?int { return $this->idTienda; }
    public function getPorcentaje(): ?int { return $this->porcentaje; }
    public function getPrecio(): ?int { return $this->precio; }
    public function getCantidadPack(): ?int { return $this->cantidadPack; }
    public function getMontoMinimo(): ?int { return $this->montoMinimo; }
    public function getFechaInicio(): string { return $this->fechaInicio; }
    public function getFechaFin(): string { return $this->fechaFin; }
    public function getEstado(): string { return $this->estado; }
    public function getIdEmpleado(): int { return $this->idEmpleado; }
    public function getFechaRegistro(): ?string { return $this->fechaRegistro; }

    public function esDeVenta(): bool
    {
        return $this->tipo === 'VENTA';
    }

    public function estaPausada(): bool
    {
        return $this->estado === 'PAUSADA';
    }

    // Rige ese dia (AAAA-MM-DD) en esa tienda
    public function rigeEn(string $dia, int $idTienda): bool
    {
        return !$this->estaPausada() && $dia >= $this->fechaInicio && $dia <= $this->fechaFin
            && ($this->idTienda === null || $this->idTienda === $idTienda);
    }

    // VIGENTE, PROGRAMADA (aun no empieza), VENCIDA o PAUSADA, para la lista del administrador
    public function situacion(string $hoy): string
    {
        return match (true) {
            $this->estaPausada()      => 'PAUSADA',
            $hoy < $this->fechaInicio => 'PROGRAMADA',
            $hoy > $this->fechaFin    => 'VENCIDA',
            default                   => 'VIGENTE',
        };
    }

    /**
     * Cuanto descuenta esta promocion de producto en una linea, en centimos (0 si no le conviene al cliente).
     * El importe de la linea es Cantidad::importe($milesimas, $precio), igual que el CHECK de detalle_venta.
     */
    public function descuentoLinea(int $milesimas, int $precio, bool $alPeso): int
    {
        $importe = Cantidad::importe($milesimas, $precio);
        $descuento = match ($this->tipo) {
            'PORCENTAJE' => self::porcentajeDe($importe, (int) $this->porcentaje),
            'PRECIO'     => $this->precio < $precio ? $importe - Cantidad::importe($milesimas, (int) $this->precio) : 0,
            'PACK'       => $this->descuentoPack($milesimas, $precio, $alPeso),
            default      => 0,   // VENTA: se descuenta a toda la compra, no por linea
        };
        return max(0, min($descuento, $importe));
    }

    // Cuanto descuenta una promocion VENTA a una compra de $subtotal centimos (0 si no llega al minimo)
    public function descuentoVenta(int $subtotal): int
    {
        if (!$this->esDeVenta() || $subtotal <= 0 || $subtotal < (int) $this->montoMinimo) {
            return 0;
        }
        return self::porcentajeDe($subtotal, (int) $this->porcentaje);
    }

    // Packs completos al precio del pack; lo que sobra va a precio normal. Solo productos por unidad.
    private function descuentoPack(int $milesimas, int $precio, bool $alPeso): int
    {
        if ($alPeso || $milesimas % 1000 !== 0) {
            return 0;
        }
        $packs = intdiv(intdiv($milesimas, 1000), (int) $this->cantidadPack);
        $normal = Cantidad::importe($packs * (int) $this->cantidadPack * 1000, $precio);
        return max(0, $normal - $packs * (int) $this->precio);
    }

    // $centesimas % de $centimos, redondeando la mitad hacia arriba (como ROUND de MySQL)
    public static function porcentajeDe(int $centimos, int $centesimas): int
    {
        return intdiv($centimos * $centesimas + 5000, 10000);
    }

    // "15 %", "15.50 %"
    public static function porcentajeATexto(int $centesimas): string
    {
        return rtrim(rtrim(number_format($centesimas / 100, 2, '.', ''), '0'), '.') . ' %';
    }

    // Lo que dice el cartel: "15 % menos", "S/ 2.90 c/u", "3 x S/ 10.00", "5 % desde S/ 100.00"
    public function descripcion(bool $alPeso = false): string
    {
        return match ($this->tipo) {
            'PORCENTAJE' => self::porcentajeATexto((int) $this->porcentaje) . ' menos',
            'PRECIO'     => 'S/ ' . Dinero::aTexto((int) $this->precio) . ($alPeso ? ' el kg' : ' c/u'),
            'PACK'       => $this->cantidadPack . ' x S/ ' . Dinero::aTexto((int) $this->precio),
            default      => self::porcentajeATexto((int) $this->porcentaje) . ' a toda la compra'
                . ((int) $this->montoMinimo > 0 ? ' desde S/ ' . Dinero::aTexto((int) $this->montoMinimo) : ''),
        };
    }
}
