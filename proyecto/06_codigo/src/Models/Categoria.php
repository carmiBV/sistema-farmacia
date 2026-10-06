<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de `catalog_categories`.
 *
 * El Repository lo hidrata desde la fila; el Controller lo serializa con
 * toArray(). Whitelist explícita de campos: `parent_id_key` (columna generada)
 * y cualquier otra columna interna NUNCA se exponen en la API.
 */
final class Categoria
{
    public function __construct(
        public readonly int $id,
        public readonly string $nombre,
        public readonly ?int $parent_id,
    ) {
    }

    /** @param array<string, mixed> $row fila con al menos id, nombre, parent_id */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['nombre'],
            $row['parent_id'] === null ? null : (int) $row['parent_id'],
        );
    }

    /** @return array{id: int, nombre: string, parent_id: int|null} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'parent_id' => $this->parent_id,
        ];
    }
}
