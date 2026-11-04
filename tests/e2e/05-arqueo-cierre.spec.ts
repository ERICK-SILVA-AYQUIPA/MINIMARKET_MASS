import { expect, test } from '@playwright/test';
import { entrar, listo } from './comun';

test.describe('Arqueo y cierre', () => {
    test('cuenta la gaveta a ciegas, confirma en el modal y ve el comprobante de cierre', async ({ page }) => {
        await entrar(page);
        await page.goto('/?ruta=caja');
        await page.getByRole('link', { name: 'Cerrar caja' }).click();
        const arqueo = page.locator('#arqueo-cierre');
        await expect(arqueo).toBeVisible();
        await listo(page);
        await expect(page).toHaveScreenshot('arqueo-resumen.png');

        // Paso 2: billetes y monedas (el total se suma solo)
        const conteo = arqueo.locator('[data-conteo]');
        await conteo.getByLabel('S/ 100', { exact: true }).fill('1');
        await conteo.getByLabel('S/ 20', { exact: true }).fill('1');
        await conteo.getByLabel('S/ 10', { exact: true }).fill('1');
        await expect(conteo.locator('[data-total]')).toHaveValue('130.00');
        await expect(conteo).toHaveScreenshot('arqueo-conteo.png');

        // Paso 3: confirma y cierra; pregunta antes en un modal de vidrio
        await arqueo.getByLabel(/Conté todo el efectivo/).check();
        await arqueo.getByRole('button', { name: 'Confirmar y cerrar turno' }).click();
        const modal = page.locator('dialog.confirmacion');
        await expect(modal).toBeVisible();
        await expect(modal).toContainText('S/ 130.00');
        await page.mouse.move(0, 0);
        // El modal de vidrio: se ve el formulario desenfocado detrás
        await expect(modal).toHaveScreenshot('cierre-confirmar.png');

        await modal.getByRole('button', { name: 'Sí, cerrar mi turno' }).click();
        await expect(page.locator('.cierre-resultado')).toBeVisible();
        await listo(page);
        await expect(page).toHaveScreenshot('cierre-comprobante.png', { fullPage: true });
    });
});
