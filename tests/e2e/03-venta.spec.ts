import { expect, test } from '@playwright/test';
import { entrar, listo } from './comun';

test.describe('Venta', () => {
    test('agrega productos tocando su foto, cobra en efectivo y emite la boleta', async ({ page }) => {
        await entrar(page);
        await page.goto('/?ruta=venta_nueva');
        const rejilla = page.locator('[data-rejilla-venta]');
        await expect(rejilla).toBeVisible();
        await listo(page);
        await expect(page).toHaveScreenshot('venta-vacia.png');

        // Dos del primer producto y uno del segundo
        const productos = rejilla.locator('button.producto-venta:not([disabled])');
        await productos.nth(0).click();
        await productos.nth(0).click();
        await productos.nth(1).click();
        const carrito = page.locator('[data-carrito]');
        await expect(carrito.locator('li')).toHaveCount(2);
        await expect(page.locator('[data-carrito-cuenta]')).toHaveText('2'); // dos productos distintos

        // Quién recibe la boleta, al momento
        const cliente = page.locator('[data-cliente]');
        await expect(cliente).toHaveText('Cliente varios');
        await page.getByLabel('DNI o RUC del cliente').fill('1234');
        await expect(cliente).toHaveText('Faltan dígitos');
        await page.getByLabel('DNI o RUC del cliente').fill('');

        // Los montos rápidos: "Exacto" escribe lo que falta; luego paga con un billete de 100
        const rapido = page.locator('[data-efectivo-rapido]');
        await rapido.getByRole('button', { name: /^Exacto/ }).click();
        await expect(page.locator('[data-vuelto]')).toHaveText('S/ 0.00');
        await rapido.getByRole('button', { name: 'S/ 100.00' }).click();
        await expect(page.getByLabel(/Efectivo que entrega/)).toHaveValue('100.00');
        await expect(page.locator('[data-vuelto]')).not.toHaveText('S/ 0.00');
        await page.mouse.move(0, 0); // sin hover en la captura
        // El carrito se desplaza por dentro (al tocar un monto puede quedar a medio píxel): se vuelve arriba para capturar igual
        await page.locator('.venta-carrito').evaluate((carrito) => { carrito.scrollTop = 0; });
        await listo(page);
        await expect(page.locator('.venta-carrito')).toHaveScreenshot('venta-carrito.png');

        await page.getByRole('button', { name: 'Registrar venta' }).click();
        await expect(page).toHaveURL(/ruta=comprobante/);
        await expect(page.locator('.aviso.ok')).toBeVisible();
        await listo(page);
        await expect(page).toHaveScreenshot('venta-comprobante.png', { fullPage: true });
    });

    test('micro-interacción: el producto crece al pasar el cursor', async ({ page }) => {
        await entrar(page);
        await page.goto('/?ruta=venta_nueva');
        const producto = page.locator('[data-rejilla-venta] button.producto-venta:not([disabled])').first();
        await listo(page);
        await producto.hover();
        // El resorte termina en scale(1.03) (var(--escala-hover-tarjeta))
        await expect.poll(() => producto.evaluate((b) => getComputedStyle(b).transform)).toBe('matrix(1.03, 0, 0, 1.03, 0, 0)');
    });
});
