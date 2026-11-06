<?php
declare(strict_types=1);

/**
 * Variables de configuracion: salen del sistema o del archivo .env (CLAVE=valor), nunca del codigo.
 * La usan Database (conexion), Emisor (datos del ticket) y FacturacionElectronica (proveedor OSE/PSE).
 */
final class Entorno
{
    private static bool $cargado = false;

    // Valor de la variable; si no existe o esta vacia, $porDefecto
    public static function leer(string $clave, string $porDefecto = ''): string
    {
        self::cargar();
        $valor = getenv($clave);
        return $valor === false || trim($valor) === '' ? $porDefecto : $valor;
    }

    // Lee lineas CLAVE=valor del .env una sola vez. Una variable que ya existe en el sistema no se pisa.
    public static function cargar(?string $ruta = null): void
    {
        if (self::$cargado && $ruta === null) {
            return;
        }
        self::$cargado = true;
        $ruta ??= __DIR__ . '/../../.env';
        if (!is_readable($ruta)) {
            return;
        }
        foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
            $linea = trim($linea);
            if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
                continue;
            }
            [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
            // Comillas opcionales: EMPRESA_RAZON_SOCIAL="MI EMPRESA S.A.C."
            if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && $valor[-1] === $valor[0]) {
                $valor = substr($valor, 1, -1);
            }
            if (getenv($clave) === false) {
                putenv($clave . '=' . $valor);
            }
        }
    }
}
