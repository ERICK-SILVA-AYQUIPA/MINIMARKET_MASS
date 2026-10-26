<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';

/**
 * Registrar y anular ventas. Cada operacion es una sola transaccion:
 * venta + detalle + pagos + stock + kardex + correlativo, o nada.
 */
class VentaServicio extends Servicio
{
    /**
     * Registra una venta del cajero en su turno abierto y devuelve [serie, correlativo].
     *
     * @param string $tipo 'B' boleta o 'F' factura
     * @param ?string $documento DNI (8 digitos) o RUC (11) del cliente; vacio = CLIENTE VARIOS (solo boleta)
     * @param array<int, array{id_producto?: string, cantidad?: string}> $lineas
     * @param array{tarjeta_monto?: string, tarjeta_tipo?: string, tarjeta_marca?: string, tarjeta_ultimos4?: string,
     *              tarjeta_autorizacion?: string, sip_monto?: string, sip_modalidad?: string, sip_operacion?: string,
     *              efectivo_recibido?: string} $pago
     * @return array{serie: string, numero: int}
     */
    public function registrar(Usuario $cajero, string $tipo, ?string $documento, array $lineas, array $pago): array
    {
        if (!$cajero->puedeVender()) {
            throw new ReglaNegocioException('Solo un cajero activo puede registrar ventas.');
        }
        if (!in_array($tipo, ['B', 'F'], true)) {
            throw new ReglaNegocioException('Elige boleta o factura.');
        }

        return $this->enTransaccion(function () use ($cajero, $tipo, $documento, $lineas, $pago): array {
            // 1. Turno abierto del cajero, en una caja de su propia tienda
            $consulta = $this->db->prepare(
                'SELECT ct.id_caja_turno, c.id_tienda
                   FROM caja_turno ct
                   JOIN caja c ON c.id_caja = ct.id_caja
                  WHERE ct.id_empleado = :empleado AND ct.estado = \'ABIERTO\'
                  LIMIT 1
                  FOR UPDATE'
            );
            $consulta->execute(['empleado' => $cajero->getIdEmpleado()]);
            $turno = $consulta->fetch();
            if ($turno === false) {
                throw new ReglaNegocioException('Primero abre tu turno de caja.');
            }
            $idTienda = (int) $turno['id_tienda'];
            if ($idTienda !== $cajero->getIdTienda()) {
                throw new ReglaNegocioException('Tu turno está en una caja de otra tienda.');
            }

            // 2. Cliente: la factura exige RUC
            $idCliente = $this->clientePara($tipo, $documento);

            // 3. Lineas: precio actual del producto e importe = ROUND(cantidad * precio, 2)
            $productos = $this->leerLineas($lineas);
            $totalCentimos = 0;
            foreach ($productos as &$p) {
                $p['importe'] = Cantidad::importe($p['milesimas'], $p['precio']);
                $totalCentimos += $p['importe'];
            }
            unset($p);
            if ($totalCentimos <= 0) {
                throw new ReglaNegocioException('El total de la venta debe ser mayor que 0.');
            }

            // 4. Pagos: tarjeta y Sip por su monto; el efectivo cubre el resto
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
                'INSERT INTO detalle_venta (cantidad, precio_unitario, importe, id_venta, id_producto)
                 VALUES (:cantidad, :precio, :importe, :venta, :producto)'
            );
            foreach ($productos as $idProducto => $p) {
                $insertarDetalle->execute([
                    'cantidad' => Cantidad::aTexto($p['milesimas']),
                    'precio'   => Dinero::aTexto($p['precio']),
                    'importe'  => Dinero::aTexto($p['importe']),
                    'venta'    => $idVenta,
                    'producto' => $idProducto,
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

            return ['serie' => $serie['serie'], 'numero' => $numero];
        });
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
                'SELECT v.estado, s.id_tienda
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

            $this->db->prepare(
                'INSERT INTO anulacion_venta (fecha_hora, motivo, id_venta, id_empleado) VALUES (NOW(), :motivo, :venta, :empleado)'
            )->execute(['motivo' => $motivo, 'venta' => $idVenta, 'empleado' => $administrador->getIdEmpleado()]);

            $this->db->prepare('UPDATE venta SET estado = \'ANULADA\' WHERE id_venta = :venta')
                ->execute(['venta' => $idVenta]);
            $this->db->prepare('UPDATE pago_venta SET estado = \'ANULADO\' WHERE id_venta = :venta')
                ->execute(['venta' => $idVenta]);

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
