<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de `ops_registers`.
 *
 * Whitelist explícita de campos expuestos en la API.
 */
final class Caja
{
    public function __construct(
        public readonly int $id,
        public readonly int $store_id,
        public readonly string $codigo,
        public readonly string $estado,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['store_id'],
            (string) $row['codigo'],
            (string) $row['estado'],
        );
    }

    /** @return array{id:int,store_id:int,codigo:string,estado:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'codigo' => $this->codigo,
            'estado' => $this->estado,
        ];
    }
}
