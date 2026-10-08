<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Usuario de auth_users. El hash de contraseña NUNCA sale del repositorio
 * (no está en $fillable ni en toArray).
 */
final class Usuario
{
    /**
     * @param array<string,mixed> $row
     */
    private function __construct(
        public readonly int $id,
        public readonly string $usuario,
        public readonly string $passwordHash,
        public readonly string $estado,
        public readonly ?string $ultimoAccesoAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int)$row['id'],
            (string)$row['usuario'],
            (string)$row['password_hash'],
            (string)$row['estado'],
            isset($row['ultimo_acceso_at']) && $row['ultimo_acceso_at'] !== null ? (string)$row['ultimo_acceso_at'] : null,
        );
    }

    public function estaActivo(): bool
    {
        return $this->estado === 'activo';
    }
}
