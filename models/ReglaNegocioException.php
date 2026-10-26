<?php
declare(strict_types=1);

/**
 * Una operacion que rompe una regla del negocio (sin stock, sin permiso, pagos que no cuadran...).
 * Su mensaje es para el usuario: se muestra en pantalla. Cualquier otra excepcion es un error interno.
 */
class ReglaNegocioException extends DomainException
{
}
