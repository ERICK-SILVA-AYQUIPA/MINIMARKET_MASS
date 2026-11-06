<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/CatalogoCsv.php';
require_once __DIR__ . '/../models/FotoProducto.php';
require_once __DIR__ . '/../models/Paginacion.php';
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
     * &buscar=leche (el buscador de la barra superior) deja escrito el texto en el buscador de la lista, que filtra al cargar.
     */
    public function lista(?string $tiendaPedida, ?string $ver = null, ?string $vistaPedida = null, ?string $categoriaPedida = null,
        ?string $buscarPedido = null): void
    {
        // Solo se vuelve a mostrar en el campo (escapado en la vista); no entra a ninguna consulta
        $buscado = mb_substr(trim($buscarPedido ?? ''), 0, 80);
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendasVisibles();
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

        // Lo recien agregado: etiqueta NUEVO en cada producto y un aviso arriba con enlace a la pestaña Recien agregados.
        // El aviso cuenta solo lo registrado desde la sede de quien mira (el administrador, toda la cadena)
        $desdeNuevo = (new DateTimeImmutable('today'))->modify('-' . (self::DIAS_NUEVO - 1) . ' days')->format('Y-m-d');
        $nuevos = $this->repo->idsNuevos($desdeNuevo);
        $diasNuevo = self::DIAS_NUEVO;
        $avisoNuevos = [
            'semana'     => $this->repo->contarNuevos(['tienda' => $this->tiendaRestringida(), 'desde' => $desdeNuevo]),
            'pendientes' => $puedeEditar ? $this->repo->contarNuevos(['estado' => 'pendientes']) : 0,
        ];

        // Ficha en el panel lateral; si el id no existe, solo se muestra la lista
        $ficha = null;
        if ($ver !== null && preg_match('/^[1-9][0-9]{0,9}$/', $ver) === 1) {
            $elegido = $this->repo->buscarPorId((int) $ver);
            $ficha = $elegido !== null ? $this->datosFicha($elegido, $tienda) : null;
        }
        unset($producto, $elegido, $id, $c, $m, $idCategoria, $marcas, $desdeNuevo, $buscarPedido);

        $this->vista('productos/lista', get_defined_vars());
    }

    // Cuantos dias un producto lleva la etiqueta NUEVO en el catalogo
    public const DIAS_NUEVO = 7;

    // Periodos rapidos de Recien agregados: valor de &periodo= => [texto, dias hacia atras (null = todo)]
    public const PERIODOS = ['hoy' => ['Hoy', 1], '7' => ['7 días', 7], '30' => ['30 días', 30], '90' => ['90 días', 90], 'todo' => ['Todo', null]];

    // Origenes de un producto nuevo (migracion 018)
    public const ORIGENES = ['APP' => 'App (escáner)', 'WEB' => 'Formulario web', 'CSV' => 'Importación CSV'];

    /**
     * GET /?ruta=productos_nuevos&periodo=7&origen=APP&estado=pendientes&tienda=2&desde=&hasta=&q=leche&pagina=2
     * Los productos recien agregados al catalogo: desde la app (al escanear un codigo nuevo), el formulario web o un CSV.
     * Origen, quien, cuando, desde que sucursal, stock inicial y de hoy, y lo que falta completar (foto, marca, minimo).
     * La ven todos los roles. El administrador ve todas las sucursales (&tienda= deja una) y los marca como revisados;
     * el cajero y el almacenero solo ven lo registrado desde su sede.
     * @param array<string, mixed> $consulta los parametros del GET (solo se leen textos)
     */
    public function recienAgregados(array $consulta): void
    {
        if ($this->usuario === null) {
            $this->prohibido('Entra con tu usuario para ver los productos nuevos.');
            return;
        }
        $tiendas = $this->tiendasVisibles();
        $f = $this->leerFiltrosNuevos($consulta, $tiendas);
        $puedeRevisar = $this->puedeEditarCatalogo();

        $resumen = $this->repo->resumenNuevos($f['filtros']);
        $paginacion = new Paginacion($resumen['total'], $f['pagina'], 30);
        $filas = [];
        foreach ($this->repo->paginaNuevos($f['filtros'], $paginacion->getPorPagina(), $paginacion->offset()) as $fila) {
            $idProducto = (int) $fila['id_producto'];
            $fila['foto'] = $this->fotos->datos($idProducto, (string) $fila['categoria']);
            $fila['faltantes'] = self::faltantes($fila);
            $filas[] = $fila;
        }
        $periodo = $f['periodo'];
        $desde = $f['filtros']['desde'];
        $hasta = $f['filtros']['hasta'];
        $idTienda = $f['filtros']['tienda'];
        $origen = $f['filtros']['origen'];
        $estado = $f['filtros']['estado'];
        $texto = $f['filtros']['texto'];
        $hayFiltros = $f['hayFiltros'];
        $consultaActual = $f['consulta'];
        $urlPagina = '/?' . http_build_query(['ruta' => 'productos_nuevos'] + $consultaActual);
        $periodos = self::PERIODOS;
        $origenes = self::ORIGENES;
        $diasNuevo = self::DIAS_NUEVO;
        unset($fila, $idProducto, $f, $consulta);
        $this->vista('productos/nuevos', get_defined_vars());
    }

    // GET /?ruta=productos_app (la pestaña "Desde la app" de antes): ahora es Recien agregados con el origen App
    public function desdeApp(array $consulta): void
    {
        $destino = ['ruta' => 'productos_nuevos', 'origen' => 'APP', 'periodo' => 'todo'];
        foreach (['tienda', 'desde', 'hasta', 'q'] as $clave) {
            $valor = $this->campo($consulta, $clave);
            if ($valor !== '') {
                $destino[$clave] = mb_substr($valor, 0, 60);
            }
        }
        header('Location: /?' . http_build_query($destino), true, 301);
    }

    /**
     * POST /?ruta=productos_revisar con ids[] (y los filtros de la lista, para volver a ella).
     * El administrador da por revisados los productos recien agregados elegidos.
     */
    public function marcarRevisados(array $post): void
    {
        if (!$this->puedeEditarCatalogo()) {
            $this->prohibido('Solo un administrador revisa los productos nuevos del catálogo.');
            return;
        }
        $filtros = $this->leerFiltrosNuevos($post, $this->tiendasVisibles());
        $volver = '/?' . http_build_query(['ruta' => 'productos_nuevos'] + $filtros['consulta']
            + ($filtros['pagina'] !== null && $filtros['filtros']['estado'] !== 'pendientes' ? ['pagina' => $filtros['pagina']] : []));
        $ids = [];
        foreach (is_array($post['ids'] ?? null) ? $post['ids'] : [] as $valor) {
            $id = is_string($valor) ? $this->leerId($valor) : 0;
            if ($id > 0) {
                $ids[] = $id;
            }
        }
        try {
            $marcados = $this->servicio->marcarRevisados($this->usuario, $ids);
        } catch (ReglaNegocioException $ex) {
            $this->sesion->avisar('error', $ex->getMessage());
            header('Location: ' . $volver, true, 303);
            return;
        }
        $this->redirigir($volver, match (true) {
            $marcados === 0 => 'Esos productos ya estaban revisados.',
            $marcados === 1 => 'Producto marcado como revisado.',
            default         => $marcados . ' productos marcados como revisados.',
        }, $marcados === 0 ? 'alerta' : 'ok');
    }

    /**
     * Lee y valida los filtros de Recien agregados (del GET, o de los campos ocultos del POST para volver a la lista).
     * consulta: los mismos filtros ya limpios, para armar enlaces; nunca se copia el texto pedido tal cual.
     * @param array<string, mixed> $fuente
     * @param array<int, Tienda> $tiendas
     * @return array{filtros: array{tienda: ?int, desde: ?string, hasta: ?string, texto: string, origen: ?string, estado: ?string},
     *   periodo: string, pagina: ?string, hayFiltros: bool, consulta: array<string, string|int>}
     */
    private function leerFiltrosNuevos(array $fuente, array $tiendas): array
    {
        $tiendaPedida = $this->campo($fuente, 'tienda');
        // El administrador filtra la sucursal que quiera (o todas); el cajero y el almacenero ven solo la suya
        $idTienda = ctype_digit($tiendaPedida) && isset($tiendas[(int) $tiendaPedida]) ? (int) $tiendaPedida : null;
        $idTienda = $this->tiendaRestringida() ?? $idTienda;

        $desde = $this->leerFecha($this->campo($fuente, 'desde'));
        $hasta = $this->leerFecha($this->campo($fuente, 'hasta'));
        if ($desde !== null && $hasta !== null && $desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        // Con fechas propias manda el rango; si no, el periodo rapido. Por defecto todo, lo mas reciente primero:
        // asi el numero de la pestaña (por revisar, de cualquier fecha) coincide con lo que se ve al abrirla
        $periodo = $this->campo($fuente, 'periodo');
        if ($desde !== null || $hasta !== null) {
            $periodo = 'rango';
        } else {
            $periodo = isset(self::PERIODOS[$periodo]) ? $periodo : 'todo';
            $dias = self::PERIODOS[$periodo][1];
            $desde = $dias === null ? null : (new DateTimeImmutable('today'))->modify('-' . ($dias - 1) . ' days')->format('Y-m-d');
        }
        $origen = $this->campo($fuente, 'origen');
        $origen = isset(self::ORIGENES[$origen]) ? $origen : null;
        $estado = $this->campo($fuente, 'estado');
        $estado = in_array($estado, ['pendientes', 'revisados'], true) ? $estado : null;
        $texto = mb_substr(trim($this->campo($fuente, 'q')), 0, 60);
        $pagina = $this->campo($fuente, 'pagina');

        $consulta = array_filter([
            'periodo' => $periodo === 'rango' ? null : $periodo,
            'desde'   => $periodo === 'rango' ? $desde : null,
            'hasta'   => $periodo === 'rango' ? $hasta : null,
            'tienda'  => $this->tiendaRestringida() === null ? $idTienda : null,
            'origen'  => $origen,
            'estado'  => $estado,
            'q'       => $texto !== '' ? $texto : null,
        ], static fn($v): bool => $v !== null);

        return [
            'filtros'    => ['tienda' => $idTienda, 'desde' => $desde, 'hasta' => $hasta, 'texto' => $texto, 'origen' => $origen, 'estado' => $estado],
            'periodo'    => $periodo,
            'pagina'     => ctype_digit($pagina) ? $pagina : null,
            'hayFiltros' => $periodo !== 'todo' || ($idTienda !== null && count($tiendas) > 1) || $origen !== null || $estado !== null || $texto !== '',
            'consulta'   => $consulta,
        ];
    }

    /**
     * Lo que le falta a un producto nuevo para quedar completo en su sucursal: foto, marca, stock minimo o stock.
     * @param array<string, mixed> $fila una fila de ProductoRepository::paginaNuevos con su 'foto'
     * @return array<string, string> clave => texto
     */
    private static function faltantes(array $fila): array
    {
        $faltan = [];
        if ($fila['foto']['url'] === null) {
            $faltan['foto'] = 'Sin foto';
        }
        if (mb_strtolower((string) $fila['marca']) === 'sin marca') {
            $faltan['marca'] = 'Sin marca';
        }
        if ($fila['stock_minimo'] !== null && (float) $fila['stock_minimo'] <= 0) {
            $faltan['minimo'] = 'Sin stock mínimo';
        }
        if ($fila['stock'] !== null && (float) $fila['stock'] <= 0) {
            $faltan['stock'] = 'Sin stock';
        }
        return $faltan;
    }

    // GET /?ruta=producto&codigo=7750000000011  o  /?ruta=producto&id=8
    public function detalle(?string $codigo, ?string $id, ?string $tiendaPedida): void
    {
        $producto = null;
        if ($codigo !== null && CodigoBarras::esValido(CodigoBarras::limpiar($codigo))) {
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
            // Los otros codigos con los que el escaner tambien lo reconoce (migracion 023)
            'otrosCodigos'     => $this->repo->otrosCodigos($producto->getIdProducto()),
            // El minimo se fija solo en la tienda propia del administrador
            'puedeFijarMinimo' => $this->usuario?->puedeAdministrarTienda($tienda->getIdTienda()) === true,
        ];
    }

    // GET /?ruta=stock: el stock de cada producto en todas las tiendas, lado a lado
    public function stockTiendas(): void
    {
        $tiendas = $this->tiendasVisibles();

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
        $tiendas = $this->tiendasVisibles();
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

    // POST /?ruta=producto_codigo_quitar: el administrador quita un codigo que se vinculo mal desde la app
    public function quitarCodigo(array $post): void
    {
        if (!$this->puedeEditarCatalogo()) {
            $this->prohibido('Solo un administrador cambia el catálogo.');
            return;
        }
        $idProducto = $this->leerId($this->campo($post, 'producto'));
        try {
            $idProducto = $this->servicio->quitarCodigo($this->usuario, $this->leerId($this->campo($post, 'id')));
        } catch (ReglaNegocioException $ex) {
            $this->sesion->avisar('error', $ex->getMessage());
            header('Location: /?ruta=producto&id=' . $idProducto, true, 303);
            return;
        }
        $this->redirigir('/?ruta=producto&id=' . $idProducto, 'Código quitado: el escáner ya no lo reconoce.');
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
