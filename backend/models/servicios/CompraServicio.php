<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../Proveedor.php';
require_once __DIR__ . '/../OrdenCompra.php';

/**
 * Proveedores y ordenes de compra (migraciones/015).
 * La orden no toca el stock: la mercaderia entra con InventarioServicio::registrarIngreso(), que la recibe contra la orden.
 */
class CompraServicio extends Servicio
{
    /**
     * El administrador agrega un proveedor ($idProveedor null) o corrige sus datos. El RUC no cambia despues de creado:
     * los ingresos y las ordenes ya estan a ese RUC. Devuelve el id del proveedor.
     * @param array{ruc?: string, razon_social?: string, contacto?: string, telefono?: string, correo?: string, dias_entrega?: string} $datos
     */
    public function guardarProveedor(Usuario $administrador, ?int $idProveedor, array $datos): int
    {
        if (!$administrador->puedeAdministrarProveedores()) {
            throw new ReglaNegocioException('Solo un administrador agrega o corrige proveedores.');
        }
        $razonSocial = $this->textoObligatorio($datos['razon_social'] ?? null, 100, 'La razón social');
        $contacto = $this->textoOpcional($datos['contacto'] ?? null, 80, 'Contacto');
        $telefono = $this->textoOpcional($datos['telefono'] ?? null, 15, 'Teléfono');
        if ($telefono !== null && preg_match('/^\+?[0-9 ]{6,15}$/', $telefono) !== 1) {
            throw new ReglaNegocioException('El teléfono va solo con números (puede empezar con +), por ejemplo 054 251234 o 987654321.');
        }
        $correo = $this->textoOpcional($datos['correo'] ?? null, 100, 'Correo');
        if ($correo !== null && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            throw new ReglaNegocioException('El correo no es válido.');
        }
        $diasTexto = trim((string) ($datos['dias_entrega'] ?? ''));
        $dias = null;
        if ($diasTexto !== '') {
            if (preg_match('/^[0-9]{1,2}$/', $diasTexto) !== 1 || (int) $diasTexto > 90) {
                throw new ReglaNegocioException('Los días de entrega van de 0 a 90.');
            }
            $dias = (int) $diasTexto;
        }

        return $this->enTransaccion(function () use ($administrador, $idProveedor, $datos, $razonSocial, $contacto, $telefono, $correo, $dias): int {
            $valores = ['razon' => $razonSocial, 'contacto' => $contacto, 'telefono' => $telefono, 'correo' => $correo, 'dias' => $dias];
            if ($idProveedor === null) {
                $ruc = trim((string) ($datos['ruc'] ?? ''));
                if (preg_match('/^[0-9]{11}$/', $ruc) !== 1) {
                    throw new ReglaNegocioException('El RUC debe tener 11 dígitos.');
                }
                $repetido = $this->db->prepare('SELECT razon_social FROM proveedor WHERE ruc = :ruc');
                $repetido->execute(['ruc' => $ruc]);
                $yaEsta = $repetido->fetchColumn();
                if ($yaEsta !== false) {
                    throw new ReglaNegocioException("El RUC $ruc ya es del proveedor $yaEsta.");
                }
                try {
                    $this->db->prepare(
                        'INSERT INTO proveedor (ruc, razon_social, contacto, telefono, correo, dias_entrega)
                         VALUES (:ruc, :razon, :contacto, :telefono, :correo, :dias)'
                    )->execute(['ruc' => $ruc] + $valores);
                } catch (PDOException $ex) {
                    if ($this->esDuplicado($ex)) {
                        throw new ReglaNegocioException("El RUC $ruc ya está registrado.");
                    }
                    throw $ex;
                }
                $id = (int) $this->db->lastInsertId();
                $this->auditar($administrador, 'PROVEEDOR_CREADO', sprintf('Proveedor %d: %s (RUC %s)', $id, $razonSocial, $ruc));
                return $id;
            }

            $actual = $this->db->prepare('SELECT ruc, razon_social FROM proveedor WHERE id_proveedor = :id FOR UPDATE');
            $actual->execute(['id' => $idProveedor]);
            $fila = $actual->fetch();
            if ($fila === false) {
                throw new ReglaNegocioException('El proveedor no existe.');
            }
            $this->db->prepare(
                'UPDATE proveedor SET razon_social = :razon, contacto = :contacto, telefono = :telefono, correo = :correo,
                        dias_entrega = :dias WHERE id_proveedor = :id'
            )->execute($valores + ['id' => $idProveedor]);
            $this->auditar($administrador, 'PROVEEDOR_EDITADO', sprintf('Proveedor %d (RUC %s): %s', $idProveedor, $fila['ruc'],
                $fila['razon_social'] === $razonSocial ? 'datos de contacto' : $fila['razon_social'] . ' -> ' . $razonSocial));
            return $idProveedor;
        });
    }

    /**
     * Activa o desactiva un proveedor. Uno con ordenes abiertas no se desactiva: primero se reciben o se cierran.
     */
    public function cambiarEstadoProveedor(Usuario $administrador, int $idProveedor, string $estado): void
    {
        if (!$administrador->puedeAdministrarProveedores()) {
            throw new ReglaNegocioException('Solo un administrador activa o desactiva proveedores.');
        }
        if (!in_array($estado, Proveedor::ESTADOS, true)) {
            throw new ReglaNegocioException('Estado no válido.');
        }
        $this->enTransaccion(function () use ($administrador, $idProveedor, $estado): void {
            $consulta = $this->db->prepare('SELECT razon_social, estado FROM proveedor WHERE id_proveedor = :id FOR UPDATE');
            $consulta->execute(['id' => $idProveedor]);
            $fila = $consulta->fetch();
            if ($fila === false) {
                throw new ReglaNegocioException('El proveedor no existe.');
            }
            if ($fila['estado'] === $estado) {
                return;
            }
            if ($estado === 'INACTIVO') {
                $abiertas = $this->db->prepare(
                    "SELECT COUNT(*) FROM orden_compra WHERE id_proveedor = :id AND estado IN ('PENDIENTE', 'PARCIAL')"
                );
                $abiertas->execute(['id' => $idProveedor]);
                $cuantas = (int) $abiertas->fetchColumn();
                if ($cuantas > 0) {
                    throw new ReglaNegocioException(sprintf('%s tiene %d orden(es) de compra abierta(s): recíbelas o ciérralas antes de desactivarlo.',
                        $fila['razon_social'], $cuantas));
                }
            }
            $this->db->prepare('UPDATE proveedor SET estado = :estado WHERE id_proveedor = :id')
                ->execute(['estado' => $estado, 'id' => $idProveedor]);
            $this->auditar($administrador, 'PROVEEDOR_ESTADO', sprintf('Proveedor %d: %s pasa a %s', $idProveedor, $fila['razon_social'], $estado));
        });
    }

    /**
     * El almacenero (o el administrador) de una tienda le pide mercaderia a un proveedor activo.
     * Cada producto va una sola vez, con la cantidad y el costo pactado. Devuelve el id de la orden.
     * @param array<int, array{id_producto?: string, cantidad?: string, costo?: string}> $lineas
     */
    public function registrarOrden(Usuario $empleado, int $idProveedor, ?string $fechaEntrega, ?string $observacion, array $lineas): int
    {
        $idTienda = $empleado->getIdTienda();
        if (!$empleado->puedeEmitirOrdenCompra($idTienda)) {
            throw new ReglaNegocioException('Las órdenes de compra las emiten el almacenero y el administrador de la tienda.');
        }
        $fechaEntrega = trim((string) $fechaEntrega);
        if ($fechaEntrega === '') {
            $fechaEntrega = null;
        } elseif (preg_match('/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/', $fechaEntrega, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            throw new ReglaNegocioException('La fecha de entrega no es válida.');
        } elseif ($fechaEntrega < date('Y-m-d')) {
            throw new ReglaNegocioException('La fecha de entrega no puede ser pasada.');
        }
        $observacion = $this->textoOpcional($observacion, 200, 'Observación');

        return $this->enTransaccion(function () use ($empleado, $idTienda, $idProveedor, $fechaEntrega, $observacion, $lineas): int {
            $tienda = $this->db->prepare('SELECT estado FROM tienda WHERE id_tienda = :id');
            $tienda->execute(['id' => $idTienda]);
            if ($tienda->fetchColumn() !== 'ACTIVA') {
                throw new ReglaNegocioException('Tu tienda no está activa: no puede pedir mercadería.');
            }
            $proveedor = $this->db->prepare('SELECT razon_social, estado FROM proveedor WHERE id_proveedor = :id');
            $proveedor->execute(['id' => $idProveedor]);
            $filaProveedor = $proveedor->fetch();
            if ($filaProveedor === false) {
                throw new ReglaNegocioException('Elige el proveedor.');
            }
            if ($filaProveedor['estado'] !== 'ACTIVO') {
                throw new ReglaNegocioException($filaProveedor['razon_social'] . ' está inactivo: no recibe órdenes nuevas.');
            }
            // Mismas reglas que un ingreso (producto activo, cantidad, costo con 2 decimales, una linea por producto)
            $productos = $this->leerLineasIngreso($lineas);
            $total = array_sum(array_column($productos, 'costoTotal'));
            if ($total > Dinero::MAXIMO_CENTIMOS) {
                throw new ReglaNegocioException('El total de la orden es demasiado grande.');
            }

            $this->db->prepare(
                'INSERT INTO orden_compra (fecha_hora, fecha_entrega, estado, total, observacion, id_proveedor, id_tienda, id_empleado)
                 VALUES (NOW(), :entrega, \'PENDIENTE\', :total, :observacion, :proveedor, :tienda, :empleado)'
            )->execute([
                'entrega'     => $fechaEntrega,
                'total'       => Dinero::aTexto($total),
                'observacion' => $observacion,
                'proveedor'   => $idProveedor,
                'tienda'      => $idTienda,
                'empleado'    => $empleado->getIdEmpleado(),
            ]);
            $idOrden = (int) $this->db->lastInsertId();
            foreach ($productos as $idProducto => $p) {
                $this->db->prepare(
                    'INSERT INTO detalle_orden_compra (id_orden_compra, id_producto, cantidad_pedida, costo_unitario, costo_total)
                     VALUES (:orden, :producto, :cantidad, :costo, :total)'
                )->execute([
                    'orden'    => $idOrden,
                    'producto' => $idProducto,
                    'cantidad' => Cantidad::aTexto($p['milesimas']),
                    'costo'    => Dinero::aTexto($p['costo']),
                    'total'    => Dinero::aTexto($p['costoTotal']),
                ]);
            }
            $this->auditar($empleado, 'ORDEN_COMPRA_EMITIDA', sprintf('%s a %s: %d producto(s), S/ %s',
                OrdenCompra::formatear($idOrden), $filaProveedor['razon_social'], count($productos), Dinero::aTexto($total)));
            return $idOrden;
        });
    }

    /**
     * El administrador cancela lo que falta de una orden abierta de SU tienda: si no llego nada queda ANULADA,
     * si ya llego una parte queda CERRADA (lo recibido se mantiene). Devuelve el estado final.
     */
    public function cancelarOrden(Usuario $administrador, int $idOrden, ?string $motivo): string
    {
        $motivo = $this->textoObligatorio($motivo, 200, 'El motivo');
        return $this->enTransaccion(function () use ($administrador, $idOrden, $motivo): string {
            $consulta = $this->db->prepare('SELECT estado, id_tienda FROM orden_compra WHERE id_orden_compra = :id FOR UPDATE');
            $consulta->execute(['id' => $idOrden]);
            $fila = $consulta->fetch();
            $numero = OrdenCompra::formatear($idOrden);
            if ($fila === false || (int) $fila['id_tienda'] !== $administrador->getIdTienda()) {
                throw new ReglaNegocioException('Esa orden de compra no es de tu tienda.');
            }
            if (!$administrador->puedeCancelarOrdenCompra((int) $fila['id_tienda'])) {
                throw new ReglaNegocioException('Solo el administrador de la tienda anula o cierra una orden de compra.');
            }
            if (!in_array($fila['estado'], OrdenCompra::ABIERTAS, true)) {
                throw new ReglaNegocioException("La orden $numero ya está " . strtolower($fila['estado']) . '.');
            }
            $estado = $fila['estado'] === 'PENDIENTE' ? 'ANULADA' : 'CERRADA';
            $this->db->prepare(
                'UPDATE orden_compra SET estado = :estado, fecha_cierre = NOW(), id_empleado_cierre = :empleado, motivo_cierre = :motivo
                  WHERE id_orden_compra = :id'
            )->execute(['estado' => $estado, 'empleado' => $administrador->getIdEmpleado(), 'motivo' => $motivo, 'id' => $idOrden]);
            $this->auditar($administrador, $estado === 'ANULADA' ? 'ORDEN_COMPRA_ANULADA' : 'ORDEN_COMPRA_CERRADA', "$numero: $motivo");
            return $estado;
        });
    }
}
