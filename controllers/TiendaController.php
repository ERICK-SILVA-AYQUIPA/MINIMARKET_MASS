<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/VentaRepository.php';
require_once __DIR__ . '/../models/servicios/TiendaServicio.php';

/**
 * C: las tiendas con su distrito, cajas, series de comprobante y personal,
 * y su mantenimiento (abrir una tienda, editar la propia, cajas y distritos).
 */
class TiendaController extends Controller
{
    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private EmpleadoRepository $empleados,
        private VentaRepository $ventas,
        private TiendaServicio $servicio
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=tiendas
    public function lista(): void
    {
        $filas = [];
        foreach ($this->tiendas->todas() as $idTienda => $tienda) {
            $filas[] = [
                'tienda'    => $tienda,
                'distrito'  => $this->tiendas->nombreDistrito($tienda->getIdDistrito()) ?? '-',
                'cajas'     => $this->tiendas->cajasDeTienda($idTienda),
                'series'    => $this->ventas->seriesDeTienda($idTienda),
                'empleados' => $this->empleados->deTienda($idTienda),
            ];
        }

        $puedeAbrir = $this->usuario?->puedeAbrirTiendas() === true;
        $this->vista('tiendas/lista', get_defined_vars());
    }

    // GET /?ruta=tienda_editar: datos, cajas y series de la tienda del administrador
    public function editar(array $viejo = [], ?string $error = null): void
    {
        if ($this->usuario === null || !$this->usuario->puedeAdministrarTienda($this->usuario->getIdTienda())) {
            $this->prohibido('Solo el administrador de la tienda cambia sus datos.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        $distritos = $this->tiendas->distritos();
        $cajas = $this->tiendas->cajasDeTienda($tienda->getIdTienda());
        $series = $this->ventas->seriesDeTienda($tienda->getIdTienda());
        $puedeAbrir = $this->usuario->puedeAbrirTiendas();
        $this->vista('tiendas/formulario', get_defined_vars());
    }

    // POST /?ruta=tienda_guardar
    public function guardar(array $post): void
    {
        try {
            $this->servicio->editar($this->usuario, $this->usuario->getIdTienda(), $this->datosTienda($post), $this->campo($post, 'estado'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->editar($post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=tienda_editar', 'Datos de la tienda guardados.');
    }

    // GET /?ruta=tienda_nueva
    public function nueva(array $viejo = [], ?string $error = null): void
    {
        if ($this->usuario?->puedeAbrirTiendas() !== true) {
            $this->prohibido('Solo un administrador abre tiendas nuevas.');
            return;
        }
        $distritos = $this->tiendas->distritos();
        $this->vista('tiendas/nueva', get_defined_vars());
    }

    // POST /?ruta=tienda_abrir
    public function abrir(array $post): void
    {
        try {
            $id = $this->servicio->abrir($this->usuario, $this->datosTienda($post), [
                'nombres'   => $this->campo($post, 'admin_nombres'),
                'apellidos' => $this->campo($post, 'admin_apellidos'),
                'dni'       => $this->campo($post, 'admin_dni'),
                'clave'     => $this->campo($post, 'admin_clave'),
            ]);
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            unset($post['admin_clave']); // la clave no vuelve al formulario
            $this->nueva($post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=tiendas', "Tienda $id abierta con su caja 1, sus series y su administrador.");
    }

    // POST /?ruta=caja_crear
    public function agregarCaja(): void
    {
        try {
            $numero = $this->servicio->agregarCaja($this->usuario);
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->editar([], $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=tienda_editar', "Caja $numero agregada.");
    }

    // POST /?ruta=caja_estado
    public function estadoCaja(array $post): void
    {
        try {
            $this->servicio->cambiarEstadoCaja($this->usuario, $this->leerId($this->campo($post, 'id')), $this->campo($post, 'estado'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->editar([], $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=tienda_editar', 'Estado de la caja actualizado.');
    }

    // POST /?ruta=distrito_crear (desde el formulario de tienda nueva)
    public function crearDistrito(array $post): void
    {
        try {
            $this->servicio->crearDistrito($this->usuario, $this->campo($post, 'nombre'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->nueva([], $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=tienda_nueva', 'Distrito agregado.');
    }

    /** @return array{nombre: string, direccion: string, telefono: string, distrito: string} */
    private function datosTienda(array $post): array
    {
        return [
            'nombre'    => $this->campo($post, 'nombre'),
            'direccion' => $this->campo($post, 'direccion'),
            'telefono'  => $this->campo($post, 'telefono'),
            'distrito'  => $this->campo($post, 'distrito'),
        ];
    }
}
