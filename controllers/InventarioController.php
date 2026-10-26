<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/InventarioRepository.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/CatalogoRepository.php';
require_once __DIR__ . '/../models/servicios/InventarioServicio.php';

/**
 * C: kardex, traslados entre tiendas y mermas.
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

    // GET /?ruta=kardex&tienda=1&producto=8
    public function kardex(?string $tiendaPedida, ?string $productoPedido): void
    {
        $tienda = $this->leerTienda($tiendaPedida);
        $tiendas = $this->tiendas->todas();
        $rutaSelector = 'kardex';
        $productos = $this->productosPorId();
        $idProducto = $productoPedido !== null && ctype_digit($productoPedido) && isset($productos[(int) $productoPedido])
            ? (int) $productoPedido : null;

        $tipos = $this->catalogo->tiposMovimiento();
        $nombresEmpleado = $this->nombresEmpleado();
        $movimientos = $this->inventario->kardex($tienda->getIdTienda(), $idProducto);
        $this->vista('inventario/kardex', get_defined_vars());
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
