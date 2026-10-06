<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de `catalog_prices`.
 *
 * La tabla NO tiene columna `estado`: la baja es cerrar la vigencia
 * (`vigente_hasta`), nunca un DELETE. Whitelist explícita: `store_id_key`
 * (columna generada) NUNCA se expone en la API.
 */
final class Precio
{
    public function __construct(
        public readonly int $id,
        public readonly int $product_id,
        public readonly ?int $store_id,
        public readonly string $precio,
        public readonly string $vigente_desde,
        public readonly ?string $vigente_hasta,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['product_id'],
            $row['store_id'] === null ? null : (int) $row['store_id'],
            (string) $row['precio'],
            (string) $row['vigente_desde'],
            $row['vigente_hasta'] === null ? null : (string) $row['vigente_hasta'],
        );
    }

    /** @return array{id:int,product_id:int,store_id:int|null,precio:string,vigente_desde:string,vigente_hasta:string|null} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'store_id' => $this->store_id,
            'precio' => $this->precio,
            'vigente_desde' => $this->vigente_desde,
            'vigente_hasta' => $this->vigente_hasta,
        ];
    }
}
