<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class TokenBlacklistRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::pdo();
    }

    public function estaRevocado(string $tokenHash): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM token_blacklist WHERE token_hash = ? LIMIT 1');
        $stmt->execute([$tokenHash]);
        return $stmt->fetch() !== false;
    }

    /** @throws \PDOException 1062 si el token ya estaba revocado */
    public function revocar(string $tokenHash, string $expiresAtUtc): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO token_blacklist (token_hash, expires_at) VALUES (?, ?)'
        );
        $stmt->execute([$tokenHash, $expiresAtUtc]);
    }

    /**
     * Limpieza de expirados fuera de horario pico. ponytail: se omite en cada
     * login para no pagar el DELETE en el camino crítico del mostrador; añadir
     * tarea programada (cron/workflow) si la tabla crece.
     */
    public function purgarExpirados(): void
    {
        $this->pdo->exec('DELETE FROM token_blacklist WHERE expires_at < UTC_TIMESTAMP(6)');
    }
}
