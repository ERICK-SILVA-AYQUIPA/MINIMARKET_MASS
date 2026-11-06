/*
 * Minimarket Mass · mejoras de la interfaz con JavaScript.
 *
 * Reglas de este archivo:
 * - Mejora progresiva: todo funciona igual sin JavaScript. Aqui solo se agrega comodidad
 *   (teclado, calculos en vivo, confirmaciones); las reglas y los permisos siguen en el servidor.
 * - Nunca se arma HTML con datos: se usa textContent, asi un nombre con <script> se ve como texto.
 * - Sin librerias ni archivos de internet: la CSP solo permite scripts de este sitio (script-src 'self').
 */
(function () {
    'use strict';

    document.documentElement.classList.add('js');

    const $ = (selector, raiz = document) => raiz.querySelector(selector);
    const $$ = (selector, raiz = document) => Array.from(raiz.querySelectorAll(selector));

    // ---------- Sucursal del administrador (menu lateral): se cambia al elegirla, sin el boton ----------
    const formTienda = $('[data-elegir-tienda]');
    if (formTienda) {
        const botonTienda = $('[data-elegir-tienda-boton]', formTienda);
        if (botonTienda) {
            botonTienda.hidden = true;
        }
        $('select', formTienda).addEventListener('change', () => formTienda.submit());
    }

    // ---------- Codigos de barras: las mismas variantes que backend/models/CodigoBarras.php ----------

    // Todas las formas del mismo codigo, la leida primero: "775 1271-011013", 07751271011013 (QR GS1)...
    function variantesCodigo(texto) {
        const codigo = String(texto).trim().replace(/[\s.\-]+/g, '');
        if (!/^\d{8,14}$/.test(codigo)) {
            return [codigo];
        }
        const variantes = [codigo];
        const nucleo = codigo.replace(/^0+/, '');
        if (nucleo !== '') {
            [8, 12, 13, 14].forEach((largo) => {
                if (nucleo.length <= largo) {
                    variantes.push(nucleo.padStart(largo, '0'));
                }
            });
        }
        return Array.from(new Set(variantes));
    }

    // ---------- Dinero y cantidades en enteros (centimos y milesimas), como el servidor ----------

    // "12.5" -> 1250 centimos; null si no es un monto valido
    function centimos(texto) {
        const limpio = String(texto).trim().replace(',', '.');
        if (!/^\d{1,7}(\.\d{1,2})?$/.test(limpio)) {
            return null;
        }
        const [entero, decimales = ''] = limpio.split('.');
        return Number(entero) * 100 + Number(decimales.padEnd(2, '0'));
    }

    // "0.75" -> 750 milesimas; null si no es una cantidad valida
    function milesimas(texto) {
        const limpio = String(texto).trim().replace(',', '.');
        if (!/^\d{1,7}(\.\d{1,3})?$/.test(limpio)) {
            return null;
        }
        const [entero, decimales = ''] = limpio.split('.');
        return Number(entero) * 1000 + Number(decimales.padEnd(3, '0'));
    }

    // Mismo redondeo que Cantidad::importe(): (milesimas * centimos + 500) / 1000
    const importe = (mil, cent) => Math.floor((mil * cent + 500) / 1000);

    const soles = (cent) => 'S/ ' + (cent / 100).toFixed(2);

    // ---------- Paneles laterales (drawers) ----------

    const enfocables = 'a[href], button:not([disabled]), input:not([type=hidden]):not([disabled]), select:not([disabled]), textarea:not([disabled]), summary, [tabindex]:not([tabindex="-1"])';

    function drawerAbierto() {
        return $$('.drawer').find((d) => d.classList.contains('abierto') || d.matches(':target')) || null;
    }

    let ultimoFoco = null;

    function enfocarDrawer() {
        const drawer = drawerAbierto();
        if (drawer === null) {
            return;
        }
        if (!drawer.contains(document.activeElement)) {
            ultimoFoco = document.activeElement;
            // Primero el primer campo del formulario; si no hay, el boton Cerrar
            const campo = $('.drawer-cuerpo input:not([type=hidden]), .drawer-cuerpo select, .drawer-cuerpo textarea', drawer);
            (campo || $('.drawer-cerrar', drawer)).focus({ preventScroll: true });
        }
    }

    function cerrarDrawer(drawer) {
        const cerrar = $('.drawer-cerrar', drawer);
        if (cerrar === null) {
            return;
        }
        const destino = cerrar.getAttribute('href') || '#contenido';
        if (destino.startsWith('#')) {
            // Panel abierto con :target: basta cambiar el ancla, sin recargar
            window.location.hash = destino;
            const fila = document.getElementById(destino.slice(1));
            const volver = fila ? $('a, button', fila) : ultimoFoco;
            if (volver) {
                volver.focus({ preventScroll: true });
            }
        } else {
            window.location.href = destino;
        }
    }

    // Tab y Shift+Tab no salen del panel abierto
    function atraparFoco(evento, drawer) {
        const elementos = $$(enfocables, drawer).filter((el) => el.offsetParent !== null);
        if (elementos.length === 0) {
            return;
        }
        const primero = elementos[0];
        const ultimo = elementos[elementos.length - 1];
        if (evento.shiftKey && document.activeElement === primero) {
            evento.preventDefault();
            ultimo.focus();
        } else if (!evento.shiftKey && document.activeElement === ultimo) {
            evento.preventDefault();
            primero.focus();
        }
    }

    window.addEventListener('hashchange', enfocarDrawer);

    // ---------- Menu de celular ----------

    const casillaMenu = $('#menu-casilla');
    if (casillaMenu !== null) {
        const etiqueta = $('label[for=menu-casilla]');
        const actualizar = () => {
            if (etiqueta) {
                etiqueta.setAttribute('aria-expanded', casillaMenu.checked ? 'true' : 'false');
            }
        };
        casillaMenu.addEventListener('change', actualizar);
        actualizar();
        // Elegir una opcion cierra el menu
        $$('#menu-lateral nav a').forEach((a) => a.addEventListener('click', () => {
            casillaMenu.checked = false;
            actualizar();
        }));
    }

    // ---------- Buscador en vivo y filtro por categoria (tabla o tarjetas) ----------

    const normalizar = (texto) => texto.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');

    // Un filtro por lista: lo que se escribio en Buscar y la categoria elegida en los chips
    const filtros = new Map();

    function filtroDe(lista) {
        if (filtros.has(lista)) {
            return filtros.get(lista);
        }
        const esTabla = lista.tagName === 'TBODY';
        const elementos = $$(esTabla ? ':scope > tr' : ':scope > li', lista);
        const textos = elementos.map((el) => normalizar(el.textContent));

        // Aviso "sin coincidencias", creado con textContent
        const sinResultados = document.createElement(esTabla ? 'tr' : 'li');
        const texto = esTabla ? document.createElement('td') : sinResultados;
        if (esTabla) {
            texto.colSpan = elementos[0] ? elementos[0].children.length : 1;
            sinResultados.appendChild(texto);
        }
        texto.className = 'sutil centro sin-resultados';
        sinResultados.hidden = true;
        lista.appendChild(sinResultados);

        const filtro = {
            texto: '',
            categoria: '',
            aplicar() {
                const buscado = normalizar(this.texto.trim());
                let visibles = 0;
                elementos.forEach((el, i) => {
                    const coincide = (buscado === '' || textos[i].includes(buscado))
                        && (this.categoria === '' || el.dataset.categoria === this.categoria);
                    el.hidden = !coincide;
                    visibles += coincide ? 1 : 0;
                });
                texto.textContent = this.texto.trim() !== ''
                    ? 'Nada coincide con "' + this.texto.trim() + '".'
                    : 'No hay productos en esta categoría.';
                sinResultados.hidden = visibles > 0;
            },
        };
        filtros.set(lista, filtro);
        return filtro;
    }

    $$('[data-buscador]').forEach((caja) => {
        const campo = $('input[data-filtrar]', caja);
        const lista = campo ? document.getElementById(campo.dataset.filtrar) : null;
        if (lista === null) {
            return;
        }
        caja.hidden = false;
        const filtro = filtroDe(lista);
        campo.addEventListener('input', () => {
            filtro.texto = campo.value;
            filtro.aplicar();
        });
        campo.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape' && campo.value !== '') {
                evento.stopPropagation();
                campo.value = '';
                campo.dispatchEvent(new Event('input'));
            }
        });
    });

    // Chips de categoria: filtran sin recargar. Sin JavaScript son enlaces y filtra el servidor.
    $$('[data-chips]').forEach((nav) => {
        const lista = document.getElementById(nav.dataset.chips);
        if (lista === null) {
            return;
        }
        const filtro = filtroDe(lista);
        nav.addEventListener('click', (evento) => {
            const chip = evento.target.closest('[data-categoria]');
            if (chip === null || evento.ctrlKey || evento.metaKey || evento.shiftKey) {
                return;
            }
            evento.preventDefault();
            $$('[data-categoria]', nav).forEach((c) => c.removeAttribute('aria-current'));
            chip.setAttribute('aria-current', 'true');
            filtro.categoria = chip.dataset.categoria;
            filtro.aplicar();
            if (chip.href) {
                history.replaceState(null, '', chip.href);
            }
        });
    });

    // ---------- Menu lateral plegable (computadora): fijo, solo iconos o automatico ----------
    // El servidor ya pinto el modo guardado (cookie menu_<id>); aqui van el boton, la eleccion y los globos.

    const raiz = document.documentElement;
    const lateral = $('#menu-lateral');
    const botonPlegar = $('[data-menu-plegar]');
    const modoMenu = $('[data-modo-menu]');
    if (lateral !== null && botonPlegar !== null && modoMenu !== null) {
        const cookieMenu = 'menu_' + raiz.dataset.usuario;
        let relojAnimacion = 0;
        const ponerModo = (modo, guardar) => {
            if (modo === 'fijo') {
                delete raiz.dataset.menu;
            } else {
                raiz.dataset.menu = modo;
            }
            const abierto = modo === 'fijo';
            const texto = abierto ? 'Ocultar menú' : 'Fijar menú abierto';
            botonPlegar.setAttribute('aria-expanded', abierto ? 'true' : 'false');
            botonPlegar.title = texto;
            $('.texto-menu', botonPlegar).textContent = texto;
            $$('input', modoMenu).forEach((radio) => { radio.checked = radio.value === modo; });
            if (guardar) {
                // Los textos entran con un fundido corto mientras el menu cambia de ancho (ver estilos.css)
                raiz.classList.add('menu-animando');
                clearTimeout(relojAnimacion);
                relojAnimacion = setTimeout(() => raiz.classList.remove('menu-animando'), 450);
                // Un año, solo en este navegador; el servidor solo acepta fijo, iconos o auto
                document.cookie = cookieMenu + '=' + modo + '; path=/; max-age=31536000; SameSite=Lax';
            }
        };
        ponerModo(raiz.dataset.menu || 'fijo', false);
        botonPlegar.hidden = false;
        modoMenu.hidden = false;
        botonPlegar.addEventListener('click', () => {
            ponerModo(raiz.dataset.menu ? 'fijo' : 'iconos', true);
            botonPlegar.focus();
        });
        modoMenu.addEventListener('change', (evento) => ponerModo(evento.target.value, true));
        // En "auto" el menu se queda abierto mientras tenga el foco (para Tab). Tras un clic con el
        // mouse se suelta el foco, asi el menu se pliega apenas el cursor sale.
        lateral.addEventListener('click', (evento) => {
            if (evento.detail > 0) {
                setTimeout(() => { // despues del cambio de modo que hizo este mismo clic
                    if (raiz.dataset.menu === 'auto' && lateral.contains(document.activeElement)) {
                        document.activeElement.blur();
                    }
                }, 0);
            }
        });

        // Con solo iconos, el nombre de cada opcion sale en un globo al pasar el cursor o con Tab.
        // Va fuera del menu (position: fixed) porque el menu recorta lo que sobresale.
        const globo = document.createElement('div');
        globo.className = 'menu-globo';
        globo.hidden = true;
        globo.setAttribute('aria-hidden', 'true'); // el lector de pantalla ya lee el texto del enlace
        document.body.appendChild(globo);
        const mostrarGlobo = (evento) => {
            const opcion = evento.target.closest('a, button');
            const texto = opcion ? $('.texto-menu', opcion) : null;
            if (raiz.dataset.menu !== 'iconos' || texto === null || window.innerWidth <= 960) {
                globo.hidden = true;
                return;
            }
            const caja = opcion.getBoundingClientRect();
            globo.textContent = texto.textContent;
            globo.style.left = (lateral.getBoundingClientRect().right + 8) + 'px';
            globo.style.top = (caja.top + caja.height / 2) + 'px';
            globo.hidden = false;
        };
        const ocultarGlobo = () => { globo.hidden = true; };
        lateral.addEventListener('mouseover', mostrarGlobo);
        lateral.addEventListener('focusin', mostrarGlobo);
        lateral.addEventListener('mouseleave', ocultarGlobo);
        lateral.addEventListener('focusout', ocultarGlobo);
        lateral.addEventListener('scroll', ocultarGlobo);
        botonPlegar.addEventListener('click', ocultarGlobo);
    }

    // ---------- Guias "Como se usa": se recuerdan cerradas en este navegador ----------

    $$('details[data-guia]').forEach((guia) => {
        const clave = 'guia:' + guia.dataset.guia;
        try {
            if (localStorage.getItem(clave) === 'cerrada') {
                guia.open = false;
            }
        } catch (e) {
            // Navegador sin almacenamiento: la guia solo sale abierta
        }
        guia.addEventListener('toggle', () => {
            try {
                localStorage.setItem(clave, guia.open ? 'abierta' : 'cerrada');
            } catch (e) {
                // Sin almacenamiento no se recuerda, y no pasa nada
            }
        });
    });

    // ---------- Avisos cortos que se van solos (toast) ----------

    let toast = null;
    let toastReloj = 0;

    function avisar(texto, error = false) {
        if (toast === null) {
            toast = document.createElement('div');
            toast.className = 'toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.appendChild(toast);
        }
        toast.textContent = texto;
        toast.classList.toggle('error', error);
        toast.classList.add('visible');
        clearTimeout(toastReloj);
        toastReloj = setTimeout(() => toast.classList.remove('visible'), 2200);
    }

    // ---------- Foto del producto: se sube apenas se elige el archivo ----------

    $$('input[type=file][data-enviar-al-elegir]').forEach((campo) => {
        campo.addEventListener('change', () => {
            if (campo.files.length > 0) {
                avisar('Subiendo la foto…');
                campo.form.requestSubmit();
            }
        });
    });

    // ---------- localStorage que no rompe la pagina (modo privado o bloqueado) ----------
    const memoria = {
        leer(clave) {
            try { return localStorage.getItem(clave); } catch (error) { return null; }
        },
        poner(clave, valor) {
            try { localStorage.setItem(clave, valor); } catch (error) { /* sin espacio o bloqueado: se sigue sin guardar */ }
        },
        quitar(clave) {
            try { localStorage.removeItem(clave); } catch (error) { /* idem */ }
        },
    };

    // La venta ya se registro: el comprobante llega con &registrada=<clave> y se borra su copia del navegador
    const registrada = new URLSearchParams(location.search).get('registrada');
    if (registrada !== null && document.documentElement.dataset.usuario) {
        const claveBorrador = 'venta_borrador_' + document.documentElement.dataset.usuario;
        try {
            if ((JSON.parse(memoria.leer(claveBorrador) || 'null') || {}).clave === registrada) {
                memoria.quitar(claveBorrador);
            }
        } catch (error) {
            memoria.quitar(claveBorrador);
        }
        history.replaceState(null, '', location.href.replace(/&registrada=[^&#]*/, ''));
    }

    // ---------- Conexion con el servidor: franja roja si se cae (red de la tienda, internet o servidor) ----------
    // navigator.onLine solo sabe si la PC tiene red; para saber si el servidor responde se pide ?ruta=salud.
    const conexion = (() => {
        let caida = !navigator.onLine;
        let franja = null;
        let reloj = 0;
        let vigilando = false;

        function pintar(volvio) {
            if (franja === null) {
                franja = document.createElement('div');
                franja.className = 'conexion-aviso';
                franja.setAttribute('role', 'alert');
                franja.hidden = true;
                (document.querySelector('.cuerpo') || document.body).prepend(franja);
            }
            if (caida) {
                franja.classList.remove('volvio');
                franja.textContent = document.querySelector('form[data-venta]')
                    ? 'Sin conexión con el servidor. No cierres esta página: la venta queda guardada y la registras cuando vuelva.'
                    : 'Sin conexión con el servidor. Lo que guardes ahora no llegará; espera a que vuelva la conexión.';
                franja.hidden = false;
            } else if (volvio) {
                franja.classList.add('volvio');
                franja.textContent = 'Conexión recuperada.';
                franja.hidden = false;
                setTimeout(() => {
                    if (!caida) {
                        franja.hidden = true;
                    }
                }, 4000);
            }
        }

        function cambiar(nuevo) {
            const antes = caida;
            caida = nuevo;
            if (antes !== caida || caida) {
                pintar(antes && !caida);
            }
        }

        async function probar() {
            if (!navigator.onLine) {
                cambiar(true);
                return;
            }
            const corte = new AbortController();
            const espera = setTimeout(() => corte.abort(), 5000);
            try {
                const respuesta = await fetch('/?ruta=salud', { cache: 'no-store', signal: corte.signal });
                cambiar(!respuesta.ok);
            } catch (error) {
                cambiar(true);
            } finally {
                clearTimeout(espera);
            }
        }

        // Cada 30 segundos (5 si esta caida), solo con la pagina a la vista
        function programar() {
            clearTimeout(reloj);
            if (vigilando) {
                reloj = setTimeout(async () => {
                    if (document.visibilityState === 'visible') {
                        await probar();
                    }
                    programar();
                }, caida ? 5000 : 30000);
            }
        }

        window.addEventListener('offline', () => cambiar(true));
        window.addEventListener('online', () => probar().then(programar));
        if (caida) {
            pintar(false);
        }
        return {
            caida: () => caida,
            vigilar() {
                vigilando = true;
                programar();
            },
        };
    })();

    // ---------- Pantalla de venta: total, pagos y avisos en vivo ----------

    const formVenta = $('form[data-venta]');
    if (formVenta !== null) {
        const caja = $('[data-total-vivo]', formVenta);
        const cuerpoLineas = $('[data-lineas] tbody', formVenta);
        const lineas = () => $$(':scope > tr', cuerpoLineas);
        const campo = (nombre) => formVenta.elements.namedItem(nombre);
        const carrito = $('[data-carrito]', formVenta);
        const nombreDe = (opcion) => opcion.textContent.split('·')[0].trim();
        caja.hidden = false;

        // Promociones del dia y tope del descuento manual: vienen en data-promociones (los manda la vista, ya escapados).
        // Mismas reglas que Descuentos::calcular() del servidor, que al registrar vuelve a calcular todo.
        let datosPromo = { promociones: [], tope: 0 };
        try {
            datosPromo = JSON.parse(formVenta.dataset.promociones || '{}');
        } catch (e) {
            // Sin datos: se muestra el total sin descuentos
        }
        const promociones = datosPromo.promociones || [];
        const porcentajeDe = (cent, centesimas) => Math.floor((cent * centesimas + 5000) / 10000);
        function descuentoLinea(promo, mil, precio, alPeso) {
            const imp = importe(mil, precio);
            let d = 0;
            if (promo.tipo === 'PORCENTAJE') {
                d = porcentajeDe(imp, promo.porcentaje);
            } else if (promo.tipo === 'PRECIO') {
                d = promo.precio < precio ? imp - importe(mil, promo.precio) : 0;
            } else if (promo.tipo === 'PACK' && !alPeso && mil % 1000 === 0) {
                const packs = Math.floor(mil / 1000 / promo.pack);
                d = importe(packs * promo.pack * 1000, precio) - packs * promo.precio;
            }
            return Math.max(0, Math.min(d, imp));
        }
        function calcularDescuentos(pedido, avisos, listaDescuentos) {
            let productos = 0;
            let base = 0;
            pedido.forEach((mil, id) => {
                const opcion = $('select option[value="' + CSS.escape(id) + '"]', formVenta);
                const precio = centimos(opcion.dataset.precio);
                const imp = importe(mil, precio);
                let mejor = null;
                let d = 0;
                promociones.forEach((promo) => {
                    if (promo.tipo !== 'VENTA' && String(promo.producto) === id) {
                        const x = descuentoLinea(promo, mil, precio, opcion.dataset.peso === '1');
                        if (x > d) {
                            d = x;
                            mejor = promo;
                        }
                    }
                });
                if (d > 0) {
                    listaDescuentos.push(nombreDe(opcion) + ' · ' + mejor.nombre + ': −' + soles(d));
                }
                productos += d;
                base += imp - d;
            });
            // Un solo descuento a toda la compra: la mejor promocion VENTA o el del cajero, el mayor
            let general = null;
            promociones.forEach((promo) => {
                if (promo.tipo === 'VENTA' && base >= promo.minimo) {
                    const m = porcentajeDe(base, promo.porcentaje);
                    if (m > 0 && (general === null || m > general.monto)) {
                        general = { nombre: promo.nombre, monto: m };
                    }
                }
            });
            const valor = campo('descuento_valor');
            const texto = valor ? valor.value.trim() : '';
            if (texto !== '' && !/^0+(\.0*)?$/.test(texto)) {
                const n = centimos(texto);
                const enPorcentaje = campo('descuento_unidad').value !== 'MONTO';
                const tope = porcentajeDe(base, datosPromo.tope);
                const monto = n === null ? null : (enPorcentaje ? porcentajeDe(base, n) : n);
                if (monto === null || monto <= 0) {
                    avisos.push('El descuento no es válido.');
                } else if ((enPorcentaje && n > datosPromo.tope) || monto > tope) {
                    avisos.push('Puedes descontar hasta ' + soles(tope) + ' (' + (datosPromo.tope / 100) + ' %). Para más, pide una promoción.');
                } else if (campo('descuento_motivo').value.trim() === '') {
                    avisos.push('Escribe el motivo del descuento.');
                } else if (general === null || monto > general.monto) {
                    general = { nombre: 'Descuento del cajero', monto };
                } else {
                    avisos.push('La promoción de hoy descuenta más que tu descuento: se aplica la promoción.');
                }
            }
            if (general !== null) {
                listaDescuentos.push(general.nombre + ': −' + soles(general.monto));
            }
            return productos + (general ? general.monto : 0);
        }

        // Medio de pago elegido: muestra solo sus campos (sin JavaScript se ven todos)
        const medioElegido = () => {
            const marcado = formVenta.querySelector('input[name=medio_pago]:checked');
            return marcado ? marcado.value : 'EFECTIVO';
        };
        const rotulosMedio = { EFECTIVO: 'Tarjeta o Sip', DEBITO: 'Con débito', CREDITO: 'Con crédito', SIP: 'Con Sip' };

        function calcular() {
            let total = 0;
            const avisos = [];
            const pedidoPorProducto = new Map();

            lineas().forEach((fila, i) => {
                const select = $('select', fila);
                const cantidad = $('input[name="cantidad[]"]', fila);
                const celda = $('[data-importe]', fila);
                const opcion = select.selectedOptions[0];
                cantidad.classList.remove('campo-error');
                if (!opcion || opcion.value === '') {
                    celda.textContent = '—';
                    return;
                }
                const mil = milesimas(cantidad.value);
                const cent = centimos(opcion.dataset.precio);
                const nombreFila = carrito !== null ? nombreDe(opcion) : 'Fila ' + (i + 1);
                if (mil === null || mil === 0 || cent === null) {
                    celda.textContent = '—';
                    if (cantidad.value.trim() !== '') {
                        cantidad.classList.add('campo-error');
                        avisos.push(nombreFila + ': la cantidad no es válida.');
                    }
                    return;
                }
                if (opcion.dataset.peso !== '1' && mil % 1000 !== 0) {
                    cantidad.classList.add('campo-error');
                    avisos.push(nombreFila + ': se vende por unidad, sin decimales.');
                }
                const sub = importe(mil, cent);
                celda.textContent = soles(sub);
                total += sub;
                pedidoPorProducto.set(opcion.value, (pedidoPorProducto.get(opcion.value) || 0) + mil);
            });

            // Stock: suma lo pedido del mismo producto en varias filas
            pedidoPorProducto.forEach((mil, id) => {
                const opcion = $('select option[value="' + CSS.escape(id) + '"]', formVenta);
                const stock = opcion && opcion.dataset.stock !== undefined ? milesimas(opcion.dataset.stock) : null;
                if (stock !== null && mil > stock) {
                    avisos.push(opcion.textContent.split('·')[0].trim() + ': solo hay ' + (stock / 1000) + ' en la tienda.');
                }
            });

            // Promociones del dia y descuento manual
            const subtotal = total;
            const listaDescuentos = [];
            const descuentos = pedidoPorProducto.size > 0 ? calcularDescuentos(pedidoPorProducto, avisos, listaDescuentos) : 0;
            total -= descuentos;

            const medio = medioElegido();
            const conTarjeta = medio === 'DEBITO' || medio === 'CREDITO';
            const montoMedio = (nombre) => {
                const texto = campo(nombre).value.trim();
                return texto === '' ? total : centimos(texto);
            };
            const tarjeta = conTarjeta ? montoMedio('tarjeta_monto') : 0;
            const sip = medio === 'SIP' ? montoMedio('sip_monto') : 0;
            const efectivo = centimos(campo('efectivo_recibido').value || '0');
            const otros = (tarjeta || 0) + (sip || 0);
            const falta = Math.max(0, total - otros);
            const vuelto = efectivo === null ? 0 : Math.max(0, efectivo - falta);
            $$('[data-pago]', formVenta).forEach((bloque) => {
                const tipo = bloque.dataset.pago;
                bloque.hidden = tipo === 'tarjeta' ? !conTarjeta : tipo === 'sip' ? medio !== 'SIP' : medio !== 'EFECTIVO' && falta <= 0;
            });
            $('[data-rotulo-otros]', caja).textContent = rotulosMedio[medio] || 'Tarjeta o Sip';

            if (tarjeta === null || sip === null || efectivo === null) {
                avisos.push('Un monto de pago no es válido (usa hasta 2 decimales).');
            } else if (total > 0 && otros > total) {
                avisos.push((conTarjeta ? 'El monto con tarjeta' : 'El monto con Sip') + ' pasa el total.');
            } else if (total > 0 && efectivo < falta) {
                avisos.push('Falta ' + soles(falta - efectivo) + ' en efectivo.');
            }

            const tipo = formVenta.querySelector('input[name=tipo]:checked');
            const documento = campo('documento').value.trim();
            if (tipo && tipo.value === 'F' && !/^\d{11}$/.test(documento)) {
                avisos.push('La factura necesita el RUC del cliente (11 dígitos).');
            } else if (documento !== '' && !/^(\d{8}|\d{11})$/.test(documento)) {
                avisos.push('El documento debe ser un DNI de 8 dígitos o un RUC de 11.');
            }

            $('[data-total]', caja).textContent = soles(total);
            $$('[data-fila-descuento]', caja).forEach((el) => { el.hidden = descuentos <= 0; });
            $('[data-subtotal]', caja).textContent = soles(subtotal);
            $('[data-descuentos]', caja).textContent = '−' + soles(descuentos);
            const ulDescuentos = $('[data-lista-descuentos]', caja);
            ulDescuentos.hidden = listaDescuentos.length === 0;
            ulDescuentos.replaceChildren(...listaDescuentos.map((texto) => {
                const li = document.createElement('li');
                li.textContent = texto;
                return li;
            }));
            $('[data-otros]', caja).textContent = soles(otros);
            $('[data-falta]', caja).textContent = soles(falta);
            $('[data-vuelto]', caja).textContent = soles(vuelto);
            const lista = $('[data-avisos]', caja);
            lista.replaceChildren(...avisos.map((texto) => {
                const li = document.createElement('li');
                li.textContent = texto;
                return li;
            }));
            caja.classList.toggle('con-avisos', avisos.length > 0);
            pintarCliente(tipo ? tipo.value : 'B', documento);
            pintarEfectivoRapido(falta);
            totalVivo = total;
            actualizarCarrito();
        }

        // Quien recibe el comprobante, al momento: CLIENTE VARIOS, DNI o RUC (el servidor vuelve a validar)
        const etiquetaCliente = $('[data-cliente]', formVenta);
        function pintarCliente(tipo, documento) {
            if (etiquetaCliente === null) {
                return;
            }
            let texto = 'Cliente varios';
            let clase = 'neutra';
            if (/^\d{11}$/.test(documento)) {
                texto = 'RUC';
                clase = 'ok';
            } else if (/^\d{8}$/.test(documento)) {
                texto = tipo === 'F' ? 'La factura pide RUC' : 'DNI';
                clase = tipo === 'F' ? 'reponer' : 'ok';
            } else if (documento !== '') {
                texto = documento.length < 8 ? 'Faltan dígitos' : 'Ni DNI ni RUC';
                clase = 'reponer';
            } else if (tipo === 'F') {
                texto = 'Falta el RUC';
                clase = 'reponer';
            }
            etiquetaCliente.hidden = false;
            etiquetaCliente.textContent = texto;
            etiquetaCliente.className = 'etiqueta ' + clase;
        }

        // Botones de efectivo: lo exacto y los billetes con los que suele pagar el cliente (los que alcanzan)
        const efectivoRapido = $('[data-efectivo-rapido]', formVenta);
        let faltaRapido = -1;
        function pintarEfectivoRapido(falta) {
            if (efectivoRapido === null || falta === faltaRapido) {
                return;
            }
            faltaRapido = falta;
            const montos = [falta];
            [1000, 2000, 5000, 10000, 20000].forEach((billete) => {
                const redondeado = Math.ceil(falta / billete) * billete;
                if (redondeado > falta && !montos.includes(redondeado)) {
                    montos.push(redondeado);
                }
            });
            efectivoRapido.hidden = falta <= 0;
            efectivoRapido.replaceChildren(...montos.slice(0, 5).map((monto, i) => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'secundario chico';
                boton.dataset.monto = (monto / 100).toFixed(2);
                boton.textContent = i === 0 ? 'Exacto' : soles(monto);
                if (i === 0) {
                    boton.setAttribute('aria-label', 'Exacto, ' + soles(monto));
                }
                return boton;
            }));
        }
        if (efectivoRapido !== null) {
            efectivoRapido.addEventListener('click', (evento) => {
                const boton = evento.target.closest('button[data-monto]');
                if (boton) {
                    const campoEfectivo = campo('efectivo_recibido');
                    campoEfectivo.value = boton.dataset.monto;
                    campoEfectivo.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        }

        // Celular: barra fija con el total y "Cobrar", que baja al cobro. Se va cuando el cobro ya esta a la vista.
        const cobroFijo = $('[data-cobro-fijo]', formVenta);
        let totalVivo = 0;
        function pintarCobroFijo(productos) {
            if (cobroFijo === null) {
                return;
            }
            cobroFijo.hidden = productos === 0;
            $('[data-cobro-fijo-cuenta]', cobroFijo).textContent = productos + (productos === 1 ? ' producto' : ' productos');
            $('[data-cobro-fijo-monto]', cobroFijo).textContent = soles(totalVivo);
        }
        if (cobroFijo !== null && 'IntersectionObserver' in window) {
            new IntersectionObserver((entradas) => {
                cobroFijo.classList.toggle('cobro-a-la-vista', entradas[0].isIntersecting);
            }, { rootMargin: '0px 0px -45% 0px' }).observe($('#cobro', formVenta));
        }
        if (cobroFijo !== null) {
            $('[data-cobro-fijo-ir]', cobroFijo).addEventListener('click', (evento) => {
                evento.preventDefault();
                const cobro = $('#cobro', formVenta);
                const suave = !matchMedia('(prefers-reduced-motion: reduce)').matches;
                cobro.scrollIntoView({ behavior: suave ? 'smooth' : 'auto', block: 'start' });
                campo('efectivo_recibido').focus({ preventScroll: true });
            });
        }

        // F9 registra la venta desde cualquier campo, como en una caja registradora (requestSubmit pasa por la validacion)
        document.addEventListener('keydown', (evento) => {
            if (evento.key === 'F9' && drawerAbierto() === null) {
                evento.preventDefault();
                if (totalVivo <= 0) {
                    avisar('Agrega al menos un producto antes de cobrar.', true);
                    return;
                }
                formVenta.requestSubmit($('button[type=submit]', formVenta));
            }
        });

        // ---------- Carrito: las filas del formulario vistas como productos con foto, cantidad e importe ----------
        // Las filas (select + cantidad) siguen siendo lo que se envia; el carrito solo las muestra y las cambia.
        function filaDe(id) {
            return lineas().find((f) => $('select', f).value === id) || null;
        }

        function quitarFila(fila) {
            $('select', fila).value = '';
            $('input[name="cantidad[]"]', fila).value = '';
            calcular();
            pintarCarrito();
        }

        // Suma o resta: 1 por unidad, 0.100 kg al peso. En cero, el producto sale del carrito.
        function cambiarCantidad(fila, signo) {
            const cantidad = $('input[name="cantidad[]"]', fila);
            const alPeso = $('select', fila).selectedOptions[0].dataset.peso === '1';
            const paso = alPeso ? 100 : 1000;
            const actual = milesimas(cantidad.value || '0') || 0;
            const nueva = Math.max(0, actual + signo * paso);
            if (nueva === 0) {
                quitarFila(fila);
                return;
            }
            cantidad.value = alPeso ? (nueva / 1000).toFixed(3) : String(nueva / 1000);
            calcular();
            pintarCarrito();
        }

        // Los botones con su icono vienen de un <template> de la vista (iconos.svg), sin armar SVG aqui
        const plantilla = $('template[data-plantilla-carrito]', formVenta);
        function botonCarrito(tipo, etiqueta, alTocar) {
            const boton = $('[data-boton="' + tipo + '"]', plantilla.content).cloneNode(true);
            boton.setAttribute('aria-label', etiqueta);
            boton.addEventListener('click', alTocar);
            return boton;
        }

        function itemCarrito(fila, opcion) {
            const id = opcion.value;
            const alPeso = opcion.dataset.peso === '1';
            const cantidadFila = $('input[name="cantidad[]"]', fila);
            const li = document.createElement('li');
            li.className = 'carrito-item';
            li.dataset.producto = id;
            li.filaVenta = fila;

            const tarjeta = $('[data-agregar="' + CSS.escape(id) + '"] .foto', formVenta);
            const foto = tarjeta !== null ? tarjeta.cloneNode(true) : document.createElement('span');
            foto.classList.add('chica');
            li.appendChild(foto);

            const info = document.createElement('div');
            info.className = 'carrito-info';
            const nombre = document.createElement('strong');
            nombre.textContent = nombreDe(opcion);
            const precio = document.createElement('small');
            precio.textContent = soles(centimos(opcion.dataset.precio)) + (alPeso ? ' / kg' : ' c/u');
            info.append(nombre, precio);
            li.appendChild(info);

            const control = document.createElement('div');
            control.className = 'carrito-cantidad';
            const entrada = document.createElement('input');
            entrada.inputMode = 'decimal';
            entrada.autocomplete = 'off';
            entrada.value = cantidadFila.value;
            entrada.placeholder = alPeso ? 'kg' : '0';
            entrada.setAttribute('aria-label', 'Cantidad de ' + nombre.textContent + (alPeso ? ' en kg' : ''));
            entrada.dataset.cantidadCarrito = id;
            entrada.addEventListener('input', () => {
                cantidadFila.value = entrada.value;
                calcular();
            });
            // Enter en la cantidad no envia la venta: vuelve al lector
            entrada.addEventListener('keydown', (evento) => {
                if (evento.key === 'Enter') {
                    evento.preventDefault();
                    const escanear = $('[data-escanear]', formVenta);
                    if (escanear) {
                        escanear.focus();
                    }
                }
            });
            control.append(
                botonCarrito('menos', 'Uno menos de ' + nombre.textContent, () => cambiarCantidad(fila, -1)),
                entrada,
                botonCarrito('mas', 'Uno más de ' + nombre.textContent, () => cambiarCantidad(fila, 1))
            );
            li.appendChild(control);

            const importeItem = document.createElement('strong');
            importeItem.className = 'carrito-importe';
            importeItem.dataset.importeCarrito = '';
            li.appendChild(importeItem);
            li.appendChild(botonCarrito('quitar', 'Quitar ' + nombre.textContent, () => quitarFila(fila)));
            return li;
        }

        // Importes y cantidades del carrito y numero sobre cada foto de la cuadricula
        function actualizarCarrito() {
            if (carrito === null) {
                return;
            }
            const enCarrito = new Map();
            $$(':scope > li.carrito-item', carrito).forEach((li) => {
                const fila = li.filaVenta;
                $('[data-importe-carrito]', li).textContent = $('[data-importe]', fila).textContent;
                const entrada = $('input', li);
                entrada.classList.toggle('campo-error', $('input[name="cantidad[]"]', fila).classList.contains('campo-error'));
                const mil = milesimas(entrada.value || '0') || 0;
                const alPeso = $('select', fila).selectedOptions[0].dataset.peso === '1';
                enCarrito.set(li.dataset.producto, alPeso ? (mil > 0 ? '✓' : '') : (mil > 0 ? String(mil / 1000) : ''));
            });
            $$('[data-agregar]', formVenta).forEach((boton) => {
                const marca = $('[data-en-carrito]', boton);
                const texto = enCarrito.get(boton.dataset.agregar) || '';
                marca.textContent = texto;
                boton.classList.toggle('en-carrito', enCarrito.has(boton.dataset.agregar));
            });
            const productos = enCarrito.size;
            $('[data-carrito-cuenta]', formVenta).textContent = String(productos);
            $('[data-carrito-vaciar]', formVenta).hidden = productos === 0;
            pintarCobroFijo(productos);
        }

        function pintarCarrito() {
            if (carrito === null) {
                return;
            }
            const items = [];
            lineas().forEach((fila) => {
                const opcion = $('select', fila).selectedOptions[0];
                if (opcion && opcion.value !== '') {
                    items.push(itemCarrito(fila, opcion));
                }
            });
            if (items.length === 0) {
                const vacio = document.createElement('li');
                vacio.className = 'carrito-vacio';
                const titulo = document.createElement('strong');
                titulo.textContent = 'El carrito está vacío';
                const texto = document.createElement('span');
                texto.textContent = 'Escanea un código o toca la foto de un producto para empezar.';
                vacio.append(titulo, texto);
                items.push(vacio);
            }
            carrito.replaceChildren(...items);
            actualizarCarrito();
        }

        if (carrito !== null) {
            carrito.hidden = false;
            $('[data-carrito-cabeza]', formVenta).hidden = false;
            formVenta.classList.add('con-carrito');
            $('[data-carrito-vaciar]', formVenta).addEventListener('click', async () => {
                if (await confirmar('¿Quitar todos los productos de esta venta?', 'Sí, vaciar')) {
                    lineas().forEach((fila) => {
                        $('select', fila).value = '';
                        $('input[name="cantidad[]"]', fila).value = '';
                    });
                    calcular();
                    pintarCarrito();
                    const escanear = $('[data-escanear]', formVenta);
                    if (escanear) {
                        escanear.focus();
                    }
                }
            });
        }

        formVenta.addEventListener('input', calcular);
        formVenta.addEventListener('change', calcular);
        pintarCarrito();
        calcular();

        // ---------- Cuadricula de productos y lector de codigo de barras ----------
        // Tocar un producto (o escanear su codigo) lo pone en la lista de arriba: si ya esta, suma 1.
        // Las filas siguen siendo el formulario que se envia; esto solo las llena.
        const selector = $('[data-selector]', formVenta);
        const MAXIMO_FILAS = 40;

        // Fila vacia nueva, copiando la ultima (sin sus datos)
        function filaNueva() {
            const filas = lineas();
            if (filas.length >= MAXIMO_FILAS) {
                return null;
            }
            const copia = filas[filas.length - 1].cloneNode(true);
            const numero = filas.length + 1;
            copia.cells[0].textContent = String(numero);
            $('select', copia).value = '';
            $('select', copia).setAttribute('aria-label', 'Producto ' + numero);
            const cantidad = $('input[name="cantidad[]"]', copia);
            cantidad.value = '';
            cantidad.setAttribute('aria-label', 'Cantidad ' + numero);
            cuerpoLineas.appendChild(copia);
            return copia;
        }

        function agregar(id) {
            const opcion = $('select option[value="' + CSS.escape(id) + '"]', formVenta);
            if (opcion === null) {
                avisar('Ese producto no se puede vender ahora.', true);
                return;
            }
            const nombre = nombreDe(opcion);
            const alPeso = opcion.dataset.peso === '1';
            let fila = filaDe(id);
            if (fila === null) {
                fila = lineas().find((f) => $('select', f).value === '') || filaNueva();
                if (fila === null) {
                    avisar('La venta ya tiene ' + MAXIMO_FILAS + ' productos distintos.', true);
                    return;
                }
                $('select', fila).value = id;
                $('input[name="cantidad[]"]', fila).value = alPeso ? '' : '0';
            }
            const cantidad = $('input[name="cantidad[]"]', fila);
            if (!alPeso) {
                const actual = milesimas(cantidad.value || '0') || 0;
                cantidad.value = String(Math.floor(actual / 1000) + 1);
                avisar('+1 ' + nombre + ' (van ' + cantidad.value + ')');
            }
            fila.classList.remove('recien');
            void fila.offsetWidth;   // reinicia la animacion
            fila.classList.add('recien');
            calcular();
            pintarCarrito();
            // Al peso: el cajero escribe los kg que marca la balanza (en el carrito o, sin carrito, en la fila)
            const destino = carrito !== null ? $('[data-cantidad-carrito="' + CSS.escape(id) + '"]', carrito) : cantidad;
            if (carrito !== null) {
                const item = destino.closest('li');
                item.classList.remove('recien');
                void item.offsetWidth;
                item.classList.add('recien');
                // Solo se mueve la lista del carrito, no la pagina
                carrito.scrollTop = item.offsetTop - carrito.offsetTop - carrito.clientHeight / 2;
            }
            if (alPeso) {
                destino.focus();
                destino.select();
                avisar(nombre + ': escribe los kg de la balanza.');
            }
        }

        if (selector !== null) {
            selector.hidden = false;
            const rejilla = $('[data-rejilla-venta]', selector);
            const filtro = filtroDe(rejilla);

            rejilla.addEventListener('click', (evento) => {
                const boton = evento.target.closest('[data-agregar]');
                if (boton !== null) {
                    agregar(boton.dataset.agregar);
                    boton.classList.remove('agregado');
                    void boton.offsetWidth;
                    boton.classList.add('agregado');
                }
            });

            const buscar = $('[data-buscar-producto]', selector);
            buscar.addEventListener('input', () => {
                filtro.texto = buscar.value;
                filtro.aplicar();
            });
            // Enter en el buscador no envia la venta: agrega el unico producto que quedo visible
            buscar.addEventListener('keydown', (evento) => {
                if (evento.key !== 'Enter') {
                    return;
                }
                evento.preventDefault();
                const visibles = $$(':scope > li:not([hidden]) [data-agregar]:not([disabled])', rejilla);
                if (visibles.length === 1) {
                    agregar(visibles[0].dataset.agregar);
                    buscar.value = '';
                    buscar.dispatchEvent(new Event('input'));
                }
            });

            $('[data-chips-venta]', selector).addEventListener('click', (evento) => {
                const chip = evento.target.closest('[data-categoria]');
                if (chip === null) {
                    return;
                }
                $$('[data-categoria]', evento.currentTarget).forEach((c) => c.setAttribute('aria-pressed', 'false'));
                chip.setAttribute('aria-pressed', 'true');
                filtro.categoria = chip.dataset.categoria;
                filtro.aplicar();
            });

            // El lector escribe el codigo y aprieta Enter: se agrega sin enviar el formulario
            const escanear = $('[data-escanear]', selector);
            escanear.addEventListener('keydown', (evento) => {
                if (evento.key !== 'Enter') {
                    return;
                }
                evento.preventDefault();
                const codigo = escanear.value.trim();
                escanear.value = '';
                if (codigo === '') {
                    return;
                }
                // El codigo propio o uno de sus otros codigos, aunque venga con otros ceros adelante
                const opcion = variantesCodigo(codigo)
                    .map((v) => $('select option[data-codigos~="' + CSS.escape(v) + '"]', formVenta))
                    .find((o) => o !== null) || null;
                if (opcion === null) {
                    avisar('El código ' + codigo + ' no está en el catálogo (o el producto está descontinuado).', true);
                    return;
                }
                agregar(opcion.value);
                if (opcion.dataset.peso !== '1') {
                    escanear.focus();
                }
            });
            escanear.focus({ preventScroll: true });
        }

        // ---------- Venta sin terminar: se guarda en este navegador y se recupera ----------
        // Si se corta la red (o se cierra la pagina) la venta armada no se pierde. Cada formulario trae su
        // clave_operacion: si el envio llego al servidor, reenviarla devuelve el mismo comprobante (no cobra dos veces).
        // Al volver a Nueva venta se pregunta al servidor si la venta enviada se registro (?ruta=venta_estado).
        const campoClave = $('[data-clave-operacion]', formVenta);
        const CAMPOS_BORRADOR = ['documento', 'efectivo_recibido', 'tarjeta_monto', 'tarjeta_tipo', 'tarjeta_marca',
            'tarjeta_ultimos4', 'tarjeta_autorizacion', 'sip_monto', 'sip_modalidad', 'sip_operacion'];
        const HORAS_BORRADOR = 12;

        function leidoDelFormulario() {
            const filas = lineas()
                .map((fila) => [$('select', fila).value, $('input[name="cantidad[]"]', fila).value])
                .filter(([id]) => id !== '');
            const campos = { tipo: campo('tipo').value };
            CAMPOS_BORRADOR.forEach((nombre) => {
                campos[nombre] = campo(nombre).value;
            });
            return { clave: campoClave.value, guardado: Date.now(), enviado: false, lineas: filas, campos };
        }

        function guardarBorrador(enviado = false) {
            if (!enviado && formVenta.dataset.enviando === '1') {
                return;   // ya se envio: no se pisa la marca de "enviada"
            }
            const borrador = leidoDelFormulario();
            borrador.enviado = enviado;
            if (borrador.lineas.length === 0 && !enviado) {
                memoria.quitar(CLAVE_BORRADOR);
                return;
            }
            memoria.poner(CLAVE_BORRADOR, JSON.stringify(borrador));
        }
        const guardarLuego = () => setTimeout(() => guardarBorrador(), 0);

        function ponerEnFormulario(borrador) {
            lineas().forEach((fila) => {
                $('select', fila).value = '';
                $('input[name="cantidad[]"]', fila).value = '';
            });
            let faltan = 0;
            borrador.lineas.forEach(([id, cantidad]) => {
                const opcion = $('select option[value="' + CSS.escape(String(id)) + '"]', formVenta);
                const fila = opcion === null ? null : (lineas().find((f) => $('select', f).value === '') || filaNueva());
                if (fila === null) {
                    faltan++;
                    return;
                }
                $('select', fila).value = String(id);
                $('input[name="cantidad[]"]', fila).value = String(cantidad);
            });
            const campos = borrador.campos || {};
            if (campos.tipo === 'B' || campos.tipo === 'F') {
                campo('tipo').value = campos.tipo;
            }
            CAMPOS_BORRADOR.forEach((nombre) => {
                if (typeof campos[nombre] === 'string' && campos[nombre] !== '') {
                    campo(nombre).value = campos[nombre];
                }
            });
            ['tarjeta_monto', 'sip_monto'].forEach((nombre) => {
                if (campo(nombre).value !== '') {
                    campo(nombre).closest('details').open = true;
                }
            });
            campoClave.value = borrador.clave;
            calcular();
            pintarCarrito();
            return faltan;
        }

        // Aviso que se queda (no se va solo como el toast): arriba del formulario, con un enlace opcional
        function avisoVenta(tipo, texto, enlace) {
            const aviso = document.createElement('div');
            aviso.className = 'aviso ' + tipo;
            aviso.setAttribute('role', tipo === 'error' ? 'alert' : 'status');
            const parrafo = document.createElement('span');
            parrafo.textContent = texto + ' ';
            if (enlace) {
                const a = document.createElement('a');
                a.href = enlace[0];
                a.textContent = enlace[1];
                parrafo.appendChild(a);
            }
            aviso.appendChild(parrafo);
            const cerrar = document.createElement('button');
            cerrar.type = 'button';
            cerrar.className = 'aviso-cerrar';
            cerrar.setAttribute('aria-label', 'Cerrar aviso');
            cerrar.textContent = '×';
            cerrar.addEventListener('click', () => aviso.remove());
            aviso.appendChild(cerrar);
            formVenta.before(aviso);
        }

        async function recuperarBorrador() {
            let borrador = null;
            try {
                borrador = JSON.parse(memoria.leer(CLAVE_BORRADOR) || 'null');
            } catch (error) {
                borrador = null;
            }
            const valido = borrador !== null && typeof borrador.clave === 'string' && Array.isArray(borrador.lineas)
                && Date.now() - Number(borrador.guardado) < HORAS_BORRADOR * 3600 * 1000;
            if (!valido) {
                memoria.quitar(CLAVE_BORRADOR);
                return;
            }
            if (!borrador.enviado) {
                const faltan = ponerEnFormulario(borrador);
                avisoVenta('alerta', 'Recuperamos la venta que estabas armando y no se llegó a registrar.'
                    + (faltan > 0 ? ' ' + faltan + ' producto(s) ya no se pueden vender y no se agregaron.' : '')
                    + ' Revísala y regístrala, o pulsa Vaciar.');
                return;
            }
            // Se envio y no sabemos si llego: se pregunta al servidor
            try {
                const respuesta = await fetch('/?ruta=venta_estado&clave=' + encodeURIComponent(borrador.clave),
                    { cache: 'no-store', credentials: 'same-origin' });
                if (!respuesta.ok) {
                    throw new Error('HTTP ' + respuesta.status);
                }
                const estado = await respuesta.json();
                if (estado.registrada) {
                    memoria.quitar(CLAVE_BORRADOR);
                    avisoVenta('alerta', 'Tu venta anterior sí quedó registrada' + (estado.comprobante ? ' (' + estado.comprobante + ')' : '')
                        + '. No la cobres otra vez.', estado.url ? [estado.url, 'Ver comprobante'] : null);
                    return;
                }
                ponerEnFormulario(borrador);
                avisoVenta('alerta', 'La venta que enviaste no llegó al servidor: no se registró ni se cobró en el sistema. '
                    + 'La recuperamos tal cual; revísala y vuelve a registrarla.');
            } catch (error) {
                // Sin conexion: se arma igual con la MISMA clave. Si ya estaba registrada, el servidor no la duplica.
                ponerEnFormulario(borrador);
                avisoVenta('error', 'Sin conexión: no se puede saber todavía si tu última venta se registró. '
                    + 'La dejamos armada; al registrarla, si ya estaba, el sistema muestra el mismo comprobante sin cobrar dos veces.');
            }
        }

        const CLAVE_BORRADOR = 'venta_borrador_' + (document.documentElement.dataset.usuario || '0');
        if (campoClave !== null) {
            if (formVenta.hasAttribute('data-con-error')) {
                // El servidor devolvio el formulario con un error: lo que se ve es lo ultimo que se envio
                guardarBorrador();
            } else {
                recuperarBorrador();
            }
            formVenta.addEventListener('input', guardarLuego);
            formVenta.addEventListener('change', guardarLuego);
            formVenta.addEventListener('click', guardarLuego);
            formVenta.addEventListener('keydown', guardarLuego);
            document.addEventListener('click', guardarLuego);   // el dialogo de "Vaciar" esta fuera del formulario
            window.addEventListener('pagehide', () => {
                if (formVenta.dataset.enviando !== '1') {
                    guardarBorrador();
                }
            });

            // Sin conexion no se envia: la venta queda armada y guardada hasta que vuelva
            formVenta.addEventListener('submit', (evento) => {
                if (conexion.caida()) {
                    evento.preventDefault();
                    guardarBorrador();
                    avisar('Sin conexión con el servidor: la venta NO se envió. Queda guardada; regístrala cuando vuelva la conexión.', true);
                    return;
                }
                guardarBorrador(true);
            });
            conexion.vigilar();
        }
    }

    // ---------- Validacion de formularios con mensajes junto al campo ----------

    function mensajeDe(campo) {
        if (campo.validity.valueMissing) {
            return campo.dataset.mensaje || 'Este dato es obligatorio.';
        }
        if (campo.validity.patternMismatch || campo.validity.tooShort) {
            return campo.dataset.mensaje || campo.title || 'El formato no es válido.';
        }
        if (campo.validity.rangeOverflow) {
            return 'La fecha no puede ser futura.';
        }
        return campo.validationMessage;
    }

    function marcarCampo(campo) {
        const id = campo.id ? campo.id + '-error' : '';
        let nota = id ? document.getElementById(id) : null;
        if (campo.checkValidity()) {
            campo.removeAttribute('aria-invalid');
            if (nota) {
                nota.remove();
            }
            return true;
        }
        campo.setAttribute('aria-invalid', 'true');
        if (nota === null && id) {
            nota = document.createElement('span');
            nota.className = 'error-campo';
            nota.id = id;
            campo.setAttribute('aria-describedby', id);
            campo.insertAdjacentElement('afterend', nota);
        }
        if (nota) {
            nota.textContent = mensajeDe(campo);
        }
        return false;
    }

    // ---------- Confirmacion propia (en vez de la ventana del navegador) ----------

    let dialogo = null;

    function confirmar(mensaje, textoBoton) {
        if (dialogo === null) {
            dialogo = document.createElement('dialog');
            dialogo.className = 'confirmacion';
            dialogo.setAttribute('aria-labelledby', 'confirmacion-texto');
            const texto = document.createElement('p');
            texto.id = 'confirmacion-texto';
            const botones = document.createElement('div');
            botones.className = 'botones-formulario';
            const cancelar = document.createElement('button');
            cancelar.type = 'button';
            cancelar.className = 'secundario';
            cancelar.textContent = 'Cancelar';
            cancelar.value = 'no';
            const aceptar = document.createElement('button');
            aceptar.type = 'button';
            aceptar.className = 'peligro';
            aceptar.value = 'si';
            botones.append(cancelar, aceptar);
            dialogo.append(texto, botones);
            [cancelar, aceptar].forEach((b) => b.addEventListener('click', () => dialogo.close(b.value)));
            document.body.appendChild(dialogo);
        }
        $('#confirmacion-texto', dialogo).textContent = mensaje;
        $('button.peligro', dialogo).textContent = textoBoton || 'Confirmar';
        dialogo.returnValue = 'no';
        return new Promise((resolver) => {
            dialogo.addEventListener('close', () => resolver(dialogo.returnValue === 'si'), { once: true });
            dialogo.showModal();
            $('button.secundario', dialogo).focus();
        });
    }

    // ---------- Envio: validar, confirmar y no enviar dos veces ----------

    $$('form').forEach((form) => {
        if (form.method.toLowerCase() === 'post') {
            form.noValidate = true; // los mensajes los pone marcarCampo(), junto al campo
            $$('input, select, textarea', form).forEach((campo) => {
                campo.addEventListener('blur', () => {
                    if (campo.hasAttribute('aria-invalid') || campo.value !== '') {
                        marcarCampo(campo);
                    }
                });
                campo.addEventListener('input', () => {
                    if (campo.hasAttribute('aria-invalid')) {
                        marcarCampo(campo);
                    }
                });
            });
        }
    });

    document.addEventListener('submit', async (evento) => {
        const form = evento.target;
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post' || evento.defaultPrevented) {
            return;
        }
        if (form.dataset.enviando === '1') {
            evento.preventDefault();
            return;
        }
        const invalidos = $$('input, select, textarea', form).filter((campo) => campo.willValidate && !marcarCampo(campo));
        if (invalidos.length > 0) {
            evento.preventDefault();
            invalidos[0].focus();
            return;
        }
        const boton = evento.submitter;
        let mensaje = (boton && boton.dataset.confirmar) || form.dataset.confirmar;
        // {total}: el total contado del formulario (arqueo de cierre y reconteo), para confirmar lo que se declara
        if (mensaje && mensaje.includes('{total}')) {
            const campoTotal = $('[data-total]', form);
            const cent = campoTotal ? centimos(campoTotal.value) : null;
            mensaje = mensaje.replace('{total}', cent === null ? '—' : soles(cent));
        }
        if (mensaje && form.dataset.confirmado !== '1') {
            evento.preventDefault();
            const textoBoton = (boton && boton.dataset.confirmarBoton) || form.dataset.confirmarBoton;
            if (await confirmar(mensaje, textoBoton)) {
                form.dataset.confirmado = '1';
                form.requestSubmit(boton || undefined);
            }
            return;
        }
        // Va al servidor: se bloquea el boton para no registrar dos veces lo mismo.
        // Un boton deshabilitado no viaja en el envio: su name=value (Aprobar o Pedir reconteo) va en un campo oculto
        if (boton && boton.name) {
            const valor = document.createElement('input');
            valor.type = 'hidden';
            valor.name = boton.name;
            valor.value = boton.value;
            form.appendChild(valor);
        }
        form.dataset.enviando = '1';
        form.setAttribute('aria-busy', 'true');
        $$('button[type=submit], button:not([type])', form).forEach((b) => {
            b.disabled = true;
        });
        if (boton) {
            boton.dataset.textoOriginal = boton.textContent;
            boton.classList.add('cargando');
            const ultimoTexto = Array.from(boton.childNodes).reverse().find((n) => n.nodeType === Node.TEXT_NODE && n.textContent.trim() !== '');
            if (ultimoTexto) {
                ultimoTexto.textContent = 'Guardando…';
            }
        }
    });

    // Si el navegador vuelve con Atras a una pagina guardada, los botones vuelven a funcionar
    window.addEventListener('pageshow', (evento) => {
        if (!evento.persisted) {
            return;
        }
        $$('form[data-enviando]').forEach((form) => {
            delete form.dataset.enviando;
            delete form.dataset.confirmado;
            form.removeAttribute('aria-busy');
            $$('button', form).forEach((b) => {
                b.disabled = false;
                b.classList.remove('cargando');
            });
        });
    });

    // ---------- Avisos: se pueden cerrar y el de exito se va solo ----------

    $$('[data-aviso]').forEach((aviso) => {
        const cerrar = document.createElement('button');
        cerrar.type = 'button';
        cerrar.className = 'aviso-cerrar';
        cerrar.setAttribute('aria-label', 'Cerrar aviso');
        cerrar.textContent = '×';
        cerrar.addEventListener('click', () => aviso.remove());
        aviso.appendChild(cerrar);
        if (aviso.classList.contains('ok')) {
            setTimeout(() => aviso.classList.add('saliendo'), 6000);
            setTimeout(() => aviso.remove(), 6500); // tambien si el usuario pidio menos movimiento
        }
    });

    // ---------- Teclado ----------

    document.addEventListener('keydown', (evento) => {
        const escribiendo = evento.target.matches('input, select, textarea') || evento.target.isContentEditable;
        const drawer = drawerAbierto();

        if (evento.key === 'Escape') {
            if (dialogo !== null && dialogo.open) {
                return; // el <dialog> se cierra solo
            }
            if (drawer !== null) {
                evento.preventDefault();
                cerrarDrawer(drawer);
            } else if (casillaMenu !== null && casillaMenu.checked) {
                casillaMenu.checked = false;
                casillaMenu.dispatchEvent(new Event('change'));
            }
            return;
        }
        if (evento.key === 'Tab' && drawer !== null) {
            atraparFoco(evento, drawer);
            return;
        }
        // "/" lleva al buscador de la pagina
        if (evento.key === '/' && !escribiendo && drawer === null) {
            const buscador = $('[data-buscador]:not([hidden]) input') || $('form.filtros input:not([type=hidden])');
            if (buscador) {
                evento.preventDefault();
                buscador.focus();
                buscador.select();
            }
        }
    });

    // ---------- Pestañas de Productos y Movimientos ----------
    // Productos y Movimientos del menu llevan a la ultima pestaña que se uso (se recuerda en este navegador),
    // y en el celular la pestaña elegida queda a la vista aunque la fila sea mas ancha que la pantalla.
    // La clave lleva el usuario: en una PC compartida cada empleado tiene sus pestañas (y sus permisos)
    const clavePestana = (grupo) => 'pestana:' + (document.documentElement.dataset.usuario || '') + ':' + grupo;
    const pestanas = $('nav.pestanas-seccion');
    if (pestanas !== null) {
        const actual = $('a[aria-current]', pestanas);
        if (actual !== null) {
            try {
                localStorage.setItem(clavePestana(pestanas.dataset.pestanas), actual.getAttribute('href'));
            } catch (e) {
                // Sin almacenamiento el menu lleva a la primera pestaña, como sin JavaScript
            }
            const lista = $('.pestanas-lista', pestanas);
            if (lista.scrollWidth > lista.clientWidth) {
                lista.scrollLeft = actual.offsetLeft - (lista.clientWidth - actual.offsetWidth) / 2;
            }
        }
    }
    $$('.lateral nav a[data-pestanas]').forEach((opcion) => {
        try {
            const ultima = localStorage.getItem(clavePestana(opcion.dataset.pestanas));
            // Solo una ruta interna simple (/?ruta=...), por si alguien escribio otra cosa en el almacenamiento
            if (ultima !== null && /^\/\?ruta=[a-z_]+$/.test(ultima)) {
                opcion.setAttribute('href', ultima);
            }
        } catch (e) {
            // Sin almacenamiento queda el enlace del servidor
        }
    });

    // ---------- Caja: movimientos de efectivo con su categoria ----------
    // La lista de categorias muestra solo las del tipo elegido (retiro o ingreso). Sin JavaScript se ven las dos
    // listas y el servidor rechaza una categoria que no corresponde al tipo.
    const formMovimiento = $('form[data-movimiento]');
    if (formMovimiento !== null) {
        const categoria = formMovimiento.elements.namedItem('categoria');
        const tipoElegido = () => {
            const marcado = formMovimiento.querySelector('input[name=tipo]:checked');
            return marcado ? marcado.value : 'RETIRO';
        };
        const filtrarCategorias = () => {
            const tipo = tipoElegido();
            $$('optgroup', categoria).forEach((grupo) => {
                const delTipo = grupo.dataset.tipo === tipo;
                grupo.hidden = !delTipo;
                grupo.disabled = !delTipo;
            });
            const elegida = categoria.selectedOptions[0];
            if (!elegida || elegida.parentElement.dataset.tipo !== tipo) {
                const primera = $('optgroup[data-tipo="' + tipo + '"] option', categoria);
                categoria.value = primera ? primera.value : '';
            }
        };
        $$('input[name=tipo]', formMovimiento).forEach((radio) => radio.addEventListener('change', filtrarCategorias));
        filtrarCategorias();

        // "Retirar S/ X" del aviso de tope: llena el formulario como retiro de seguridad (el cajero confirma con Registrar)
        $$('[data-retiro-seguridad]').forEach((enlace) => {
            enlace.addEventListener('click', (evento) => {
                evento.preventDefault();
                const retiro = formMovimiento.querySelector('input[name=tipo][value=RETIRO]');
                retiro.checked = true;
                filtrarCategorias();
                categoria.value = 'DEPOSITO';
                formMovimiento.elements.namedItem('motivo').value = 'Retiro de seguridad a la caja fuerte';
                const monto = formMovimiento.elements.namedItem('monto');
                monto.value = enlace.dataset.retiroSeguridad || '';
                formMovimiento.scrollIntoView({ behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'center' });
                monto.focus({ preventScroll: true });
            });
        });
    }

    // ---------- Caja: contar billetes y monedas ----------
    // Suma la gaveta en centimos mientras se escribe y compara con el fondo fijo o con lo esperado.
    // El servidor vuelve a sumar el desglose: esto solo ayuda a contar.
    $$('[data-conteo]').forEach((conteo) => {
        const total = $('[data-total]', conteo);
        const resultado = $('[data-resultado]', conteo);
        const campos = $$('[data-denominacion]', conteo);

        function comparar() {
            resultado.className = 'conteo-resultado';
            resultado.textContent = '';
            const contado = centimos(total.value);
            if (conteo.dataset.esperado === undefined || contado === null) {
                return;
            }
            const esperado = centimos(conteo.dataset.esperado);
            const diferencia = contado - esperado;
            if (diferencia === 0) {
                resultado.textContent = 'Cuadra con ' + conteo.dataset.rotulo;
                resultado.classList.add('cuadra');
            } else if (diferencia < 0) {
                resultado.textContent = 'Faltan ' + soles(-diferencia) + ' para ' + conteo.dataset.rotulo;
                resultado.classList.add('falta');
            } else {
                resultado.textContent = 'Sobran ' + soles(diferencia) + ' sobre ' + conteo.dataset.rotulo;
                resultado.classList.add('sobra');
            }
        }

        function sumar() {
            let suma = 0;
            let alguno = false;
            campos.forEach((campo) => {
                const subtotal = $('[data-subtotal]', campo.closest('.conteo-den'));
                const texto = campo.value.trim();
                const cantidad = /^\d{1,4}$/.test(texto) ? Number(texto) : 0;
                if (texto !== '') {
                    alguno = true;
                }
                const valor = cantidad * centimos(campo.dataset.denominacion);
                subtotal.textContent = cantidad > 0 ? soles(valor) : '';
                suma += valor;
            });
            if (alguno) {
                total.value = (suma / 100).toFixed(2);
                total.readOnly = true;
            } else if (total.readOnly) {
                // Se borraron todos los billetes: el total vuelve a quedar libre y vacio
                total.value = '';
                total.readOnly = false;
            }
            // Con billetes contados el total ya esta; sin ellos hay que escribirlo
            total.required = !alguno;
            total.dataset.mensaje = 'Cuenta el efectivo: escribe cuántos billetes y monedas hay, o solo el total.';
            if (modo !== null) {
                modo.hidden = false;
                modo.textContent = alguno ? 'Suma automática de los billetes y monedas' : 'O escribe aquí solo el total';
                modo.classList.toggle('auto', alguno);
            }
            if (alguno && total.getAttribute('aria-invalid') === 'true') {
                marcarCampo(total);
            }
            comparar();
        }

        // Botones − y + en cada billete y moneda: tocar suma uno, sin teclado
        const modo = $('[data-conteo-modo]', conteo);
        campos.forEach((campo) => {
            const paso = (signo, texto, etiqueta) => {
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.className = 'conteo-paso';
                boton.textContent = texto;
                boton.setAttribute('aria-label', etiqueta);
                boton.addEventListener('click', () => {
                    const actual = /^\d{1,4}$/.test(campo.value.trim()) ? Number(campo.value.trim()) : 0;
                    const nueva = Math.min(9999, Math.max(0, actual + signo));
                    campo.value = nueva === 0 ? '' : String(nueva);
                    sumar();
                });
                return boton;
            };
            const rotulo = campo.closest('.conteo-den').firstElementChild.textContent;
            // El nombre del campo sigue siendo solo "S/ 100" (los botones quedan dentro de la misma etiqueta)
            campo.setAttribute('aria-label', rotulo);
            const fila = document.createElement('span');
            fila.className = 'conteo-control';
            campo.replaceWith(fila);
            fila.append(paso(-1, '−', 'Uno menos de ' + rotulo), campo, paso(1, '+', 'Uno más de ' + rotulo));
        });
        // input, change y keyup: algunos teclados (Safari, autocompletar) no avisan con input
        ['input', 'change', 'keyup'].forEach((tipo) => campos.forEach((campo) => campo.addEventListener(tipo, sumar)));
        total.addEventListener('input', comparar);
        conteo.conteoComparar = comparar;

        // "Viene exacto" (apertura): escribe el fondo fijo como total y borra el desglose
        const exacto = $('[data-conteo-exacto]', conteo);
        if (exacto !== null) {
            exacto.hidden = false;
            exacto.addEventListener('click', () => {
                campos.forEach((campo) => {
                    campo.value = '';
                });
                sumar();
                total.value = conteo.dataset.esperado || '';
                total.dispatchEvent(new Event('input', { bubbles: true }));
                total.focus();
            });
        }
        sumar();
    });

    // ---------- Apertura de caja: el resumen de la derecha sigue a la caja elegida y al conteo ----------
    const formApertura = $('form[data-apertura]');
    if (formApertura !== null) {
        const conteo = $('[data-conteo]', formApertura);
        const total = $('[data-total]', conteo);
        const observacion = $('[data-si-descuadra]', formApertura);
        const pasos = $$('.pasos li');

        function resumir() {
            const radio = $('input[name="caja"]:checked', formApertura);
            const fondo = radio ? centimos(radio.dataset.fondo) : null;
            const contado = centimos(total.value);
            $('[data-resumen-caja]', formApertura).textContent = radio ? 'Caja ' + radio.dataset.numero : '—';
            $('[data-resumen-fondo]', formApertura).textContent = fondo === null ? '—' : soles(fondo);
            $('[data-resumen-contado]', formApertura).textContent = contado === null ? '—' : soles(contado);
            const estado = $('[data-resumen-estado]', formApertura);
            estado.className = 'apertura-estado';
            let descuadra = false;
            if (contado === null || fondo === null) {
                estado.textContent = 'Cuenta el fondo para continuar.';
            } else if (contado === fondo) {
                estado.textContent = 'Cuadra con el fondo fijo. Ya puedes abrir.';
                estado.classList.add('cuadra');
            } else {
                descuadra = true;
                const dif = contado - fondo;
                estado.textContent = (dif < 0 ? 'Faltan ' : 'Sobran ') + soles(Math.abs(dif)) + ': escribe por qué.';
                estado.classList.add(dif < 0 ? 'falta' : 'sobra');
            }
            // El motivo solo se pide cuando no cuadra (el servidor tambien lo exige)
            observacion.hidden = !descuadra;
            const hechos = [radio !== null, contado !== null, false];
            pasos.forEach((paso, i) => {
                paso.classList.toggle('hecho', hechos[i]);
                paso.classList.toggle('activo', !hechos[i] && hechos.slice(0, i).every(Boolean));
            });
        }

        $$('input[name="caja"]', formApertura).forEach((radio) => {
            radio.addEventListener('change', () => {
                if (radio.checked) {
                    conteo.dataset.esperado = radio.dataset.fondo;
                    conteo.conteoComparar();
                }
                resumir();
            });
        });
        formApertura.addEventListener('input', resumir);
        resumir();
    }

    // ---------- Caja: "Ver estimado" se vuelve a ocultar solo a los 10 segundos ----------
    $$('details[data-revelar]').forEach((revelar) => {
        let reloj = null;
        revelar.addEventListener('toggle', () => {
            clearTimeout(reloj);
            if (revelar.open) {
                reloj = setTimeout(() => {
                    revelar.open = false;
                }, 10000);
            }
        });
    });

    // ---------- Cierre automatico: los pasos se marcan solos mientras el cajero cuenta y confirma ----------
    const formCierre = $('form[data-cierre]');
    const pasosCierre = $$('[data-pasos-cierre] li');
    if (formCierre !== null && pasosCierre.length === 3) {
        const totalCierre = $('[data-total]', formCierre);
        const confirmo = $('#cierre-confirmo', formCierre);
        // ciego: 1 contar, 2 tarjeta y Sip (se ven al contar), 3 confirmar. visible: 1 revisar el cuadre, 2 contar, 3 confirmar
        const ciego = $('[data-pasos-cierre]').dataset.modo === 'ciego';
        let empezo = false;
        const marcarPasos = (evento) => {
            empezo = empezo || evento !== undefined;
            const contado = centimos(totalCierre.value) !== null;
            const confirmado = contado && confirmo !== null && confirmo.checked;
            const hechos = ciego ? [contado, contado, confirmado] : [empezo || contado, contado, confirmado];
            pasosCierre.forEach((paso, i) => {
                paso.classList.toggle('hecho', hechos[i]);
                paso.classList.toggle('activo', !hechos[i] && hechos.slice(0, i).every(Boolean));
            });
        };
        formCierre.addEventListener('input', marcarPasos);
        formCierre.addEventListener('change', marcarPasos);
        marcarPasos();
    }

    // ---------- Comprobante de cierre: boton de imprimir (sin JavaScript queda Ctrl + P) ----------
    $$('[data-imprimir]').forEach((boton) => {
        boton.hidden = false;
        boton.addEventListener('click', () => window.print());
    });

    // ---------- Nueva promocion: solo los campos del tipo elegido (sin JavaScript se ven todos) ----------
    const formPromo = $('form[data-promocion]');
    if (formPromo) {
        const tipoPromo = $('[data-promo-tipo]', formPromo);
        const mostrarCamposPromo = () => {
            $$('[data-promo-campo]', formPromo).forEach((bloque) => {
                bloque.hidden = !bloque.dataset.promoCampo.split(' ').includes(tipoPromo.value);
            });
        };
        tipoPromo.addEventListener('change', mostrarCamposPromo);
        mostrarCamposPromo();
    }

    // ---------- Cierre de sesion por inactividad (el servidor la cierra a los N minutos sin pedidos) ----------
    // Sin esto, una caja olvidada sigue mostrando ventas y montos aunque la sesion ya vencio.
    // - Si se usa el teclado o el mouse, cada 5 minutos se avisa al servidor (GET ?ruta=sesion_activa) que se sigue trabajando.
    // - 2 minutos antes de vencer sale un aviso con cuenta regresiva; al vencer se va al login.
    // - Las pestanas comparten la hora del ultimo contacto (localStorage): una pestana quieta no saca a la otra.
    const minutosSesion = Number(document.documentElement.dataset.inactividad || 0);
    if (minutosSesion > 0) {
        const limite = minutosSesion * 60 * 1000;
        const avisarAntes = Math.min(2 * 60 * 1000, limite / 2);
        const avisarServidorCada = Math.min(5 * 60 * 1000, limite / 3);
        const claveContacto = 'mass_contacto_' + (document.documentElement.dataset.usuario || '');
        let ultimoContacto = Date.now();   // la pagina recien llego del servidor
        let aviso = null;
        let cerrando = false;

        const leerContacto = () => {
            try {
                const otro = Number(localStorage.getItem(claveContacto) || 0);
                if (otro > ultimoContacto && otro <= Date.now()) {
                    ultimoContacto = otro;
                }
            } catch (e) { /* sin localStorage: solo cuenta esta pestana */ }
            return ultimoContacto;
        };
        const guardarContacto = () => {
            try { localStorage.setItem(claveContacto, String(ultimoContacto)); } catch (e) { /* sin localStorage */ }
        };
        guardarContacto();

        // Una imagen (no fetch): el servidor responde 204 y renueva la sesion con la misma cookie
        const avisarServidor = () => {
            new Image().src = '/?ruta=sesion_activa&t=' + Date.now();
            ultimoContacto = Date.now();
            guardarContacto();
            quitarAviso();
        };

        const quitarAviso = () => {
            if (aviso) {
                aviso.remove();
                aviso = null;
            }
        };

        const mostrarAviso = (restante) => {
            if (!aviso) {
                aviso = document.createElement('div');
                aviso.className = 'aviso alerta aviso-inactividad';
                aviso.setAttribute('role', 'alertdialog');
                aviso.setAttribute('aria-live', 'assertive');
                const texto = document.createElement('span');
                texto.dataset.texto = '';
                const boton = document.createElement('button');
                boton.type = 'button';
                boton.textContent = 'Seguir conectado';
                boton.addEventListener('click', avisarServidor);
                aviso.append(texto, boton);
                document.body.append(aviso);
                boton.focus();
            }
            const seg = Math.max(0, Math.ceil(restante / 1000));
            $('[data-texto]', aviso).textContent = 'Por seguridad, tu sesión se cerrará en '
                + Math.floor(seg / 60) + ':' + String(seg % 60).padStart(2, '0') + ' si no la usas.';
        };

        const usar = () => {
            // Si el aviso ya salio, se espera a que pulse el boton (un movimiento del mouse no basta)
            if (!aviso && Date.now() - leerContacto() >= avisarServidorCada) {
                avisarServidor();
            }
        };
        ['keydown', 'pointerdown', 'input', 'wheel', 'touchstart'].forEach((tipo) => {
            document.addEventListener(tipo, usar, { passive: true, capture: true });
        });

        setInterval(() => {
            if (cerrando) {
                return;
            }
            const transcurrido = Date.now() - leerContacto();
            const restante = limite - transcurrido;
            if (restante <= -5000) {
                // 5 segundos de margen: el servidor ya la cerro y muestra el aviso en el login
                cerrando = true;
                window.location.href = '/?ruta=inicio';
            } else if (restante <= avisarAntes) {
                mostrarAviso(Math.max(0, restante));
            } else {
                quitarAviso();   // otra pestana renovo la sesion
            }
        }, 1000);
    }

    enfocarDrawer();
}());
