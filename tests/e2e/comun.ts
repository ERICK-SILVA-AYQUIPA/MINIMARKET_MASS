import { expect, type Page } from '@playwright/test';

/** Miguel Nunez: cajero de la tienda 3. En la base recién creada no tiene turno abierto (datos.sql). */
export const CAJERO = { dni: '70000008', clave: 'Mass2026!', nombre: 'Miguel' };

/** Entra con DNI y clave como lo haría la persona, y espera a que se vaya la bienvenida. */
export async function entrar(page: Page, usuario = CAJERO): Promise<void> {
    await page.goto('/?ruta=login');
    await page.getByLabel('DNI').fill(usuario.dni);
    await page.getByLabel('Clave').fill(usuario.clave);
    await page.getByRole('button', { name: /Entrar/ }).click();
    await expect(page).not.toHaveURL(/ruta=login/);
}

/**
 * Lo que cambia en cada corrida (fechas, horas, "abierto hace...") se reemplaza en la página por un valor fijo
 * antes de capturar: así la comparación píxel a píxel solo mira el diseño. Se reemplaza el texto en vez de
 * taparlo con mask porque "19:58" y "20:01" no miden lo mismo con Inter y la caja tapada cambiaría de ancho.
 */
export async function fijarVariables(page: Page): Promise<void> {
    await page.evaluate(() => {
        const recorrer = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        for (let nodo = recorrer.nextNode(); nodo !== null; nodo = recorrer.nextNode()) {
            const antes = nodo.nodeValue ?? '';
            const despues = antes
                .replace(/\d{2}\/\d{2}\/\d{4}/g, '01/11/2026')
                .replace(/\b\d{1,2}:\d{2}\b/g, '08:00')
                .replace(/(Abierto hace )\S.*$/, (_, inicio: string) => inicio + '10 min');
            if (despues !== antes) {
                nodo.nodeValue = despues;
            }
        }
    });
}

/** Espera las letras propias (Inter, JetBrains Mono) y las fotos, y fija fechas y horas, antes de capturar. */
export async function listo(page: Page): Promise<void> {
    await fijarVariables(page);
    await page.evaluate(async () => {
        await document.fonts.ready;
        // Solo las fotos que se ven: las de más abajo son loading="lazy" y no cargan hasta que se baja
        const visibles = Array.from(document.images).filter((i) => !i.complete && i.getBoundingClientRect().top < innerHeight);
        await Promise.all(visibles.map((i) => new Promise((r) => { i.onload = i.onerror = r; })));
    });
}
