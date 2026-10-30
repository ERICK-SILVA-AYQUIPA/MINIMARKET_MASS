<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../../models/ConsultaMovilRepository.php';
require_once __DIR__ . '/../../models/VentaRepository.php';
require_once __DIR__ . '/../../models/CajaRepository.php';
require_once __DIR__ . '/../../models/TiendaRepository.php';
require_once __DIR__ . '/../../models/servicios/VentaServicio.php';
require_once __DIR__ . '/../../models/servicios/CajaServicio.php';
require_once __DIR__ . '/../../models/servicios/OperacionMovilServicio.php';

/**
 * Ventas desde el celular: el mismo VentaServicio de la pagina web (turno abierto, precio actual,
 * stock bloqueado con FOR UPDATE, correlativo, pagos y kardex en una transaccion), mas la clave
 * Idempotency-Key para que un reintento de red no registre la venta dos veces.
 */
class VentaApiController extends ApiController
{
    private const POR_PAGINA = 30;

    public function __construct(
        ?Usuario $usuario,
        private ConsultaMovilRepository $consultas,
        private VentaRepository $ventas,
        private CajaRepository $cajas,
        private TiendaRepository $tiendas,
        private VentaServicio $servicio,
        private CajaServicio $cajaServicio,
        private OperacionMovilServicio $operaciones
    ) {
        parent::__construct($usuario);
    }

    /**
     * POST /api/v1/ventas  (cabecera Idempotency-Key)
     * {"tipo_comprobante": "B", "documento": "", "total_esperado": "13.00",
     *  "lineas": [{"id_producto": 1, "cantidad": "2"}],
     *  "pago": {"metodo": "EFECTIVO", "efectivo_recibido": "20.00"}}
     * @return array{respuesta: array, repetida: bool}
     */
    public function registrar(array $datos, string $clave): array
    {
        $cajero = $this->usuario();
        if (!$cajero->puedeVender()) {
            $this->prohibido('Solo un cajero puede registrar ventas.');
        }
        $tipo = $this->texto($datos, 'tipo_comprobante');
        $documento = $this->texto($datos, 'documento');
        $lineas = $this->lineas($datos);

        // El precio lo pone el servidor. Si cambio desde que la app armo el carrito, se avisa antes de cobrar.
        $totalActual = $this->totalActual($lineas, $cajero->getIdTienda());
        $esperado = Dinero::deTexto($this->texto($datos, 'total_esperado'));
        if ($totalActual !== null && $esperado !== null && $esperado !== $totalActual) {
            throw new ErrorApi(409, 'PRECIO_CAMBIO', sprintf(
                'Un precio cambió: el total ahora es S/ %s (el carrito decía S/ %s). Actualiza el carrito y vuelve a cobrar.',
                Dinero::aTexto($totalActual), Dinero::aTexto($esperado)
            ));
        }
        $pago = $this->pago(is_array($datos['pago'] ?? null) ? $datos['pago'] : [], $totalActual);

        return $this->operaciones->unaVez($cajero, $clave, 'VENTA', function () use ($cajero, $tipo, $documento, $lineas, $pago): array {
            $r = $this->servicio->registrar($cajero, $tipo, $documento, $lineas, $pago);
            $venta = $this->ventas->buscarPorComprobante($r['serie'], $r['numero']);
            return $this->detalleCompleto($venta->getIdVenta());
        });
    }

    // GET /api/v1/ventas?desde=2026-10-01&hasta=2026-10-31&estado=EMITIDA&comprobante=B001-5&pagina=1
    public function lista(?string $desde, ?string $hasta, ?string $estado, ?string $comprobante, ?string $pagina): array
    {
        $idTienda = $this->usuario()->getIdTienda();
        $filtro = [
            'desde'  => $this->fecha($desde),
            'hasta'  => $this->fecha($hasta),
            'estado' => in_array($estado, ['EMITIDA', 'ANULADA'], true) ? $estado : null,
        ];
        // B001-5 o B001-00000005
        if ($comprobante !== null && preg_match('/^([BF][0-9]{3})-([0-9]{1,8})$/', strtoupper(trim($comprobante)), $m) === 1) {
            $filtro['serie'] = $m[1];
            $filtro['numero'] = $m[2];
        }
        $numero = $this->pagina($pagina);
        $total = $this->consultas->contarVentas($idTienda, $filtro);
        return [
            'ventas'  => $this->consultas->ventas($idTienda, $filtro, self::POR_PAGINA, ($numero - 1) * self::POR_PAGINA),
            'pagina'  => $numero,
            'total'   => $total,
            'hay_mas' => $numero * self::POR_PAGINA < $total,
        ];
    }

    // GET /api/v1/ventas/15
    public function detalle(string $id): array
    {
        $venta = $this->consultas->venta($this->id($id));
        if ($venta === null) {
            $this->noEncontrado('La venta no existe.');
        }
        if ($venta['id_tienda'] !== $this->usuario()->getIdTienda()) {
            $this->prohibido('Desde la app solo se ven las ventas de tu tienda.');
        }
        return $this->detalleCompleto($venta['id_venta']);
    }

    // POST /api/v1/ventas/15/anular {"motivo": "..."}: el mismo proceso del administrador en la web (queda la anulacion y el kardex)
    public function anular(string $id, array $datos): array
    {
        $usuario = $this->usuario();
        $venta = $this->consultas->venta($this->id($id));
        if ($venta === null) {
            $this->noEncontrado('La venta no existe.');
        }
        if (!$usuario->puedeAnularVenta($venta['id_tienda'])) {
            $this->prohibido('Solo el administrador de la tienda de la venta puede anularla.');
        }
        $this->servicio->anular($usuario, $venta['id_venta'], $this->texto($datos, 'motivo'));
        return $this->detalleCompleto($venta['id_venta']);
    }

    // GET /api/v1/caja: el turno abierto del cajero y las cajas de su tienda
    public function caja(): array
    {
        $usuario = $this->usuario();
        $turno = $usuario->puedeManejarCaja() ? $this->cajas->turnoAbiertoDe($usuario->getIdEmpleado()) : null;
        $numeroCaja = $turno !== null ? $this->tiendas->buscarCaja($turno->getIdCaja())?->getNumeroCaja() : null;
        return [
            'puede_vender' => $usuario->puedeVender(),
            'turno'        => $turno === null ? null : [
                'id_caja_turno' => $turno->getIdCajaTurno(),
                'id_caja'       => $turno->getIdCaja(),
                'numero_caja'   => $numeroCaja,
                'apertura'      => $turno->getFechaHoraApertura(),
                'monto_inicial' => number_format($turno->getMontoInicial(), 2, '.', ''),
            ],
            'cajas'        => array_map(fn(Caja $c): array => [
                'id_caja'     => $c->getIdCaja(),
                'numero_caja' => $c->getNumeroCaja(),
                'estado'      => $c->getEstado(),
            ], $this->tiendas->cajasDeTienda($usuario->getIdTienda())),
        ];
    }

    // POST /api/v1/caja/turno {"id_caja": 1, "monto_inicial": "100.00"}: CajaServicio::abrirTurno, igual que en la web
    public function abrirTurno(array $datos): array
    {
        $usuario = $this->usuario();
        if (!$usuario->puedeManejarCaja()) {
            $this->prohibido('Solo un cajero abre turnos.');
        }
        $this->cajaServicio->abrirTurno($usuario, $this->id($this->texto($datos, 'id_caja')), $this->texto($datos, 'monto_inicial'));
        return $this->caja();
    }

    /**
     * El pago de la app (un metodo por venta) en el formato de VentaServicio::registrar().
     * Tarjeta y Sip solo REGISTRAN el cobro hecho en el POS o con el QR: la app no cobra nada.
     * @return array<string, string>
     */
    private function pago(array $pago, ?int $total): array
    {
        $monto = $total === null ? '' : Dinero::aTexto($total);
        return match ($this->texto($pago, 'metodo')) {
            'EFECTIVO' => ['efectivo_recibido' => $this->texto($pago, 'efectivo_recibido')],
            'TARJETA'  => [
                'tarjeta_monto'        => $monto,
                'tarjeta_tipo'         => $this->texto($pago, 'tarjeta_tipo'),
                'tarjeta_marca'        => $this->texto($pago, 'tarjeta_marca'),
                'tarjeta_ultimos4'     => $this->texto($pago, 'tarjeta_ultimos4'),
                'tarjeta_autorizacion' => $this->texto($pago, 'tarjeta_autorizacion'),
            ],
            'SIP'      => [
                'sip_monto'     => $monto,
                'sip_modalidad' => $this->texto($pago, 'sip_modalidad'),
                'sip_operacion' => $this->texto($pago, 'sip_operacion'),
            ],
            default    => throw new ErrorApi(422, 'REGLA_NEGOCIO', 'Elige el método de pago: EFECTIVO, TARJETA o SIP.'),
        };
    }

    /**
     * Total con los precios de ahora, como lo calcula VentaServicio (importe = ROUND(cantidad * precio, 2)).
     * null si alguna linea no es valida: el servicio dara el mensaje exacto.
     */
    private function totalActual(array $lineas, int $idTienda): ?int
    {
        $total = 0;
        foreach ($lineas as $linea) {
            $producto = ctype_digit($linea['id_producto']) ? $this->consultas->productoPorId((int) $linea['id_producto'], $idTienda) : null;
            $milesimas = $producto !== null ? Cantidad::deTexto($linea['cantidad'], $producto['al_peso']) : null;
            if ($milesimas === null) {
                return null;
            }
            $total += Cantidad::importe($milesimas, Dinero::deTexto($producto['precio_venta']));
        }
        return $total;
    }

    /** @return array<string, mixed> la venta con productos, pagos y anulacion */
    private function detalleCompleto(int $idVenta): array
    {
        $venta = $this->consultas->venta($idVenta);
        $pagos = array_map(function (PagoVenta $p): array {
            $fila = [
                'metodo'  => $p->getNombreMetodo(),
                'monto'   => number_format($p->getMontoPagado(), 2, '.', ''),
                'estado'  => $p->getEstado(),
                'detalle' => $p->detalle(),
            ];
            if ($p instanceof PagoEfectivo) {
                $fila['recibido'] = number_format($p->getMontoRecibido(), 2, '.', '');
                $fila['vuelto'] = number_format($p->getVuelto(), 2, '.', '');
            }
            return $fila;
        }, $this->ventas->pagosDeVenta($idVenta));
        $anulacion = $this->ventas->anulacionDeVenta($idVenta);

        return $venta + [
            'lineas'    => $this->consultas->detalleVenta($idVenta),
            'pagos'     => $pagos,
            'anulacion' => $anulacion === null ? null : [
                'fecha_hora' => $anulacion->getFechaHora(),
                'motivo'     => $anulacion->getMotivo(),
            ],
            'puede_anular' => $venta['estado'] === 'EMITIDA' && $this->usuario?->puedeAnularVenta($venta['id_tienda']) === true
                && $this->ventas->devolucionesDeVenta($idVenta) === [],
        ];
    }
}
