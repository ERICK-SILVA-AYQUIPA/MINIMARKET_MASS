<?php
declare(strict_types=1);

require_once __DIR__ . '/ApiController.php';
require_once __DIR__ . '/../../models/EmpleadoRepository.php';
require_once __DIR__ . '/../../models/IntentoLoginRepository.php';
require_once __DIR__ . '/../../models/TokenApiRepository.php';
require_once __DIR__ . '/../../models/TiendaRepository.php';

/**
 * Login de la app con el mismo DNI y la misma clave de la pagina web.
 * Usa el mismo bloqueo por intentos fallidos (tabla intento_login) que AuthController.
 */
class AuthApiController extends ApiController
{
    // El mismo hash de relleno que AuthController: un DNI inexistente tarda lo mismo que una clave errada
    private const HASH_RELLENO = '$2y$10$6Smnl4tMrPrGB3pGyXDm1OWkegngDRho.SdSydahKxDzWJpjAQDf.';

    public function __construct(
        ?Usuario $usuario,
        private EmpleadoRepository $empleados,
        private IntentoLoginRepository $intentos,
        private TokenApiRepository $tokens,
        private TiendaRepository $tiendas
    ) {
        parent::__construct($usuario);
    }

    // POST /api/v1/auth/login  {"dni": "70000002", "clave": "...", "dispositivo": "Pixel 7"}
    public function entrar(array $datos, string $ip): array
    {
        $dni = trim($this->texto($datos, 'dni'));
        $clave = $this->texto($datos, 'clave');
        $dispositivo = mb_substr(trim($this->texto($datos, 'dispositivo')), 0, 60);
        $dniValido = preg_match('/^[0-9]{8}$/', $dni) === 1 ? $dni : null;

        if ($this->intentos->estaBloqueado($dniValido, $ip)) {
            throw new ErrorApi(429, 'BLOQUEADO', sprintf(
                'Demasiados intentos fallidos. Espera %d minutos o pide ayuda al administrador.', IntentoLoginRepository::MINUTOS
            ));
        }

        $credenciales = $dniValido !== null ? $this->empleados->credencialesPorDni($dniValido) : null;
        $hash = $credenciales['claveHash'] ?? null;
        $claveCorrecta = password_verify($clave, $hash ?? self::HASH_RELLENO) && $hash !== null;
        $empleado = $claveCorrecta ? $this->empleados->buscarPorId($credenciales['idEmpleado']) : null;

        $entro = $empleado !== null && $empleado->estaActivo();
        $this->intentos->registrar($dniValido, $ip, $entro);
        if (!$entro) {
            // El mismo mensaje para DNI inexistente, clave mala o empleado inactivo
            throw new ErrorApi(401, 'CREDENCIALES', 'DNI o clave incorrectos.');
        }

        $sesion = $this->tokens->crear($empleado->getIdEmpleado(), $dispositivo === '' ? null : $dispositivo);
        return ['token' => $sesion['token'], 'expira' => $sesion['expira'], 'usuario' => $this->perfil($empleado)];
    }

    // POST /api/v1/auth/logout: el token deja de servir
    public function salir(string $token): array
    {
        $this->tokens->revocar($token);
        return ['mensaje' => 'Sesión cerrada.'];
    }

    // GET /api/v1/auth/yo: quien es y que puede hacer (la app arma su menu con esto)
    public function yo(): array
    {
        return $this->perfil($this->usuario());
    }

    /** @return array<string, mixed> */
    private function perfil(Usuario $u): array
    {
        $idTienda = $u->getIdTienda();
        return [
            'id_empleado' => $u->getIdEmpleado(),
            'nombres'     => $u->getNombres(),
            'apellidos'   => $u->getApellidos(),
            'dni'         => $u->dniOculto(),
            'rol'         => $u->getNombreRol(),
            'id_tienda'   => $idTienda,
            'tienda'      => $this->tiendas->buscarPorId($idTienda)?->getNombreTienda() ?? '',
            // Los mismos permisos de las clases de rol: el servidor los vuelve a revisar en cada operacion
            'permisos'    => [
                'vender'            => $u->puedeVender(),
                'manejar_caja'      => $u->puedeManejarCaja(),
                'registrar_ingreso' => $u->puedeRegistrarIngreso($idTienda),
                'ver_ingresos'      => $u->puedeVerIngresos($idTienda),
                'crear_producto'    => $u->puedeCrearProducto(),
                'anular_venta'      => $u->puedeAnularVenta($idTienda),
                'ver_inventario'    => true,
                'ver_ventas'        => true,
            ],
        ];
    }
}
