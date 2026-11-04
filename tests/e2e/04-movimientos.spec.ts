import { expect, test } from '@playwright/test';
import { entrar, listo } from './comun';

test.describe('Movimientos de efectivo', () => {
    test('registra un retiro al depósito y un ingreso de sencillo, cada uno con su categoría', async ({ page }) => {
        await entrar(page);
        await page.goto('/?ruta=caja');
        const seccion = page.locator('section.movimientos-turno');
        await expect(seccion).toBeVisible();

        await seccion.locator('.segmentado label', { hasText: 'Retiro' }).click();
        // Solo se ofrecen las categorías del tipo elegido
        await expect(seccion.getByLabel('Categoría')).toHaveValue('DEPOSITO');
        await seccion.getByLabel(/Monto/).fill('20');
        await seccion.getByLabel(/Detalle/).fill('Depósito al banco');
        await seccion.getByLabel(/Comprobante/).fill('dep-0042');
        await seccion.getByRole('button', { name: 'Registrar' }).click();
        await expect(page.locator('.aviso.ok')).toBeVisible();

        await seccion.locator('.segmentado label', { hasText: 'Ingreso' }).click();
        await expect(seccion.getByLabel('Categoría')).toHaveValue('SENCILLO');
        await seccion.getByLabel(/Monto/).fill('10');
        await seccion.getByLabel(/Detalle/).fill('Sencillo del almacén');
        await seccion.getByRole('button', { name: 'Registrar' }).click();
        await expect(seccion.locator('tbody tr')).toHaveCount(3); // retiro, ingreso y la apertura
        await expect(seccion.locator('tbody')).toContainText('Retiro de seguridad Depósito al banco · DEP-0042');
        await expect(seccion.locator('tbody')).toContainText('Sencillo o cambio Sencillo del almacén');

        await page.mouse.move(0, 0);
        await listo(page);
        await expect(seccion).toHaveScreenshot('movimientos.png');
    });
});
