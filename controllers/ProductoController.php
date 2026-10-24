<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ProductoRepository.php';

/**
 * C: recibe el pedido, valida la entrada, pide datos al Repository
 * y elige la vista. Aqui no hay HTML ni SQL.
 */
class ProductoController
{
    private const TIENDA_POR_DEFECTO = 1; // Mass Cayma

    public function __construct(private ProductoRepository $repo) {}

    // GET /?ruta=productos&tienda=1
    public function lista(?string $tiendaPedida): void
    {
        $tiendas = $this->repo->tiendas();
        $idTienda = $this->leerTienda($tiendaPedida, $tiendas);

        $filas = [];
        foreach ($this->repo->todos() as $producto) {
            $filas[] = [
                'producto' => $producto,
                'stock'    => $this->repo->stockEnTienda($producto->getIdProducto(), $idTienda),
            ];
        }

        $nombreTienda = $tiendas[$idTienda];
        require __DIR__ . '/../views/productos/lista.php';
    }

    // GET /?ruta=producto&codigo=7750000000011  o  /?ruta=producto&id=8
    public function detalle(?string $codigo, ?string $id, ?string $tiendaPedida): void
    {
        $producto = null;
        if ($codigo !== null && preg_match('/^[0-9]{8,14}$/', $codigo) === 1) {
            $producto = $this->repo->buscarPorCodigo($codigo);
        } elseif ($id !== null && ctype_digit($id)) {
            $producto = $this->repo->buscarPorId((int) $id);
        }

        if ($producto === null) {
            $this->noEncontrado('El producto no existe.');
            return;
        }

        $tiendas = $this->repo->tiendas();
        $idTienda = $this->leerTienda($tiendaPedida, $tiendas);
        $stock = $this->repo->stockEnTienda($producto->getIdProducto(), $idTienda);
        $nombreTienda = $tiendas[$idTienda];
        require __DIR__ . '/../views/productos/detalle.php';
    }

    public function noEncontrado(string $mensaje = 'La pagina no existe.'): void
    {
        http_response_code(404);
        require __DIR__ . '/../views/errores/404.php';
    }

    // Solo acepta un id de tienda que exista; si no, usa la tienda por defecto
    private function leerTienda(?string $valor, array $tiendas): int
    {
        if ($valor !== null && ctype_digit($valor) && isset($tiendas[(int) $valor])) {
            return (int) $valor;
        }
        return self::TIENDA_POR_DEFECTO;
    }
}
