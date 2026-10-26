<?php
declare(strict_types=1);

/**
 * La sesion de PHP: quien entro, el token CSRF de los formularios y los mensajes de una sola vez.
 */
final class Sesion
{
    public function iniciar(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');      // no acepta ids de sesion inventados
        ini_set('session.use_only_cookies', '1');
        session_name('MASSID');
        session_set_cookie_params([
            'lifetime' => 0,                           // se borra al cerrar el navegador
            'path'     => '/',
            'secure'   => !empty($_SERVER['HTTPS']),
            'httponly' => true,                        // JavaScript no puede leer la cookie
            'samesite' => 'Lax',                       // otro sitio no puede mandar formularios con ella
        ]);
        session_start();
    }

    // Minutos sin usar la aplicacion antes de pedir el login otra vez
    public const MINUTOS_INACTIVIDAD = 30;

    public function idEmpleado(): ?int
    {
        $id = $_SESSION['id_empleado'] ?? null;
        if (!is_int($id)) {
            return null;
        }
        // Una sesion olvidada abierta en la caja se cierra sola
        $ultima = $_SESSION['ultima_actividad'] ?? 0;
        if (!is_int($ultima) || time() - $ultima > self::MINUTOS_INACTIVIDAD * 60) {
            // Se vacia y se cambia el id, pero la sesion sigue viva para el formulario de login
            $_SESSION = [];
            session_regenerate_id(true);
            $this->avisar('error', 'Tu sesión venció por inactividad. Ingresa otra vez.');
            return null;
        }
        $_SESSION['ultima_actividad'] = time();
        return $id;
    }

    // Despues del login se cambia el id de sesion (evita robar una sesion fijada antes)
    public function entrar(int $idEmpleado): void
    {
        session_regenerate_id(true);
        $_SESSION['id_empleado'] = $idEmpleado;
        $_SESSION['ultima_actividad'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    public function salir(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    // Token que va oculto en cada formulario POST
    public function tokenCsrf(): string
    {
        if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public function csrfValido(?string $token): bool
    {
        return $token !== null && isset($_SESSION['csrf']) && is_string($_SESSION['csrf'])
            && hash_equals($_SESSION['csrf'], $token);
    }

    // Mensaje que se muestra una sola vez despues de una redireccion
    public function avisar(string $tipo, string $mensaje): void
    {
        $_SESSION['aviso'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    }

    /** @return array{tipo: string, mensaje: string}|null */
    public function tomarAviso(): ?array
    {
        $aviso = $_SESSION['aviso'] ?? null;
        unset($_SESSION['aviso']);
        return is_array($aviso) ? $aviso : null;
    }
}
