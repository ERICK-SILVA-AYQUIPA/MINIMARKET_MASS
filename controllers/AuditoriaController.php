<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/AuditoriaRepository.php';
require_once __DIR__ . '/../models/Paginacion.php';

/**
 * C: el administrador revisa quien hizo cada accion delicada en SU tienda.
 */
class AuditoriaController extends Controller
{
    public function __construct(TiendaRepository $tiendas, Sesion $sesion, ?Usuario $usuario, private AuditoriaRepository $auditoria)
    {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=auditoria&accion=PRECIO_CAMBIADO&pagina=2
    public function lista(?string $accionPedida, ?string $pagina): void
    {
        if ($this->usuario === null || !$this->usuario->puedeAdministrarTienda($this->usuario->getIdTienda())) {
            $this->prohibido('La auditoría la revisa el administrador de la tienda.');
            return;
        }
        $tienda = $this->tiendas->buscarPorId($this->usuario->getIdTienda());
        $acciones = $this->auditoria->acciones($tienda->getIdTienda());
        // Solo una accion que ya existe en la bitacora; cualquier otra cosa se ignora
        $accion = in_array($accionPedida, $acciones, true) ? $accionPedida : null;

        $paginacion = new Paginacion($this->auditoria->contar($tienda->getIdTienda(), $accion), $pagina, 50);
        $filas = $this->auditoria->pagina($tienda->getIdTienda(), $accion, $paginacion->getPorPagina(), $paginacion->offset());
        $urlPagina = '/?' . http_build_query(['ruta' => 'auditoria', 'accion' => $accion]);
        $this->vista('auditoria/lista', get_defined_vars());
    }
}
