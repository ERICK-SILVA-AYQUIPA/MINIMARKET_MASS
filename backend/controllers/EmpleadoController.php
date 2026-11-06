<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/servicios/EmpleadoServicio.php';

/**
 * C: el administrador maneja el personal de SU tienda (alta, cambios, baja y clave).
 */
class EmpleadoController extends Controller
{
    public const ROLES = [1 => 'ADMINISTRADOR', 2 => 'CAJERO', 3 => 'ALMACENERO'];

    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private EmpleadoRepository $empleados,
        private EmpleadoServicio $servicio
    ) {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=empleados
    public function lista(array $viejo = [], ?string $error = null): void
    {
        if (!$this->esAdministrador()) {
            $this->prohibido('Solo el administrador de la tienda ve y cambia su personal.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        $personal = $this->empleados->deTienda($tienda->getIdTienda());
        $roles = self::ROLES;
        $this->vista('empleados/lista', get_defined_vars());
    }

    // GET /?ruta=empleado&id=2
    public function editar(?string $id, array $viejo = [], ?string $error = null): void
    {
        if (!$this->esAdministrador()) {
            $this->prohibido('Solo el administrador de la tienda ve y cambia su personal.');
            return;
        }
        $empleado = $this->empleados->buscarPorId($this->leerId($id));
        // Un empleado de otra tienda se trata como si no existiera
        if ($empleado === null || $empleado->getIdTienda() !== $this->usuario->getIdTienda()) {
            $this->noEncontrado('El empleado no existe en tu tienda.');
            return;
        }
        $roles = self::ROLES;
        $esElMismo = $empleado->getIdEmpleado() === $this->usuario->getIdEmpleado();
        $this->vista('empleados/formulario', get_defined_vars());
    }

    // POST /?ruta=empleado_crear
    public function crear(array $post): void
    {
        if (!$this->esAdministrador()) {
            $this->prohibido('Solo el administrador de la tienda ve y cambia su personal.');
            return;
        }
        try {
            $this->servicio->crear(
                $this->usuario,
                $this->campo($post, 'nombres'),
                $this->campo($post, 'apellidos'),
                $this->campo($post, 'dni'),
                $this->leerId($this->campo($post, 'rol')),
                $this->campo($post, 'clave'),
                $this->campo($post, 'direccion'),
                $this->campo($post, 'telefono')
            );
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            unset($post['clave']); // la clave no vuelve al formulario
            $this->lista($post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=empleados', 'Empleado registrado. Entrega la clave en persona: la cambiará al entrar.');
    }

    // POST /?ruta=empleado_guardar
    public function guardar(array $post): void
    {
        if (!$this->esAdministrador()) {
            $this->prohibido('Solo el administrador de la tienda ve y cambia su personal.');
            return;
        }
        $id = $this->campo($post, 'id');
        try {
            $this->servicio->editar(
                $this->usuario,
                $this->leerId($id),
                $this->campo($post, 'nombres'),
                $this->campo($post, 'apellidos'),
                $this->leerId($this->campo($post, 'rol')),
                $this->campo($post, 'estado'),
                [
                    'dni'       => $this->campo($post, 'dni'),
                    'direccion' => $this->campo($post, 'direccion'),
                    'telefono'  => $this->campo($post, 'telefono'),
                ]
            );
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->editar($id, $post, $ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=empleados', 'Datos del empleado actualizados.');
    }

    // POST /?ruta=empleado_clave
    public function reiniciarClave(array $post): void
    {
        if (!$this->esAdministrador()) {
            $this->prohibido('Solo el administrador de la tienda ve y cambia su personal.');
            return;
        }
        $id = $this->campo($post, 'id');
        try {
            $idEmpleado = $this->leerId($id);
            $version = $this->servicio->reiniciarClave($this->usuario, $idEmpleado, $this->campo($post, 'clave'));
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->editar($id, [], $ex->getMessage());
            return;
        }
        if ($idEmpleado === $this->usuario->getIdEmpleado()) {
            // Se reinicio su propia clave: sigue en esta sesion
            $this->sesion->actualizarVersionClave($version);
            $this->redirigir('/?ruta=empleados', 'Tu clave fue cambiada. Se cerraron tus sesiones en otras PCs y en la app.');
            return;
        }
        $this->redirigir('/?ruta=empleados', 'Clave cambiada y sesiones del empleado cerradas. Entrégala en persona: la cambiará al entrar.');
    }

    private function esAdministrador(): bool
    {
        return $this->usuario !== null && $this->usuario->puedeAdministrarTienda($this->usuario->getIdTienda());
    }
}
