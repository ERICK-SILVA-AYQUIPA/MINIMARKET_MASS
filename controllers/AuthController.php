<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';

/**
 * C: entrar con DNI y clave, y salir.
 */
class AuthController extends Controller
{
    // Hash de relleno: si el DNI no existe igual se llama a password_verify,
    // asi la respuesta tarda lo mismo y no revela que DNI estan registrados
    private const HASH_RELLENO = '$2y$10$6Smnl4tMrPrGB3pGyXDm1OWkegngDRho.SdSydahKxDzWJpjAQDf.';

    public function __construct(TiendaRepository $tiendas, Sesion $sesion, ?Usuario $usuario, private EmpleadoRepository $empleados)
    {
        parent::__construct($tiendas, $sesion, $usuario);
    }

    // GET /?ruta=login
    public function formulario(string $dni = '', ?string $error = null): void
    {
        $this->vista('auth/login', get_defined_vars());
    }

    // POST /?ruta=login
    public function entrar(array $post): void
    {
        $dni = trim($this->campo($post, 'dni'));
        $clave = $this->campo($post, 'clave');

        $credenciales = preg_match('/^[0-9]{8}$/', $dni) === 1 ? $this->empleados->credencialesPorDni($dni) : null;
        $hash = $credenciales['claveHash'] ?? null;
        $claveCorrecta = password_verify($clave, $hash ?? self::HASH_RELLENO) && $hash !== null;
        $empleado = $claveCorrecta ? $this->empleados->buscarPorId($credenciales['idEmpleado']) : null;

        if ($empleado === null || !$empleado->estaActivo()) {
            http_response_code(401);
            // El mismo mensaje para DNI inexistente, clave mala o empleado inactivo
            $this->formulario($dni, 'DNI o clave incorrectos.');
            return;
        }

        $this->sesion->entrar($empleado->getIdEmpleado());
        $this->redirigir('/?ruta=productos', 'Hola, ' . $empleado->getNombres() . '.');
    }

    // POST /?ruta=salir
    public function salir(): void
    {
        $this->sesion->salir();
        header('Location: /?ruta=login', true, 303);
    }
}
