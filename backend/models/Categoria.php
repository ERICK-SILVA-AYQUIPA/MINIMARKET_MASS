<?php
declare(strict_types=1);

/** Una fila de `categoria` (abarrotes, lacteos, bebidas...). */
final class Categoria
{
    public function __construct(
        private int $idCategoria,          // id_categoria SMALLINT UNSIGNED
        private string $nombreCategoria    // nombre_categoria VARCHAR(50), unico
    ) {}

    public function getIdCategoria(): int { return $this->idCategoria; }
    public function getNombreCategoria(): string { return $this->nombreCategoria; }
}
