<?php
declare(strict_types=1);

/** Una fila de `unidad_medida`: unidad, kilogramo, litro. */
final class UnidadMedida
{
    public function __construct(
        private int $idUnidad,          // id_unidad TINYINT UNSIGNED
        private string $abreviatura,    // abreviatura VARCHAR(5): und, kg, lt
        private string $nombreUnidad    // nombre_unidad VARCHAR(30)
    ) {}

    public function getIdUnidad(): int { return $this->idUnidad; }
    public function getAbreviatura(): string { return $this->abreviatura; }
    public function getNombreUnidad(): string { return $this->nombreUnidad; }
}
