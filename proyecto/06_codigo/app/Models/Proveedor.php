<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de `catalog_suppliers`.
 *
 * Whitelist explícita de campos expuestos en la API.
 */
final class Proveedor
{
    public function __construct(
        public readonly int $id,
        public readonly string $identificacion,
        public readonly string $nombre,
        public readonly ?string $contacto,
        public readonly string $estado,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['identificacion'],
            (string) $row['nombre'],
            $row['contacto'] === null ? null : (string) $row['contacto'],
            (string) $row['estado'],
        );
    }

    /** @return array{id:int,identificacion:string,nombre:string,contacto:string|null,estado:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'identificacion' => $this->identificacion,
            'nombre' => $this->nombre,
            'contacto' => $this->contacto,
            'estado' => $this->estado,
        ];
    }
}
