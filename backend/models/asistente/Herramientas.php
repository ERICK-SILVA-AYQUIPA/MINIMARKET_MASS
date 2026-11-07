<?php
declare(strict_types=1);

/**
 * Lo que el Asistente le ofrece a Claude. La implementacion real es HerramientasAsistente (consulta la base
 * con los permisos de quien pregunta); las pruebas unitarias usan una falsa, sin base de datos.
 */
interface Herramientas
{
    /** @return array<int, array<string, mixed>> */
    public function definiciones(): array;

    /**
     * @param array<string, mixed> $entrada
     * @return array<string, mixed>
     */
    public function ejecutar(string $nombre, array $entrada): array;

    public function contexto(): string;

    /** @return array<int, array{texto: string, url: string}> */
    public function enlaces(): array;

    /** @return array<int, array<string, mixed>> tarjetas, tablas y graficos de lo consultado (Presentacion) */
    public function bloques(): array;
}
