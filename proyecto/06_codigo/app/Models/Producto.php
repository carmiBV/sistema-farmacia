<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de `catalog_products`.
 *
 * Whitelist explícita de campos expuestos en la API.
 */
final class Producto
{
    public function __construct(
        public readonly int $id,
        public readonly string $sku,
        public readonly string $nombre,
        public readonly string $principio_activo,
        public readonly string $presentacion,
        public readonly string $concentracion,
        public readonly string $condicion_venta,
        public readonly string $estado,
        public readonly string $created_at,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['sku'],
            (string) $row['nombre'],
            (string) $row['principio_activo'],
            (string) $row['presentacion'],
            (string) $row['concentracion'],
            (string) $row['condicion_venta'],
            (string) $row['estado'],
            (string) $row['created_at'],
        );
    }

    /** @return array{id:int,sku:string,nombre:string,principio_activo:string,presentacion:string,concentracion:string,condicion_venta:string,estado:string,created_at:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'nombre' => $this->nombre,
            'principio_activo' => $this->principio_activo,
            'presentacion' => $this->presentacion,
            'concentracion' => $this->concentracion,
            'condicion_venta' => $this->condicion_venta,
            'estado' => $this->estado,
            'created_at' => $this->created_at,
        ];
    }
}
