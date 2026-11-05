<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/PromocionRepository.php';
require_once __DIR__ . '/../models/ProductoRepository.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/servicios/PromocionServicio.php';

/**
 * C: promociones de la tienda. El administrador las crea, pausa y reactiva; el cajero las ve (se aplican solas al vender).
 */
class PromocionController extends Controller
{
    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private PromocionRepository $promociones,
        private ProductoRepository $productos,
        private EmpleadoRepository $empleados,
        private PromocionServicio $servicio
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=promociones
    public function lista(array $viejo = [], ?string $error = null): void
    {
        if ($this->usuario === null) {
            $this->prohibido('Entra con tu cuenta.');
            return;
        }
        $idTienda = $this->usuario->getIdTienda();
        if (!$this->usuario->puedeVender() && !$this->usuario->puedeAdministrarPromociones($idTienda)) {
            $this->prohibido('Las promociones las ven el administrador y los cajeros.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($idTienda);
        $hoy = date('Y-m-d');
        $filas = $this->promociones->deTienda($idTienda);
        $puedeCrear = $this->usuario->puedeAdministrarPromociones($idTienda);
        $puedeCadena = $this->usuario->puedeAdministrarPromociones(null);
        $productos = array_values(array_filter($this->productos->todos(), fn(Producto $p): bool => $p->estaActivo()));
        $nombresEmpleado = [];
        foreach ($this->empleados->todos() as $empleado) {
            $nombresEmpleado[$empleado->getIdEmpleado()] = $empleado->nombreCompleto();
        }
        $topeCajero = Promocion::porcentajeATexto(Cajero::TOPE_DESCUENTO);
        unset($empleado);
        $this->vista('promociones/lista', get_defined_vars());
    }

    // POST /?ruta=promocion_crear
    public function crear(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeAdministrarPromociones($this->usuario->getIdTienda())) {
            $this->prohibido('Solo el administrador crea promociones.');
            return;
        }
        $datos = [];
        foreach (['nombre', 'tipo', 'id_producto', 'alcance', 'porcentaje', 'precio', 'cantidad_pack', 'monto_minimo', 'fecha_inicio', 'fecha_fin'] as $c) {
            $datos[$c] = $this->campo($post, $c);
        }
        try {
            $this->servicio->crear($this->usuario, $datos);
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->lista($post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=promociones', 'Promoción creada: se aplica sola al vender mientras esté vigente.');
    }

    // POST /?ruta=promocion_estado (pausar o reactivar)
    public function cambiarEstado(array $post): void
    {
        if ($this->usuario === null || !$this->usuario->puedeAdministrarPromociones($this->usuario->getIdTienda())) {
            $this->prohibido('Solo el administrador cambia las promociones.');
            return;
        }
        $estado = $this->campo($post, 'estado');
        try {
            $this->servicio->cambiarEstado($this->usuario, $this->leerId($this->campo($post, 'id_promocion')), $estado);
        } catch (ReglaNegocioException $ex) {
            $this->sesion->avisar('error', $ex->getMessage());
            header('Location: /?ruta=promociones', true, 303);
            return;
        }
        $this->redirigir('/?ruta=promociones', $estado === 'PAUSADA' ? 'Promoción pausada: ya no se aplica.' : 'Promoción activa otra vez.');
    }
}
