<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';

/**
 * Abrir y cerrar turnos de caja, y sacar o meter efectivo de la gaveta durante el turno.
 */
class CajaServicio extends Servicio
{
    // El cajero abre una caja ACTIVA de su tienda que no tenga otro turno abierto. Devuelve el id del turno.
    public function abrirTurno(Usuario $cajero, int $idCaja, string $montoInicial): int
    {
        if (!$cajero->puedeManejarCaja()) {
            throw new ReglaNegocioException('Solo un cajero activo abre turnos de caja.');
        }
        $monto = Dinero::deTexto($montoInicial);
        if ($monto === null) {
            throw new ReglaNegocioException('Monto inicial no válido.');
        }

        return $this->enTransaccion(function () use ($cajero, $idCaja, $monto): int {
            $consulta = $this->db->prepare(
                'SELECT c.id_tienda, c.estado, t.estado AS estado_tienda
                   FROM caja c JOIN tienda t ON t.id_tienda = c.id_tienda
                  WHERE c.id_caja = :caja FOR UPDATE'
            );
            $consulta->execute(['caja' => $idCaja]);
            $caja = $consulta->fetch();
            if ($caja === false || (int) $caja['id_tienda'] !== $cajero->getIdTienda()) {
                throw new ReglaNegocioException('Esa caja no es de tu tienda.');
            }
            if ($caja['estado'] !== 'ACTIVA') {
                throw new ReglaNegocioException('Esa caja está inactiva.');
            }
            if ($caja['estado_tienda'] !== 'ACTIVA') {
                throw new ReglaNegocioException('La tienda está inactiva: no se abren turnos.');
            }

            $abiertos = $this->db->prepare(
                'SELECT id_caja, id_empleado FROM caja_turno WHERE estado = \'ABIERTO\' AND (id_caja = :caja OR id_empleado = :empleado)'
            );
            $abiertos->execute(['caja' => $idCaja, 'empleado' => $cajero->getIdEmpleado()]);
            foreach ($abiertos->fetchAll() as $abierto) {
                throw new ReglaNegocioException((int) $abierto['id_empleado'] === $cajero->getIdEmpleado()
                    ? 'Ya tienes un turno abierto: ciérralo antes de abrir otro.'
                    : 'Esa caja ya tiene un turno abierto.');
            }

            $this->db->prepare(
                'INSERT INTO caja_turno (fecha_hora_apertura, monto_inicial, estado, id_caja, id_empleado)
                 VALUES (NOW(), :monto, \'ABIERTO\', :caja, :empleado)'
            )->execute(['monto' => Dinero::aTexto($monto), 'caja' => $idCaja, 'empleado' => $cajero->getIdEmpleado()]);
            return (int) $this->db->lastInsertId();
        });
    }

    // El cajero cierra SU turno abierto con el efectivo que cuenta en caja
    public function cerrarTurno(Usuario $cajero, string $montoCierre): void
    {
        if (!$cajero->puedeManejarCaja()) {
            throw new ReglaNegocioException('Solo un cajero activo cierra turnos de caja.');
        }
        $monto = Dinero::deTexto($montoCierre);
        if ($monto === null) {
            throw new ReglaNegocioException('Monto de cierre no válido.');
        }

        $this->enTransaccion(function () use ($cajero, $monto): void {
            $consulta = $this->db->prepare(
                'SELECT id_caja_turno FROM caja_turno WHERE id_empleado = :empleado AND estado = \'ABIERTO\' FOR UPDATE'
            );
            $consulta->execute(['empleado' => $cajero->getIdEmpleado()]);
            $idTurno = $consulta->fetchColumn();
            if ($idTurno === false) {
                throw new ReglaNegocioException('No tienes un turno abierto.');
            }

            // CHECK ck_turno_estado: CERRADO lleva fecha y monto de cierre
            $this->db->prepare(
                'UPDATE caja_turno
                    SET estado = \'CERRADO\', fecha_hora_cierre = NOW(), monto_cierre = :monto
                  WHERE id_caja_turno = :turno'
            )->execute(['monto' => Dinero::aTexto($monto), 'turno' => (int) $idTurno]);
        });
    }

    /**
     * Retiro (al deposito, un pago menor) o ingreso de sencillo en la gaveta del turno abierto del cajero.
     * Un retiro no puede dejar la gaveta en negativo.
     */
    public function registrarMovimiento(Usuario $cajero, string $tipo, string $montoTexto, string $motivo): void
    {
        if (!$cajero->puedeManejarCaja()) {
            throw new ReglaNegocioException('Solo un cajero activo mueve efectivo de su caja.');
        }
        if (!in_array($tipo, ['RETIRO', 'INGRESO'], true)) {
            throw new ReglaNegocioException('Elige retiro o ingreso de efectivo.');
        }
        $monto = Dinero::deTexto($montoTexto);
        if ($monto === null || $monto <= 0) {
            throw new ReglaNegocioException('Monto no válido.');
        }
        $motivo = $this->textoObligatorio($motivo, 150, 'El motivo');

        $this->enTransaccion(function () use ($cajero, $tipo, $monto, $motivo): void {
            $consulta = $this->db->prepare(
                'SELECT id_caja_turno FROM caja_turno WHERE id_empleado = :empleado AND estado = \'ABIERTO\' FOR UPDATE'
            );
            $consulta->execute(['empleado' => $cajero->getIdEmpleado()]);
            $idTurno = $consulta->fetchColumn();
            if ($idTurno === false) {
                throw new ReglaNegocioException('No tienes un turno abierto.');
            }
            $idTurno = (int) $idTurno;

            if ($tipo === 'RETIRO') {
                $enGaveta = $this->efectivoEnGaveta($idTurno);
                if ($monto > $enGaveta) {
                    throw new ReglaNegocioException('Solo hay S/ ' . Dinero::aTexto(max(0, $enGaveta)) . ' en la gaveta.');
                }
            } elseif ($this->efectivoEnGaveta($idTurno) + $monto > Dinero::MAXIMO_CENTIMOS) {
                throw new ReglaNegocioException('Monto demasiado grande.');
            }

            $this->db->prepare(
                'INSERT INTO movimiento_caja (fecha_hora, tipo, monto, motivo, id_caja_turno, id_empleado)
                 VALUES (NOW(), :tipo, :monto, :motivo, :turno, :empleado)'
            )->execute([
                'tipo' => $tipo, 'monto' => Dinero::aTexto($monto), 'motivo' => $motivo,
                'turno' => $idTurno, 'empleado' => $cajero->getIdEmpleado(),
            ]);
            $this->auditar($cajero, $tipo === 'RETIRO' ? 'CAJA_RETIRO' : 'CAJA_INGRESO', 'S/ ' . Dinero::aTexto($monto) . ': ' . $motivo);
        });
    }
}
