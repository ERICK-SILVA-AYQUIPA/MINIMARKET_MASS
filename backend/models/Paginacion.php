<?php
declare(strict_types=1);

/**
 * Parte una lista larga en paginas: cuantas filas saltar (OFFSET) y cuantas traer (LIMIT).
 * La pagina pedida se corrige si esta fuera de rango (?pagina=0 o ?pagina=9999).
 */
final class Paginacion
{
    private int $pagina;

    public function __construct(private int $totalFilas, ?string $paginaPedida, private int $porPagina = 25)
    {
        $pedida = $paginaPedida !== null && preg_match('/^[0-9]{1,6}$/', $paginaPedida) === 1 ? (int) $paginaPedida : 1;
        $this->pagina = max(1, min($pedida, $this->totalPaginas()));
    }

    public function getPagina(): int { return $this->pagina; }
    public function getPorPagina(): int { return $this->porPagina; }
    public function getTotalFilas(): int { return $this->totalFilas; }

    public function totalPaginas(): int
    {
        return max(1, (int) ceil($this->totalFilas / $this->porPagina));
    }

    public function offset(): int
    {
        return ($this->pagina - 1) * $this->porPagina;
    }

    public function hayAnterior(): bool
    {
        return $this->pagina > 1;
    }

    public function haySiguiente(): bool
    {
        return $this->pagina < $this->totalPaginas();
    }
}
