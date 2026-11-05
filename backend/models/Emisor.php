<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/Entorno.php';

/**
 * La empresa que vende: lo que va en la cabecera del ticket (razon social, RUC y direccion fiscal).
 * No esta en la base: es una sola empresa para las 3 tiendas y se configura en .env
 * (EMPRESA_RAZON_SOCIAL, EMPRESA_RUC, EMPRESA_NOMBRE_COMERCIAL, EMPRESA_DIRECCION_FISCAL).
 * Mientras falte, el ticket no inventa un RUC: deja el aviso para configurarlo.
 */
final class Emisor
{
    public function __construct(
        private string $razonSocial,
        private string $ruc,
        private string $nombreComercial = 'TIENDA MASS',
        private string $direccionFiscal = ''
    ) {
    }

    public static function desdeEntorno(): self
    {
        return new self(
            Entorno::leer('EMPRESA_RAZON_SOCIAL'),
            preg_replace('/\s+/', '', Entorno::leer('EMPRESA_RUC')) ?? '',
            Entorno::leer('EMPRESA_NOMBRE_COMERCIAL', 'TIENDA MASS'),
            Entorno::leer('EMPRESA_DIRECCION_FISCAL')
        );
    }

    public function getRazonSocial(): string { return $this->razonSocial; }
    public function getRuc(): string { return $this->ruc; }
    public function getNombreComercial(): string { return $this->nombreComercial; }
    public function getDireccionFiscal(): string { return $this->direccionFiscal; }

    // Tiene razon social y un RUC que pasa el digito verificador de SUNAT
    public function estaConfigurado(): bool
    {
        return trim($this->razonSocial) !== '' && self::rucValido($this->ruc);
    }

    /**
     * RUC de 11 digitos que empieza con 10, 15, 16, 17 o 20 y cuyo ultimo digito es el verificador
     * (modulo 11 con los pesos 5 4 3 2 7 6 5 4 3 2, como lo calcula SUNAT).
     */
    public static function rucValido(string $ruc): bool
    {
        if (preg_match('/^(10|15|16|17|20)[0-9]{9}$/', $ruc) !== 1) {
            return false;
        }
        $pesos = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
        $suma = 0;
        foreach ($pesos as $i => $peso) {
            $suma += (int) $ruc[$i] * $peso;
        }
        $digito = 11 - ($suma % 11);
        $digito = $digito === 10 ? 0 : ($digito === 11 ? 1 : $digito);
        return (int) $ruc[10] === $digito;
    }
}
