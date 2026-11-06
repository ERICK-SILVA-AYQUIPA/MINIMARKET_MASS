<?php
declare(strict_types=1);

/**
 * Quien responde las preguntas del asistente: recibe la peticion de la API de mensajes de Claude
 * (system, messages, tools) y devuelve la respuesta ya decodificada.
 * La implementacion real es ClienteClaude; las pruebas usan una falsa que no sale a internet.
 */
interface ModeloLenguaje
{
    /**
     * @param array<string, mixed> $peticion
     * @return array<string, mixed>
     * @throws RuntimeException si no hay respuesta valida (sin internet, clave mala, limite de uso)
     */
    public function enviar(array $peticion): array;
}
