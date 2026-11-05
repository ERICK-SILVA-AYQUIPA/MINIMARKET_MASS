import { expect, test } from '@playwright/test';
import { CAJERO, entrar, listo } from './comun';

test.describe('Login', () => {
    test('pantalla de entrada', async ({ page }) => {
        await page.goto('/?ruta=login');
        await listo(page);
        await expect(page.getByRole('heading', { name: 'Ingresar' })).toBeVisible();
        await expect(page).toHaveScreenshot('login.png');
    });

    test('campo con foco: borde y halo del acento', async ({ page }) => {
        await page.goto('/?ruta=login');
        await page.getByLabel('DNI').focus();
        await listo(page);
        await expect(page.locator('.entrada-tarjeta')).toHaveScreenshot('login-foco.png');
    });

    test('clave errada: muestra el aviso y no entra', async ({ page }) => {
        await page.goto('/?ruta=login');
        await page.getByLabel('DNI').fill(CAJERO.dni);
        await page.getByLabel('Clave').fill('otra-clave1');
        await page.getByRole('button', { name: /Entrar/ }).click();
        await expect(page).toHaveURL(/ruta=login/);
        await expect(page.getByRole('alert')).toBeVisible();
        await listo(page);
        await expect(page.locator('.entrada-tarjeta')).toHaveScreenshot('login-error.png');
    });

    test('con la clave correcta entra al sistema', async ({ page }) => {
        await entrar(page);
        await expect(page.locator('.lateral')).toContainText('Núñez');
    });
});
