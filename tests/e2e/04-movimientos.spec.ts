import { expect, test } from '@playwright/test';
import { entrar, listo } from './comun';

test.describe('Movimientos de efectivo', () => {
    test('registra un retiro al depósito y un ingreso de sencillo', async ({ page }) => {
        await entrar(page);
        await page.goto('/?ruta=caja');
        const seccion = page.locator('section.movimientos-turno');
        await expect(seccion).toBeVisible();

        await seccion.locator('.segmentado label', { hasText: 'Retiro' }).click();
        await seccion.getByLabel(/Monto/).fill('20');
        await seccion.getByLabel(/Motivo/).fill('Depósito al banco');
        await seccion.getByRole('button', { name: 'Registrar' }).click();
        await expect(page.locator('.aviso.ok')).toBeVisible();

        await seccion.locator('.segmentado label', { hasText: 'Ingreso' }).click();
        await seccion.getByLabel(/Monto/).fill('10');
        await seccion.getByLabel(/Motivo/).fill('Sencillo del almacén');
        await seccion.getByRole('button', { name: 'Registrar' }).click();
        await expect(seccion.locator('tbody tr')).toHaveCount(3); // retiro, ingreso y la apertura

        await page.mouse.move(0, 0);
        await listo(page);
        await expect(seccion).toHaveScreenshot('movimientos.png');
    });
});
