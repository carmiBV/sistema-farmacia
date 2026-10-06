<?php
declare(strict_types=1);

namespace App\Validators;

use App\Http\AppException;

/**
 * Validacion de filtros de auditoria y eventos salientes (CP-BACK-10).
 * RF-090 / RF-091 / decision 16 (outbox) - reportes por periodo.
 */
final class AuditValidator
{
    /** @var list<string> */
    public const ACCIONES_OPERACION = ['creacion', 'modificacion', 'eliminacion', 'solicitud'];

    /** @var list<string> */
    public const ACCIONES_PII = ['consulta', 'creacion', 'modificacion', 'exportacion'];

    /** @var list<string> */
    public const ESTADOS_EVENTO = ['pendiente', 'procesado', 'fallido'];

    public function __construct(
        private readonly InventarioValidator $base = new InventarioValidator(),
    ) {
    }

    /** @return array<string,int|string|null> */
    public function filtrosOperaciones(array $q): array
    {
        return [
            'usuario_id' => $this->base->entero($q, 'usuario_id', 1, PHP_INT_MAX),
            'accion' => $this->base->enumParam($q, 'accion', self::ACCIONES_OPERACION),
            'entidad' => $this->texto($q, 'entidad', 100),
            'entidad_id' => $this->base->entero($q, 'entidad_id', 1, PHP_INT_MAX),
        ] + $this->fechas($q) + $this->base->paginacion($q);
    }

    /** @return array<string,int|string|null> */
    public function filtrosPii(array $q): array
    {
        return [
            'usuario_id' => $this->base->entero($q, 'usuario_id', 1, PHP_INT_MAX),
            'accion' => $this->base->enumParam($q, 'accion', self::ACCIONES_PII),
            'paciente_id' => $this->base->entero($q, 'paciente_id', 1, PHP_INT_MAX),
            'prescription_id' => $this->base->entero($q, 'prescription_id', 1, PHP_INT_MAX),
        ] + $this->fechas($q) + $this->base->paginacion($q);
    }

    /** @return array<string,int|string|null> */
    public function filtrosEventos(array $q): array
    {
        return [
            'estado' => $this->base->enumParam($q, 'estado', self::ESTADOS_EVENTO),
            'tipo_evento' => $this->texto($q, 'tipo_evento', 100),
            'agregado_tipo' => $this->texto($q, 'agregado_tipo', 50),
        ] + $this->fechas($q) + $this->base->paginacion($q);
    }

    /** @return array{desde:?string,hasta:?string} */
    private function fechas(array $q): array
    {
        $desde = $this->fecha($q, 'desde');
        $hasta = $this->fecha($q, 'hasta');
        if ($desde !== null && $hasta !== null && $hasta < $desde) {
            throw new AppException('VALIDATION_ERROR', "El filtro 'hasta' no puede ser anterior a 'desde'.", 400);
        }
        return ['desde' => $desde, 'hasta' => $hasta];
    }

    private function texto(array $q, string $key, int $max): ?string
    {
        if (!isset($q[$key]) || $q[$key] === '' || $q[$key] === null) {
            return null;
        }
        if (!is_string($q[$key])) {
            throw new AppException('VALIDATION_ERROR', "Filtro '{$key}' invalido.", 400);
        }
        $v = trim($q[$key]);
        if ($v === '' || mb_strlen($v) > $max) {
            throw new AppException('VALIDATION_ERROR', "Filtro '{$key}' invalido.", 400);
        }
        return $v;
    }

    private function fecha(array $q, string $key): ?string
    {
        if (!isset($q[$key]) || $q[$key] === '' || $q[$key] === null) {
            return null;
        }
        if (!is_string($q[$key])) {
            throw new AppException('VALIDATION_ERROR', "Filtro '{$key}' invalido (YYYY-MM-DD).", 400);
        }
        $v = trim($q[$key]);
        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $v);
        if ($dt === false || $dt->format('Y-m-d') !== $v) {
            throw new AppException('VALIDATION_ERROR', "Filtro '{$key}' invalido (YYYY-MM-DD).", 400);
        }
        return $v;
    }
}
