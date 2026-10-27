<?php
declare(strict_types=1);

require_once __DIR__ . '/Controller.php';
require_once __DIR__ . '/../models/EmpleadoRepository.php';
require_once __DIR__ . '/../models/IntentoLoginRepository.php';
require_once __DIR__ . '/../models/servicios/EmpleadoServicio.php';

/**
 * C: entrar con DNI y clave, salir y cambiar la clave propia.
 */
class AuthController extends Controller
{
    // Hash de relleno: si el DNI no existe igual se llama a password_verify,
    // asi la respuesta tarda lo mismo y no revela que DNI estan registrados
    private const HASH_RELLENO = '$2y$10$6Smnl4tMrPrGB3pGyXDm1OWkegngDRho.SdSydahKxDzWJpjAQDf.';

    public function __construct(
        TiendaRepository $tiendas,
        Sesion $sesion,
        ?Usuario $usuario,
        private EmpleadoRepository $empleados,
        private IntentoLoginRepository $intentos,
        private EmpleadoServicio $servicio
    ) {
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

        $dniValido = preg_match('/^[0-9]{8}$/', $dni) === 1 ? $dni : null;
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'desconocida');

        // Muchas claves erradas seguidas: se espera antes de probar otra (ni siquiera se revisa la clave)
        if ($this->intentos->estaBloqueado($dniValido, $ip)) {
            http_response_code(429);
            $this->formulario($dni, sprintf(
                'Demasiados intentos fallidos. Espera %d minutos o pide ayuda al administrador.',
                IntentoLoginRepository::MINUTOS
            ));
            return;
        }

        $credenciales = $dniValido !== null ? $this->empleados->credencialesPorDni($dniValido) : null;
        $hash = $credenciales['claveHash'] ?? null;
        $claveCorrecta = password_verify($clave, $hash ?? self::HASH_RELLENO) && $hash !== null;
        $empleado = $claveCorrecta ? $this->empleados->buscarPorId($credenciales['idEmpleado']) : null;

        $entro = $empleado !== null && $empleado->estaActivo();
        $this->intentos->registrar($dniValido, $ip, $entro);
        if (!$entro) {
            http_response_code(401);
            // El mismo mensaje para DNI inexistente, clave mala o empleado inactivo
            $this->formulario($dni, 'DNI o clave incorrectos.');
            return;
        }

        $this->sesion->entrar($empleado->getIdEmpleado());
        $this->sesion->marcarBienvenida();
        $this->redirigir('/?ruta=inicio', 'Hola, ' . $empleado->getNombres() . '.');
    }

    // POST /?ruta=salir
    public function salir(): void
    {
        $this->sesion->salir();
        header('Location: /?ruta=login', true, 303);
    }

    // GET /?ruta=mi_clave
    public function formularioClave(?string $error = null): void
    {
        $this->vista('auth/mi_clave', get_defined_vars());
    }

    // POST /?ruta=mi_clave_guardar
    public function cambiarClave(array $post): void
    {
        try {
            $this->servicio->cambiarMiClave(
                $this->usuario,
                $this->campo($post, 'actual'),
                $this->campo($post, 'nueva'),
                $this->campo($post, 'repetida')
            );
        } catch (ReglaNegocioException $ex) {
            http_response_code(422);
            $this->formularioClave($ex->getMessage());
            return;
        }
        $this->redirigir('/?ruta=inicio', 'Tu clave fue cambiada.');
    }
}
