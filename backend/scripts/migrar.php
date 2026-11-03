<?php
declare(strict_types=1);

/**
 * Pone la base al dia SIN borrar datos: aplica en orden las migraciones de
 * backend/database/migraciones/ que todavia no estan en la tabla migracion_aplicada.
 *   php backend/scripts/migrar.php            aplica las que faltan
 *   php backend/scripts/migrar.php --revisar  solo dice cuales faltan (no cambia nada)
 *
 * Lo corren actualizar.sh / actualizar.bat y el despliegue (docs/despliegue.md, seccion 8).
 * Se puede correr las veces que se quiera: si no falta nada, no hace nada.
 * Necesita permiso de ALTER y CREATE. Si el usuario de .env no lo tiene (MySQL sin Docker),
 * se pasa otro solo para esta vez:  DB_USER=root DB_PASSWORD='...' php backend/scripts/migrar.php
 * Termina con codigo 1 si algo falla (o, con --revisar, si falta aplicar alguna).
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la consola.');
}
require __DIR__ . '/../config/Database.php';
require __DIR__ . '/../config/Migraciones.php';
date_default_timezone_set('America/Lima');

$soloRevisar = in_array('--revisar', $argv, true);

try {
    $db = Database::conexion();
} catch (PDOException) {
    fwrite(STDERR, "[ERROR] No se pudo conectar a MySQL con los datos de .env (DB_HOST, DB_PORT, DB_USER, DB_PASSWORD).\n");
    exit(1);
}

$hayEmpleado = (int) $db->query(
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'empleado'"
)->fetchColumn();
if ($hayEmpleado === 0) {
    fwrite(STDERR, "[ERROR] La base esta vacia: primero se crea con los 12 scripts (docs/despliegue.md, seccion 4).\n");
    exit(1);
}

$migraciones = new Migraciones($db);
try {
    if ($soloRevisar) {
        $tabla = (int) $db->query(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'migracion_aplicada'"
        )->fetchColumn();
        $estado = $tabla === 0
            ? ['pendientes' => array_keys(Migraciones::archivos()), 'cambiadas' => [], 'desconocidas' => []]
            : $migraciones->estado();
        foreach ($estado['pendientes'] as $nombre) {
            echo "  FALTA     $nombre\n";
        }
        foreach ($estado['cambiadas'] as $nombre) {
            echo "  CAMBIADA  $nombre (se aplico y despues alguien edito el archivo)\n";
        }
        foreach ($estado['desconocidas'] as $nombre) {
            echo "  AVISO     $nombre esta en la base pero no en el codigo (el codigo es mas viejo que la base)\n";
        }
        $alDia = $estado['pendientes'] === [] && $estado['cambiadas'] === [];
        echo $alDia ? "La base esta al dia.\n" : "Falta poner la base al dia: php backend/scripts/migrar.php\n";
        exit($alDia ? 0 : 1);
    }

    $hechas = $migraciones->aplicarPendientes(static function (string $linea): void {
        echo "[..] $linea\n";
    });
} catch (Throwable $ex) {
    fwrite(STDERR, '[ERROR] ' . $ex->getMessage() . "\n");
    if ($ex instanceof PDOException && str_contains($ex->getMessage(), 'denied')) {
        fwrite(STDERR, "        El usuario de .env no puede cambiar tablas. Corre: DB_USER=root DB_PASSWORD='...' php backend/scripts/migrar.php\n");
    }
    exit(1);
}
echo $hechas === []
    ? "[OK] La base ya estaba al dia (" . count(Migraciones::archivos()) . " migraciones aplicadas).\n"
    : '[OK] Se aplicaron ' . count($hechas) . " migraciones. La base esta al dia.\n";
