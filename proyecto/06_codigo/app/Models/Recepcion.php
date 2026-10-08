<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de purchase_receptions (RF-031, RN-09).
 * Whitelist explícito: nunca exponer campos internos.
 */
final class Recepcion
{
    public function __construct(
        public readonly int $id,
        public readonly int $orderId,
        public readonly string $estado,
        public readonly int $recepcionadoPor,
        public readonly ?int $confirmadoPor,
        public readonly string $createdAt,
        public readonly ?string $confirmadoAt,
        public readonly ?string $idempotencyKey,
    ) {
    }

    /** @param array<string,mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['order_id'],
            (string) $row['estado'],
            (int) $row['recepcionado_por'],
            $row['confirmado_por'] !== null ? (int) $row['confirmado_por'] : null,
            (string) $row['created_at'],
            $row['confirmado_at'] !== null ? (string) $row['confirmado_at'] : null,
            $row['idempotency_key'] !== null ? (string) $row['idempotency_key'] : null,
        );
    }

    /**
     * @return array{id:int,order_id:int,estado:string,recepcionado_por:int,confirmado_por:int|null,created_at:string,confirmado_at:string|null,idempotency_key:string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->orderId,
            'estado' => $this->estado,
            'recepcionado_por' => $this->recepcionadoPor,
            'confirmado_por' => $this->confirmadoPor,
            'created_at' => $this->createdAt,
            'confirmado_at' => $this->confirmadoAt,
            'idempotency_key' => $this->idempotencyKey,
        ];
    }
}
