<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/CompraRepository.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/PanelRepository.php';
require_once __DIR__ . '/../models/Reposicion.php';
require_once __DIR__ . '/../models/Paginacion.php';
require_once __DIR__ . '/../models/servicios/CompraServicio.php';

/**
 * C: proveedores y ordenes de compra. La orden se arma a mano o desde Reponer (lo sugerido),
 * y se recibe en Ingresos (InventarioController), que sube el stock y deja el kardex.
 */
class CompraController extends Controller
{
    private const FILAS_NUEVA = 8;

    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private CompraRepository $compras,
        private ProductoRepository $productos,
        private EmpleadoRepository $empleados,
        private PanelRepository $panel,
        private CompraServicio $servicio
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=proveedores
    public function proveedores(array $viejo = [], ?string $error = null, ?int $idConError = null): void
    {
        if (!$this->puedeVerCompras()) {
            $this->prohibido('Los proveedores los ven el almacenero y el administrador de la tienda.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        $proveedores = $this->compras->proveedores();
        $resumen = $this->compras->resumenProveedores($tienda->getIdTienda());
        $puedeEditar = $this->usuario->puedeAdministrarProveedores();
        $puedePedir = $this->usuario->puedeEmitirOrdenCompra($tienda->getIdTienda()) && $tienda->estaActiva();
        $activos = count(array_filter($proveedores, fn(Proveedor $p): bool => $p->estaActivo()));
        $this->vista('compras/proveedores', get_defined_vars());
    }

    // POST /?ruta=proveedor_guardar (sin id: nuevo)
    public function guardarProveedor(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeAdministrarProveedores()) {
            $this->prohibido('Solo un administrador agrega o corrige proveedores.');
            return;
        }
        $id = $this->leerId($this->campo($post, 'id'));
        try {
            $id = $this->servicio->guardarProveedor($this->usuario, $id > 0 ? $id : null, [
                'ruc'          => $this->campo($post, 'ruc'),
                'razon_social' => $this->campo($post, 'razon_social'),
                'contacto'     => $this->campo($post, 'contacto'),
                'telefono'     => $this->campo($post, 'telefono'),
                'correo'       => $this->campo($post, 'correo'),
                'dias_entrega' => $this->campo($post, 'dias_entrega'),
            ]);
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->proveedores($post, $ex->getMessage(), $id > 0 ? $id : null);
            return;
        }
        $this->redirigir('/?ruta=proveedores#fila-prov-' . $id, 'Proveedor guardado.');
    }

    // POST /?ruta=proveedor_estado
    public function estadoProveedor(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeAdministrarProveedores()) {
            $this->prohibido('Solo un administrador activa o desactiva proveedores.');
            return;
        }
        $id = $this->leerId($this->campo($post, 'id'));
        $estado = $this->campo($post, 'estado');
        try {
            $this->servicio->cambiarEstadoProveedor($this->usuario, $id, $estado);
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->proveedores([], $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=proveedores#fila-prov-' . $id, $estado === 'ACTIVO' ? 'Proveedor activado.' : 'Proveedor desactivado.');
    }

    /**
     * GET /?ruta=ordenes&estado=ABIERTAS&pagina=2
     * Con &nueva=1 abre el formulario; con &desde=reponer lo llena con lo sugerido en Reponer
     * (solo lo del &proveedor= si viene, con el costo de la ultima compra).
     */
    public function ordenes(?string $estadoPedido, ?string $pagina, ?string $nueva = null, ?string $desde = null,
        ?string $proveedorPedido = null, array $viejo = [], ?string $error = null, ?string $errorOrden = null): void
    {
        if (!$this->puedeVerCompras()) {
            $this->prohibido('Las órdenes de compra las ven el almacenero y el administrador de la tienda.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        $estado = in_array($estadoPedido, array_merge(['ABIERTAS'], OrdenCompra::ESTADOS), true) ? $estadoPedido : null;
        $estados = ['ABIERTAS' => 'Abiertas'] + array_combine(OrdenCompra::ESTADOS, array_map('ucfirst', array_map('strtolower', OrdenCompra::ESTADOS)));
        $proveedores = $this->compras->proveedores();
        $productos = $this->productosPorId();
        $nombresEmpleado = $this->nombresEmpleado();
        $hoy = date('Y-m-d');
        $puedePedir = $this->usuario->puedeEmitirOrdenCompra($tienda->getIdTienda()) && $tienda->estaActiva();
        $puedeRecibir = $this->usuario->puedeRegistrarIngreso($tienda->getIdTienda()) && $tienda->estaActiva();
        $puedeCancelar = $this->usuario->puedeCancelarOrdenCompra($tienda->getIdTienda());

        $paginacion = new Paginacion($this->compras->contarOrdenes($tienda->getIdTienda(), $estado), $pagina, 25);
        $filas = [];
        foreach ($this->compras->ordenes($tienda->getIdTienda(), $estado, $paginacion->getPorPagina(), $paginacion->offset()) as $orden) {
            $filas[] = [
                'orden'    => $orden,
                'detalle'  => $this->compras->detalleOrden($orden->getIdOrdenCompra()),
                'ingresos' => $this->compras->ingresosDeOrden($orden->getIdOrdenCompra()),
            ];
        }
        $abiertas = $this->compras->contarOrdenes($tienda->getIdTienda(), 'ABIERTAS');
        $urlPagina = '/?' . http_build_query(['ruta' => 'ordenes', 'estado' => $estado]);

        // Formulario de orden nueva: abierto si lo pidieron, si viene de Reponer o si volvio con un error
        $abrirNueva = $puedePedir && ($nueva === '1' || $desde === 'reponer' || $error !== null);
        $idProveedorElegido = $this->leerId($proveedorPedido !== null ? $proveedorPedido : (is_string($viejo['proveedor'] ?? null) ? $viejo['proveedor'] : null));
        $proveedorElegido = $proveedores[$idProveedorElegido] ?? null;
        $sugeridos = 0;
        if ($puedePedir && $desde === 'reponer' && $viejo === []) {
            $viejo = $this->lineasSugeridas($tienda->getIdTienda(), $proveedorElegido);
            $sugeridos = count($viejo['producto']);
        }
        if ($proveedorElegido !== null && !isset($viejo['fecha_entrega'])) {
            $viejo['fecha_entrega'] = $proveedorElegido->entregaEstimada($hoy) ?? '';
        }
        $filasNueva = max(self::FILAS_NUEVA, count(is_array($viejo['producto'] ?? null) ? $viejo['producto'] : []) + 2);
        $this->vista('compras/ordenes', get_defined_vars());
    }

    // POST /?ruta=orden_registrar
    public function registrarOrden(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeEmitirOrdenCompra($this->usuario->getIdTienda())) {
            $this->prohibido('Las órdenes de compra las emiten el almacenero y el administrador de la tienda.');
            return;
        }
        try {
            $id = $this->servicio->registrarOrden(
                $this->usuario,
                $this->leerId($this->campo($post, 'proveedor')),
                $this->campo($post, 'fecha_entrega'),
                $this->campo($post, 'observacion'),
                $this->lineasDelFormulario($post)
            );
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->ordenes(null, null, null, null, null, $post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=ordenes#oc-' . $id, 'Orden ' . OrdenCompra::formatear($id) . ' emitida. Imprímela o envíasela al proveedor; '
            . 'cuando llegue la mercadería, recíbela desde la misma orden.');
    }

    // POST /?ruta=orden_cancelar
    public function cancelarOrden(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeCancelarOrdenCompra($this->usuario->getIdTienda())) {
            $this->prohibido('Solo el administrador de la tienda anula o cierra una orden de compra.');
            return;
        }
        $id = $this->leerId($this->campo($post, 'id'));
        try {
            $estado = $this->servicio->cancelarOrden($this->usuario, $id, $this->campo($post, 'motivo'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->ordenes(null, null, null, null, null, [], null, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=ordenes#oc-' . $id, OrdenCompra::formatear($id)
            . ($estado === 'ANULADA' ? ' anulada.' : ' cerrada: lo que ya llegó se mantiene y el resto ya no se espera.'));
    }

    // GET /?ruta=orden_imprimir&id=12: la orden en una hoja A4, para imprimirla o guardarla en PDF y enviarla al proveedor
    public function imprimir(?string $idTexto): void
    {
        if (!$this->puedeVerCompras()) {
            $this->prohibido('Las órdenes de compra las ven el almacenero y el administrador de la tienda.');
            return;
        }
        $orden = $this->compras->orden($this->leerId($idTexto));
        if ($orden === null || $orden->getIdTienda() !== $this->usuario->getIdTienda()) {
            $this->noEncontrado('Esa orden de compra no existe en tu tienda.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($orden->getIdTienda());
        $proveedor = $this->compras->proveedor($orden->getIdProveedor());
        $detalle = $this->compras->detalleOrden($orden->getIdOrdenCompra());
        $productos = $this->productosPorId();
        $emitio = $this->empleados->buscarPorId($orden->getIdEmpleado());
        $unidades = [];
        foreach ($detalle as $idProducto => $d) {
            $p = $productos[$idProducto] ?? null;
            $unidades[$idProducto] = $p !== null ? $this->productos->abreviaturaUnidad($p->getIdUnidad()) : '';
        }
        require_once __DIR__ . '/../../frontend/views/parciales/funciones.php';
        require __DIR__ . '/../../frontend/views/compras/imprimir.php';
    }

    /**
     * Lo que Reponer sugiere pedir, descontando lo que ya esta pedido en otra orden abierta.
     * Con proveedor: solo los productos cuya ultima compra fue a ese proveedor.
     * @return array{producto: string[], cantidad: string[], costo: string[]} como si el usuario lo hubiera escrito
     */
    private function lineasSugeridas(int $idTienda, ?Proveedor $proveedor): array
    {
        $ultima = $this->compras->ultimaCompraPorProducto();
        $porLlegar = $this->compras->porLlegar($idTienda);
        $lineas = ['producto' => [], 'cantidad' => [], 'costo' => []];
        foreach ($this->panel->reposicion($idTienda, Reposicion::DIAS_VENTA) as $f) {
            $compra = $ultima[$f['id_producto']] ?? null;
            if ($proveedor !== null && ($compra === null || $compra['id_proveedor'] !== $proveedor->getIdProveedor())) {
                continue;
            }
            $actual = $f['actual'] + ($porLlegar[$f['id_producto']] ?? 0);
            $sugerido = Reposicion::sugerido($actual, $f['minimo'], Reposicion::ventaDiaria($f['vendido']), $f['al_peso']);
            if ($sugerido <= 0) {
                continue;
            }
            $lineas['producto'][] = (string) $f['id_producto'];
            $lineas['cantidad'][] = $f['al_peso'] ? Cantidad::aTexto($sugerido) : (string) intdiv($sugerido, 1000);
            $lineas['costo'][] = $compra !== null ? Dinero::aTexto($compra['costo']) : '';
        }
        return $lineas;
    }

    // Quien recibe mercaderia y su administrador (los mismos que ven los ingresos)
    private function puedeVerCompras(): bool
    {
        return $this->usuario !== null && $this->usuario->puedeVerIngresos($this->usuario->getIdTienda());
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
