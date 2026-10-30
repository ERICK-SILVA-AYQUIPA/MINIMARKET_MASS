<?php
declare(strict_types=1);

require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../models/ReglaNegocioException.php';

/**
 * Un error de la API con su codigo HTTP: 400, 401, 403, 404, 409...
 * public/api/index.php lo convierte en {"ok": false, "error": {...}}.
 */
final class ErrorApi extends RuntimeException
{
    public function __construct(private int $http, private string $clave, string $mensaje)
    {
        parent::__construct($mensaje);
    }

    public function getHttp(): int { return $this->http; }
    public function getClave(): string { return $this->clave; }
}

/**
 * Lo comun a los controladores de la API /api/v1 que usa la app Android.
 * No muestran vistas: cada metodo devuelve un arreglo y public/api/index.php lo responde como JSON.
 * Las reglas del negocio NO estan aqui: se llaman los mismos servicios y repositorios que usa la pagina web.
 */
abstract class ApiController
{
    public function __construct(protected ?Usuario $usuario) {}

    // El usuario del token; la ruta ya exigio token, asi que nunca deberia faltar
    protected function usuario(): Usuario
    {
        if ($this->usuario === null) {
            throw new ErrorApi(401, 'NO_AUTENTICADO', 'Inicia sesión otra vez.');
        }
        return $this->usuario;
    }

    protected function prohibido(string $mensaje): never
    {
        throw new ErrorApi(403, 'PROHIBIDO', $mensaje);
    }

    protected function noEncontrado(string $mensaje): never
    {
        throw new ErrorApi(404, 'NO_ENCONTRADO', $mensaje);
    }

    // Un texto del JSON recibido; un numero se acepta como texto ("2" o 2), lo demas cuenta como vacio
    protected function texto(array $datos, string $clave): string
    {
        $valor = $datos[$clave] ?? null;
        return match (true) {
            is_string($valor)            => $valor,
            is_int($valor)               => (string) $valor,
            default                      => '',
        };
    }

    // Id de la URL; 0 si no es un numero (ningun id vale 0)
    protected function id(string $valor): int
    {
        return preg_match('/^[0-9]{1,9}$/', $valor) === 1 ? (int) $valor : 0;
    }

    // Numero de pagina de la consulta (?pagina=2); 1 si no es valido
    protected function pagina(?string $valor): int
    {
        return $valor !== null && preg_match('/^[1-9][0-9]{0,5}$/', $valor) === 1 ? (int) $valor : 1;
    }

    // Fecha AAAA-MM-DD que existe en el calendario. null si no es valida.
    protected function fecha(?string $valor): ?string
    {
        if ($valor === null || preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $valor, $m) !== 1) {
            return null;
        }
        return checkdate((int) $m[2], (int) $m[3], (int) $m[1]) ? $valor : null;
    }

    /**
     * Lineas [{id_producto, cantidad, ...}] del JSON -> el formato de lineas de los servicios (todo texto).
     * @param string[] $extras campos adicionales que se copian (costo, vence)
     * @return array<int, array<string, string>>
     */
    protected function lineas(array $datos, array $extras = []): array
    {
        $lineas = $datos['lineas'] ?? null;
        if (!is_array($lineas) || !array_is_list($lineas) || $lineas === [] || count($lineas) > 200) {
            throw new ErrorApi(400, 'DATOS_INVALIDOS', 'Envía de 1 a 200 líneas de productos.');
        }
        $resultado = [];
        foreach ($lineas as $linea) {
            if (!is_array($linea)) {
                throw new ErrorApi(400, 'DATOS_INVALIDOS', 'Cada línea debe tener id_producto y cantidad.');
            }
            $fila = ['id_producto' => $this->texto($linea, 'id_producto'), 'cantidad' => $this->texto($linea, 'cantidad')];
            // Una cantidad vacia haria que el servicio ignore la linea: aqui es un error
            if ($fila['id_producto'] === '' || $fila['cantidad'] === '') {
                throw new ErrorApi(422, 'REGLA_NEGOCIO', 'Cada línea debe tener id_producto y cantidad.');
            }
            foreach ($extras as $extra) {
                $fila[$extra] = $this->texto($linea, $extra);
            }
            $resultado[] = $fila;
        }
        return $resultado;
    }
}
