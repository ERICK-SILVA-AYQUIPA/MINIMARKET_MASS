<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';
require_once __DIR__ . '/../CajaRepository.php';

/**
 * La caja de cada cajero: abrir el turno contando el fondo, pausar, retiros e ingresos, cerrar contando
 * (a ciegas si la tienda lo pide) y volver a contar si el administrador lo pide.
 * Y lo del administrador: arqueo sorpresa, revisar cierres con diferencia y las reglas de caja.
 *
 * Un conteo llega como total ("100.00") o como desglose de billetes y monedas (["50.00" => "1", "20.00" => "2", ...]).
 * Si hay desglose, manda el desglose: el total es su suma y queda guardado en arqueo_detalle.
 */
class CajaServicio extends Servicio
{
    /**
     * El cajero abre una caja ACTIVA de su tienda que no tenga otro turno abierto. Devuelve el id del turno.
     * Lo contado se compara con el fondo fijo de la caja; si no cuadra y $motivoObligatorio, pide el porque.
     * @param array<string, string> $conteo billete o moneda => cantidad
     */
    public function abrirTurno(Usuario $cajero, int $idCaja, string $montoInicial, array $conteo = [],
                               ?string $observacion = null, bool $motivoObligatorio = false): int
    {
        if (!$cajero->puedeManejarCaja()) {
            throw new ReglaNegocioException('Solo un cajero activo abre turnos de caja.');
        }
        [$monto, $detalle] = $this->leerConteo($montoInicial, $conteo, 'Monto inicial no válido.');
        $observacion = $this->textoOpcional($observacion, 150, 'La observación');

        return $this->enTransaccion(function () use ($cajero, $idCaja, $monto, $detalle, $observacion, $motivoObligatorio): int {
            $consulta = $this->db->prepare(
                'SELECT c.id_tienda, c.estado, c.fondo_fijo, t.estado AS estado_tienda
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

            $pendientes = $this->db->prepare(
                'SELECT id_caja_turno, id_caja, id_empleado, estado FROM caja_turno
                  WHERE (estado = \'ABIERTO\' AND id_caja = :caja)
                     OR (estado IN (\'ABIERTO\', \'OBSERVADO\', \'RECONTEO\') AND id_empleado = :empleado)'
            );
            $pendientes->execute(['caja' => $idCaja, 'empleado' => $cajero->getIdEmpleado()]);
            foreach ($pendientes->fetchAll() as $p) {
                if ((int) $p['id_empleado'] !== $cajero->getIdEmpleado()) {
                    throw new ReglaNegocioException('Esa caja ya tiene un turno abierto.');
                }
                throw new ReglaNegocioException(match ($p['estado']) {
                    'ABIERTO'   => 'Ya tienes un turno abierto: ciérralo antes de abrir otro.',
                    'OBSERVADO' => 'Tu cierre del turno ' . $p['id_caja_turno'] . ' tiene una diferencia y lo está revisando el administrador.',
                    default     => 'El administrador pidió que vuelvas a contar el turno ' . $p['id_caja_turno'] . ' antes de abrir otro.',
                });
            }

            $fondo = (int) Dinero::deTexto((string) $caja['fondo_fijo']);
            if ($monto !== $fondo && $motivoObligatorio && $observacion === null) {
                throw new ReglaNegocioException('Contaste S/ ' . Dinero::aTexto($monto) . ' y el fondo de la caja es S/ '
                    . Dinero::aTexto($fondo) . ': escribe por qué no cuadra.');
            }

            $this->db->prepare(
                'INSERT INTO caja_turno (fecha_hora_apertura, monto_inicial, estado, id_caja, id_empleado)
                 VALUES (NOW(), :monto, \'ABIERTO\', :caja, :empleado)'
            )->execute(['monto' => Dinero::aTexto($monto), 'caja' => $idCaja, 'empleado' => $cajero->getIdEmpleado()]);
            $idTurno = (int) $this->db->lastInsertId();

            // El conteo de apertura queda como arqueo, comparado con el fondo fijo de la caja
            $this->registrarArqueo($idTurno, 'APERTURA', $monto, $fondo, $cajero->getIdEmpleado(), $detalle, $observacion);
            if ($monto !== $fondo) {
                $this->auditar($cajero, 'CAJA_APERTURA_DIFERENCIA', 'Turno ' . $idTurno . ': contó S/ ' . Dinero::aTexto($monto)
                    . ', fondo S/ ' . Dinero::aTexto($fondo) . ($observacion === null ? '' : '. ' . $observacion));
            }
            return $idTurno;
        });
    }

    /**
     * El cajero cierra SU turno abierto con el efectivo que cuenta. Si la diferencia entra en la tolerancia de la
     * tienda queda CERRADO; si no, OBSERVADO y lo revisa el administrador.
     * @param array<string, string> $conteo
     * @return array{turno: int, esperado: int, diferencia: int, estado: string} montos en centimos
     */
    public function cerrarTurno(Usuario $cajero, string $montoCierre, array $conteo = [], ?string $observacion = null): array
    {
        if (!$cajero->puedeManejarCaja()) {
            throw new ReglaNegocioException('Solo un cajero activo cierra turnos de caja.');
        }
        [$monto, $detalle] = $this->leerConteo($montoCierre, $conteo, 'Monto de cierre no válido.');
        $observacion = $this->textoOpcional($observacion, 150, 'La observación');

        return $this->enTransaccion(function () use ($cajero, $monto, $detalle, $observacion): array {
            $idTurno = $this->turnoDelCajero($cajero, 'ABIERTO', 'No tienes un turno abierto.');
            return $this->guardarCierre($cajero, $idTurno, $monto, $detalle, $observacion, 'CAJA_CIERRE');
        });
    }

    /**
     * El administrador pidio un nuevo conteo: el cajero vuelve a contar su turno en RECONTEO.
     * @param array<string, string> $conteo
     * @return array{turno: int, esperado: int, diferencia: int, estado: string}
     */
    public function recontarTurno(Usuario $cajero, string $montoCierre, array $conteo = [], ?string $observacion = null): array
    {
        if (!$cajero->puedeManejarCaja()) {
            throw new ReglaNegocioException('Solo un cajero activo cuenta su caja.');
        }
        [$monto, $detalle] = $this->leerConteo($montoCierre, $conteo, 'Monto contado no válido.');
        $observacion = $this->textoOpcional($observacion, 150, 'La observación');

        return $this->enTransaccion(function () use ($cajero, $monto, $detalle, $observacion): array {
            $idTurno = $this->turnoDelCajero($cajero, 'RECONTEO', 'No tienes un turno para volver a contar.');
            return $this->guardarCierre($cajero, $idTurno, $monto, $detalle, $observacion, 'CAJA_RECONTEO');
        });
    }

    // Pausa (el cajero se va un momento) o reanuda su turno. En pausa nadie vende con ese turno.
    public function pausarTurno(Usuario $cajero, bool $pausar): void
    {
        if (!$cajero->puedeManejarCaja()) {
            throw new ReglaNegocioException('Solo un cajero pausa su caja.');
        }
        $this->enTransaccion(function () use ($cajero, $pausar): void {
            $idTurno = $this->turnoDelCajero($cajero, 'ABIERTO', 'No tienes un turno abierto.');
            $sql = $pausar
                ? 'UPDATE caja_turno SET en_pausa_desde = COALESCE(en_pausa_desde, NOW()) WHERE id_caja_turno = :turno'
                : 'UPDATE caja_turno SET en_pausa_desde = NULL WHERE id_caja_turno = :turno';
            $this->db->prepare($sql)->execute(['turno' => $idTurno]);
        });
    }

    /**
     * El administrador revisa un cierre OBSERVADO de su tienda: lo aprueba (queda CERRADO con su comentario)
     * o pide que el cajero vuelva a contar (RECONTEO).
     */
    public function revisarCierre(Usuario $administrador, int $idTurno, string $decision, string $comentario): void
    {
        if (!in_array($decision, ['APROBAR', 'RECONTEO'], true)) {
            throw new ReglaNegocioException('Elige aprobar o pedir un nuevo conteo.');
        }
        $comentario = $this->textoObligatorio($comentario, 200, 'El comentario');

        $this->enTransaccion(function () use ($administrador, $idTurno, $decision, $comentario): void {
            $turno = $this->turnoDeLaTienda($administrador, $idTurno);
            if ($turno['estado'] !== 'OBSERVADO') {
                throw new ReglaNegocioException('Ese turno no tiene un cierre por revisar.');
            }
            $this->db->prepare(
                'UPDATE caja_turno SET estado = :estado, observacion = :comentario,
                        id_empleado_revisa = :revisa, fecha_hora_revision = NOW()
                  WHERE id_caja_turno = :turno'
            )->execute([
                'estado' => $decision === 'APROBAR' ? 'CERRADO' : 'RECONTEO', 'comentario' => $comentario,
                'revisa' => $administrador->getIdEmpleado(), 'turno' => $idTurno,
            ]);
            $this->auditar($administrador, $decision === 'APROBAR' ? 'CAJA_DIFERENCIA_APROBADA' : 'CAJA_RECONTEO_PEDIDO',
                'Turno ' . $idTurno . ' (diferencia S/ ' . $turno['diferencia'] . '): ' . $comentario);
        });
    }

    /**
     * Arqueo sorpresa: el administrador cuenta la gaveta de un turno abierto de su tienda.
     * @param array<string, string> $conteo
     * @return array{esperado: int, diferencia: int} en centimos
     */
    public function arqueoSorpresa(Usuario $administrador, int $idTurno, string $montoContado, array $conteo = [], ?string $observacion = null): array
    {
        [$monto, $detalle] = $this->leerConteo($montoContado, $conteo, 'Monto contado no válido.');
        $observacion = $this->textoOpcional($observacion, 150, 'La observación');

        return $this->enTransaccion(function () use ($administrador, $idTurno, $monto, $detalle, $observacion): array {
            $turno = $this->turnoDeLaTienda($administrador, $idTurno);
            if ($turno['estado'] !== 'ABIERTO') {
                throw new ReglaNegocioException('El arqueo sorpresa es para un turno abierto.');
            }
            $esperado = $this->efectivoEnGaveta($idTurno);
            $this->registrarArqueo($idTurno, 'PARCIAL', $monto, $esperado, $administrador->getIdEmpleado(), $detalle, $observacion);
            $this->auditar($administrador, 'CAJA_ARQUEO_SORPRESA', 'Turno ' . $idTurno . ': contó S/ ' . Dinero::aTexto($monto)
                . ', se esperaba S/ ' . self::conSigno($esperado));
            return ['esperado' => $esperado, 'diferencia' => $monto - $esperado];
        });
    }

    /**
     * Las reglas de caja de la tienda del administrador y el fondo fijo de cada caja.
     * @param array<string, string> $fondos id_caja => monto
     */
    public function guardarReglas(Usuario $administrador, string $tolerancia, string $tope, bool $cierreCiego, string $horas, array $fondos): void
    {
        $idTienda = $administrador->getIdTienda();
        if (!$administrador->puedeAdministrarTienda($idTienda)) {
            throw new ReglaNegocioException('Solo el administrador de la tienda cambia las reglas de caja.');
        }
        $tolerancia = Dinero::deTexto($tolerancia);
        $tope = Dinero::deTexto($tope);
        if ($tolerancia === null || $tolerancia > 10000) {
            throw new ReglaNegocioException('La tolerancia va de S/ 0.00 a S/ 100.00.');
        }
        if ($tope === null || $tope < 1000) {
            throw new ReglaNegocioException('El tope de la gaveta debe ser de al menos S/ 10.00.');
        }
        if (!ctype_digit($horas) || (int) $horas < 1 || (int) $horas > 24) {
            throw new ReglaNegocioException('Las horas máximas de un turno van de 1 a 24.');
        }
        $montosFondo = [];
        foreach ($fondos as $idCaja => $texto) {
            $centimos = Dinero::deTexto((string) $texto);
            if (!ctype_digit((string) $idCaja) || $centimos === null || $centimos > 1000000) {
                throw new ReglaNegocioException('El fondo fijo de cada caja va de S/ 0.00 a S/ 10000.00.');
            }
            $montosFondo[(int) $idCaja] = $centimos;
        }

        $this->enTransaccion(function () use ($administrador, $idTienda, $tolerancia, $tope, $cierreCiego, $horas, $montosFondo): void {
            $this->db->prepare(
                'INSERT INTO regla_caja (id_tienda, tolerancia, tope_gaveta, cierre_ciego, horas_maximas)
                 VALUES (:tienda, :tolerancia, :tope, :ciego, :horas)
                 ON DUPLICATE KEY UPDATE tolerancia = VALUES(tolerancia), tope_gaveta = VALUES(tope_gaveta),
                        cierre_ciego = VALUES(cierre_ciego), horas_maximas = VALUES(horas_maximas)'
            )->execute([
                'tienda' => $idTienda, 'tolerancia' => Dinero::aTexto($tolerancia), 'tope' => Dinero::aTexto($tope),
                'ciego' => $cierreCiego ? 'S' : 'N', 'horas' => (int) $horas,
            ]);
            $fondo = $this->db->prepare('UPDATE caja SET fondo_fijo = :fondo WHERE id_caja = :caja AND id_tienda = :tienda');
            foreach ($montosFondo as $idCaja => $centimos) {
                $fondo->execute(['fondo' => Dinero::aTexto($centimos), 'caja' => $idCaja, 'tienda' => $idTienda]);
                if ($fondo->rowCount() === 0 && !$this->cajaEsDeTienda($idCaja, $idTienda)) {
                    throw new ReglaNegocioException('Una de las cajas no es de tu tienda.');
                }
            }
            $this->auditar($administrador, 'CAJA_REGLAS', 'Tolerancia S/ ' . Dinero::aTexto($tolerancia) . ', tope S/ ' . Dinero::aTexto($tope)
                . ', cierre ciego ' . ($cierreCiego ? 'sí' : 'no') . ', ' . (int) $horas . ' h');
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
            $idTurno = $this->turnoDelCajero($cajero, 'ABIERTO', 'No tienes un turno abierto.');

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

    /**
     * Un conteo: total escrito o desglose de billetes y monedas. Devuelve [centimos, [denominacion => cantidad]].
     * @param array<string, string> $conteo
     * @return array{0: int, 1: array<string, int>}
     */
    private function leerConteo(string $total, array $conteo, string $error): array
    {
        $detalle = [];
        $suma = 0;
        foreach ($conteo as $denominacion => $cantidad) {
            $cantidad = trim((string) $cantidad);
            if ($cantidad === '' || $cantidad === '0') {
                continue;
            }
            if (!in_array((string) $denominacion, CajaRepository::DENOMINACIONES, true)) {
                throw new ReglaNegocioException('Billete o moneda no válido: ' . $denominacion . '.');
            }
            if (!ctype_digit($cantidad) || (int) $cantidad > 9999) {
                throw new ReglaNegocioException('Cantidad no válida de S/ ' . $denominacion . ': escribe cuántos hay, de 0 a 9999.');
            }
            $detalle[(string) $denominacion] = (int) $cantidad;
            $suma += (int) Dinero::deTexto((string) $denominacion) * (int) $cantidad;
        }
        if ($detalle !== []) {
            return [$suma, $detalle];
        }
        $monto = Dinero::deTexto($total);
        if ($monto === null) {
            throw new ReglaNegocioException($error);
        }
        return [$monto, []];
    }

    /**
     * Guarda el cierre (o el reconteo) de un turno: esperado, diferencia y estado segun la tolerancia de la tienda.
     * @param array<string, int> $detalle
     * @return array{turno: int, esperado: int, diferencia: int, estado: string}
     */
    private function guardarCierre(Usuario $cajero, int $idTurno, int $monto, array $detalle, ?string $observacion, string $accion): array
    {
        $esperado = $this->efectivoEnGaveta($idTurno);
        $diferencia = $monto - $esperado;
        $regla = $this->db->prepare(
            'SELECT COALESCE(r.tolerancia, 1.00) FROM caja_turno ct JOIN caja c ON c.id_caja = ct.id_caja
               LEFT JOIN regla_caja r ON r.id_tienda = c.id_tienda WHERE ct.id_caja_turno = :turno'
        );
        $regla->execute(['turno' => $idTurno]);
        $tolerancia = (int) Dinero::deTexto((string) $regla->fetchColumn());
        $estado = abs($diferencia) <= $tolerancia ? 'CERRADO' : 'OBSERVADO';

        // CHECK ck_turno_estado: cerrado con fecha y monto, sin pausa; ck_turno_diferencia: cierre - esperado.
        // Un reconteo borra la revision anterior: el administrador vuelve a revisar si sigue sin cuadrar.
        $this->db->prepare(
            'UPDATE caja_turno
                SET estado = :estado, fecha_hora_cierre = NOW(), monto_cierre = :monto, en_pausa_desde = NULL,
                    monto_esperado = :esperado, diferencia = :diferencia
              WHERE id_caja_turno = :turno'
        )->execute([
            'estado' => $estado, 'monto' => Dinero::aTexto($monto), 'esperado' => self::conSigno($esperado),
            'diferencia' => self::conSigno($diferencia), 'turno' => $idTurno,
        ]);
        $this->registrarArqueo($idTurno, 'CIERRE', $monto, $esperado, $cajero->getIdEmpleado(), $detalle, $observacion);
        if ($estado === 'OBSERVADO' || $accion === 'CAJA_RECONTEO') {
            $this->auditar($cajero, $accion, 'Turno ' . $idTurno . ': contó S/ ' . Dinero::aTexto($monto) . ', diferencia S/ '
                . self::conSigno($diferencia) . ' (' . $estado . ')');
        }
        return ['turno' => $idTurno, 'esperado' => $esperado, 'diferencia' => $diferencia, 'estado' => $estado];
    }

    // El turno del cajero en ese estado, bloqueado hasta el fin de la transaccion
    private function turnoDelCajero(Usuario $cajero, string $estado, string $error): int
    {
        $consulta = $this->db->prepare(
            'SELECT id_caja_turno FROM caja_turno WHERE id_empleado = :empleado AND estado = :estado FOR UPDATE'
        );
        $consulta->execute(['empleado' => $cajero->getIdEmpleado(), 'estado' => $estado]);
        $idTurno = $consulta->fetchColumn();
        if ($idTurno === false) {
            throw new ReglaNegocioException($error);
        }
        return (int) $idTurno;
    }

    /**
     * Un turno de la tienda del administrador, bloqueado.
     * @return array{estado: string, diferencia: ?string}
     */
    private function turnoDeLaTienda(Usuario $administrador, int $idTurno): array
    {
        if (!$administrador->puedeVerArqueo($administrador->getIdTienda())) {
            throw new ReglaNegocioException('Solo el administrador de la tienda revisa sus cajas.');
        }
        $consulta = $this->db->prepare(
            'SELECT ct.estado, ct.diferencia, c.id_tienda FROM caja_turno ct JOIN caja c ON c.id_caja = ct.id_caja
              WHERE ct.id_caja_turno = :turno FOR UPDATE'
        );
        $consulta->execute(['turno' => $idTurno]);
        $turno = $consulta->fetch();
        if ($turno === false || (int) $turno['id_tienda'] !== $administrador->getIdTienda()) {
            throw new ReglaNegocioException('Ese turno no es de tu tienda.');
        }
        return ['estado' => $turno['estado'], 'diferencia' => $turno['diferencia']];
    }

    private function cajaEsDeTienda(int $idCaja, int $idTienda): bool
    {
        $consulta = $this->db->prepare('SELECT 1 FROM caja WHERE id_caja = :caja AND id_tienda = :tienda');
        $consulta->execute(['caja' => $idCaja, 'tienda' => $idTienda]);
        return $consulta->fetchColumn() !== false;
    }

    /**
     * Un conteo de la gaveta (APERTURA, PARCIAL o CIERRE) en arqueo_caja, con su desglose en arqueo_detalle.
     * @param array<string, int> $detalle
     */
    private function registrarArqueo(int $idTurno, string $tipo, int $contado, int $esperado, int $idEmpleado,
                                     array $detalle = [], ?string $observacion = null): void
    {
        $this->db->prepare(
            'INSERT INTO arqueo_caja (fecha_hora, tipo, monto_contado, monto_esperado, observacion, id_caja_turno, id_empleado)
             VALUES (NOW(), :tipo, :contado, :esperado, :observacion, :turno, :empleado)'
        )->execute([
            'tipo' => $tipo, 'contado' => Dinero::aTexto($contado),
            // CHECK ck_arqueo_montos: el esperado no es negativo (una gaveta no puede deber plata)
            'esperado' => Dinero::aTexto(max(0, $esperado)), 'observacion' => $observacion,
            'turno' => $idTurno, 'empleado' => $idEmpleado,
        ]);
        $idArqueo = (int) $this->db->lastInsertId();
        $fila = $this->db->prepare('INSERT INTO arqueo_detalle (id_arqueo, denominacion, cantidad) VALUES (:arqueo, :denominacion, :cantidad)');
        foreach ($detalle as $denominacion => $cantidad) {
            $fila->execute(['arqueo' => $idArqueo, 'denominacion' => $denominacion, 'cantidad' => $cantidad]);
        }
    }

    // Dinero::aTexto solo recibe montos positivos; la diferencia puede ser un faltante (negativo)
    private static function conSigno(int $centimos): string
    {
        return ($centimos < 0 ? '-' : '') . Dinero::aTexto(abs($centimos));
    }
}
