<?php
declare(strict_types=1);

namespace App\Models;

/**
 * Modelo de dominio de `catalog_patients`.
 *
 * Whitelist explícita de campos expuestos en la API.
 */
final class Paciente
{
    public function __construct(
        public readonly int $id,
        public readonly string $identificacion,
        public readonly string $nombre,
        public readonly ?string $fecha_nacimiento,
        public readonly ?string $contacto,
        public readonly string $estado,
        public readonly string $created_at,
        public readonly ?string $anonimizado_at,
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['identificacion'],
            (string) $row['nombre'],
            $row['fecha_nacimiento'] === null ? null : (string) $row['fecha_nacimiento'],
            $row['contacto'] === null ? null : (string) $row['contacto'],
            (string) $row['estado'],
            (string) $row['created_at'],
            $row['anonimizado_at'] === null ? null : (string) $row['anonimizado_at'],
        );
    }

    /**
     * @return array{id:int,identificacion:string,nombre:string,fecha_nacimiento:string|null,
     *     contacto:string|null,estado:string,created_at:string,anonimizado_at:string|null}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'identificacion' => $this->identificacion,
            'nombre' => $this->nombre,
            'fecha_nacimiento' => $this->fecha_nacimiento,
            'contacto' => $this->contacto,
            'estado' => $this->estado,
            'created_at' => $this->created_at,
            'anonimizado_at' => $this->anonimizado_at,
        ];
    }
}
