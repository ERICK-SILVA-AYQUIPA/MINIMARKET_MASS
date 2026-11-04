<?php
declare(strict_types=1);

/**
 * Antes de las pruebas E2E (lo llama tests/e2e/preparar.ts):
 * 1. Revisa que la base este recien creada: Miguel (cajero de Yanahuara) no tiene turnos propios todavia.
 *    Si ya corrieron las pruebas (o tests/integracion.php), las capturas no saldrian iguales: se para y lo dice.
 * 2. Marca la clave de prueba de Miguel y de Lucía como ya cambiada (los empleados de ejemplo deben cambiarla al entrar).
 */
if (PHP_SAPI !== 'cli') {
    exit('Solo desde la terminal.');
}
require_once __DIR__ . '/../../backend/config/Database.php';

$db = Database::conexion();
$turnos = (int) $db->query("SELECT COUNT(*) FROM caja_turno WHERE id_empleado = 8 AND id_caja_turno > 6")->fetchColumn();
if ($turnos > 0) {
    fwrite(STDERR, "La base ya tiene turnos de Miguel creados por otra prueba.\n"
        . "Las pruebas E2E piden la base recien creada:\n"
        . "  docker compose down -v && docker compose up -d --wait mysql\n");
    exit(1);
}
// Lucía (administradora de Yanahuara) entra en la prueba del celular para aprobar el cierre de Miguel
$db->exec("UPDATE empleado SET debe_cambiar_clave = 'N' WHERE dni IN ('70000008', '70000007')");
echo "Base lista para las pruebas E2E\n";
