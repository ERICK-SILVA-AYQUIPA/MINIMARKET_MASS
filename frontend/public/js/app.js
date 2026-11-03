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
                if (mil === null || mil === 0 || cent === null) {
                    celda.textContent = '—';
                    if (cantidad.value.trim() !== '') {
                        cantidad.classList.add('campo-error');
                        avisos.push('Fila ' + (i + 1) + ': la cantidad no es válida.');
                    }
                    return;
                }
                if (opcion.dataset.peso !== '1' && mil % 1000 !== 0) {
                    cantidad.classList.add('campo-error');
                    avisos.push('Fila ' + (i + 1) + ': este producto se vende por unidad, sin decimales.');
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
        }

        formVenta.addEventListener('input', calcular);
        formVenta.addEventListener('change', calcular);
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
            const nombre = opcion.textContent.split('·')[0].trim();
            const alPeso = opcion.dataset.peso === '1';
            let fila = lineas().find((f) => $('select', f).value === id);
            if (fila === undefined) {
                fila = lineas().find((f) => $('select', f).value === '') || filaNueva();
                if (fila === null) {
                    avisar('La venta ya tiene ' + MAXIMO_FILAS + ' productos distintos.', true);
                    return;
                }
                $('select', fila).value = id;
                $('input[name="cantidad[]"]', fila).value = alPeso ? '' : '0';
            }
            const cantidad = $('input[name="cantidad[]"]', fila);
            if (alPeso) {
                // Al peso: el cajero escribe los kg que marca la balanza
                cantidad.focus();
                cantidad.select();
                avisar(nombre + ': escribe los kg de la balanza.');
            } else {
                const actual = milesimas(cantidad.value || '0') || 0;
                cantidad.value = String(Math.floor(actual / 1000) + 1);
                avisar('+1 ' + nombre + ' (van ' + cantidad.value + ')');
            }
            fila.classList.remove('recien');
            void fila.offsetWidth;   // reinicia la animacion
            fila.classList.add('recien');
            calcular();
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
        const mensaje = (boton && boton.dataset.confirmar) || form.dataset.confirmar;
        if (mensaje && form.dataset.confirmado !== '1') {
            evento.preventDefault();
            const textoBoton = (boton && boton.dataset.confirmarBoton) || form.dataset.confirmarBoton;
            if (await confirmar(mensaje, textoBoton)) {
                form.dataset.confirmado = '1';
                form.requestSubmit(boton || undefined);
            }
            return;
        }
        // Va al servidor: se bloquea el boton para no registrar dos veces lo mismo
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

    enfocarDrawer();
}());
