<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/ClienteRepository.php';
require_once __DIR__ . '/../models/VentaRepository.php';
require_once __DIR__ . '/../models/Paginacion.php';
require_once __DIR__ . '/../models/servicios/ClienteServicio.php';

/**
 * C: clientes (DNI y RUC, nombre, direccion y telefono), registrarlos a mano, sus compras y completar el documento que les falta.
 * El DNI y el RUC son datos personales: solo los ven el cajero y el administrador.
 */
class ClienteController extends Controller
{
    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private ClienteRepository $clientes,
        private VentaRepository $ventas,
        private ClienteServicio $servicio
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=clientes&documento=7012&pagina=2 (documento: inicio del DNI/RUC, o parte del nombre)
    public function lista(?string $documento, ?string $pagina, ?string $ver = null, array $viejo = [], ?string $error = null): void
    {
        if (!$this->puedeVerClientes()) {
            $this->prohibido('Los clientes los ven el cajero y el administrador.');
            return;
        }
        // Digitos: el inicio de un DNI o de un RUC. Texto: parte del nombre
        $documento = $documento !== null ? trim($documento) : null;
        $documento = $documento !== null && (preg_match('/^[0-9]{1,11}$/', $documento) === 1
            || (!ctype_digit($documento) && mb_strlen($documento) >= 2 && mb_strlen($documento) <= 60)) ? $documento : null;
        $paginacion = new Paginacion($this->clientes->contar($documento), $pagina);
        $filas = $this->clientes->buscar($documento, $paginacion->getPorPagina(), $paginacion->offset(), $this->tiendaRestringida());
        $urlPagina = '/?' . http_build_query(['ruta' => 'clientes', 'documento' => $documento]);

        // Ficha del cliente en el panel lateral (&ver=ID); si no existe, solo se muestra la lista
        $ficha = null;
        if ($ver !== null && preg_match('/^[1-9][0-9]{0,9}$/', $ver) === 1) {
            $elegido = $this->clientes->buscarPorId((int) $ver);
            $ficha = $elegido !== null ? $this->datosFicha($elegido) : null;
        }
        unset($elegido);
        $this->vista('clientes/lista', get_defined_vars());
    }

    // GET /?ruta=cliente&id=2
    public function detalle(?string $id, ?string $error = null): void
    {
        if (!$this->puedeVerClientes()) {
            $this->prohibido('Los clientes los ven el cajero y el administrador.');
            return;
        }
        $cliente = $this->clientes->buscarPorId($this->leerId($id));
        if ($cliente === null) {
            $this->noEncontrado('El cliente no existe.');
            return;
        }
        $this->vista('clientes/detalle', $this->datosFicha($cliente) + ['error' => $error]);
    }

    /**
     * Lo que muestra la ficha de un cliente: sus datos y sus compras (en toda la cadena o, para el cajero, en su sucursal).
     * @return array{cliente: Cliente, compras: array<int, array{venta: Venta, serie: SerieComprobante, tienda: ?Tienda}>}
     */
    private function datosFicha(Cliente $cliente): array
    {
        $compras = [];
        // El administrador ve las compras del cliente en toda la cadena; el cajero, solo las de su sucursal
        foreach ($this->ventas->ventasDeCliente($cliente->getIdCliente(), $this->tiendaRestringida()) as $venta) {
            $serie = $this->ventas->buscarSerie($venta->getIdSerie());
            if ($serie === null) {
                continue;
            }
            $compras[] = ['venta' => $venta, 'serie' => $serie, 'tienda' => $this->tiendas->buscarPorId($serie->getIdTienda())];
        }
        return ['cliente' => $cliente, 'compras' => $compras];
    }

    // POST /?ruta=cliente_crear
    public function crear(array $post): void
    {
        if (!$this->puedeVerClientes()) {
            $this->prohibido('Los clientes los ven el cajero y el administrador.');
            return;
        }
        try {
            $id = $this->servicio->crear(
                $this->usuario,
                $this->campo($post, 'nombre'),
                $this->campo($post, 'dni'),
                $this->campo($post, 'ruc'),
                $this->campo($post, 'direccion'),
                $this->campo($post, 'telefono')
            );
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->lista(null, null, null, $post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=clientes&ver=' . $id, 'Cliente registrado.');
    }

    // POST /?ruta=cliente_contacto
    public function guardarContacto(array $post): void
    {
        if (!$this->puedeVerClientes()) {
            $this->prohibido('Los clientes los ven el cajero y el administrador.');
            return;
        }
        $id = $this->campo($post, 'id');
        try {
            $this->servicio->editarContacto(
                $this->usuario,
                $this->leerId($id),
                $this->campo($post, 'nombre'),
                $this->campo($post, 'direccion'),
                $this->campo($post, 'telefono')
            );
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->detalle($id, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=cliente&id=' . $this->leerId($id), 'Datos del cliente guardados.');
    }

    // POST /?ruta=cliente_documento
    public function completarDocumento(array $post): void
    {
        if (!$this->puedeVerClientes()) {
            $this->prohibido('Los clientes los ven el cajero y el administrador.');
            return;
        }
        $id = $this->campo($post, 'id');
        try {
            $this->servicio->completarDocumento($this->usuario, $this->leerId($id), $this->campo($post, 'dni'), $this->campo($post, 'ruc'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->detalle($id, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=cliente&id=' . $this->leerId($id), 'Documento agregado.');
    }

    private function puedeVerClientes(): bool
    {
        return $this->usuario !== null
            && ($this->usuario->puedeVender() || $this->usuario->puedeAdministrarTienda($this->usuario->getIdTienda()));
    }
}
