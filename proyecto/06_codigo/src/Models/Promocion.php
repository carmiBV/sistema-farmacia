<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de `catalog_promotions`.
 *
 * La tabla NO tiene columna `estado`: la baja es cerrar la vigencia
 * (`hasta`), nunca un DELETE. Whitelist explícita de campos expuestos.
 */
final class Promocion
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $product_id,
        public readonly ?int $category_id,
        public readonly string $descuento_pct,
        public readonly string $desde,
        public readonly string $hasta,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['product_id'] === null ? null : (int) $row['product_id'],
            $row['category_id'] === null ? null : (int) $row['category_id'],
            (string) $row['descuento_pct'],
            (string) $row['desde'],
            (string) $row['hasta'],
        );
    }

    /** @return array{id:int,product_id:int|null,category_id:int|null,descuento_pct:string,desde:string,hasta:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'category_id' => $this->category_id,
            'descuento_pct' => $this->descuento_pct,
            'desde' => $this->desde,
            'hasta' => $this->hasta,
        ];
    }
}
