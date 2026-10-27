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

    // ---------- Buscador en vivo sobre una tabla ----------

    const normalizar = (texto) => texto.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

    $$('[data-buscador]').forEach((caja) => {
        const campo = $('input[data-filtrar]', caja);
        const cuerpo = campo ? document.getElementById(campo.dataset.filtrar) : null;
        if (cuerpo === null) {
            return;
        }
        caja.hidden = false;
        const filas = $$(':scope > tr', cuerpo);
        const columnas = filas[0] ? filas[0].children.length : 1;

        // Fila "sin coincidencias", creada con textContent
        const sinResultados = document.createElement('tr');
        const celda = document.createElement('td');
        celda.colSpan = columnas;
        celda.className = 'sutil centro';
        sinResultados.appendChild(celda);
        sinResultados.hidden = true;
        cuerpo.appendChild(sinResultados);

        const textos = filas.map((fila) => normalizar(fila.textContent));
        campo.addEventListener('input', () => {
            const buscado = normalizar(campo.value.trim());
            let visibles = 0;
            filas.forEach((fila, i) => {
                const coincide = buscado === '' || textos[i].includes(buscado);
                fila.hidden = !coincide;
                visibles += coincide ? 1 : 0;
            });
            celda.textContent = 'Nada coincide con "' + campo.value.trim() + '".';
            sinResultados.hidden = visibles > 0;
        });
        campo.addEventListener('keydown', (evento) => {
            if (evento.key === 'Escape' && campo.value !== '') {
                evento.stopPropagation();
                campo.value = '';
                campo.dispatchEvent(new Event('input'));
            }
        });
    });

    // ---------- Pantalla de venta: total, pagos y avisos en vivo ----------

    const formVenta = $('form[data-venta]');
    if (formVenta !== null) {
        const caja = $('[data-total-vivo]', formVenta);
        const lineas = $$('[data-lineas] tbody tr', formVenta);
        const campo = (nombre) => formVenta.elements.namedItem(nombre);
        caja.hidden = false;

        function calcular() {
            let total = 0;
            const avisos = [];
            const pedidoPorProducto = new Map();

            lineas.forEach((fila, i) => {
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
