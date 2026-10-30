<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../../models/ConsultaMovilRepository.php';
require_once __DIR__ . '/../../models/CatalogoRepository.php';
require_once __DIR__ . '/../../models/servicios/ProductoServicio.php';

/**
 * Inventario de la app: buscar productos, leer un codigo de barras y ver el kardex.
 * El stock que se muestra es el de la tienda del usuario.
 * Si el codigo escaneado no existe, el almacenero o el administrador pueden registrarlo (crear()).
 */
class ProductoApiController extends ApiController
{
    private const POR_PAGINA = 40;

    public function __construct(
        ?Usuario $usuario,
        private ConsultaMovilRepository $consultas,
        private CatalogoRepository $catalogo,
        private ProductoServicio $servicio
    ) {
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
            // No se inventa nada: lo registra quien puede (permisos.crear_producto) o se avisa al almacenero
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

    // GET /api/v1/catalogo: las listas del formulario de producto nuevo (sin la unidad kg: desde la app es por unidad)
    public function opciones(): array
    {
        if (!$this->usuario()->puedeCrearProducto()) {
            $this->prohibido('Solo el almacenero o el administrador registran un producto nuevo.');
        }
        $unidades = array_filter($this->catalogo->unidades(), static fn(UnidadMedida $u): bool => $u->getAbreviatura() !== 'kg');
        return [
            'categorias' => array_values(array_map(static fn(Categoria $c): array => ['id' => $c->getIdCategoria(), 'nombre' => $c->getNombreCategoria()], $this->catalogo->categorias())),
            'marcas'     => array_values(array_map(static fn(Marca $m): array => ['id' => $m->getIdMarca(), 'nombre' => $m->getNombreMarca()], $this->catalogo->marcas())),
            'unidades'   => array_values(array_map(static fn(UnidadMedida $u): array => ['id' => $u->getIdUnidad(), 'nombre' => $u->getNombreUnidad(), 'abreviatura' => $u->getAbreviatura()], $unidades)),
        ];
    }

    /**
     * POST /api/v1/productos: registra el producto del codigo escaneado (stock 0 en todas las tiendas)
     * {"codigo": "7750000000999", "nombre": "...", "precio": "4.50", "categoria": 1, "marca": 3, "unidad": 1}
     * o, con una marca nueva, "marca_nombre": "Gloria" en vez de "marca".
     * Si el codigo ya existe (otro lo registro, o es un reintento) responde 409 YA_EXISTE: la app lo vuelve a buscar.
     */
    public function crear(array $datos): array
    {
        $usuario = $this->usuario();
        if (!$usuario->puedeCrearProducto()) {
            $this->prohibido('Solo el almacenero o el administrador registran un producto nuevo. Avísale al almacenero.');
        }
        $campos = [];
        foreach (['codigo', 'nombre', 'precio', 'categoria', 'marca', 'marca_nombre', 'unidad'] as $clave) {
            $campos[$clave] = trim($this->texto($datos, $clave));
        }
        if ($campos['codigo'] !== '' && $this->consultas->productoPorCodigo($campos['codigo'], $usuario->getIdTienda()) !== null) {
            throw new ErrorApi(409, 'YA_EXISTE', 'Ese código ya está registrado en el catálogo.');
        }
        try {
            $id = $this->servicio->crearAlEscanear($usuario, $campos);
        } catch (ReglaNegocioException $ex) {
            // Otro celular lo registro en el mismo instante
            if (str_starts_with($ex->getMessage(), 'Ya hay un producto con ese código')) {
                throw new ErrorApi(409, 'YA_EXISTE', 'Ese código ya está registrado en el catálogo.');
            }
            throw $ex;
        }
        return (array) $this->consultas->productoPorId($id, $usuario->getIdTienda());
    }
}
