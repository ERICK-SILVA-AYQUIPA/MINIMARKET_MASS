<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require_once __DIR__ . '/../models/CatalogoRepository.php';
require_once __DIR__ . '/../models/servicios/ProductoServicio.php';

/**
 * C: recibe el pedido, valida la entrada, pide datos al Repository
 * y elige la vista. Aqui no hay HTML ni SQL.
 */
class ProductoController extends Controller
{
    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private ProductoRepository $repo,
        private CatalogoRepository $catalogo,
        private ProductoServicio $servicio
    )
    {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=productos&tienda=1
    public function lista(?string $tiendaPedida): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendas->todas();
        $rutaSelector = 'productos';
        $puedeEditar = $this->usuario?->puedeAdministrarCatalogo() === true;

        $filas = [];
        foreach ($this->repo->todos() as $producto) {
            $filas[] = [
                'producto' => $producto,
                'stock'    => $this->repo->stockEnTienda($producto->getIdProducto(), $tienda->getIdTienda()),
            ];
        }

        $this->vista('productos/lista', get_defined_vars());
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
        $categoria = $this->catalogo->buscarCategoria($producto->getIdCategoria());
        $marca = $this->catalogo->buscarMarca($producto->getIdMarca());
        $unidad = $this->catalogo->buscarUnidad($producto->getIdUnidad());
        $puedeEditar = $this->usuario?->puedeAdministrarCatalogo() === true;
        // El minimo se fija solo en la tienda propia del administrador
        $puedeFijarMinimo = $this->usuario?->puedeAdministrarTienda($tienda->getIdTienda()) === true;
        $this->vista('productos/detalle', get_defined_vars());
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

        $this->vista('productos/stock', get_defined_vars());
    }

    // GET /?ruta=producto_form (nuevo)  o  /?ruta=producto_form&id=8 (editar)
    public function formulario(?string $id, array $viejo = [], ?string $error = null): void
    {
        if ($this->usuario?->puedeAdministrarCatalogo() !== true) {
            $this->prohibido('Solo un administrador cambia el catálogo.');
            return;
        }
        $producto = null;
        if ($id !== null && $id !== '') {
            $producto = $this->repo->buscarPorId($this->leerId($id));
            if ($producto === null) {
                $this->noEncontrado('El producto no existe.');
                return;
            }
        }
        $categorias = $this->catalogo->categorias();
        $marcas = $this->catalogo->marcas();
        $unidades = $this->catalogo->unidades();
        $this->vista('productos/formulario', get_defined_vars());
    }

    // POST /?ruta=producto_guardar: con id edita, sin id crea
    public function guardar(array $post): void
    {
        $id = $this->campo($post, 'id');
        $datos = [];
        foreach (['nombre', 'codigo', 'precio', 'por_peso', 'categoria', 'marca', 'unidad'] as $nombre) {
            $datos[$nombre] = $this->campo($post, $nombre);
        }
        try {
            if ($id === '') {
                $id = (string) $this->servicio->crear($this->usuario, $datos);
                $mensaje = 'Producto creado con stock 0 en las 3 tiendas.';
            } else {
                $this->servicio->editar($this->usuario, $this->leerId($id), $datos);
                $mensaje = 'Producto actualizado.';
            }
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->formulario($id, $post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=producto&id=' . (int) $id, $mensaje);
    }

    // POST /?ruta=producto_estado
    public function cambiarEstado(array $post): void
    {
        $id = $this->leerId($this->campo($post, 'id'));
        $estado = $this->campo($post, 'estado');
        try {
            $this->servicio->cambiarEstado($this->usuario, $id, $estado);
        } catch (ReglaNegocioException $ex) {
            $this->sesion->avisar('error', $ex->getMessage());
            header('Location: /?ruta=producto&id=' . $id, true, 303);
            return;
        }
        $this->redirigir('/?ruta=producto&id=' . $id, $estado === 'ACTIVO' ? 'Producto activado.' : 'Producto descontinuado: ya no se vende.');
    }

    // POST /?ruta=stock_minimo
    public function fijarMinimo(array $post): void
    {
        $id = $this->leerId($this->campo($post, 'id'));
        $volver = '/?ruta=producto&id=' . $id . '&tienda=' . $this->usuario->getIdTienda();
        try {
            $this->servicio->fijarStockMinimo($this->usuario, $id, $this->campo($post, 'minimo'));
        } catch (ReglaNegocioException $ex) {
            $this->sesion->avisar('error', $ex->getMessage());
            header('Location: ' . $volver, true, 303);
            return;
        }
        $this->redirigir($volver, 'Stock mínimo actualizado.');
    }

    // GET /?ruta=catalogo: categorias y marcas
    public function catalogo(?string $error = null): void
    {
        $categorias = $this->catalogo->categorias();
        $marcas = $this->catalogo->marcas();
        $unidades = $this->catalogo->unidades();
        $puedeEditar = $this->usuario?->puedeAdministrarCatalogo() === true;
        $this->vista('productos/catalogo', get_defined_vars());
    }

    // POST /?ruta=categoria_crear
    public function crearCategoria(array $post): void
    {
        try {
            $this->servicio->crearCategoria($this->usuario, $this->campo($post, 'nombre'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->catalogo($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=catalogo', 'Categoría creada.');
    }

    // POST /?ruta=marca_crear
    public function crearMarca(array $post): void
    {
        try {
            $this->servicio->crearMarca($this->usuario, $this->campo($post, 'nombre'), $this->campo($post, 'propia') === 'S');
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->catalogo($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=catalogo', 'Marca creada.');
    }
}
