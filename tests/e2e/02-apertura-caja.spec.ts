import { expect, test } from '@playwright/test';
import { entrar, listo } from './comun';

test.describe('Apertura de caja', () => {
    test('elige la caja, cuenta el fondo y abre el turno', async ({ page }) => {
        await entrar(page);
        await page.goto('/?ruta=caja');
        const apertura = page.locator('section.apertura');
        await expect(apertura.getByRole('heading', { name: 'Abre tu turno' })).toBeVisible();
        await listo(page);
        await expect(page).toHaveScreenshot('apertura-inicio.png', { fullPage: true });

        // Paso 2: el sobre viene con el fondo exacto
        await apertura.getByRole('button', { name: 'Viene exacto' }).click();
        await expect(apertura.locator('[data-resumen-estado]')).not.toHaveText('Cuenta el fondo para continuar.');
        await expect(apertura).toHaveScreenshot('apertura-cuadra.png');

        // Paso 3
        await apertura.getByRole('button', { name: 'Abrir mi turno' }).click();
        const barra = page.locator('.turno-barra');
        await expect(barra).toContainText('Turno activo');
        await listo(page);
        await expect(barra).toHaveScreenshot('turno-abierto-barra.png');
        await expect(page).toHaveScreenshot('turno-abierto.png', { fullPage: true });
    });
});
