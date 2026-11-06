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

        // El carrito ya no tiene el cobro: "Cobrar" abre la ventana flotante de pago con el total
        await page.mouse.move(0, 0); // sin hover en la captura
        await listo(page);
        await expect(page.locator('.venta-carrito')).toHaveScreenshot('venta-carrito.png');
        const ventanaPago = page.getByRole('dialog', { name: 'Cobrar' });
        await expect(ventanaPago).toBeHidden();
        await page.getByRole('button', { name: /^Cobrar/ }).click();
        await expect(ventanaPago).toBeVisible();
        await expect(ventanaPago.getByLabel(/Efectivo que entrega/)).toBeFocused();
        // Escape la cierra sin perder el carrito; F9 la vuelve a abrir
        await page.keyboard.press('Escape');
        await expect(ventanaPago).toBeHidden();
        await expect(carrito.locator('li')).toHaveCount(2);
        await page.keyboard.press('F9');
        await expect(ventanaPago).toBeVisible();

        // Quién recibe la boleta, al momento
        const cliente = ventanaPago.locator('[data-cliente]');
        await expect(cliente).toHaveText('Cliente varios');
        await ventanaPago.getByLabel('DNI o RUC del cliente').fill('1234');
        await expect(cliente).toHaveText('Faltan dígitos');
        await ventanaPago.getByLabel('DNI o RUC del cliente').fill('');

        // Los montos rápidos: "Exacto" escribe lo que falta; luego paga con un billete de 100
        const rapido = ventanaPago.locator('[data-efectivo-rapido]');
        await rapido.getByRole('button', { name: /^Exacto/ }).click();
        await expect(ventanaPago.locator('[data-pago-vuelto]')).toHaveText('S/ 0.00');
        await rapido.getByRole('button', { name: 'S/ 100.00' }).click();
        await expect(ventanaPago.getByLabel(/Efectivo que entrega/)).toHaveValue('100.00');
        await expect(ventanaPago.locator('[data-pago-vuelto]')).not.toHaveText('S/ 0.00');
        await expect(page.locator('[data-vuelto]')).toHaveText(await ventanaPago.locator('[data-pago-vuelto]').innerText());
        await page.mouse.move(0, 0);
        await ventanaPago.locator('.ventana-pago').evaluate((v) => { v.scrollTop = 0; });
        await listo(page);
        await expect(ventanaPago).toHaveScreenshot('venta-ventana-pago.png');

        await ventanaPago.getByRole('button', { name: 'Registrar venta' }).click();
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
