/*
 * Minimarket Mass · ticket para la ticketera termica (frontend/views/ventas/ticket.php).
 *
 * - "Imprimir ticket" abre la impresion del navegador sin Ctrl + P.
 * - Cada caja recuerda su ancho de papel (58 u 80 mm) en este navegador.
 * - Con &auto=1 imprime al abrir y, al terminar, vuelve al comprobante: es el boton "Imprimir en ticketera"
 *   del comprobante. Si Chrome se abre con --kiosk-printing, sale directo a la impresora predeterminada,
 *   sin ventana (docs/facturacion_electronica.md, seccion Impresion).
 * Sin JavaScript la pagina se imprime igual con Ctrl + P.
 */
(function () {
    'use strict';

    const CLAVE = 'mass.ticket.ancho';
    const url = new URL(window.location.href);
    const anchoPagina = document.body.dataset.ancho;

    function guardar(valor) {
        try {
            window.localStorage.setItem(CLAVE, valor);
        } catch (e) { /* navegador sin almacenamiento: se usa el ancho de .env */ }
    }
    function guardado() {
        try {
            return window.localStorage.getItem(CLAVE);
        } catch (e) {
            return null;
        }
    }

    // Sin ?ancho= en la direccion: se usa el que esta caja eligio antes, si es otro
    if (url.searchParams.has('ancho')) {
        guardar(anchoPagina);
    } else {
        const elegido = guardado();
        if ((elegido === '58' || elegido === '80') && elegido !== anchoPagina) {
            url.searchParams.set('ancho', elegido);
            window.location.replace(url.toString());
            return;
        }
    }

    document.querySelectorAll('[data-ancho-elegir]').forEach((enlace) => {
        enlace.addEventListener('click', () => guardar(enlace.dataset.anchoElegir));
    });

    document.querySelectorAll('[data-imprimir]').forEach((boton) => {
        boton.hidden = false;
        boton.addEventListener('click', () => window.print());
    });

    if (url.searchParams.get('auto') === '1') {
        window.addEventListener('afterprint', () => {
            const volver = document.body.dataset.volver;
            if (volver && volver.startsWith('/?')) {
                window.location.href = volver;
            }
        });
        window.addEventListener('load', () => window.print());
    }
}());
