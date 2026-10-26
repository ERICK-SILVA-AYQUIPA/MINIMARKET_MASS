<?php
declare(strict_types=1);

/** Una fila de `marca`. */
final class Marca
{
    public function __construct(
        private int $idMarca,            // id_marca SMALLINT UNSIGNED
        private string $nombreMarca,     // nombre_marca VARCHAR(50), unico
        private string $esMarcaPropia    // es_marca_propia CHAR(1): 'S' o 'N'
    ) {
        if (!in_array($esMarcaPropia, ['S', 'N'], true)) {
            throw new InvalidArgumentException("es_marca_propia debe ser 'S' o 'N'");
        }
    }

    public function getIdMarca(): int { return $this->idMarca; }
    public function getNombreMarca(): string { return $this->nombreMarca; }
    public function esMarcaPropia(): bool { return $this->esMarcaPropia === 'S'; }
}
