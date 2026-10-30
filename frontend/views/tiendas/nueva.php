<?php
declare(strict_types=1);
/**
 * V: abrir una tienda nueva con su primer administrador.
 * @var array<int, string> $distritos
 * @var array $viejo
 * @var ?string $error
 */
$titulo = 'Abrir tienda';
require __DIR__ . '/../layout_inicio.php';
$viejoTexto = static fn(string $campo): string => is_string($viejo[$campo] ?? null) ? $viejo[$campo] : '';
?>
<a class="volver" href="/?ruta=tiendas"><?= icono('volver') ?>Volver a tiendas</a>
<div class="cabecera">
    <div>
        <h1>Abrir una tienda</h1>
        <p class="sutil">Se crea con la caja 1, una serie de boleta y una de factura (las siguientes libres de la cadena),
            todos los productos en stock 0 y su administrador, que luego registra a su personal.</p>
    </div>
</div>
<?php if ($error !== null): ?><div class="aviso error" role="alert"><?= e($error) ?></div><?php endif; ?>

<form class="formulario panel" method="post" action="/?ruta=tienda_abrir" autocomplete="off">
    <?= campoCsrf($csrf) ?>
    <fieldset>
        <legend>Tienda</legend>
        <label for="nombre">Nombre</label>
        <input id="nombre" name="nombre" maxlength="60" size="40" required value="<?= e($viejoTexto('nombre')) ?>">
        <label for="direccion">Dirección</label>
        <input id="direccion" name="direccion" maxlength="120" size="60" required value="<?= e($viejoTexto('direccion')) ?>">
        <label for="telefono">Teléfono <span class="ayuda">opcional</span></label>
        <input id="telefono" name="telefono" maxlength="15" value="<?= e($viejoTexto('telefono')) ?>">
        <label for="distrito">Distrito</label>
        <select id="distrito" name="distrito" required>
            <option value="">Elige…</option>
            <?php foreach ($distritos as $id => $nombre): ?>
                <option value="<?= $id ?>" <?= $viejoTexto('distrito') === (string) $id ? 'selected' : '' ?>><?= e($nombre) ?></option>
            <?php endforeach; ?>
        </select>
    </fieldset>
    <fieldset>
        <legend>Su administrador</legend>
        <label for="admin_nombres">Nombres</label>
        <input id="admin_nombres" name="admin_nombres" maxlength="60" required value="<?= e($viejoTexto('admin_nombres')) ?>">
        <label for="admin_apellidos">Apellidos</label>
        <input id="admin_apellidos" name="admin_apellidos" maxlength="60" required value="<?= e($viejoTexto('admin_apellidos')) ?>">
        <label for="admin_dni">DNI <span class="ayuda">8 dígitos; con él ingresa</span></label>
        <input id="admin_dni" name="admin_dni" inputmode="numeric" pattern="[0-9]{8}" maxlength="8" required value="<?= e($viejoTexto('admin_dni')) ?>">
        <label for="admin_clave">Clave inicial <span class="ayuda">8 a 72 caracteres, con letras y números</span></label>
        <input id="admin_clave" name="admin_clave" type="password" minlength="8" maxlength="72" required autocomplete="new-password">
    </fieldset>
    <div class="botones-formulario"><button class="acento" type="submit"><?= icono('tiendas') ?>Abrir tienda</button></div>
</form>

<form class="formulario panel" method="post" action="/?ruta=distrito_crear">
    <h2>¿Falta el distrito?</h2>
    <?= campoCsrf($csrf) ?>
    <label for="distrito_nuevo">Nombre del distrito</label>
    <div class="en-linea"><input id="distrito_nuevo" name="nombre" maxlength="40" required>
    <button type="submit"><?= icono('mas') ?>Agregar distrito</button></div>
</form>
<?php require __DIR__ . '/../layout_fin.php'; ?>
