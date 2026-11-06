<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/TiendaRepository.php';
require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/ReglaNegocioException.php';
require_once __DIR__ . '/../config/Sesion.php';

/**
 * Lo comun a todos los controladores: la sesion, quien entro, leer la tienda pedida,
 * mostrar una vista y responder 403, 404 o redirigir.
 * Es abstract: no se usa solo, cada controlador hereda de el.
 */
abstract class Controller
{
    protected const TIENDA_POR_DEFECTO = 1; // Mass Cayma

    // La ruta pedida, para resaltar la opcion del menu (la fija public/index.php)
    private static string $rutaActual = 'inicio';

    public static function marcarRuta(string $ruta): void
    {
        self::$rutaActual = $ruta;
    }

    // Como quiere ver el menu lateral este usuario: '' (fijo), 'iconos' o 'auto' (lo fija public/index.php)
    private static string $modoMenu = '';

    public static function marcarModoMenu(?string $modo): void
    {
        self::$modoMenu = in_array($modo, ['iconos', 'auto'], true) ? $modo : '';
    }

    // Cuantos lotes vencen esta semana y cuantos productos llegaron a su minimo en la tienda del usuario,
    // para los numeros del menu de Inventario (lo fija public/index.php; solo se calcula si el rol los ve)
    private static ?Closure $alertasInventario = null;

    public static function usarAlertasInventario(Closure $calcular): void
    {
        self::$alertasInventario = $calcular;
    }

    // Estado del turno de caja de quien entro ('abierto', 'pausa', 'revision' o 'cerrado'), para el indicador de la barra superior.
    // Lo fija public/index.php; solo se consulta para quien maneja caja (los demas no ven el indicador)
    private static ?Closure $estadoTurno = null;

    public static function usarEstadoTurno(Closure $calcular): void
    {
        self::$estadoTurno = $calcular;
    }

    public function __construct(
        protected TiendaRepository $tiendas,
        protected Sesion $sesion,
        protected ?Usuario $usuario
    ) {}

    public function noEncontrado(string $mensaje = 'La página no existe.'): void
    {
        http_response_code(404);
        $this->vista('errores/404', ['mensaje' => $mensaje]);
    }

    // El usuario entro, pero su rol no le permite esto. El permiso se revisa aqui, en PHP.
    public function prohibido(string $mensaje): void
    {
        http_response_code(403);
        $this->vista('errores/403', ['mensaje' => $mensaje]);
    }

    /**
     * Muestra una vista con sus datos, mas lo que necesita el layout:
     * el usuario actual, el token CSRF y el aviso pendiente.
     */
    protected function vista(string $__vista, array $__datos = []): void
    {
        unset($__datos['this']);
        extract($__datos, EXTR_SKIP);
        $usuarioActual = $this->usuario;
        $csrf = $this->sesion->tokenCsrf();
        $aviso = $this->sesion->tomarAviso();
        $bienvenida = $this->usuario !== null && $this->sesion->tomarBienvenida();
        $rutaActual = self::$rutaActual;
        $modoMenu = self::$modoMenu;
        $alertasInventario = $this->usuario !== null && self::$alertasInventario !== null
            && $this->usuario->puedeVerIngresos($this->usuario->getIdTienda())
            ? (self::$alertasInventario)($this->usuario->getIdTienda()) : [];
        // La tienda del usuario, para el menu lateral
        $nombreTiendaActual = $this->usuario !== null
            ? ($this->tiendas->buscarPorId($this->usuario->getIdTienda())?->getNombreTienda() ?? '') : '';
        // El administrador elige la sucursal en el menu lateral: todas las tiendas, con la actual marcada
        $tiendasParaElegir = $this->usuario?->puedeElegirTienda() === true ? $this->tiendas->todas() : [];
        $estadoTurno = $this->usuario !== null && self::$estadoTurno !== null && $this->usuario->puedeManejarCaja()
            ? (self::$estadoTurno)($this->usuario->getIdEmpleado()) : null;
        require __DIR__ . '/../../frontend/views/' . $__vista . '.php';
    }

    // Despues de un POST que guardo algo: aviso + redireccion (asi F5 no repite la operacion)
    // $tipo: 'ok' (verde) o 'alerta' (ambar: se guardo, pero hay algo que mirar)
    protected function redirigir(string $url, string $mensaje, string $tipo = 'ok'): void
    {
        $this->sesion->avisar($tipo === 'alerta' ? 'alerta' : 'ok', $mensaje);
        header('Location: ' . $url, true, 303);
    }

    /**
     * Solo acepta un id de tienda que exista y que el usuario pueda ver; si no, usa la del usuario
     * (o la tienda por defecto). El cajero y el almacenero no salen de su sucursal aunque cambien
     * el &tienda= de la URL: se les muestra la suya, nunca la otra.
     */
    protected function leerTienda(?string $valor): Tienda
    {
        if ($valor !== null && ctype_digit($valor) && $this->puedeVerTienda((int) $valor)) {
            $tienda = $this->tiendas->buscarPorId((int) $valor);
            if ($tienda !== null) {
                return $tienda;
            }
        }
        return $this->tiendas->buscarPorId($this->usuario?->getIdTienda() ?? self::TIENDA_POR_DEFECTO);
    }

    // Ver los datos (stock, ventas, kardex, personal) de esa sucursal: el administrador, todas; los demas, solo la suya
    protected function puedeVerTienda(int $idTienda): bool
    {
        return $this->usuario !== null
            && ($this->usuario->puedeElegirTienda() || $idTienda === $this->usuario->getIdTienda());
    }

    // La unica sucursal que este usuario puede ver; null si puede ver todas (el administrador)
    protected function tiendaRestringida(): ?int
    {
        return $this->usuario?->puedeElegirTienda() === true ? null : $this->usuario?->getIdTienda();
    }

    /**
     * Las tiendas que este usuario puede ver (pestañas, columnas del stock, lista de tiendas).
     * @return array<int, Tienda>
     */
    protected function tiendasVisibles(): array
    {
        return array_filter($this->tiendas->todas(), fn(Tienda $t): bool => $this->puedeVerTienda($t->getIdTienda()));
    }

    /**
     * Lineas de producto de un formulario: producto[], cantidad[] y costo[] -> [[id_producto, cantidad, costo], ...]
     * @return array<int, array{id_producto: string, cantidad: string, costo: string}>
     */
    protected function lineasDelFormulario(array $post): array
    {
        $productos = is_array($post['producto'] ?? null) ? $post['producto'] : [];
        $cantidades = is_array($post['cantidad'] ?? null) ? $post['cantidad'] : [];
        $costos = is_array($post['costo'] ?? null) ? $post['costo'] : [];   // solo el ingreso de mercaderia
        $vences = is_array($post['vence'] ?? null) ? $post['vence'] : [];   // idem: fecha de vencimiento
        $lineas = [];
        foreach ($productos as $i => $idProducto) {
            $lineas[] = [
                'id_producto' => is_string($idProducto) ? $idProducto : '',
                'cantidad'    => is_string($cantidades[$i] ?? null) ? $cantidades[$i] : '',
                'costo'       => is_string($costos[$i] ?? null) ? $costos[$i] : '',
                'vence'       => is_string($vences[$i] ?? null) ? $vences[$i] : '',
            ];
        }
        return $lineas;
    }

    // Fecha AAAA-MM-DD que existe en el calendario (2026-02-30 no). null si no es valida.
    protected function leerFecha(?string $valor): ?string
    {
        if ($valor === null || preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $valor, $m) !== 1) {
            return null;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $valor : null;
    }

    // Id numerico de la URL o del formulario; 0 si no es un numero (ningun id vale 0)
    protected function leerId(?string $valor): int
    {
        return $valor !== null && preg_match('/^[0-9]{1,9}$/', $valor) === 1 ? (int) $valor : 0;
    }

    /**
     * Descarga un CSV que Excel abre bien (BOM UTF-8 para las tildes).
     * Un texto que empieza con = + - @ (o tabulador / retorno) se abre en Excel como formula: se le antepone un apostrofo.
     * @param string[] $encabezado
     * @param iterable<array<int|string, mixed>> $filas
     */
    protected function enviarCsv(string $nombreArchivo, array $encabezado, iterable $filas): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9_.-]/', '_', $nombreArchivo) . '"');
        $salida = fopen('php://output', 'w');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, $encabezado, ',', '"', '');
        foreach ($filas as $fila) {
            fputcsv($salida, array_map(
                fn($v): string => preg_match('/^[=+\-@\t\r]/', (string) $v) === 1 ? "'" . $v : (string) $v,
                array_values($fila)
            ), ',', '"', '');
        }
        fclose($salida);
    }

    // Un campo de texto del POST; si no es texto (por ejemplo un arreglo) se toma como vacio
    protected function campo(array $post, string $nombre): string
    {
        return is_string($post[$nombre] ?? null) ? $post[$nombre] : '';
    }
}
