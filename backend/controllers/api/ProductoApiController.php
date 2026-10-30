<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../../models/ConsultaMovilRepository.php';

/**
 * Inventario de la app: buscar productos, leer un codigo de barras y ver el kardex.
 * Solo lectura; el stock que se muestra es el de la tienda del usuario.
 */
class ProductoApiController extends ApiController
{
    private const POR_PAGINA = 40;

    public function __construct(?Usuario $usuario, private ConsultaMovilRepository $consultas)
    {
        parent::__construct($usuario);
    }

    // GET /api/v1/productos?q=leche&filtro=bajo&pagina=1
    public function lista(?string $texto, ?string $filtro, ?string $pagina): array
    {
        $idTienda = $this->usuario()->getIdTienda();
        $texto = mb_substr(trim((string) $texto), 0, 60);
        $filtro = in_array($filtro, ['agotados', 'bajo'], true) ? $filtro : 'todos';
        $numero = $this->pagina($pagina);
        $total = $this->consultas->contarProductos($idTienda, $texto, $filtro);
        return [
            'productos' => $this->consultas->productos($idTienda, $texto, $filtro, self::POR_PAGINA, ($numero - 1) * self::POR_PAGINA),
            'pagina'    => $numero,
            'total'     => $total,
            'hay_mas'   => $numero * self::POR_PAGINA < $total,
        ];
    }

    // GET /api/v1/productos/codigo/7750000000011: lo que lee el escaner
    public function porCodigo(string $codigo): array
    {
        $producto = $this->consultas->productoPorCodigo(trim($codigo), $this->usuario()->getIdTienda());
        if ($producto === null) {
            // No se inventa nada: el producto se registra en la pagina web (Catálogo)
            $this->noEncontrado('El código ' . preg_replace('/[^0-9A-Za-z-]/', '', mb_substr($codigo, 0, 30))
                . ' no está registrado en el catálogo.');
        }
        return $producto;
    }

    // GET /api/v1/productos/12: ficha con stock y sus ultimos movimientos en la tienda
    public function detalle(string $id): array
    {
        $idTienda = $this->usuario()->getIdTienda();
        $producto = $this->consultas->productoPorId($this->id($id), $idTienda);
        if ($producto === null) {
            $this->noEncontrado('El producto no existe.');
        }
        return $producto + ['movimientos' => $this->consultas->kardex($idTienda, $producto['id_producto'], 15, 0)];
    }

    // GET /api/v1/movimientos?producto=12&pagina=1: kardex de la tienda del usuario
    public function movimientos(?string $producto, ?string $pagina): array
    {
        $numero = $this->pagina($pagina);
        $idProducto = $producto !== null && $this->id($producto) > 0 ? $this->id($producto) : null;
        return [
            'movimientos' => $this->consultas->kardex($this->usuario()->getIdTienda(), $idProducto, 50, ($numero - 1) * 50),
            'pagina'      => $numero,
        ];
    }
}
