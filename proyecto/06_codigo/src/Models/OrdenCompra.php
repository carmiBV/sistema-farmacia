<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de purchase_orders (RF-030).
 * Whitelist explícito: nunca exponer campos internos.
 */
final class OrdenCompra
{
    public function __construct(
        public readonly int $id,
        public readonly string $numero,
        public readonly int $supplierId,
        public readonly string $estado,
        public readonly int $creadoPor,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['numero'],
            (int) $row['supplier_id'],
            (string) $row['estado'],
            (int) $row['creado_por'],
            (string) $row['created_at'],
            (string) $row['updated_at'],
        );
    }

    /**
     * @return array{id:int,numero:string,supplier_id:int,estado:string,creado_por:int,created_at:string,updated_at:string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'numero' => $this->numero,
            'supplier_id' => $this->supplierId,
            'estado' => $this->estado,
            'creado_por' => $this->creadoPor,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
