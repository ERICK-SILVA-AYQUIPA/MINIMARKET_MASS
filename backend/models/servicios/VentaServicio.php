<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../Descuentos.php';
require_once __DIR__ . '/../PromocionRepository.php';

/**
 * Registrar, anular y devolver ventas. Cada operacion es una sola transaccion:
 * venta + detalle + pagos + stock + kardex + correlativo, o nada.
 */
class VentaServicio extends Servicio
{
    // Dias que tiene el cliente para devolver un producto
    public const DIAS_DEVOLUCION = 7;

    /**
     * Registra una venta del cajero en su turno abierto y devuelve [serie, correlativo].
     *
     * @param string $tipo 'B' boleta o 'F' factura
     * @param ?string $documento DNI (8 digitos) o RUC (11) del cliente; vacio = CLIENTE VARIOS (solo boleta)
     * @param array<int, array{id_producto?: string, cantidad?: string}> $lineas
     * @param array{medio?: string, tarjeta_monto?: string, tarjeta_tipo?: string, tarjeta_marca?: string, tarjeta_ultimos4?: string,
     *              tarjeta_autorizacion?: string, sip_monto?: string, sip_modalidad?: string, sip_operacion?: string,
     *              efectivo_recibido?: string} $pago  medio: EFECTIVO, DEBITO, CREDITO o SIP (vacio = como antes, se combinan)
     * @param array{valor?: string, unidad?: string, motivo?: string} $descuento  descuento manual del cajero a toda la venta
     * @return array{serie: string, numero: int}
     */
    public function registrar(Usuario $cajero, string $tipo, ?string $documento, array $lineas, array $pago, array $descuento = []): array
    {
        if (!$cajero->puedeVender()) {
            throw new ReglaNegocioException('Solo un cajero activo puede registrar ventas.');
        }
        if (!in_array($tipo, ['B', 'F'], true)) {
            throw new ReglaNegocioException('Elige boleta o factura.');
        }

        $manual = $this->leerDescuentoManual($descuento);

        return $this->enTransaccion(function () use ($cajero, $tipo, $documento, $lineas, $pago, $manual): array {
            // 1. Turno abierto del cajero, en una caja de su propia tienda
            $consulta = $this->db->prepare(
                'SELECT ct.id_caja_turno, ct.en_pausa_desde, c.id_tienda, t.estado AS estado_tienda
                   FROM caja_turno ct
                   JOIN caja c   ON c.id_caja = ct.id_caja
                   JOIN tienda t ON t.id_tienda = c.id_tienda
                  WHERE ct.id_empleado = :empleado AND ct.estado = \'ABIERTO\'
                  LIMIT 1
                  FOR UPDATE'
            );
            $consulta->execute(['empleado' => $cajero->getIdEmpleado()]);
            $turno = $consulta->fetch();
            if ($turno === false) {
                throw new ReglaNegocioException('Primero abre tu turno de caja.');
            }
            if ($turno['en_pausa_desde'] !== null) {
                throw new ReglaNegocioException('Tu caja está en pausa: reanúdala en Caja para seguir vendiendo.');
            }
            $idTienda = (int) $turno['id_tienda'];
            if ($idTienda !== $cajero->getIdTienda()) {
                throw new ReglaNegocioException('Tu turno está en una caja de otra tienda.');
            }
            if ($turno['estado_tienda'] !== 'ACTIVA') {
                throw new ReglaNegocioException('La tienda está inactiva: no se puede vender.');
            }

            // 2. Cliente: la factura exige RUC
            $idCliente = $this->clientePara($tipo, $documento);

            // 3. Lineas: precio actual del producto e importe = ROUND(cantidad * precio, 2),
            //    menos las promociones que rigen hoy en la tienda y el descuento manual (Descuentos::calcular)
            $productos = $this->leerLineas($lineas);
            $calculo = Descuentos::calcular($productos, (new PromocionRepository($this->db))->vigentes($idTienda), $manual,
                $cajero->topeDescuentoManual());
            $totalCentimos = $calculo['total'];
            if ($totalCentimos <= 0) {
                throw new ReglaNegocioException('El total de la venta debe ser mayor que 0.');
            }
            // Cada importe y el total van en columnas DECIMAL(10,2)
            if ($totalCentimos > Dinero::MAXIMO_CENTIMOS) {
                throw new ReglaNegocioException('El total pasa de S/ ' . Dinero::aTexto(Dinero::MAXIMO_CENTIMOS) . ': divide la venta.');
            }

            // 4. Pagos: tarjeta y Sip por su monto (todo el total si se eligio ese medio); el efectivo cubre el resto
            $pagos = $this->armarPagos($pago, $totalCentimos);

            // 5. Siguiente correlativo de la serie de la tienda (bloqueada hasta el commit)
            $consulta = $this->db->prepare(
                'SELECT s.id_serie, s.serie, s.ultimo_numero
                   FROM serie_comprobante s
                   JOIN tipo_comprobante tc ON tc.id_tipo_comprobante = s.id_tipo_comprobante
                  WHERE s.id_tienda = :tienda AND tc.letra_serie = :letra
                  ORDER BY s.id_serie
                  LIMIT 1
                  FOR UPDATE'
            );
            $consulta->execute(['tienda' => $idTienda, 'letra' => $tipo]);
            $serie = $consulta->fetch();
            if ($serie === false) {
                throw new ReglaNegocioException('La tienda no tiene serie para ese comprobante.');
            }
            $numero = (int) $serie['ultimo_numero'] + 1;
            if ($numero > 99999999) {
                throw new ReglaNegocioException('La serie ' . $serie['serie'] . ' llegó a su último número.');
            }

            // 6. Cabecera, detalle y salida de stock con su kardex
            $this->db->prepare(
                'INSERT INTO venta (numero_correlativo, fecha_hora, total, estado, id_serie, id_caja_turno, id_cliente)
                 VALUES (:numero, NOW(), :total, \'EMITIDA\', :serie, :turno, :cliente)'
            )->execute([
                'numero'  => $numero,
                'total'   => Dinero::aTexto($totalCentimos),
                'serie'   => (int) $serie['id_serie'],
                'turno'   => (int) $turno['id_caja_turno'],
                'cliente' => $idCliente,
            ]);
            $idVenta = (int) $this->db->lastInsertId();

            $insertarDetalle = $this->db->prepare(
                'INSERT INTO detalle_venta (cantidad, precio_unitario, importe, descuento, descuento_general, id_promocion, id_venta, id_producto)
                 VALUES (:cantidad, :precio, :importe, :descuento, :general, :promocion, :venta, :producto)'
            );
            foreach ($productos as $idProducto => $p) {
                $c = $calculo['lineas'][$idProducto];
                $insertarDetalle->execute([
                    'cantidad'  => Cantidad::aTexto($p['milesimas']),
                    'precio'    => Dinero::aTexto($p['precio']),
                    'importe'   => Dinero::aTexto($c['importe']),
                    'descuento' => Dinero::aTexto($c['descuento']),
                    'general'   => Dinero::aTexto($c['general']),
                    'promocion' => $c['id_promocion'],
                    'venta'     => $idVenta,
                    'producto'  => $idProducto,
                ]);
                $this->moverStock($idTienda, $idProducto, -$p['milesimas'], TipoMovimiento::VENTA,
                    $cajero->getIdEmpleado(), ['id_venta' => $idVenta]);
            }

            // 7. Pagos con su subtipo
            foreach ($pagos as $p) {
                $this->insertarPago($idVenta, $p);
            }

            // 8. La serie avanza de uno en uno
            $this->db->prepare('UPDATE serie_comprobante SET ultimo_numero = :numero WHERE id_serie = :serie')
                ->execute(['numero' => $numero, 'serie' => (int) $serie['id_serie']]);

            // 9. Descuento a toda la venta (promocion VENTA o manual). El manual queda en la auditoria con su motivo.
            $general = $calculo['general'];
            if ($general !== null) {
                $this->db->prepare(
                    'INSERT INTO descuento_venta (id_venta, origen, id_promocion, porcentaje, monto, motivo, id_empleado)
                     VALUES (:venta, :origen, :promocion, :porcentaje, :monto, :motivo, :empleado)'
                )->execute([
                    'venta' => $idVenta, 'origen' => $general['origen'], 'promocion' => $general['id_promocion'],
                    'porcentaje' => $general['porcentaje'] === null ? null : Dinero::aTexto($general['porcentaje']),
                    'monto' => Dinero::aTexto($general['monto']), 'motivo' => $general['motivo'], 'empleado' => $cajero->getIdEmpleado(),
                ]);
                if ($general['origen'] === 'MANUAL') {
                    $this->auditar($cajero, 'DESCUENTO_MANUAL', sprintf('%s-%08d: S/ %s%s de S/ %s. %s', $serie['serie'], $numero,
                        Dinero::aTexto($general['monto']),
                        $general['porcentaje'] !== null ? ' (' . Promocion::porcentajeATexto($general['porcentaje']) . ')' : '',
                        Dinero::aTexto($calculo['subtotal'] - $calculo['descuentoProductos']), $general['motivo']));
                }
            }

            return ['serie' => $serie['serie'], 'numero' => $numero];
        });
    }

    /**
     * Lo que costaria la venta ahora, con las promociones de hoy y el descuento manual, sin guardar nada.
     * Lo usan la vista previa del carrito (ruta venta_calcular) y la API de la app.
     * @param array<int, array{id_producto?: string, cantidad?: string}> $lineas
     * @param array{valor?: string, unidad?: string, motivo?: string} $descuento
     * @return array<string, mixed> el resultado de Descuentos::calcular
     */
    public function cotizar(Usuario $cajero, array $lineas, array $descuento = []): array
    {
        $productos = $this->leerLineas($lineas);
        return Descuentos::calcular($productos, (new PromocionRepository($this->db))->vigentes($cajero->getIdTienda()),
            $this->leerDescuentoManual($descuento), $cajero->topeDescuentoManual());
    }

    /**
     * El descuento manual del formulario: vacio = sin descuento.
     * @param array{valor?: string, unidad?: string, motivo?: string} $descuento
     * @return ?array{unidad: string, valor: int, motivo: string}
     */
    private function leerDescuentoManual(array $descuento): ?array
    {
        $valorTexto = trim((string) ($descuento['valor'] ?? ''));
        if ($valorTexto === '' || preg_match('/^0+(\.0*)?$/', $valorTexto) === 1) {
            return null;
        }
        $unidad = ($descuento['unidad'] ?? '') === 'MONTO' ? 'MONTO' : 'PORCENTAJE';
        $valor = Dinero::deTexto($valorTexto);
        if ($valor === null) {
            throw new ReglaNegocioException('El descuento no es válido: usa un número con hasta 2 decimales.');
        }
        $motivo = trim((string) ($descuento['motivo'] ?? ''));
        if ($motivo === '' || mb_strlen($motivo) > 150) {
            throw new ReglaNegocioException('Escribe el motivo del descuento (máximo 150 caracteres).');
        }
        return ['unidad' => $unidad, 'valor' => $valor, 'motivo' => $motivo];
    }

    /**
     * Anula una venta EMITIDA: registra la anulacion, anula sus pagos y devuelve el stock (kardex).
     * Solo el ADMINISTRADOR de la tienda de la venta.
     */
    public function anular(Usuario $administrador, int $idVenta, string $motivo): void
    {
        $motivo = trim($motivo);
        if ($motivo === '' || mb_strlen($motivo) > 150) {
            throw new ReglaNegocioException('Escribe el motivo de la anulación (máximo 150 caracteres).');
        }

        $this->enTransaccion(function () use ($administrador, $idVenta, $motivo): void {
            $consulta = $this->db->prepare(
                'SELECT v.estado, v.numero_correlativo, s.id_tienda, s.serie
                   FROM venta v
                   JOIN serie_comprobante s ON s.id_serie = v.id_serie
                  WHERE v.id_venta = :venta
                  FOR UPDATE'
            );
            $consulta->execute(['venta' => $idVenta]);
            $venta = $consulta->fetch();
            if ($venta === false) {
                throw new ReglaNegocioException('La venta no existe.');
            }
            $idTienda = (int) $venta['id_tienda'];
            if (!$administrador->puedeAnularVenta($idTienda)) {
                throw new ReglaNegocioException('Solo el administrador de la tienda de la venta puede anularla.');
            }
            if ($venta['estado'] !== 'EMITIDA') {
                throw new ReglaNegocioException('La venta ya está anulada.');
            }
            // Anular devolveria otra vez al stock lo que ya volvio con la devolucion
            $devoluciones = $this->db->prepare('SELECT 1 FROM devolucion_venta WHERE id_venta = :venta LIMIT 1');
            $devoluciones->execute(['venta' => $idVenta]);
            if ($devoluciones->fetchColumn() !== false) {
                throw new ReglaNegocioException('La venta tiene devoluciones: ya no se puede anular.');
            }
            // Cuadre de caja: anular saca el efectivo de lo esperado del turno de la venta. Si ese turno ya se contó
            // y cerró, el dinero que se le devuelve al cliente sale de otra gaveta y ninguna caja lo registraría.
            // En ese caso se usa la devolución, que descuenta el efectivo del turno abierto de quien lo entrega.
            $efectivoCerrado = $this->db->prepare(
                'SELECT 1 FROM pago_venta pv JOIN venta v ON v.id_venta = pv.id_venta
                   JOIN caja_turno ct ON ct.id_caja_turno = v.id_caja_turno
                  WHERE pv.id_venta = :venta AND pv.estado = \'VALIDO\' AND pv.id_metodo_pago = 1 AND ct.estado <> \'ABIERTO\'
                  LIMIT 1'
            );
            $efectivoCerrado->execute(['venta' => $idVenta]);
            if ($efectivoCerrado->fetchColumn() !== false) {
                throw new ReglaNegocioException('Se cobró en efectivo en un turno que ya cerró su caja: anularla descuadraría el arqueo. Registra una devolución desde una caja abierta.');
            }

            $this->db->prepare(
                'INSERT INTO anulacion_venta (fecha_hora, motivo, id_venta, id_empleado) VALUES (NOW(), :motivo, :venta, :empleado)'
            )->execute(['motivo' => $motivo, 'venta' => $idVenta, 'empleado' => $administrador->getIdEmpleado()]);

            $this->db->prepare('UPDATE venta SET estado = \'ANULADA\' WHERE id_venta = :venta')
                ->execute(['venta' => $idVenta]);
            $this->db->prepare('UPDATE pago_venta SET estado = \'ANULADO\' WHERE id_venta = :venta')
                ->execute(['venta' => $idVenta]);

            $this->auditar($administrador, 'VENTA_ANULADA', sprintf('%s-%08d: %s', $venta['serie'], $venta['numero_correlativo'], $motivo));

            // Devolucion al stock: ANULACION DE VENTA, mismo origen (la venta)
            $consulta = $this->db->prepare(
                'SELECT id_producto, cantidad FROM detalle_venta WHERE id_venta = :venta ORDER BY id_producto'
            );
            $consulta->execute(['venta' => $idVenta]);
            foreach ($consulta->fetchAll() as $detalle) {
                $this->moverStock($idTienda, (int) $detalle['id_producto'], Cantidad::deDecimal($detalle['cantidad']),
                    TipoMovimiento::ANULACION_VENTA, $administrador->getIdEmpleado(), ['id_venta' => $idVenta]);
            }
        });
    }

    /**
     * Devolucion parcial de una venta EMITIDA de los ultimos DIAS_DEVOLUCION dias.
     * El producto vuelve al stock (kardex DEVOLUCION DE VENTA) y el efectivo sale de la gaveta del cajero.
     * Devuelve el total devuelto en centimos.
     * @param array<int, array{id_producto?: string, cantidad?: string}> $lineas cantidad vacia = no se devuelve
     */
    public function devolver(Usuario $cajero, int $idVenta, array $lineas, string $motivo): int
    {
        $motivo = $this->textoObligatorio($motivo, 150, 'El motivo de la devolución');

        return $this->enTransaccion(function () use ($cajero, $idVenta, $lineas, $motivo): int {
            $consulta = $this->db->prepare(
                'SELECT v.estado, v.numero_correlativo, s.id_tienda, s.serie,
                        v.fecha_hora >= NOW() - INTERVAL ' . self::DIAS_DEVOLUCION . ' DAY AS a_tiempo
                   FROM venta v
                   JOIN serie_comprobante s ON s.id_serie = v.id_serie
                  WHERE v.id_venta = :venta
                  FOR UPDATE'
            );
            $consulta->execute(['venta' => $idVenta]);
            $venta = $consulta->fetch();
            if ($venta === false) {
                throw new ReglaNegocioException('La venta no existe.');
            }
            $idTienda = (int) $venta['id_tienda'];
            if (!$cajero->puedeDevolverVenta($idTienda)) {
                throw new ReglaNegocioException('Solo un cajero de la tienda de la venta registra devoluciones.');
            }
            if ($venta['estado'] !== 'EMITIDA') {
                throw new ReglaNegocioException('La venta está anulada: no tiene nada que devolver.');
            }
            if ((int) $venta['a_tiempo'] !== 1) {
                throw new ReglaNegocioException('Pasaron más de ' . self::DIAS_DEVOLUCION . ' días desde la venta: ya no se aceptan devoluciones.');
            }

            $consulta = $this->db->prepare(
                'SELECT ct.id_caja_turno, c.id_tienda FROM caja_turno ct JOIN caja c ON c.id_caja = ct.id_caja
                  WHERE ct.id_empleado = :empleado AND ct.estado = \'ABIERTO\' FOR UPDATE'
            );
            $consulta->execute(['empleado' => $cajero->getIdEmpleado()]);
            $turno = $consulta->fetch();
            if ($turno === false || (int) $turno['id_tienda'] !== $idTienda) {
                throw new ReglaNegocioException('Abre tu turno de caja para devolver el efectivo.');
            }
            $idTurno = (int) $turno['id_caja_turno'];

            // Lo vendido de cada producto (y lo que se pago por el, con descuentos), menos lo que ya se devolvio antes
            $consulta = $this->db->prepare(
                'SELECT dv.id_producto, p.nombre_producto, p.se_vende_por_peso, dv.precio_unitario, dv.cantidad,
                        dv.importe - dv.descuento - dv.descuento_general AS pagado,
                        dv.cantidad - COALESCE(dev.cantidad, 0) AS disponible, COALESCE(dev.importe, 0) AS ya_devuelto
                   FROM detalle_venta dv
                   JOIN producto p ON p.id_producto = dv.id_producto
                   LEFT JOIN (SELECT dd.id_producto, SUM(dd.cantidad) AS cantidad, SUM(dd.importe) AS importe
                                FROM detalle_devolucion dd JOIN devolucion_venta d ON d.id_devolucion = dd.id_devolucion
                               WHERE d.id_venta = :venta1
                               GROUP BY dd.id_producto) dev ON dev.id_producto = dv.id_producto
                  WHERE dv.id_venta = :venta2'
            );
            $consulta->execute(['venta1' => $idVenta, 'venta2' => $idVenta]);
            $vendidos = [];
            foreach ($consulta->fetchAll() as $f) {
                $vendidos[(int) $f['id_producto']] = $f;
            }

            $devueltos = [];
            $totalCentimos = 0;
            foreach ($lineas as $linea) {
                $idTexto = trim((string) ($linea['id_producto'] ?? ''));
                $cantidadTexto = trim((string) ($linea['cantidad'] ?? ''));
                if ($cantidadTexto === '' || preg_match('/^0+(\.0*)?$/', $cantidadTexto) === 1) {
                    continue;
                }
                if (!ctype_digit($idTexto) || !isset($vendidos[(int) $idTexto]) || isset($devueltos[(int) $idTexto])) {
                    throw new ReglaNegocioException('Solo se devuelven productos de esta venta, una vez cada uno.');
                }
                $vendido = $vendidos[(int) $idTexto];
                $alPeso = $vendido['se_vende_por_peso'] === 'S';
                $milesimas = Cantidad::deTexto($cantidadTexto, $alPeso);
                if ($milesimas === null) {
                    throw new ReglaNegocioException($vendido['nombre_producto'] . ': cantidad no válida ('
                        . ($alPeso ? 'kg con hasta 3 decimales' : 'solo unidades enteras') . ').');
                }
                $disponible = Cantidad::deDecimal((string) $vendido['disponible']);
                if ($milesimas > $disponible) {
                    throw new ReglaNegocioException(sprintf('%s: se puede devolver hasta %s.',
                        $vendido['nombre_producto'], Cantidad::aTexto($disponible)));
                }
                $precio = (int) Dinero::deTexto($vendido['precio_unitario']);
                $importe = $this->importeADevolver($vendido, $milesimas, $disponible, $precio);
                if ($importe <= 0) {
                    throw new ReglaNegocioException($vendido['nombre_producto'] . ': la cantidad es tan pequeña que no vale ni un céntimo.');
                }
                $devueltos[(int) $idTexto] = ['milesimas' => $milesimas, 'precio' => $precio, 'importe' => $importe];
                $totalCentimos += $importe;
            }
            if ($devueltos === []) {
                throw new ReglaNegocioException('Escribe cuánto devuelve el cliente de al menos un producto.');
            }
            ksort($devueltos);

            $enGaveta = $this->efectivoEnGaveta($idTurno);
            if ($totalCentimos > $enGaveta) {
                throw new ReglaNegocioException(sprintf('No alcanza el efectivo: hay S/ %s en tu gaveta y la devolución es de S/ %s.',
                    Dinero::aTexto(max(0, $enGaveta)), Dinero::aTexto($totalCentimos)));
            }

            $this->db->prepare(
                'INSERT INTO devolucion_venta (fecha_hora, motivo, total, id_venta, id_caja_turno, id_empleado)
                 VALUES (NOW(), :motivo, :total, :venta, :turno, :empleado)'
            )->execute([
                'motivo' => $motivo, 'total' => Dinero::aTexto($totalCentimos), 'venta' => $idVenta,
                'turno' => $idTurno, 'empleado' => $cajero->getIdEmpleado(),
            ]);
            $idDevolucion = (int) $this->db->lastInsertId();

            $comprobante = sprintf('%s-%08d', $venta['serie'], $venta['numero_correlativo']);
            $insertar = $this->db->prepare(
                'INSERT INTO detalle_devolucion (id_devolucion, id_producto, cantidad, precio_unitario, importe)
                 VALUES (:devolucion, :producto, :cantidad, :precio, :importe)'
            );
            foreach ($devueltos as $idProducto => $d) {
                $insertar->execute([
                    'devolucion' => $idDevolucion, 'producto' => $idProducto, 'cantidad' => Cantidad::aTexto($d['milesimas']),
                    'precio' => Dinero::aTexto($d['precio']), 'importe' => Dinero::aTexto($d['importe']),
                ]);
                $this->moverStock($idTienda, $idProducto, $d['milesimas'], TipoMovimiento::DEVOLUCION,
                    $cajero->getIdEmpleado(), ['id_devolucion' => $idDevolucion], 'Devolución de ' . $comprobante);
            }

            $this->auditar($cajero, 'DEVOLUCION_REGISTRADA', sprintf('%s: S/ %s, %s', $comprobante, Dinero::aTexto($totalCentimos), $motivo));
            return $totalCentimos;
        });
    }

    /**
     * Lo que se le devuelve al cliente por $milesimas de una linea: lo que pago, no el precio de lista.
     * - Linea sin descuento: cantidad x precio, como siempre.
     * - Linea con descuento: la parte proporcional de lo pagado; si devuelve todo lo que queda, exactamente lo que falta.
     * Nunca pasa de lo que falta devolver de la linea, aunque se devuelva en varias veces.
     * @param array{cantidad: string, precio_unitario: string, pagado: string, ya_devuelto: string} $vendido
     */
    private function importeADevolver(array $vendido, int $milesimas, int $disponible, int $precio): int
    {
        $pagado = (int) Dinero::deTexto((string) $vendido['pagado']);
        $queda = $pagado - (int) Dinero::deTexto((string) $vendido['ya_devuelto']);
        $vendidas = Cantidad::deDecimal((string) $vendido['cantidad']);
        $lista = Cantidad::importe($vendidas, $precio);
        if ($pagado === $lista) {
            $importe = Cantidad::importe($milesimas, $precio);
        } elseif ($milesimas === $disponible) {
            $importe = $queda;
        } else {
            $importe = intdiv($pagado * $milesimas * 2 + $vendidas, $vendidas * 2); // redondeo a la mitad hacia arriba
        }
        // Tampoco pasa de cantidad x precio de lista (CHECK ck_detalle_devolucion_importe)
        return max(0, min($importe, $queda, Cantidad::importe($milesimas, $precio)));
    }

    // Busca el cliente por DNI o RUC; si no existe lo crea. null = CLIENTE VARIOS.
    private function clientePara(string $tipo, ?string $documento): ?int
    {
        $documento = trim((string) $documento);
        if ($documento === '') {
            if ($tipo === 'F') {
                throw new ReglaNegocioException('La factura necesita el RUC del cliente.');
            }
            return null;
        }

        if (preg_match('/^[0-9]{8}$/', $documento) === 1) {
            if ($tipo === 'F') {
                throw new ReglaNegocioException('La factura necesita RUC (11 dígitos), no DNI.');
            }
            $columna = 'dni';
        } elseif (preg_match('/^[0-9]{11}$/', $documento) === 1) {
            $columna = 'ruc';
        } else {
            throw new ReglaNegocioException('El documento debe ser un DNI de 8 dígitos o un RUC de 11.');
        }

        // $columna solo puede ser 'dni' o 'ruc' (lo decide el codigo, no el usuario)
        $consulta = $this->db->prepare("SELECT id_cliente FROM cliente WHERE $columna = :documento");
        $consulta->execute(['documento' => $documento]);
        $id = $consulta->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
        $this->db->prepare("INSERT INTO cliente ($columna) VALUES (:documento)")->execute(['documento' => $documento]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @return array<int, array{metodo: int, monto: int, datos: array<string, mixed>}>
     */
    private function armarPagos(array $pago, int $totalCentimos): array
    {
        $pagos = [];
        $cubierto = 0;

        // Medio de pago elegido en Nueva venta: solo cuentan sus campos. Tarjeta o Sip sin monto = todo el total
        // (si escribe un monto menor, el resto se paga en efectivo). Sin medio: tarjeta, Sip y efectivo se combinan.
        $medio = (string) ($pago['medio'] ?? '');
        if ($medio !== '') {
            if (!in_array($medio, ['EFECTIVO', 'DEBITO', 'CREDITO', 'SIP'], true)) {
                throw new ReglaNegocioException('Elige el medio de pago: efectivo, débito, crédito o Sip.');
            }
            $todo = Dinero::aTexto($totalCentimos);
            $conTarjeta = $medio === 'DEBITO' || $medio === 'CREDITO';
            $pago['tarjeta_tipo'] = $conTarjeta ? $medio : '';
            $pago['tarjeta_monto'] = $conTarjeta ? (trim((string) ($pago['tarjeta_monto'] ?? '')) ?: $todo) : '';
            $pago['sip_monto'] = $medio === 'SIP' ? (trim((string) ($pago['sip_monto'] ?? '')) ?: $todo) : '';
        }

        $montoTarjeta = trim((string) ($pago['tarjeta_monto'] ?? ''));
        if ($montoTarjeta !== '') {
            $monto = Dinero::deTexto($montoTarjeta);
            $tipo = (string) ($pago['tarjeta_tipo'] ?? '');
            $marca = trim((string) ($pago['tarjeta_marca'] ?? ''));
            $ultimos4 = trim((string) ($pago['tarjeta_ultimos4'] ?? ''));
            $autorizacion = trim((string) ($pago['tarjeta_autorizacion'] ?? ''));
            if ($monto === null || $monto <= 0) {
                throw new ReglaNegocioException('Monto con tarjeta no válido.');
            }
            if (!in_array($tipo, ['CREDITO', 'DEBITO'], true)
                || $marca === '' || mb_strlen($marca) > 20
                || preg_match('/^[0-9]{4}$/', $ultimos4) !== 1
                || preg_match('/^[A-Za-z0-9]{1,10}$/', $autorizacion) !== 1) {
                throw new ReglaNegocioException('Tarjeta: elige crédito o débito, la marca, los últimos 4 dígitos y el código de autorización.');
            }
            // Un voucher se cobra una sola vez: la misma tarjeta con el mismo codigo de autorizacion ya pago otra venta
            $voucher = $this->db->prepare(
                'SELECT 1 FROM tarjeta t JOIN pago_venta pv ON pv.id_pago = t.id_pago AND pv.estado = \'VALIDO\'
                  WHERE t.ultimos_4_digitos = :ultimos4 AND t.codigo_autorizacion = :autorizacion LIMIT 1'
            );
            $voucher->execute(['ultimos4' => $ultimos4, 'autorizacion' => strtoupper($autorizacion)]);
            if ($voucher->fetchColumn() !== false) {
                throw new ReglaNegocioException('Ese voucher (tarjeta ····' . $ultimos4 . ', autorización ' . strtoupper($autorizacion)
                    . ') ya se usó en otra venta: pide el voucher de esta compra.');
            }
            $pagos[] = ['metodo' => 2, 'monto' => $monto, 'datos' => [
                'tipo' => $tipo, 'marca' => mb_strtoupper($marca), 'ultimos4' => $ultimos4, 'autorizacion' => strtoupper($autorizacion),
            ]];
            $cubierto += $monto;
        }

        $montoSip = trim((string) ($pago['sip_monto'] ?? ''));
        if ($montoSip !== '') {
            $monto = Dinero::deTexto($montoSip);
            $modalidad = (string) ($pago['sip_modalidad'] ?? '');
            $operacion = trim((string) ($pago['sip_operacion'] ?? ''));
            if ($monto === null || $monto <= 0) {
                throw new ReglaNegocioException('Monto con Sip no válido.');
            }
            if (!in_array($modalidad, ['QR', 'TARJETA_SIP'], true) || preg_match('/^[A-Za-z0-9-]{1,30}$/', $operacion) !== 1) {
                throw new ReglaNegocioException('Sip: elige QR o TARJETA_SIP y escribe el código de operación.');
            }
            $existe = $this->db->prepare('SELECT 1 FROM billetera_digital_sip WHERE codigo_operacion = :codigo');
            $existe->execute(['codigo' => $operacion]);
            if ($existe->fetchColumn() !== false) {
                throw new ReglaNegocioException('Ese código de operación Sip ya fue usado.');
            }
            $pagos[] = ['metodo' => 3, 'monto' => $monto, 'datos' => ['modalidad' => $modalidad, 'operacion' => $operacion]];
            $cubierto += $monto;
        }

        $restante = $totalCentimos - $cubierto;
        if ($restante < 0) {
            throw new ReglaNegocioException('Los pagos con tarjeta y Sip pasan el total de ' . Dinero::aTexto($totalCentimos) . '.');
        }
        if ($restante > 0) {
            $recibido = Dinero::deTexto(trim((string) ($pago['efectivo_recibido'] ?? '')));
            if ($recibido === null || $recibido < $restante) {
                throw new ReglaNegocioException('Falta cobrar S/ ' . Dinero::aTexto($restante) . ' en efectivo: escribe cuánto te entregan.');
            }
            // vuelto = recibido - lo que cubre el efectivo; siempre menor que lo recibido (CHECK ck_efectivo_vuelto)
            $pagos[] = ['metodo' => 1, 'monto' => $restante, 'datos' => ['recibido' => $recibido, 'vuelto' => $recibido - $restante]];
        }
        return $pagos;
    }

    private function insertarPago(int $idVenta, array $pago): void
    {
        $this->db->prepare(
            'INSERT INTO pago_venta (monto_pagado, fecha_hora, estado, id_venta, id_metodo_pago)
             VALUES (:monto, NOW(), \'VALIDO\', :venta, :metodo)'
        )->execute(['monto' => Dinero::aTexto($pago['monto']), 'venta' => $idVenta, 'metodo' => $pago['metodo']]);
        $idPago = (int) $this->db->lastInsertId();
        $d = $pago['datos'];

        match ($pago['metodo']) {
            1 => $this->db->prepare('INSERT INTO efectivo (id_pago, monto_recibido, vuelto) VALUES (:pago, :recibido, :vuelto)')
                ->execute(['pago' => $idPago, 'recibido' => Dinero::aTexto($d['recibido']), 'vuelto' => Dinero::aTexto($d['vuelto'])]),
            2 => $this->db->prepare(
                'INSERT INTO tarjeta (id_pago, tipo_tarjeta, marca_tarjeta, ultimos_4_digitos, codigo_autorizacion)
                 VALUES (:pago, :tipo, :marca, :ultimos4, :autorizacion)'
            )->execute(['pago' => $idPago, 'tipo' => $d['tipo'], 'marca' => $d['marca'], 'ultimos4' => $d['ultimos4'], 'autorizacion' => $d['autorizacion']]),
            3 => $this->db->prepare(
                'INSERT INTO billetera_digital_sip (id_pago, modalidad_pago, codigo_operacion) VALUES (:pago, :modalidad, :operacion)'
            )->execute(['pago' => $idPago, 'modalidad' => $d['modalidad'], 'operacion' => $d['operacion']]),
        };
    }
}
