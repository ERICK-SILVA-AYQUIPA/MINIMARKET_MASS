<?php
declare(strict_types=1);

// Paso 2: herencia. Tres empleados de Mass Cayma (tienda 1) de datos.sql
require __DIR__ . '/../models/Administrador.php';
require __DIR__ . '/../models/Cajero.php';
require __DIR__ . '/../models/Almacenero.php';

/** @var Usuario[] $usuarios */
$usuarios = [
    new Administrador(1, 'Rosa', 'Quispe Huaman', '70000001', 'ACTIVO', 1),
    new Cajero(2, 'Luis', 'Caceres Pinto', '70000002', 'ACTIVO', 1),
    new Almacenero(3, 'Carmen', 'Ccori Flores', '70000003', 'ACTIVO', 1),
];

$idTiendaVenta = 1; // la venta 4 (anulada en datos.sql) es de Mass Cayma

$e = fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$sino = fn(bool $b): string => $b ? 'Sí' : 'No';
?>
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>POO · Roles</title></head>
<body>
<h1>Roles y permisos</h1>
<table border="1" cellpadding="6">
    <tr><th>Empleado</th><th>DNI</th><th>Rol</th><th>Vender</th><th>Anular venta de Cayma</th><th>Anular venta de Yanahuara</th><th>Traslados y mermas</th></tr>
    <?php foreach ($usuarios as $u): ?>
    <tr>
        <td><?= $e($u->nombreCompleto()) ?></td>
        <td><?= $e($u->dniOculto()) ?></td>
        <td><?= $e($u->getNombreRol()) ?> (id_rol <?= $u->getIdRol() ?>)</td>
        <td><?= $sino($u->puedeVender()) ?></td>
        <td><?= $sino($u->puedeAnularVenta($idTiendaVenta)) ?></td>
        <td><?= $sino($u->puedeAnularVenta(3)) ?></td>
        <td><?= $sino($u->puedeMoverMercaderia()) ?></td>
    </tr>
    <?php endforeach; ?>
</table>
<p>El mismo método <code>puedeAnularVenta()</code> responde distinto según la clase del objeto: eso es polimorfismo.</p>
</body>
</html>
