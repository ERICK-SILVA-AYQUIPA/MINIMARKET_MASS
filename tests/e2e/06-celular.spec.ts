import { expect, test, type Page } from '@playwright/test';
import { CAJERO, entrar, listo } from './comun';

/**
 * La caja en el celular (proyecto "celular" de playwright.config.ts: 390 x 844, táctil).
 * Corre después de 01 → 05: el cierre de Miguel quedó con una diferencia, así que primero Lucía
 * (administradora de su tienda) lo aprueba y luego Miguel abre otro turno, vende, mueve efectivo y cierra.
 */
const ADMINISTRADORA = { dni: '70000007', clave: 'Mass2026!', nombre: 'Lucia' };

/** Captura de la pantalla visible sin el aviso corto (toast) ni el cursor encima. */
async function sinToast(page: Page): Promise<void> {
    await expect(page.locator('.toast.visible')).toHaveCount(0, { timeout: 5_000 });
}

test.describe('Caja en el celular', () => {
    test('la administradora aprueba el cierre con diferencia desde el celular', async ({ page }) => {
        await entrar(page, ADMINISTRADORA);
        await page.goto('/?ruta=caja');
        // La administradora no vende: su barra de abajo no tiene "Vender"
        const barra = page.locator('nav.barra-inferior');
        await expect(barra).toBeVisible();
        await expect(barra.getByRole('link', { name: 'Vender' })).toHaveCount(0);
        await expect(barra.getByRole('link', { name: 'Caja' })).toHaveAttribute('aria-current', 'page');

        const revisar = page.locator('.por-revisar section').first();
        await revisar.getByLabel('Comentario').fill('Billete de 10 mal contado: conforme');
        await revisar.getByRole('button', { name: 'Aprobar la diferencia' }).click();
        await expect(page.locator('.aviso.ok')).toContainText('aprobada');
    });

    test('abre el turno contando el fondo con la barra de abajo a la mano', async ({ page }) => {
        await entrar(page, CAJERO);
        await page.goto('/?ruta=caja');
        const apertura = page.locator('section.apertura');
        await expect(apertura.getByRole('heading', { name: 'Abre tu turno' })).toBeVisible();
        // Nada se sale de la pantalla a lo ancho
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);

        const conteo = apertura.locator('[data-conteo]');
        await conteo.getByLabel('S/ 50', { exact: true }).fill('1');
        await conteo.getByLabel('S/ 20', { exact: true }).fill('2');
        await conteo.getByLabel('S/ 10', { exact: true }).fill('1');
        await expect(conteo.locator('[data-total]')).toHaveValue('100.00');
        await listo(page);
        await expect(conteo).toHaveScreenshot('celular-apertura-conteo.png');

        await apertura.getByRole('button', { name: 'Abrir mi turno' }).click();
        await expect(page.locator('.turno-barra')).toContainText('Turno activo');
        await page.locator('.turno-barra').scrollIntoViewIfNeeded();
        await listo(page);
        await expect(page.locator('.turno-barra')).toHaveScreenshot('celular-turno-barra.png');
        await expect(page.locator('.metricas-turno')).toHaveScreenshot('celular-turno-metricas.png');
        await expect(page.locator('nav.barra-inferior')).toHaveScreenshot('celular-barra-inferior.png');
    });

    test('vende con el total siempre a la vista y cobra con un monto rápido', async ({ page }) => {
        await entrar(page, CAJERO);
        await page.goto('/?ruta=venta_nueva');
        const cobroFijo = page.locator('[data-cobro-fijo]');
        await expect(cobroFijo).toBeHidden(); // sin productos no hay nada que cobrar

        const productos = page.locator('[data-rejilla-venta] button.producto-venta:not([disabled])');
        await productos.nth(0).click();
        await productos.nth(1).click();
        await productos.nth(1).click();
        await expect(cobroFijo).toBeVisible();
        await expect(cobroFijo).toContainText('2 productos');
        await sinToast(page);
        await listo(page);
        await expect(page).toHaveScreenshot('celular-venta-productos.png');

        // "Cobrar" baja al carrito y la barra se va (ya no tapa el botón de registrar)
        await cobroFijo.getByRole('link', { name: 'Cobrar' }).click();
        await expect(cobroFijo).toHaveClass(/cobro-a-la-vista/);
        await expect(cobroFijo).toBeHidden();
        await expect(page.getByLabel(/Efectivo que entrega/)).toBeFocused();

        await page.locator('[data-efectivo-rapido]').getByRole('button', { name: /^Exacto/ }).click();
        await expect(page.locator('[data-vuelto]')).toHaveText('S/ 0.00');
        await listo(page);
        await expect(page.locator('.venta-carrito')).toHaveScreenshot('celular-venta-cobro.png');

        await page.getByRole('button', { name: 'Registrar venta' }).click();
        await expect(page).toHaveURL(/ruta=comprobante/);
        await expect(page.locator('.aviso.ok')).toBeVisible();
    });

    test('registra un gasto con su comprobante y ve el historial en tarjetas', async ({ page }) => {
        await entrar(page, CAJERO);
        await page.goto('/?ruta=caja');
        const seccion = page.locator('section.movimientos-turno');
        await seccion.locator('.segmentado label', { hasText: 'Retiro' }).click();
        await seccion.getByLabel('Categoría').selectOption('GASTO');
        await seccion.getByLabel(/Monto/).fill('3.50');
        await seccion.getByLabel(/Detalle/).fill('Bolsas para la caja');
        await seccion.getByLabel(/Comprobante/).fill('B001-245');
        await seccion.getByRole('button', { name: 'Registrar' }).click();
        await expect(page.locator('.aviso.ok')).toBeVisible();
        await expect(seccion.locator('tbody tr').first()).toContainText('Gasto menor de la tienda');
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
        await page.mouse.move(0, 0);
        await listo(page);
        await expect(seccion).toHaveScreenshot('celular-movimientos.png');
    });

    test('cierra el turno a ciegas: la barra de abajo no tapa el arqueo', async ({ page }) => {
        await entrar(page, CAJERO);
        await page.goto('/?ruta=caja');
        await page.getByRole('link', { name: 'Cerrar caja' }).click();
        const arqueo = page.locator('#arqueo-cierre');
        await expect(arqueo).toBeVisible();
        await expect(page.locator('nav.barra-inferior')).toBeHidden();

        const conteo = arqueo.locator('[data-conteo]');
        await conteo.getByLabel('S/ 100', { exact: true }).fill('1');
        await conteo.getByLabel('S/ 20', { exact: true }).fill('1');
        await listo(page);
        await expect(conteo).toHaveScreenshot('celular-arqueo-conteo.png');

        await arqueo.getByLabel(/Conté todo el efectivo/).check();
        await arqueo.getByRole('button', { name: 'Confirmar y cerrar turno' }).click();
        const modal = page.locator('dialog.confirmacion');
        await expect(modal).toBeVisible();
        await modal.getByRole('button', { name: 'Sí, cerrar mi turno' }).click();
        await expect(page.locator('.cierre-resultado')).toBeVisible();
        expect(await page.evaluate(() => document.documentElement.scrollWidth)).toBeLessThanOrEqual(390);
        await listo(page);
        await expect(page).toHaveScreenshot('celular-cierre-comprobante.png');
    });
});
