<?php
declare(strict_types=1);

/**
 * Lee el CSV del catalogo (database/catalogo_inicial.csv es un ejemplo) sin tocar la base.
 * Acepta lo que guarda Excel: separador ; o , con BOM UTF-8, o en ANSI (Windows-1252).
 * Es una clase sin base de datos: se prueba sola en tests/unitarios.php.
 */
final class CatalogoCsv
{
    public const COLUMNAS = ['codigo_barras', 'nombre', 'categoria', 'marca', 'unidad', 'precio_venta', 'al_peso'];
    public const MAXIMO_FILAS = 2000;
    public const MAXIMO_BYTES = 1_048_576;   // 1 MB

    /**
     * @return array{filas: array<int, array<string, string>>, errores: string[]} filas indexadas por su numero de linea
     */
    public static function leer(string $contenido): array
    {
        if (strlen($contenido) > self::MAXIMO_BYTES) {
            return ['filas' => [], 'errores' => ['El archivo pasa de 1 MB.']];
        }
        if (str_starts_with($contenido, "\xEF\xBB\xBF")) {
            $contenido = substr($contenido, 3);
        }
        if (!mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }
        $lineas = preg_split('/\r\n|\r|\n/', $contenido);
        $encabezado = array_shift($lineas) ?? '';
        $separador = substr_count($encabezado, ';') >= substr_count($encabezado, ',') ? ';' : ',';
        $columnas = array_map(fn(string $c): string => mb_strtolower(trim($c)), str_getcsv($encabezado, $separador, '"', ''));
        if ($columnas !== self::COLUMNAS) {
            return ['filas' => [], 'errores' => ['La primera fila debe ser: ' . implode($separador, self::COLUMNAS) . '.']];
        }

        $filas = [];
        $errores = [];
        foreach ($lineas as $i => $linea) {
            $numero = $i + 2;   // la linea 1 es el encabezado
            if (trim($linea, " \t;,") === '') {
                continue;
            }
            if (count($filas) >= self::MAXIMO_FILAS) {
                $errores[] = 'Más de ' . self::MAXIMO_FILAS . ' productos: divide el archivo.';
                break;
            }
            $valores = str_getcsv($linea, $separador, '"', '');
            if (count($valores) !== count(self::COLUMNAS)) {
                $errores[] = "Línea $numero: tiene " . count($valores) . ' columnas y deben ser ' . count(self::COLUMNAS) . '.';
                continue;
            }
            $filas[$numero] = array_combine(self::COLUMNAS, array_map(fn(?string $v): string => trim((string) $v), $valores));
        }
        if ($filas === [] && $errores === []) {
            $errores[] = 'El archivo no tiene productos debajo del encabezado.';
        }
        return ['filas' => $filas, 'errores' => $errores];
    }
}
