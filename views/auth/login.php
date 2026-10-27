<?php
declare(strict_types=1);
/**
 * V: pantalla de entrada: bienvenida animada (solo CSS) y formulario con DNI y clave.
 * @var string $dni
 * @var ?string $error
 */
$titulo = 'Ingresar';
$paginaEntrada = true;
require __DIR__ . '/../layout_inicio.php';
?>
<?php if ($error === null): ?>
<div class="entrada-splash" aria-hidden="true">
    <div class="splash-marca"><span class="logo">M</span><span>Minimarket <b>Mass</b></span></div>
    <div class="splash-lema">Ventas e inventario multitienda</div>
    <div class="splash-barra"><span></span></div>
</div>
<?php endif; ?>
<div class="entrada">
    <section class="entrada-hero" aria-label="Minimarket Mass">
        <span class="entrada-insignia"><span class="punto" aria-hidden="true"></span>Sistema de la tienda · Arequipa</span>
        <p class="entrada-titulo">Vende rápido,<br>controla tu <em>stock</em>,<br><span class="resalte">cuadra tu caja.</span></p>
        <p class="entrada-texto">Boletas y facturas, kardex al día, traslados entre tiendas y arqueo por turno. Cada empleado ve solo lo que le toca.</p>
        <ul class="entrada-puntos">
            <li><?= icono('venta') ?><span><strong>Caja</strong> total y vuelto al momento</span></li>
            <li><?= icono('kardex') ?><span><strong>Kardex</strong> cada movimiento con su motivo</span></li>
            <li><?= icono('tiendas') ?><span><strong>Tiendas</strong> stock y traslados entre locales</span></li>
        </ul>
    </section>

    <div class="entrada-tarjeta">
        <div class="marca-acceso"><span class="logo" aria-hidden="true">M</span><span>Minimarket <b>Mass</b></span></div>
        <h1>Ingresar</h1>
        <p class="sutil">Usa tu DNI y tu clave de empleado.</p>
        <?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form class="formulario" method="post" action="/?ruta=login" autocomplete="off">
            <?= campoCsrf($csrf) ?>
            <label for="dni">DNI</label>
            <input id="dni" name="dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required autofocus
                   autocomplete="username" placeholder="8 dígitos" value="<?= e($dni) ?>">
            <label for="clave">Clave</label>
            <input id="clave" name="clave" type="password" required autocomplete="current-password">
            <div class="botones-formulario"><button class="acento grande" type="submit">Entrar <span aria-hidden="true">→</span></button></div>
        </form>
        <p class="entrada-ayuda">Si olvidaste tu clave, pide al administrador de tu tienda que te asigne una nueva.</p>
    </div>
</div>
<?php require __DIR__ . '/../layout_fin.php'; ?>
