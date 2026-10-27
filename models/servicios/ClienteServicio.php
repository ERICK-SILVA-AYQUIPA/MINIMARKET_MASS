<?php
declare(strict_types=1);

require_once __DIR__ . '/Servicio.php';
require_once __DIR__ . '/../Usuario.php';

/**
 * Datos del cliente. Solo se completa el documento que falta: cambiar uno que ya existe
 * cambiaria lo que se imprime en sus comprobantes pasados (vw_comprobante lo lee en vivo).
 */
class ClienteServicio extends Servicio
{
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
