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
        // La tienda del usuario, para el menu lateral
        $nombreTiendaActual = $this->usuario !== null
            ? ($this->tiendas->buscarPorId($this->usuario->getIdTienda())?->getNombreTienda() ?? '') : '';
        require __DIR__ . '/../views/' . $__vista . '.php';
    }

    // Despues de un POST que guardo algo: aviso + redireccion (asi F5 no repite la operacion)
    protected function redirigir(string $url, string $mensaje): void
    {
        $this->sesion->avisar('ok', $mensaje);
        header('Location: ' . $url, true, 303);
    }

    // Solo acepta un id de tienda que exista; si no, usa la del usuario o la tienda por defecto
    protected function leerTienda(?string $valor): Tienda
    {
        if ($valor !== null && ctype_digit($valor)) {
            $tienda = $this->tiendas->buscarPorId((int) $valor);
            if ($tienda !== null) {
                return $tienda;
            }
        }
        return $this->tiendas->buscarPorId($this->usuario?->getIdTienda() ?? self::TIENDA_POR_DEFECTO);
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
        $lineas = [];
        foreach ($productos as $i => $idProducto) {
            $lineas[] = [
                'id_producto' => is_string($idProducto) ? $idProducto : '',
                'cantidad'    => is_string($cantidades[$i] ?? null) ? $cantidades[$i] : '',
                'costo'       => is_string($costos[$i] ?? null) ? $costos[$i] : '',
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
