<?php
declare(strict_types=1);

namespace App\Validators;

use App\Http\AppException;

/**
 * Validación de bodies de catálogo: catalog_suppliers / catalog_patients /
 * catalog_prescribers. Un solo validator compartido: los tres recursos comparten
 * identificacion + nombre y solo difieren en sus campos opcionales.
 */
final class DirectorioValidator
{
    /**
     * @param array<string,mixed> $input body SupplierCreate/SupplierUpdate
     * @return array{identificacion:string,nombre:string,contacto:string|null}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarProveedor(array $input): array
    {
        return [
            'identificacion' => $this->identificacion($input),
            'nombre' => $this->nombre($input),
            'contacto' => $this->opcional($input, 'contacto', 255),
        ];
    }

    /**
     * @param array<string,mixed> $input body PatientCreate/PatientUpdate
     * @return array{identificacion:string,nombre:string,fecha_nacimiento:string|null,contacto:string|null}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarPaciente(array $input): array
    {
        return [
            'identificacion' => $this->identificacion($input),
            'nombre' => $this->nombre($input),
            'fecha_nacimiento' => $this->fecha($input),
            'contacto' => $this->opcional($input, 'contacto', 255),
        ];
    }

    /**
     * @param array<string,mixed> $input body PrescriberCreate/PrescriberUpdate
     * @return array{identificacion:string,nombre:string,especialidad:string|null}
     * @throws AppException VALIDATION_ERROR (400)
     */
    public function validarPrescriptor(array $input): array
    {
        return [
            'identificacion' => $this->identificacion($input),
            'nombre' => $this->nombre($input),
            'especialidad' => $this->opcional($input, 'especialidad', 150),
        ];
    }

    /** @param array<string,mixed> $input */
    private function identificacion(array $input): string
    {
        if (!array_key_exists('identificacion', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'identificacion' es obligatorio.", 400);
        }
        $identificacion = $input['identificacion'];
        if (!is_string($identificacion)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'identificacion' debe ser texto.", 400);
        }
        $identificacion = trim($identificacion);
        if ($identificacion === '' || mb_strlen($identificacion) > 30) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'identificacion' debe tener entre 1 y 30 caracteres.",
                400
            );
        }
        return $identificacion;
    }

    /** @param array<string,mixed> $input */
    private function nombre(array $input): string
    {
        if (!array_key_exists('nombre', $input)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'nombre' es obligatorio.", 400);
        }
        $nombre = $input['nombre'];
        if (!is_string($nombre)) {
            throw new AppException('VALIDATION_ERROR', "El campo 'nombre' debe ser texto.", 400);
        }
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > 200) {
            throw new AppException('VALIDATION_ERROR', "El campo 'nombre' debe tener entre 1 y 200 caracteres.", 400);
        }
        return $nombre;
    }

    /** Fecha opcional: ausente o null → null; si no, debe ser Y-m-d estrictamente válida. */
    /** @param array<string,mixed> $input */
    private function fecha(array $input): ?string
    {
        if (!array_key_exists('fecha_nacimiento', $input)) {
            return null;
        }
        $fecha = $input['fecha_nacimiento'];
        if ($fecha === null) {
            return null;
        }
        if (!is_string($fecha)) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'fecha_nacimiento' debe ser una fecha válida (YYYY-MM-DD).",
                400
            );
        }
        $fecha = trim($fecha);
        if ($fecha === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if ($dt === false || $dt->format('Y-m-d') !== $fecha) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo 'fecha_nacimiento' debe ser una fecha válida (YYYY-MM-DD).",
                400
            );
        }
        return $fecha;
    }

    /** Campo opcional de texto: ausente/vacío → null. */
    /** @param array<string,mixed> $input */
    private function opcional(array $input, string $key, int $max): ?string
    {
        if (!array_key_exists($key, $input) || $input[$key] === null) {
            return null;
        }
        $valor = $input[$key];
        if (!is_string($valor)) {
            throw new AppException('VALIDATION_ERROR', "El campo '{$key}' debe ser texto.", 400);
        }
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if (mb_strlen($valor) > $max) {
            throw new AppException(
                'VALIDATION_ERROR',
                "El campo '{$key}' debe tener hasta {$max} caracteres.",
                400
            );
        }
        return $valor;
    }
}
