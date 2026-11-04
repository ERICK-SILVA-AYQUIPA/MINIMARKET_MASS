import { execFileSync } from 'node:child_process';

/** Se corre una vez antes de todas las pruebas: revisa que la base este recien creada (ver preparar.php). */
export default function preparar(): void {
    execFileSync('php', ['tests/e2e/preparar.php'], { stdio: 'inherit' });
}
