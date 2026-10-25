<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/ProductoRepository.php';

/**
 * C: recibe el pedido, valida la entrada, pide datos al Repository
 * y elige la vista. Aqui no hay HTML ni SQL.
 */
class ProductoController extends Controller
{
    public function __construct(TiendaRepository $tiendas, private ProductoRepository $repo)
    {
        parent::__construct($tiendas);
    }

    // GET /?ruta=productos&tienda=1
    public function lista(?string $tiendaPedida): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendas->todas();
        $rutaSelector = 'productos';

        $filas = [];
        foreach ($this->repo->todos() as $producto) {
            $filas[] = [
                'producto' => $producto,
                'stock'    => $this->repo->stockEnTienda($producto->getIdProducto(), $tienda->getIdTienda()),
            ];
        }

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

        $tienda = $this->leerTienda($tiendaPedida);
        $stock = $this->repo->stockEnTienda($producto->getIdProducto(), $tienda->getIdTienda());
        require __DIR__ . '/../views/productos/detalle.php';
    }

    // GET /?ruta=stock: el stock de cada producto en las 3 tiendas, lado a lado
    public function stockTiendas(): void
    {
        $tiendas = $this->tiendas->todas();

        $filas = [];
        $porReponer = 0;
        foreach ($this->repo->todos() as $producto) {
            $stocks = [];
            foreach ($tiendas as $idTienda => $tienda) {
                $stock = $this->repo->stockEnTienda($producto->getIdProducto(), $idTienda);
                $stocks[$idTienda] = $stock;
                if ($stock !== null && $stock->necesitaReponer()) {
                    $porReponer++;
                }
            }
            $filas[] = ['producto' => $producto, 'stocks' => $stocks];
        }

        require __DIR__ . '/../views/productos/stock.php';
    }
}
