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

    /**
     * Despues del login se cambia el id de sesion (evita robar una sesion fijada antes).
     * Se guarda la version de la clave con la que entro: si la clave cambia, esta sesion deja de valer.
     */
    public function entrar(int $idEmpleado, int $versionClave): void
    {
        session_regenerate_id(true);
        $_SESSION['id_empleado'] = $idEmpleado;
        $_SESSION['version_clave'] = $versionClave;
        $_SESSION['ultima_actividad'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        unset($_SESSION['tienda_elegida']);
    }

    // Sucursal que eligio el administrador en el menu lateral (null: la de su ficha)
    public function tiendaElegida(): ?int
    {
        $id = $_SESSION['tienda_elegida'] ?? null;
        return is_int($id) ? $id : null;
    }

    public function elegirTienda(?int $idTienda): void
    {
        if ($idTienda === null) {
            unset($_SESSION['tienda_elegida']);
        } else {
            $_SESSION['tienda_elegida'] = $idTienda;
        }
    }

    // Version de la clave con la que se entro (null en una sesion de antes de esta revision)
    public function versionClave(): ?int
    {
        $version = $_SESSION['version_clave'] ?? null;
        return is_int($version) ? $version : null;
    }

    // Quien cambia su propia clave sigue en esta sesion; las de otras PCs se cierran
    public function actualizarVersionClave(int $versionClave): void
    {
        session_regenerate_id(true);
        $_SESSION['version_clave'] = $versionClave;
    }

    // La clave cambio en otra PC (o la reinicio el administrador): se sale y se pide el login con la clave nueva
    public function cerrarPorClaveCambiada(): void
    {
        $_SESSION = [];
        session_regenerate_id(true);
        $this->avisar('error', 'Tu clave cambió, así que se cerró esta sesión. Ingresa con la clave nueva.');
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

    // Pantalla de bienvenida con el logo: sale una sola vez, en la primera pagina despues de entrar
    public function marcarBienvenida(): void
    {
        $_SESSION['bienvenida'] = true;
    }

    public function tomarBienvenida(): bool
    {
        $hay = ($_SESSION['bienvenida'] ?? false) === true;
        unset($_SESSION['bienvenida']);
        return $hay;
    }
    /**
     * La conversacion con el asistente (solo texto, las ultimas preguntas): vive en la sesion,
     * asi que se borra al salir y nadie mas la ve.
     * @return array<int, array{rol: string, texto: string, enlaces: array<int, array{texto: string, url: string}>}>
     */
    public function conversacionAsistente(): array
    {
        $turnos = [];
        foreach (is_array($_SESSION['asistente'] ?? null) ? $_SESSION['asistente'] : [] as $t) {
            if (is_array($t) && is_string($t['rol'] ?? null) && is_string($t['texto'] ?? null)) {
                $turnos[] = ['rol' => $t['rol'], 'texto' => $t['texto'], 'enlaces' => is_array($t['enlaces'] ?? null) ? $t['enlaces'] : []];
            }
        }
        return $turnos;
    }

    /** @param array<int, array{rol: string, texto: string, enlaces: array<int, array{texto: string, url: string}>}> $turnos */
    public function guardarConversacionAsistente(array $turnos, int $maximo): void
    {
        $_SESSION['asistente'] = array_slice(array_values($turnos), -$maximo);
    }

    // Cada pregunta al asistente cuesta: true si en los ultimos $segundos ya hizo $maximo (y no se cuenta esta)
    public function asistenteSaturado(int $maximo, int $segundos): bool
    {
        $ahora = time();
        $veces = array_values(array_filter(is_array($_SESSION['asistente_veces'] ?? null) ? $_SESSION['asistente_veces'] : [],
            fn(mixed $t): bool => is_int($t) && $t > $ahora - $segundos));
        if (count($veces) >= $maximo) {
            $_SESSION['asistente_veces'] = $veces;
            return true;
        }
        $veces[] = $ahora;
        $_SESSION['asistente_veces'] = $veces;
        return false;
    }
}
