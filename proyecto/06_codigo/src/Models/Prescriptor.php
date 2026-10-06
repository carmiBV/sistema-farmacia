<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de `catalog_prescribers`.
 *
 * Whitelist explícita de campos expuestos en la API.
 */
final class Prescriptor
{
    public function __construct(
        public readonly int $id,
        public readonly string $identificacion,
        public readonly string $nombre,
        public readonly ?string $especialidad,
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
            $row['especialidad'] === null ? null : (string) $row['especialidad'],
            (string) $row['estado'],
        );
    }

    /** @return array{id:int,identificacion:string,nombre:string,especialidad:string|null,estado:string} */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'identificacion' => $this->identificacion,
            'nombre' => $this->nombre,
            'especialidad' => $this->especialidad,
            'estado' => $this->estado,
        ];
    }
}
