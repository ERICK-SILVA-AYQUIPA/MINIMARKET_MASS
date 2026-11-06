<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';

/**
 * Datos del cliente. Solo se completa el documento que falta: cambiar uno que ya existe
 * cambiaria lo que se imprime en sus comprobantes pasados (vw_comprobante lo lee en vivo).
 * Nombre, direccion y telefono (migracion 026) si se cambian cuando haga falta.
 */
class ClienteServicio extends Servicio
{
    /**
     * Registra a mano un cliente nuevo (sin esperar a que compre): nombre, DNI o RUC, direccion y telefono.
     * Devuelve su id.
     */
    public function crear(Usuario $usuario, string $nombre, string $dni, string $ruc, string $direccion, string $telefono): int
    {
        $this->exigirPermiso($usuario);
        $nombre = $this->textoObligatorio($nombre, 100, 'Nombre');
        [$dni, $ruc] = $this->documentos($dni, $ruc);
        if ($dni === null && $ruc === null) {
            throw new ReglaNegocioException('Escribe el DNI o el RUC del cliente.');
        }
        $direccion = $this->textoOpcional($direccion, 120, 'Dirección');
        $telefono = $this->telefonoOpcional($telefono);

        return $this->enTransaccion(function () use ($usuario, $nombre, $dni, $ruc, $direccion, $telefono): int {
            try {
                $this->db->prepare(
                    'INSERT INTO cliente (nombre, dni, ruc, direccion, telefono) VALUES (:nombre, :dni, :ruc, :direccion, :telefono)'
                )->execute(['nombre' => $nombre, 'dni' => $dni, 'ruc' => $ruc, 'direccion' => $direccion, 'telefono' => $telefono]);
            } catch (PDOException $ex) {
                throw $this->esDuplicado($ex) ? new ReglaNegocioException('Ya hay un cliente con ese DNI o RUC: búscalo en la lista.') : $ex;
            }
            $id = (int) $this->db->lastInsertId();
            // Sin el documento ni el telefono en la bitacora: son datos personales
            $this->auditar($usuario, 'CLIENTE_CREADO', sprintf('Cliente %d: %s', $id, $nombre));
            return $id;
        });
    }

    // Cambia nombre, direccion y telefono de un cliente (vacio = se borra ese dato)
    public function editarContacto(Usuario $usuario, int $idCliente, string $nombre, string $direccion, string $telefono): void
    {
        $this->exigirPermiso($usuario);
        $nombre = $this->textoOpcional($nombre, 100, 'Nombre');
        $direccion = $this->textoOpcional($direccion, 120, 'Dirección');
        $telefono = $this->telefonoOpcional($telefono);

        $this->enTransaccion(function () use ($usuario, $idCliente, $nombre, $direccion, $telefono): void {
            $existe = $this->db->prepare('SELECT 1 FROM cliente WHERE id_cliente = :id FOR UPDATE');
            $existe->execute(['id' => $idCliente]);
            if ($existe->fetchColumn() === false) {
                throw new ReglaNegocioException('El cliente no existe.');
            }
            $this->db->prepare('UPDATE cliente SET nombre = :nombre, direccion = :direccion, telefono = :telefono WHERE id_cliente = :id')
                ->execute(['nombre' => $nombre, 'direccion' => $direccion, 'telefono' => $telefono, 'id' => $idCliente]);
            $this->auditar($usuario, 'CLIENTE_EDITADO', sprintf('Cliente %d: datos de contacto', $idCliente));
        });
    }

    /**
     * DNI y RUC escritos en un formulario: vacio -> null; si vienen, con sus digitos exactos.
     * @return array{0: ?string, 1: ?string}
     */
    private function documentos(string $dni, string $ruc): array
    {
        $dni = trim($dni);
        $ruc = trim($ruc);
        if ($dni !== '' && preg_match('/^[0-9]{8}$/', $dni) !== 1) {
            throw new ReglaNegocioException('El DNI debe tener 8 dígitos.');
        }
        if ($ruc !== '' && preg_match('/^[0-9]{11}$/', $ruc) !== 1) {
            throw new ReglaNegocioException('El RUC debe tener 11 dígitos.');
        }
        return [$dni === '' ? null : $dni, $ruc === '' ? null : $ruc];
    }

    private function exigirPermiso(Usuario $usuario): void
    {
        if (!$usuario->puedeVender() && !$usuario->puedeAdministrarTienda($usuario->getIdTienda())) {
            throw new ReglaNegocioException('Solo un cajero o el administrador registran y editan clientes.');
        }
    }

    public function completarDocumento(Usuario $usuario, int $idCliente, string $dni, string $ruc): void
    {
        if (!$usuario->puedeVender() && !$usuario->puedeAdministrarTienda($usuario->getIdTienda())) {
            throw new ReglaNegocioException('Solo un cajero o el administrador completan datos del cliente.');
        }
        $dni = trim($dni);
        $ruc = trim($ruc);
        if ($dni !== '' && preg_match('/^[0-9]{8}$/', $dni) !== 1) {
            throw new ReglaNegocioException('El DNI debe tener 8 dígitos.');
        }
        if ($ruc !== '' && preg_match('/^[0-9]{11}$/', $ruc) !== 1) {
            throw new ReglaNegocioException('El RUC debe tener 11 dígitos.');
        }
        if ($dni === '' && $ruc === '') {
            throw new ReglaNegocioException('Escribe el DNI o el RUC que falta.');
        }

        $this->enTransaccion(function () use ($usuario, $idCliente, $dni, $ruc): void {
            $consulta = $this->db->prepare('SELECT dni, ruc FROM cliente WHERE id_cliente = :id FOR UPDATE');
            $consulta->execute(['id' => $idCliente]);
            $cliente = $consulta->fetch();
            if ($cliente === false) {
                throw new ReglaNegocioException('El cliente no existe.');
            }
            if (($dni !== '' && $cliente['dni'] !== null) || ($ruc !== '' && $cliente['ruc'] !== null)) {
                throw new ReglaNegocioException('Ese documento ya está registrado; solo se completa el que falta.');
            }
            try {
                $this->db->prepare('UPDATE cliente SET dni = COALESCE(dni, :dni), ruc = COALESCE(ruc, :ruc) WHERE id_cliente = :id')
                    ->execute(['dni' => $dni === '' ? null : $dni, 'ruc' => $ruc === '' ? null : $ruc, 'id' => $idCliente]);
            } catch (PDOException $ex) {
                throw $this->esDuplicado($ex) ? new ReglaNegocioException('Otro cliente ya tiene ese documento.') : $ex;
            }
            // El documento no se copia completo a la bitacora: es un dato personal
            $this->auditar($usuario, 'CLIENTE_DOCUMENTO', sprintf('Cliente %d: agregó %s', $idCliente, $dni !== '' ? 'DNI' : 'RUC'));
        });
    }
}
