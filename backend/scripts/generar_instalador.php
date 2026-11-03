<?php
declare(strict_types=1);

/**
 * Arma backend/database/instalar_laragon.sql: los 12 scripts de la base y las migraciones, en orden.
 *   php backend/scripts/generar_instalador.php            escribe el archivo
 *   php backend/scripts/generar_instalador.php --revisar  solo compara (codigo 1 si esta desactualizado)
 * Correrlo despues de agregar una migracion. Las pruebas unitarias (y la CI) fallan si se olvida.
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la consola.');
}
require __DIR__ . '/../config/Migraciones.php';

$ruta = dirname(__DIR__) . '/database/instalar_laragon.sql';
$nuevo = Migraciones::instalador();
$actual = is_readable($ruta) ? str_replace("\r", '', (string) file_get_contents($ruta)) : '';

if (in_array('--revisar', $argv, true)) {
    if ($actual === $nuevo) {
        echo "instalar_laragon.sql esta al dia.\n";
        exit(0);
    }
    fwrite(STDERR, "instalar_laragon.sql esta desactualizado: php backend/scripts/generar_instalador.php\n");
    exit(1);
}

file_put_contents($ruta, $nuevo);
echo $actual === $nuevo ? "instalar_laragon.sql ya estaba al dia.\n" : "Se escribio backend/database/instalar_laragon.sql.\n";
