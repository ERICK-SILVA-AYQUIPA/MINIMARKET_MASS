<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/CatalogoCsv.php';
require_once __DIR__ . '/../models/FotoProducto.php';
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
        private ProductoServicio $servicio,
        private FotoProducto $fotos = new FotoProducto()
    )
    {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    /**
     * GET /?ruta=productos&tienda=1  (con &ver=8 abre la ficha del producto en el panel lateral)
     * &vista=tabla muestra la tabla en vez de las tarjetas; &categoria=2 deja solo esa categoria.
     */
    public function lista(?string $tiendaPedida, ?string $ver = null, ?string $vistaPedida = null, ?string $categoriaPedida = null): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendas->todas();
        $rutaSelector = 'productos';
        $puedeEditar = $this->puedeEditarCatalogo();
        $vista = $vistaPedida === 'tabla' ? 'tabla' : 'tarjetas';

        $categorias = [];
        foreach ($this->catalogo->categorias() as $id => $c) {
            $categorias[$id] = $c->getNombreCategoria();
        }
        $marcas = [];
        foreach ($this->catalogo->marcas() as $id => $m) {
            $marcas[$id] = $m->getNombreMarca();
        }
        $categoriaElegida = $categoriaPedida !== null && ctype_digit($categoriaPedida) && isset($categorias[(int) $categoriaPedida])
            ? (int) $categoriaPedida : null;

        $filas = [];
        $porCategoria = [];
        foreach ($this->repo->todos() as $producto) {
            $idCategoria = $producto->getIdCategoria();
            $porCategoria[$idCategoria] = ($porCategoria[$idCategoria] ?? 0) + 1;
            if ($categoriaElegida !== null && $idCategoria !== $categoriaElegida) {
                continue;
            }
            $filas[] = [
                'producto' => $producto,
                'stock'    => $this->repo->stockEnTienda($producto->getIdProducto(), $tienda->getIdTienda()),
                'foto'     => $this->fotos->datos($producto->getIdProducto(), $categorias[$idCategoria] ?? ''),
                'marca'    => $marcas[$producto->getIdMarca()] ?? '',
            ];
        }

        // Ficha en el panel lateral; si el id no existe, solo se muestra la lista
        $ficha = null;
        if ($ver !== null && preg_match('/^[1-9][0-9]{0,9}$/', $ver) === 1) {
            $elegido = $this->repo->buscarPorId((int) $ver);
            $ficha = $elegido !== null ? $this->datosFicha($elegido, $tienda) : null;
        }
        unset($producto, $elegido, $id, $c, $m, $idCategoria, $marcas);

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
        $this->vista('productos/detalle', $this->datosFicha($producto, $tienda));
    }

    /**
     * Lo que muestra la ficha de un producto (pagina propia o panel lateral del catalogo).
     * @return array<string, mixed>
     */
    private function datosFicha(Producto $producto, Tienda $tienda): array
    {
        $categoria = $this->catalogo->buscarCategoria($producto->getIdCategoria());
        return [
            'producto'         => $producto,
            'tienda'           => $tienda,
            'stock'            => $this->repo->stockEnTienda($producto->getIdProducto(), $tienda->getIdTienda()),
            'categoria'        => $categoria,
            'foto'             => $this->fotos->datos($producto->getIdProducto(), $categoria?->getNombreCategoria() ?? ''),
            'marca'            => $this->catalogo->buscarMarca($producto->getIdMarca()),
            'unidad'           => $this->catalogo->buscarUnidad($producto->getIdUnidad()),
            'puedeEditar'      => $this->puedeEditarCatalogo(),
            // El minimo se fija solo en la tienda propia del administrador
            'puedeFijarMinimo' => $this->usuario?->puedeAdministrarTienda($tienda->getIdTienda()) === true,
        ];
    }

    // GET /?ruta=stock: el stock de cada producto en todas las tiendas, lado a lado
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

    // GET /?ruta=stock_csv: la misma tabla de stock por tienda, para Excel
    public function stockCsv(): void
    {
        $tiendas = $this->tiendas->todas();
        $encabezado = ['id', 'producto', 'codigo_barras', 'precio', 'estado'];
        foreach ($tiendas as $tienda) {
            $encabezado[] = $tienda->getNombreTienda();
            $encabezado[] = $tienda->getNombreTienda() . ' (minimo)';
        }
        $filas = [];
        foreach ($this->repo->todos() as $producto) {
            $fila = [$producto->getIdProducto(), $producto->getNombreProducto(), $producto->getCodigoBarras() ?? '',
                number_format($producto->getPrecioVenta(), 2, '.', ''), $producto->getEstado()];
            foreach ($tiendas as $idTienda => $tienda) {
                $stock = $this->repo->stockEnTienda($producto->getIdProducto(), $idTienda);
                $fila[] = $stock === null ? '' : number_format($stock->getCantidadActual(), 3, '.', '');
                $fila[] = $stock === null ? '' : number_format($stock->getStockMinimo(), 3, '.', '');
            }
            $filas[] = $fila;
        }
        $this->enviarCsv('stock_' . date('Y-m-d') . '.csv', $encabezado, $filas);
    }

    // GET /?ruta=producto_form (nuevo)  o  /?ruta=producto_form&id=8 (editar)
    public function formulario(?string $id, array $viejo = [], ?string $error = null): void
    {
        if (!$this->puedeEditarCatalogo()) {
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
        if (!$this->puedeEditarCatalogo()) {
            $this->prohibido('Solo un administrador cambia el catálogo.');
            return;
        }
        $id = $this->campo($post, 'id');
        $datos = [];
        foreach (['nombre', 'codigo', 'precio', 'por_peso', 'categoria', 'marca', 'unidad'] as $nombre) {
            $datos[$nombre] = $this->campo($post, $nombre);
        }
        try {
            if ($id === '') {
                $id = (string) $this->servicio->crear($this->usuario, $datos);
                $mensaje = 'Producto creado con stock 0 en todas las tiendas.';
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
        if (!$this->puedeEditarCatalogo()) {
            $this->prohibido('Solo un administrador cambia el catálogo.');
            return;
        }
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

    /**
     * POST /?ruta=producto_foto con la foto (de $_FILES, que solo lee public/index.php), o con quitar=1.
     * @param ?array{error?: int, size?: int, tmp_name?: string} $archivo
     */
    public function cambiarFoto(array $post, ?array $archivo): void
    {
        if (!$this->puedeEditarCatalogo()) {
            $this->prohibido('Solo un administrador cambia el catálogo.');
            return;
        }
        $id = $this->leerId($this->campo($post, 'id'));
        $volver = '/?ruta=productos&ver=' . $id;
        $quitar = $this->campo($post, 'quitar') === '1';
        $contenido = null;
        if (!$quitar) {
            if ($archivo === null || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
                || !is_string($archivo['tmp_name'] ?? null) || !is_uploaded_file($archivo['tmp_name'])
                || (int) ($archivo['size'] ?? 0) > FotoProducto::MAXIMO_BYTES) {
                $this->sesion->avisar('error', 'Elige una foto JPG, PNG o WebP de hasta 2 MB.');
                header('Location: ' . $volver, true, 303);
                return;
            }
            $contenido = (string) file_get_contents($archivo['tmp_name']);
        }
        try {
            $this->servicio->cambiarFoto($this->usuario, $id, $contenido, $this->fotos);
        } catch (ReglaNegocioException $ex) {
            $this->sesion->avisar('error', $ex->getMessage());
            header('Location: ' . $volver, true, 303);
            return;
        }
        $this->redirigir($volver, $quitar ? 'Foto quitada.' : 'Foto guardada.');
    }

    // POST /?ruta=stock_minimo
    public function fijarMinimo(array $post): void
    {
        if ($this->usuario?->puedeAdministrarTienda($this->usuario->getIdTienda()) !== true) {
            $this->prohibido('Solo el administrador de la tienda fija el stock mínimo.');
            return;
        }
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
        $puedeEditar = $this->puedeEditarCatalogo();
        $this->vista('productos/catalogo', get_defined_vars());
    }

    // POST /?ruta=categoria_crear
    public function crearCategoria(array $post): void
    {
        if (!$this->puedeEditarCatalogo()) {
            $this->prohibido('Solo un administrador cambia el catálogo.');
            return;
        }
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
        if (!$this->puedeEditarCatalogo()) {
            $this->prohibido('Solo un administrador cambia el catálogo.');
            return;
        }
        try {
            $this->servicio->crearMarca($this->usuario, $this->campo($post, 'nombre'), $this->campo($post, 'propia') === 'S');
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->catalogo($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=catalogo', 'Marca creada.');
    }

    // GET /?ruta=catalogo_importar: subir el catalogo en CSV (y el resultado de la ultima subida)
    public function importar(?array $resultado = null, ?string $error = null, bool $soloRevisar = true): void
    {
        if (!$this->puedeEditarCatalogo()) {
            $this->prohibido('Solo un administrador cambia el catálogo.');
            return;
        }
        $columnas = CatalogoCsv::COLUMNAS;
        $maximoFilas = CatalogoCsv::MAXIMO_FILAS;
        $this->vista('productos/importar', get_defined_vars());
    }

    // GET /?ruta=catalogo_plantilla: el CSV con el catalogo actual, para editarlo en Excel y volver a subirlo
    public function plantilla(): void
    {
        if (!$this->puedeEditarCatalogo()) {
            $this->prohibido('Solo un administrador cambia el catálogo.');
            return;
        }
        $categorias = [];
        foreach ($this->catalogo->categorias() as $c) {
            $categorias[$c->getIdCategoria()] = $c->getNombreCategoria();
        }
        $marcas = [];
        foreach ($this->catalogo->marcas() as $m) {
            $marcas[$m->getIdMarca()] = $m->getNombreMarca();
        }
        $filas = [];
        foreach ($this->repo->todos() as $p) {
            $filas[] = [$p->getCodigoBarras() ?? '', $p->getNombreProducto(), $categorias[$p->getIdCategoria()] ?? '', $marcas[$p->getIdMarca()] ?? '',
                $this->repo->abreviaturaUnidad($p->getIdUnidad()), number_format($p->getPrecioVenta(), 2, '.', ''), $p->getSeVendePorPeso()];
        }
        $this->enviarCsv('catalogo_' . date('Y-m-d') . '.csv', CatalogoCsv::COLUMNAS, $filas);
    }

    /**
     * POST /?ruta=catalogo_subir con el archivo (de $_FILES, que solo lee public/index.php).
     * @param ?array{error?: int, size?: int, tmp_name?: string, name?: string} $archivo
     */
    public function subirCatalogo(array $post, ?array $archivo): void
    {
        if (!$this->puedeEditarCatalogo()) {
            $this->prohibido('Solo un administrador cambia el catálogo.');
            return;
        }
        $soloRevisar = $this->campo($post, 'modo') !== 'importar';
        if ($archivo === null || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($archivo['tmp_name'] ?? null) || !is_uploaded_file($archivo['tmp_name'])) {
            http_response_code(422);
            $this->importar(null, 'Elige el archivo CSV del catálogo (hasta 1 MB).', $soloRevisar);
            return;
        }
        if ((int) ($archivo['size'] ?? 0) > CatalogoCsv::MAXIMO_BYTES
            || preg_match('/\.(csv|txt)$/i', (string) ($archivo['name'] ?? '')) !== 1) {
            http_response_code(422);
            $this->importar(null, 'Sube un archivo .csv de hasta 1 MB (en Excel: Guardar como → CSV).', $soloRevisar);
            return;
        }

        $leido = CatalogoCsv::leer((string) file_get_contents($archivo['tmp_name']));
        if ($leido['errores'] !== [] && $leido['filas'] === []) {
            http_response_code(422);
            $this->importar(null, implode(' ', $leido['errores']), $soloRevisar);
            return;
        }
        $resultado = $this->servicio->importarCatalogo($this->usuario, $leido['filas'], $soloRevisar);
        $resultado['errores'] = [...$leido['errores'], ...$resultado['errores']];
        $resultado['soloRevisar'] = $soloRevisar;
        if ($resultado['errores'] !== []) {
            http_response_code(422);
        } elseif (!$soloRevisar) {
            $this->redirigir('/?ruta=productos', sprintf('Catálogo importado: %d productos nuevos y %d actualizados.',
                $resultado['creados'], $resultado['actualizados']));
            return;
        }
        $this->importar($resultado, null, $soloRevisar);
    }

    private function puedeEditarCatalogo(): bool
    {
        return $this->usuario?->puedeAdministrarCatalogo() === true;
    }
}
