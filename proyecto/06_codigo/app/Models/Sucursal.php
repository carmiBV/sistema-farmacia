<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de `ops_stores`.
 *
 * Whitelist explícita de campos expuestos en la API.
 */
final class Sucursal
{
    public function __construct(
        public readonly int $id,
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $estado,
        public readonly string $created_at,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['codigo'],
            (string) $row['nombre'],
            (string) $row['estado'],
            (string) $row['created_at'],
        );
    }

    /** @return array{id:int,codigo:string,nombre:string,estado:string,created_at:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'estado' => $this->estado,
            'created_at' => $this->created_at,
        ];
    }
}
