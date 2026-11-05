<?php
declare(strict_types=1);

/**
 * Cabecera de `orden_compra` (migraciones/015): lo que una tienda le pide a un proveedor.
 * La orden no mueve stock; lo mueve cada ingreso de mercaderia que se recibe contra ella.
 */
class OrdenCompra
{
    public const ESTADOS = ['PENDIENTE', 'PARCIAL', 'RECIBIDA', 'ANULADA', 'CERRADA'];
    // Las que todavia esperan mercaderia
    public const ABIERTAS = ['PENDIENTE', 'PARCIAL'];

    public function __construct(
        private int $idOrdenCompra,
        private string $fechaHora,           // cuando se emitio
        private ?string $fechaEntrega,       // cuando deberia llegar
        private string $estado,
        private float $total,                // al costo pactado
        private ?string $observacion,
        private int $idProveedor,
        private int $idTienda,
        private int $idEmpleado,
        private ?string $fechaCierre = null,
        private ?int $idEmpleadoCierre = null,
        private ?string $motivoCierre = null
    ) {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new InvalidArgumentException('Estado de la orden no valido');
        }
        if ($total < 0) {
            throw new InvalidArgumentException('Total negativo');
        }
    }

    public function getIdOrdenCompra(): int { return $this->idOrdenCompra; }
    public function getFechaHora(): string { return $this->fechaHora; }
    public function getFechaEntrega(): ?string { return $this->fechaEntrega; }
    public function getEstado(): string { return $this->estado; }
    public function getTotal(): float { return $this->total; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function getIdProveedor(): int { return $this->idProveedor; }
    public function getIdTienda(): int { return $this->idTienda; }
    public function getIdEmpleado(): int { return $this->idEmpleado; }
    public function getFechaCierre(): ?string { return $this->fechaCierre; }
    public function getIdEmpleadoCierre(): ?int { return $this->idEmpleadoCierre; }
    public function getMotivoCierre(): ?string { return $this->motivoCierre; }

    // "OC-000012": el numero que se le dice al proveedor
    public function numero(): string
    {
        return self::formatear($this->idOrdenCompra);
    }

    public static function formatear(int $idOrdenCompra): string
    {
        return sprintf('OC-%06d', $idOrdenCompra);
    }

    // Todavia puede recibir mercaderia (y cancelarse)
    public function estaAbierta(): bool
    {
        return in_array($this->estado, self::ABIERTAS, true);
    }

    // Abierta y con la fecha de entrega ya pasada
    public function estaAtrasada(string $hoy): bool
    {
        return $this->estaAbierta() && $this->fechaEntrega !== null && $this->fechaEntrega < $hoy;
    }

    /**
     * El estado que corresponde a lo recibido, en milesimas: nada -> PENDIENTE, una parte -> PARCIAL, todo -> RECIBIDA.
     * @param array<int, array{pedida: int, recibida: int}> $lineas
     */
    public static function estadoPorRecepcion(array $lineas): string
    {
        $recibido = 0;
        $completas = 0;
        foreach ($lineas as $l) {
            $recibido += $l['recibida'];
            if ($l['recibida'] >= $l['pedida']) {
                $completas++;
            }
        }
        if ($lineas !== [] && $completas === count($lineas)) {
            return 'RECIBIDA';
        }
        return $recibido > 0 ? 'PARCIAL' : 'PENDIENTE';
    }
}
