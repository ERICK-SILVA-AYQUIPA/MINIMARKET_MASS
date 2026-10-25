<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/TiendaRepository.php';

/**
 * Lo comun a todos los controladores: leer la tienda pedida y responder 404.
 * Es abstract: no se usa solo, cada controlador hereda de el.
 */
abstract class Controller
{
    protected const TIENDA_POR_DEFECTO = 1; // Mass Cayma

    public function __construct(protected TiendaRepository $tiendas) {}

    public function noEncontrado(string $mensaje = 'La pagina no existe.'): void
    {
        http_response_code(404);
        require __DIR__ . '/../views/errores/404.php';
    }

    // Solo acepta un id de tienda que exista; si no, usa la tienda por defecto
    protected function leerTienda(?string $valor): Tienda
    {
        if ($valor !== null && ctype_digit($valor)) {
            $tienda = $this->tiendas->buscarPorId((int) $valor);
            if ($tienda !== null) {
                return $tienda;
            }
        }
        return $this->tiendas->buscarPorId(self::TIENDA_POR_DEFECTO);
    }
}
