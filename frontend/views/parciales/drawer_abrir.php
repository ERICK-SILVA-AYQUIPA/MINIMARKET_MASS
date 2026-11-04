<?php
declare(strict_types=1);
/**
 * V: abre un panel lateral (drawer). Se cierra con parciales/drawer_cerrar.php.
 * Sin JavaScript: se muestra cuando la URL termina en #$drawerId (:target) o cuando el servidor lo pide abierto.
 * @var string $drawerId         id del panel (el enlace que lo abre apunta a #$drawerId)
 * @var string $drawerTitulo
 * @var ?string $drawerSubtitulo
 * @var string $drawerCerrar     a donde lleva "Cerrar" (por defecto #contenido, sin recargar)
 * @var bool $drawerAbierto      true: abierto desde el servidor (ficha pedida con &ver=)
 * @var bool $drawerAncho        true: panel mas ancho, para formularios con tablas
 * @var bool $drawerPantalla     true: ocupa toda la pantalla (el arqueo de cierre, sin distracciones)
 */
$drawerSubtitulo ??= null;
$drawerCerrar ??= '#contenido';
$drawerAbierto ??= false;
$drawerAncho ??= false;
$drawerPantalla ??= false;
?>
<div class="drawer<?= $drawerAbierto ? ' abierto' : '' ?><?= $drawerAncho ? ' ancho' : '' ?><?= $drawerPantalla ? ' pantalla' : '' ?>" id="<?= e($drawerId) ?>" role="dialog" aria-modal="true" aria-labelledby="<?= e($drawerId) ?>-titulo">
    <a class="drawer-fondo" href="<?= e($drawerCerrar) ?>" tabindex="-1" aria-hidden="true"></a>
    <section class="drawer-panel">
        <header class="drawer-cabecera">
            <div>
                <h2 id="<?= e($drawerId) ?>-titulo"><?= e($drawerTitulo) ?></h2>
                <?php if ($drawerSubtitulo !== null): ?><p class="sutil"><?= e($drawerSubtitulo) ?></p><?php endif; ?>
            </div>
            <a class="drawer-cerrar" href="<?= e($drawerCerrar) ?>"><?= icono('cerrar') ?><span class="visualmente-oculto">Cerrar</span></a>
        </header>
        <div class="drawer-cuerpo">
