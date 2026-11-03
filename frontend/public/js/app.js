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

            const tarjeta = centimos(campo('tarjeta_monto').value || '0');
            const sip = centimos(campo('sip_monto').value || '0');
            const efectivo = centimos(campo('efectivo_recibido').value || '0');
            const otros = (tarjeta || 0) + (sip || 0);
            const falta = Math.max(0, total - otros);
            const vuelto = efectivo === null ? 0 : Math.max(0, efectivo - falta);

            if (tarjeta === null || sip === null || efectivo === null) {
                avisos.push('Un monto de pago no es válido (usa hasta 2 decimales).');
            } else if (total > 0 && otros > total) {
                avisos.push('Tarjeta y Sip suman más que el total.');
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
            actualizarCarrito();
        }

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
                const opcion = $('select option[data-codigo="' + CSS.escape(codigo) + '"]', formVenta);
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
    }

    // ---------- Validacion de formularios con mensajes junto al campo ----------

    function mensajeDe(campo) {
        if (campo.validity.valueMissing) {
            return 'Este dato es obligatorio.';
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
        if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() !== 'post') {
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
                const subtotal = $('[data-subtotal]', campo.parentElement);
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
            } else {
                total.readOnly = false;
            }
            comparar();
        }

        campos.forEach((campo) => campo.addEventListener('input', sumar));
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

    enfocarDrawer();
}());
