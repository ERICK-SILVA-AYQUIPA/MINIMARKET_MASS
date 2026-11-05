<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Entorno.php';

/**
 * Ancho del papel de la ticketera termica: 58 mm o 80 mm.
 * Por defecto el de TICKET_ANCHO_MM en .env (80 si falta); cada caja puede elegir el suyo en la pagina del ticket
 * (?ancho=58), y el navegador de esa caja lo recuerda (js/ticket.js).
 */
final class AnchoTicket
{
    public const ANCHOS = [58, 80];

    private function __construct(private int $papelMm)
    {
    }

    // $pedido: el ?ancho= de la pagina; si no es 58 ni 80 se usa el de .env
    public static function elegir(?string $pedido): self
    {
        foreach ([$pedido, Entorno::leer('TICKET_ANCHO_MM', '80')] as $valor) {
            if ($valor !== null && in_array((int) $valor, self::ANCHOS, true) && ctype_digit(trim($valor))) {
                return new self((int) $valor);
            }
        }
        return new self(80);
    }

    public function getPapelMm(): int { return $this->papelMm; }

    // Lo que imprime la cabeza termica: 48 mm en papel de 58 y 72 mm en papel de 80
    public function getImprimibleMm(): int
    {
        return $this->papelMm === 58 ? 48 : 72;
    }

    // Letra en px: 32 caracteres por linea en 58 mm y 42 en 80 mm, como una ticketera comun
    public function getLetraPx(): int
    {
        return $this->papelMm === 58 ? 10 : 12;
    }
}
