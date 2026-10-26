<?php
declare(strict_types=1);

/**
 * Cabecera de `traslado_mercaderia`: mercaderia que pasa de una tienda a otra.
 * Las cantidades estan en el kardex (TRASLADO DE SALIDA y TRASLADO DE ENTRADA).
 */
class TrasladoMercaderia
{
    public function __construct(
        private int $idTraslado,          // id_traslado INT UNSIGNED
        private string $fechaHora,        // fecha_hora DATETIME
        private ?string $observacion,     // observacion VARCHAR(200) NULL
        private int $idTiendaOrigen,
        private int $idTiendaDestino,
        private int $idEmpleado           // quien lo registra
    ) {
        if ($idTiendaOrigen === $idTiendaDestino) {
            throw new InvalidArgumentException('El origen y el destino deben ser tiendas distintas');
        }
    }

    public function getIdTraslado(): int { return $this->idTraslado; }
    public function getFechaHora(): string { return $this->fechaHora; }
    public function getObservacion(): ?string { return $this->observacion; }
    public function getIdTiendaOrigen(): int { return $this->idTiendaOrigen; }
    public function getIdTiendaDestino(): int { return $this->idTiendaDestino; }
    public function getIdEmpleado(): int { return $this->idEmpleado; }
}
