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

    // GET /?ruta=caja. $abrir: panel que vuelve abierto tras un error (el arqueo de cierre, para corregir el conteo)
    public function panel(?string $error = null, ?string $abrir = null): void
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
        // Un cierre con diferencia que espera al administrador, o que el administrador mando a contar de nuevo
        $miPendiente = $esCajero && $miTurno === null ? $this->cajas->turnoPendienteDe($usuario->getIdEmpleado()) : null;
        $regla = $this->cajas->reglaDeTienda($tienda->getIdTienda());
        $fondos = $this->cajas->fondosDeTienda($tienda->getIdTienda());
        $arqueosPorTurno = $this->cajas->arqueosDeTienda($tienda->getIdTienda());
        $puedeCambiarReglas = $usuario->puedeAdministrarTienda($tienda->getIdTienda());

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
        // Cada caja de la tienda ahora: libre u ocupada, por quien, desde cuando y cuanto deberia tener
        $turnoDeCaja = [];
        $porRevisar = [];
        foreach ($arqueo as $fila) {
            if ($fila['turno']->estaAbierto()) {
                $turnoDeCaja[$fila['turno']->getIdCaja()] = $fila;
            } elseif ($fila['turno']->getEstado() === 'OBSERVADO') {
                $porRevisar[] = $fila;
            }
        }
        $ahora = new DateTimeImmutable();
        $horasAbierto = fn(CajaTurno $t): float => ($ahora->getTimestamp() - (new DateTimeImmutable($t->getFechaHoraApertura()))->getTimestamp()) / 3600;
        // "2 h 15 min", "3 d 4 h": cuanto lleva abierto un turno, facil de leer de un vistazo
        $tiempoAbierto = static function (float $horas): string {
            $minutos = max(0, (int) round($horas * 60));
            if ($minutos < 60) {
                return $minutos . ' min';
            }
            if ($minutos < 1440) {
                return intdiv($minutos, 60) . ' h ' . sprintf('%02d', $minutos % 60) . ' min';
            }
            return intdiv($minutos, 1440) . ' d ' . intdiv($minutos % 1440, 60) . ' h';
        };
        // Lo vendido en el turno abierto del cajero: ventas, efectivo, tarjeta y billetera Sip
        $misVentas = $miTurno !== null ? $this->cajas->ventasDeTurno($miTurno->getIdCajaTurno()) : null;
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
            $id = $this->servicio->abrirTurno($this->usuario, ctype_digit($idCaja) ? (int) $idCaja : 0, $this->campo($post, 'monto_inicial'),
                $this->conteo($post), $this->campo($post, 'observacion'), true);
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
            $cierre = $this->servicio->cerrarTurno($this->usuario, $this->campo($post, 'monto_cierre'), $this->conteo($post), $this->campo($post, 'observacion'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->panel($ex->getMessage(), 'arqueo-cierre');
            return;
        }
        $this->redirigir('/?ruta=turno_cierre&turno=' . $cierre['turno'], 'Turno ' . $cierre['turno'] . ' cerrado. ' . self::resultado($cierre), $cierre['estado'] === 'OBSERVADO' ? 'alerta' : 'ok');
    }

    /**
     * GET /?ruta=turno_cierre&turno=N: el comprobante de cierre que arma el sistema solo
     * (ventas por medio de pago, lo esperado, lo contado y la diferencia). Lo ve su cajero o el administrador.
     */
    public function comprobanteCierre(?string $turnoTexto): void
    {
        $usuario = $this->usuario;
        $esAdministrador = $usuario !== null && $usuario->puedeVerArqueo($usuario->getIdTienda());
        if ($usuario === null || (!$usuario->puedeManejarCaja() && !$esAdministrador)) {
            $this->prohibido('El cierre de caja es para su cajero y para el administrador de la tienda.');
            return;
        }
        $idTurno = $turnoTexto !== null && ctype_digit($turnoTexto) ? (int) $turnoTexto : 0;
        $tienda = $this->tiendas->buscarPorId($usuario->getIdTienda());
        $fila = null;
        foreach ($this->cajas->arqueoDeTienda($tienda->getIdTienda()) as $f) {
            if ($f['turno']->getIdCajaTurno() === $idTurno) {
                $fila = $f;
            }
        }
        // Un turno abierto todavia no tiene cierre; un cajero solo ve los suyos
        if ($fila === null || $fila['turno']->estaAbierto()
            || (!$esAdministrador && $fila['turno']->getIdEmpleado() !== $usuario->getIdEmpleado())) {
            $this->noEncontrado('No hay un cierre de caja de ese turno.');
            return;
        }
        $turno = $fila['turno'];
        $ventas = $this->cajas->ventasDeTurno($idTurno);
        $conteo = null;
        foreach ($this->cajas->arqueosDeTienda($tienda->getIdTienda())[$idTurno] ?? [] as $a) {
            if ($a['tipo'] === 'CIERRE') {
                $conteo = $a;   // el ultimo conteo de cierre (el reconteo reemplaza al primero)
            }
        }
        $numeroCaja = '-';
        foreach ($this->tiendas->cajasDeTienda($tienda->getIdTienda()) as $c) {
            if ($c->getIdCaja() === $turno->getIdCaja()) {
                $numeroCaja = (string) $c->getNumeroCaja();
            }
        }
        $cajero = '-';
        foreach ($this->empleados->deTienda($tienda->getIdTienda()) as $empleado) {
            if ($empleado->getIdEmpleado() === $turno->getIdEmpleado()) {
                $cajero = $empleado->nombreCompleto();
            }
        }
        $esSuyo = $turno->getIdEmpleado() === $usuario->getIdEmpleado();
        $this->vista('caja/cierre', compact('tienda', 'fila', 'turno', 'ventas', 'conteo', 'numeroCaja', 'cajero', 'esSuyo'));
    }

    // POST /?ruta=turno_recontar: el cajero vuelve a contar el turno que el administrador mando a recontar
    public function recontar(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeManejarCaja()) {
            $this->prohibido('Solo el cajero cuenta su caja.');
            return;
        }
        try {
            $cierre = $this->servicio->recontarTurno($this->usuario, $this->campo($post, 'monto_cierre'), $this->conteo($post), $this->campo($post, 'observacion'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->panel($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=turno_cierre&turno=' . $cierre['turno'], 'Nuevo conteo del turno ' . $cierre['turno'] . '. ' . self::resultado($cierre), $cierre['estado'] === 'OBSERVADO' ? 'alerta' : 'ok');
    }

    // POST /?ruta=turno_pausa: accion=pausar o reanudar
    public function pausa(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeManejarCaja()) {
            $this->prohibido('Solo el cajero pausa su caja.');
            return;
        }
        $pausar = $this->campo($post, 'accion') === 'pausar';
        try {
            $this->servicio->pausarTurno($this->usuario, $pausar);
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->panel($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=caja', $pausar ? 'Caja en pausa: nadie puede vender con tu turno hasta que la reanudes.' : 'Caja reanudada: ya puedes vender.');
    }

    // POST /?ruta=turno_revisar: el administrador aprueba la diferencia o pide un nuevo conteo
    public function revisar(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeVerArqueo($this->usuario->getIdTienda())) {
            $this->prohibido('Solo el administrador de la tienda revisa los cierres.');
            return;
        }
        $idTurno = $this->campo($post, 'turno');
        $decision = $this->campo($post, 'decision');
        try {
            $this->servicio->revisarCierre($this->usuario, ctype_digit($idTurno) ? (int) $idTurno : 0, $decision, $this->campo($post, 'comentario'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->panel($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=caja', $decision === 'APROBAR'
            ? "Diferencia del turno $idTurno aprobada: el turno queda cerrado."
            : "Se pidió al cajero que vuelva a contar el turno $idTurno.");
    }

    // POST /?ruta=arqueo_sorpresa: el administrador cuenta la gaveta de un turno abierto
    public function arqueoSorpresa(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeVerArqueo($this->usuario->getIdTienda())) {
            $this->prohibido('Solo el administrador de la tienda hace arqueos sorpresa.');
            return;
        }
        $idTurno = $this->campo($post, 'turno');
        try {
            $r = $this->servicio->arqueoSorpresa($this->usuario, ctype_digit($idTurno) ? (int) $idTurno : 0,
                $this->campo($post, 'monto'), $this->conteo($post), $this->campo($post, 'observacion'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->panel($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=caja', "Arqueo sorpresa del turno $idTurno guardado. " . self::textoDiferencia($r['diferencia']));
    }

    // POST /?ruta=regla_caja_guardar: tolerancia, tope de gaveta, cierre ciego, horas maximas y fondo de cada caja
    public function guardarReglas(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeAdministrarTienda($this->usuario->getIdTienda())) {
            $this->prohibido('Solo el administrador de la tienda cambia las reglas de caja.');
            return;
        }
        $fondos = is_array($post['fondo'] ?? null) ? array_map(fn($v): string => is_string($v) ? $v : '', $post['fondo']) : [];
        try {
            $this->servicio->guardarReglas($this->usuario, $this->campo($post, 'tolerancia'), $this->campo($post, 'tope'),
                $this->campo($post, 'cierre_ciego') === 'S', $this->campo($post, 'horas'), $fondos);
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->panel($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=caja', 'Reglas de caja guardadas.');
    }

    /**
     * Billetes y monedas contados: conteo[50.00]=1, conteo[20.00]=2...
     * @return array<string, string>
     */
    private function conteo(array $post): array
    {
        $conteo = [];
        foreach (is_array($post['conteo'] ?? null) ? $post['conteo'] : [] as $denominacion => $cantidad) {
            if (is_string($cantidad)) {
                $conteo[(string) $denominacion] = $cantidad;
            }
        }
        return $conteo;
    }

    /** @param array{esperado: int, diferencia: int, estado: string} $cierre */
    private static function resultado(array $cierre): string
    {
        return self::textoDiferencia($cierre['diferencia'])
            . ($cierre['estado'] === 'OBSERVADO' ? ' Pasa la tolerancia de la tienda: el administrador lo revisará.' : '');
    }

    private static function textoDiferencia(int $centimos): string
    {
        if ($centimos === 0) {
            return 'Cuadra exacto.';
        }
        return ($centimos < 0 ? 'Faltan' : 'Sobran') . ' S/ ' . Dinero::aTexto(abs($centimos)) . '.';
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
            $categoria = $this->campo($post, 'categoria');
            $this->servicio->registrarMovimiento($this->usuario, $tipo, $this->campo($post, 'monto'), $this->campo($post, 'motivo'),
                $categoria === '' ? 'OTRO' : $categoria, $this->campo($post, 'comprobante'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->panel($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=caja', $tipo === 'RETIRO' ? 'Retiro registrado: ya se descontó de lo esperado.' : 'Ingreso de efectivo registrado.');
    }
}
