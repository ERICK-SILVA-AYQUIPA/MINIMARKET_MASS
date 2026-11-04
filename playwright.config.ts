import { defineConfig, devices } from '@playwright/test';

/**
 * Pruebas E2E y de regresión visual de la página web (tests/e2e/*.spec.ts).
 *
 * - Levanta solo `php -S` sobre frontend/public (como backend/tests/web.php) y usa la base de Docker.
 * - La base tiene que estar RECIÉN CREADA (docker compose down -v && docker compose up -d --wait mysql):
 *   las pruebas abren un turno, venden, registran movimientos y cierran la caja de verdad.
 * - Los archivos corren en orden (01 → 05) con un solo worker: cada flujo sigue donde quedó el anterior.
 * - Las capturas base viven en tests/e2e/<archivo>.spec.ts-snapshots/ y se suben a GitHub.
 *   Se generan SIEMPRE dentro de la imagen de Docker de Playwright (tests/e2e/correr_en_docker.sh):
 *   la CI usa esa misma imagen, y otra PC u otro sistema dibuja las letras con otros píxeles.
 */
const PUERTO = Number(process.env.E2E_PUERTO ?? 8095);

export default defineConfig({
    testDir: './tests/e2e',
    fullyParallel: false,
    workers: 1,
    forbidOnly: !!process.env.CI,
    retries: 0,
    timeout: 60_000,
    reporter: process.env.CI ? [['list'], ['html', { open: 'never' }]] : [['list']],
    globalSetup: './tests/e2e/preparar.ts',
    snapshotPathTemplate: '{testDir}/{testFilePath}-snapshots/{arg}{ext}',
    expect: {
        toHaveScreenshot: {
            // Píxel a píxel: sin animaciones, sin cursor de texto y con la misma escala
            animations: 'disabled',
            caret: 'hide',
            scale: 'css',
            // Cero píxeles distintos: las capturas se generan y se comparan en la misma imagen de Docker.
            // Lo que cambia en cada corrida (fechas y horas) se tapa con mask (tests/e2e/comun.ts).
            maxDiffPixels: 0,
        },
    },
    use: {
        baseURL: `http://127.0.0.1:${PUERTO}`,
        locale: 'es-PE',
        timezoneId: 'America/Lima',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'escritorio',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1366, height: 900 }, deviceScaleFactor: 1 },
        },
    ],
    webServer: {
        command: `php -S 127.0.0.1:${PUERTO} -t frontend/public`,
        url: `http://127.0.0.1:${PUERTO}/?ruta=login`,
        reuseExistingServer: !process.env.CI,
        timeout: 20_000,
    },
});
