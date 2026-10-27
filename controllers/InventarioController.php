<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/InventarioRepository.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/CatalogoRepository.php';
require_once __DIR__ . '/../models/Paginacion.php';
require_once __DIR__ . '/../models/servicios/InventarioServicio.php';

/**
 * C: kardex, traslados entre tiendas, mermas y ajustes por conteo fisico.
 */
class InventarioController extends Controller
{
    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private InventarioRepository $inventario,
        private ProductoRepository $productos,
        private EmpleadoRepository $empleados,
        private CatalogoRepository $catalogo,
        private InventarioServicio $servicio
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=kardex&tienda=1&producto=8&pagina=2
    public function kardex(?string $tiendaPedida, ?string $productoPedido, ?string $pagina): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendas->todas();
        $rutaSelector = 'kardex';
        $productos = $this->productosPorId();
        $idProducto = $productoPedido !== null && ctype_digit($productoPedido) && isset($productos[(int) $productoPedido])
            ? (int) $productoPedido : null;

        $tipos = $this->catalogo->tiposMovimiento();
        $nombresEmpleado = $this->nombresEmpleado();
        $paginacion = new Paginacion($this->inventario->contarKardex($tienda->getIdTienda(), $idProducto), $pagina, 50);
        $movimientos = $this->inventario->kardex($tienda->getIdTienda(), $idProducto, $paginacion->getPorPagina(), $paginacion->offset());
        $urlPagina = '/?' . http_build_query(['ruta' => 'kardex', 'tienda' => $tienda->getIdTienda(), 'producto' => $idProducto]);
        $this->vista('inventario/kardex', get_defined_vars());
    }

    // GET /?ruta=kardex_csv&tienda=1&producto=8: todo el kardex de la tienda (o de un producto), para Excel
    public function kardexCsv(?string $tiendaPedida, ?string $productoPedido): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $productos = $this->productosPorId();
        $idProducto = $productoPedido !== null && ctype_digit($productoPedido) && isset($productos[(int) $productoPedido])
            ? (int) $productoPedido : null;
        $tipos = $this->catalogo->tiposMovimiento();
        $nombresEmpleado = $this->nombresEmpleado();

        $filas = [];
        $total = $this->inventario->contarKardex($tienda->getIdTienda(), $idProducto);
        // De 1000 en 1000, para no pedir todo de una vez
        for ($offset = 0; $offset < $total; $offset += 1000) {
            foreach ($this->inventario->kardex($tienda->getIdTienda(), $idProducto, 1000, $offset) as $m) {
                $tipo = $tipos[$m->getIdTipoMovimiento()] ?? null;
                $filas[] = [
                    $m->getIdMovimiento(),
                    $m->getFechaHora(),
                    ($productos[$m->getIdProducto()] ?? null)?->getNombreProducto() ?? '',
                    $tipo?->getNombreMovimiento() ?? '',
                    ($tipo?->esEntrada() ? '' : '-') . number_format($m->getCantidad(), 3, '.', ''),
                    number_format($m->getStockNuevo(), 3, '.', ''),
                    match (true) {
                        $m->getIdVenta() !== null    => 'Venta ' . $m->getIdVenta(),
                        $m->getIdTraslado() !== null => 'Traslado ' . $m->getIdTraslado(),
                        $m->getIdMerma() !== null    => 'Merma ' . $m->getIdMerma(),
                        default                      => 'Ajuste ' . $m->getIdAjuste(),
                    },
                    $nombresEmpleado[$m->getIdEmpleado()] ?? '',
                    $m->getObservacion() ?? '',
                ];
            }
        }
        $this->enviarCsv(sprintf('kardex_tienda%d_%s.csv', $tienda->getIdTienda(), date('Y-m-d')),
            ['id', 'fecha', 'producto', 'tipo', 'cantidad', 'stock_nuevo', 'origen', 'empleado', 'observacion'], $filas);
    }

    // GET /?ruta=traslados&tienda=1
    public function traslados(?string $tiendaPedida, array $viejo = [], ?string $error = null): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendas->todas();
        $rutaSelector = 'traslados';
        $productos = $this->productosPorId();
        $nombresEmpleado = $this->nombresEmpleado();
        $puedeRegistrar = $this->usuario?->puedeMoverMercaderia() === true;

        $filas = [];
        foreach ($this->inventario->trasladosDeTienda($tienda->getIdTienda()) as $traslado) {
            $filas[] = [
                'traslado'    => $traslado,
                // Las cantidades salen de los movimientos de SALIDA del origen
                'movimientos' => array_values(array_filter(
                    $this->inventario->movimientosDeTraslado($traslado->getIdTraslado()),
                    fn(MovimientoStock $m): bool => $m->getIdTipoMovimiento() === TipoMovimiento::TRASLADO_SALIDA
                )),
            ];
        }
        $this->vista('inventario/traslados', get_defined_vars());
    }

    // POST /?ruta=traslado_registrar
    public function registrarTraslado(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeMoverMercaderia()) {
            $this->prohibido('Solo un almacenero registra traslados.');
            return;
        }
        $destino = $this->campo($post, 'destino');
        try {
            $id = $this->servicio->trasladar(
                $this->usuario,
                ctype_digit($destino) ? (int) $destino : 0,
                $this->lineasDelFormulario($post),
                $this->campo($post, 'observacion')
            );
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->traslados((string) $this->usuario->getIdTienda(), $post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=traslados&tienda=' . $this->usuario->getIdTienda(), "Traslado $id registrado.");
    }

    // GET /?ruta=mermas&tienda=1
    public function mermas(?string $tiendaPedida, array $viejo = [], ?string $error = null): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendas->todas();
        $rutaSelector = 'mermas';
        $productos = $this->productosPorId();
        $nombresEmpleado = $this->nombresEmpleado();
        $motivos = Merma::MOTIVOS;
        $puedeRegistrar = $this->usuario?->puedeMoverMercaderia() === true
            && $this->usuario->getIdTienda() === $tienda->getIdTienda();

        $filas = [];
        foreach ($this->inventario->mermasDeTienda($tienda->getIdTienda()) as $merma) {
            $filas[] = ['merma' => $merma, 'movimientos' => $this->inventario->movimientosDeMerma($merma->getIdMerma())];
        }
        $this->vista('inventario/mermas', get_defined_vars());
    }

    // POST /?ruta=merma_registrar
    public function registrarMerma(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeMoverMercaderia()) {
            $this->prohibido('Solo un almacenero registra mermas.');
            return;
        }
        try {
            $id = $this->servicio->registrarMerma(
                $this->usuario,
                $this->campo($post, 'motivo'),
                $this->campo($post, 'observacion'),
                $this->lineasDelFormulario($post)
            );
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->mermas((string) $this->usuario->getIdTienda(), $post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=mermas&tienda=' . $this->usuario->getIdTienda(), "Merma $id registrada.");
    }

    // GET /?ruta=ajustes&tienda=1: conteo fisico y ajustes hechos
    public function ajustes(?string $tiendaPedida, array $viejo = [], ?string $error = null): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendas->todas();
        $rutaSelector = 'ajustes';
        $productos = $this->productosPorId();
        $nombresEmpleado = $this->nombresEmpleado();
        $motivos = AjusteInventario::MOTIVOS;
        $puedeRegistrar = $this->usuario?->puedeAjustarStock($tienda->getIdTienda()) === true;

        // Para la hoja de conteo: lo que dice el sistema de cada producto en esta tienda
        $stocks = [];
        foreach ($productos as $id => $producto) {
            $stocks[$id] = $this->productos->stockEnTienda($id, $tienda->getIdTienda());
        }
        $filas = [];
        foreach ($this->inventario->ajustesDeTienda($tienda->getIdTienda()) as $ajuste) {
            $filas[] = ['ajuste' => $ajuste, 'movimientos' => $this->inventario->movimientosDeAjuste($ajuste->getIdAjuste())];
        }
        $tipos = $this->catalogo->tiposMovimiento();
        $this->vista('inventario/ajustes', get_defined_vars());
    }

    // POST /?ruta=ajuste_registrar
    public function registrarAjuste(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeAjustarStock($this->usuario->getIdTienda())) {
            $this->prohibido('Solo el almacenero o el administrador de la tienda ajustan su stock.');
            return;
        }
        try {
            $r = $this->servicio->ajustarPorConteo(
                $this->usuario,
                $this->campo($post, 'motivo'),
                $this->campo($post, 'observacion'),
                $this->lineasDelFormulario($post)
            );
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->ajustes((string) $this->usuario->getIdTienda(), $post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=ajustes&tienda=' . $this->usuario->getIdTienda(),
            sprintf('Ajuste %d registrado: %d producto(s) corregido(s).', $r['idAjuste'], $r['cambios']));
    }

    /** @return array<int, Producto> */
    private function productosPorId(): array
    {
        $productos = [];
        foreach ($this->productos->todos() as $producto) {
            $productos[$producto->getIdProducto()] = $producto;
        }
        return $productos;
    }

    /** @return array<int, string> */
    private function nombresEmpleado(): array
    {
        $nombres = [];
        foreach ($this->empleados->todos() as $empleado) {
            $nombres[$empleado->getIdEmpleado()] = $empleado->nombreCompleto();
        }
        return $nombres;
    }
}
