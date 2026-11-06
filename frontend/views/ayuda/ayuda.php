<?php
declare(strict_types=1);
/**
 * V: ayuda dentro del sistema. Version, a quien llamar, que hacer si se cae el internet y el manual corto
 * del rol de quien entro. El manual completo esta en docs/manual_usuario.md.
 * @var string $version
 * @var array{nombre: string, telefono: ?string, correo: ?string, horario: ?string} $soporte
 */
$titulo = 'Ayuda';
$anchoMain = 'angosto';
require __DIR__ . '/../layout_inicio.php';
$idTiendaAyuda = $usuarioActual->getIdTienda();
?>
<div class="cabecera">
    <div>
        <h1>Ayuda</h1>
        <p class="sutil">Minimarket Mass versión <strong><?= e($version) ?></strong></p>
    </div>
</div>

<section class="panel" aria-labelledby="ayuda-soporte">
    <h2 id="ayuda-soporte">Soporte</h2>
    <dl class="datos">
        <dt>Contacto</dt><dd><?= e($soporte['nombre']) ?></dd>
        <?php if ($soporte['telefono'] !== null): ?><dt>Teléfono</dt><dd><a href="<?= e(Aplicacion::enlaceTelefono($soporte['telefono'])) ?>"><?= e($soporte['telefono']) ?></a></dd><?php endif; ?>
        <?php if ($soporte['correo'] !== null): ?><dt>Correo</dt><dd><a href="mailto:<?= e($soporte['correo']) ?>"><?= e($soporte['correo']) ?></a></dd><?php endif; ?>
        <?php if ($soporte['horario'] !== null): ?><dt>Horario</dt><dd><?= e($soporte['horario']) ?></dd><?php endif; ?>
    </dl>
    <p class="ayuda">Al llamar, ten a la mano: la pantalla donde estabas, qué hiciste, el mensaje que salió, el número de comprobante (si hay) y la versión <?= e($version) ?>.</p>
</section>

<section class="panel" aria-labelledby="ayuda-sin-internet">
    <h2 id="ayuda-sin-internet">Si se cae el internet o el servidor</h2>
    <ol>
        <li>Si arriba sale la franja roja <strong>Sin conexión con el servidor</strong>, no cierres la página. La venta que estás armando queda guardada en este navegador y el botón no la envía hasta que vuelva la conexión.</li>
        <li>Si pulsaste <strong>Registrar venta</strong> y el navegador mostró un error, <strong>no la cobres otra vez</strong>. Cuando vuelva la conexión entra a <strong>Nueva venta</strong>: el sistema te dice si esa venta ya quedó registrada (con su número) o te la devuelve armada para registrarla. Enviarla de nuevo nunca la duplica.</li>
        <li>Si el sistema sigue caído y hay clientes esperando: cobra <strong>solo en efectivo</strong> (la tarjeta y Sip necesitan internet), anota cada venta en la hoja de contingencia (hora, productos, cantidades, total y DNI o RUC si lo pide) y avisa al administrador.</li>
        <li>Al volver el sistema, registra cada venta de la hoja en <strong>Nueva venta</strong> y escribe en la hoja el número de comprobante que salió. El efectivo de la gaveta cuadra cuando todas están registradas.</li>
    </ol>
    <?php if ($usuarioActual->puedeAdministrarTienda($idTiendaAyuda)): ?>
        <p class="ayuda">Administrador: el plan completo (servidor en la tienda, qué funciona sin internet y cómo pasar la hoja de contingencia) está en <code>docs/plan_sin_internet.md</code>.</p>
    <?php endif; ?>
</section>

<section class="panel" aria-labelledby="ayuda-manual">
    <h2 id="ayuda-manual">Manual corto · <?= e(ucfirst(strtolower($usuarioActual->getNombreRol()))) ?></h2>
    <?php if ($usuarioActual->puedeVender()): ?>
        <ul>
            <li><strong>Empezar el día</strong>: Caja → elige tu caja → cuenta el efectivo inicial → Abrir turno.</li>
            <li><strong>Vender</strong>: Nueva venta → escanea o toca los productos → boleta o factura → efectivo que entrega (o tarjeta / Sip) → Registrar venta (<kbd>F9</kbd>).</li>
            <li><strong>Imprimir</strong>: en el comprobante, Imprimir ticket.</li>
            <li><strong>Devolución</strong>: Ventas → el comprobante → Abrir comprobante completo → Devoluciones.</li>
            <li><strong>Retiro o ingreso de efectivo</strong>: Caja → Movimientos de efectivo.</li>
            <li><strong>Terminar el día</strong>: Caja → cuenta billetes y monedas → Cerrar turno.</li>
        </ul>
    <?php endif; ?>
    <?php if ($usuarioActual->puedeRegistrarIngreso($idTiendaAyuda) && !$usuarioActual->puedeAdministrarTienda($idTiendaAyuda)): ?>
        <ul>
            <li><strong>Recibir mercadería</strong>: Movimientos → Ingresos → Registrar ingreso, con el documento del proveedor, cantidades, costo y vencimiento.</li>
            <li><strong>Qué pedir</strong>: Productos → Reponer. <strong>Lo que vence</strong>: Productos → Por vencer.</li>
            <li><strong>Enviar a otra tienda</strong>: Movimientos → Traslados. <strong>Dar de baja</strong>: Movimientos → Mermas.</li>
            <li><strong>Conteo físico</strong>: Movimientos → Conteo. Todo queda en el Kardex.</li>
        </ul>
    <?php endif; ?>
    <?php if ($usuarioActual->puedeAdministrarTienda($idTiendaAyuda)): ?>
        <ul>
            <li><strong>Revisar cajas</strong>: Caja → cada turno con lo esperado, lo contado y la diferencia.</li>
            <li><strong>Anular una venta</strong>: Ventas → el comprobante → Abrir comprobante → motivo → Anular.</li>
            <li><strong>Reportes y utilidad</strong>: Administración → Reportes / Utilidad, con descarga en CSV.</li>
            <li><strong>Productos y precios</strong>: Productos → Catálogo (o Importar CSV). <strong>Personal</strong>: Administración → Personal.</li>
            <li><strong>Auditoría</strong>: quién anuló, cambió precios o claves, con fecha.</li>
        </ul>
    <?php endif; ?>
    <p class="ayuda">Todos: <strong>Mi clave</strong> (al pie del menú) cambia tu clave. Con 5 claves erradas se espera 15 minutos; sin usar el sistema 30 minutos, la sesión se cierra sola.</p>
</section>
<?php require __DIR__ . '/../layout_fin.php'; ?>
