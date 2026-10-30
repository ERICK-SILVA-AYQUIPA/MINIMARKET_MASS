<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/CajaRepository.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/servicios/CajaServicio.php';

/**
 * C: el turno del cajero (abrir, cerrar, retiros e ingresos de efectivo) y el arqueo de la tienda.
 */
class CajaController extends Controller
{
    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private CajaRepository $cajas,
        private EmpleadoRepository $empleados,
        private CajaServicio $servicio
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=caja
    public function panel(?string $error = null): void
    {
        $usuario = $this->usuario;
        $esCajero = $usuario?->puedeManejarCaja() === true;
        $esAdministrador = $usuario !== null && $usuario->puedeVerArqueo($usuario->getIdTienda());
        if (!$esCajero && !$esAdministrador) {
            $this->prohibido('La caja es para cajeros y para el administrador de la tienda.');
            return;
        }

        $tienda = $this->tiendas->buscarPorId($usuario->getIdTienda());
        $cajasTienda = $this->tiendas->cajasDeTienda($tienda->getIdTienda());
        $numeroCaja = [];
        foreach ($cajasTienda as $c) {
            $numeroCaja[$c->getIdCaja()] = $c->getNumeroCaja();
        }
        $miTurno = $esCajero ? $this->cajas->turnoAbiertoDe($usuario->getIdEmpleado()) : null;

        // El cajero ve solo sus turnos; el administrador, todos los de su tienda
        $arqueo = array_values(array_filter(
            $this->cajas->arqueoDeTienda($tienda->getIdTienda()),
            fn(array $fila): bool => $esAdministrador || $fila['turno']->getIdEmpleado() === $usuario->getIdEmpleado()
        ));
        // Lo que deberia haber ahora en la gaveta del turno abierto, y sus retiros e ingresos
        $miArqueo = null;
        foreach ($arqueo as $fila) {
            if ($miTurno !== null && $fila['turno']->getIdCajaTurno() === $miTurno->getIdCajaTurno()) {
                $miArqueo = $fila;
            }
        }
        $movimientos = [];
        foreach ($arqueo as $fila) {
            $movimientos[$fila['turno']->getIdCajaTurno()] = $this->cajas->movimientosDeTurno($fila['turno']->getIdCajaTurno());
        }
        $nombresEmpleado = [];
        foreach ($this->empleados->deTienda($tienda->getIdTienda()) as $empleado) {
            $nombresEmpleado[$empleado->getIdEmpleado()] = $empleado->nombreCompleto();
        }
        $this->vista('caja/panel', get_defined_vars());
    }

    // POST /?ruta=turno_abrir
    public function abrir(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeManejarCaja()) {
            $this->prohibido('Solo un cajero abre turnos.');
            return;
        }
        $idCaja = $this->campo($post, 'caja');
        try {
            $id = $this->servicio->abrirTurno($this->usuario, ctype_digit($idCaja) ? (int) $idCaja : 0, $this->campo($post, 'monto_inicial'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->panel($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=caja', "Turno $id abierto.");
    }

    // POST /?ruta=turno_cerrar
    public function cerrar(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeManejarCaja()) {
            $this->prohibido('Solo un cajero cierra turnos.');
            return;
        }
        try {
            $this->servicio->cerrarTurno($this->usuario, $this->campo($post, 'monto_cierre'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->panel($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=caja', 'Turno cerrado. Revisa la diferencia del arqueo.');
    }

    // POST /?ruta=caja_movimiento: retiro o ingreso de efectivo en el turno abierto
    public function movimiento(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeManejarCaja()) {
            $this->prohibido('Solo un cajero mueve efectivo de su caja.');
            return;
        }
        $tipo = $this->campo($post, 'tipo');
        try {
            $this->servicio->registrarMovimiento($this->usuario, $tipo, $this->campo($post, 'monto'), $this->campo($post, 'motivo'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->panel($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=caja', $tipo === 'RETIRO' ? 'Retiro registrado: ya se descontó de lo esperado.' : 'Ingreso de efectivo registrado.');
    }
}
