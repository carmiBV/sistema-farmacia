<?php
declare(strict_types=1);

namespace App\Services;

use App\Http\AppException;
use App\Repositories\AuditRepository;
use App\Validators\AuditValidator;

/**
 * Auditoria de operaciones, accesos PII y eventos salientes (CP-BACK-10).
 *
 * RF-090: consultas sobre audit_operations (usuario, fecha, motivo, valores).
 * RF-091 / RN-13: consultas sobre audit_pii_access (una fila por acceso).
 * Decision 16: outbox_events desacopla el envio de eventos salientes; este
 * servicio expone el ciclo de vida del consumidor (pendiente -> procesado|fallido
 * -> pendiente), nunca borra eventos (RN-10).
 */
final class AuditService
{
    public function __construct(
        private readonly AuditRepository $repo = new AuditRepository(),
        private readonly AuditValidator $validator = new AuditValidator(),
    ) {
    }

    /** @return array{data:list<array<string,mixed>>,meta:array{total:int,page:int,limit:int}} */
    public function listarOperaciones(array $q): array
    {
        return $this->listar($q, 'filtrosOperaciones', 'listarOperaciones', 'contarOperaciones');
    }

    /** @return array<string,mixed> */
    public function obtenerOperacion(string $rawId): array
    {
        $r = $this->repo->buscarOperacion($this->idEntero($rawId));
        if ($r === null) {
            throw new AppException('AUDIT_ENTRY_NOT_FOUND', 'Registro de auditoria no encontrado.', 404);
        }
        return $r;
    }

    /** @return array{data:list<array<string,mixed>>,meta:array{total:int,page:int,limit:int}} */
    public function listarAccesosPii(array $q): array
    {
        return $this->listar($q, 'filtrosPii', 'listarAccesosPii', 'contarAccesosPii');
    }

    /** @return array<string,mixed> */
    public function obtenerAccesoPii(string $rawId): array
    {
        $r = $this->repo->buscarAccesoPii($this->idEntero($rawId));
        if ($r === null) {
            throw new AppException('AUDIT_ENTRY_NOT_FOUND', 'Registro de auditoria no encontrado.', 404);
        }
        return $r;
    }

    /** @return array{data:list<array<string,mixed>>,meta:array{total:int,page:int,limit:int}} */
    public function listarEventos(array $q): array
    {
        return $this->listar($q, 'filtrosEventos', 'listarEventos', 'contarEventos');
    }

    /** @return array<string,mixed> */
    public function obtenerEvento(string $rawId): array
    {
        $r = $this->repo->buscarEvento($this->idEntero($rawId));
        if ($r === null) {
            throw new AppException('EVENT_NOT_FOUND', 'Evento no encontrado.', 404);
        }
        return $r;
    }

    /** pendiente -> procesado (el consumidor entrego el evento). */
    public function procesarEvento(string $rawId): array
    {
        return $this->transicion($rawId, 'procesar', 'EVENT_NOT_PENDING');
    }

    /** pendiente -> fallido, acumulando intentos. */
    public function fallarEvento(string $rawId): array
    {
        return $this->transicion($rawId, 'fallar', 'EVENT_NOT_PENDING');
    }

    /** fallido -> pendiente (reintento del consumidor). */
    public function reintentarEvento(string $rawId): array
    {
        return $this->transicion($rawId, 'reintentar', 'EVENT_NOT_FAILED');
    }

    // ------------------------------------------------------------ privados

    private function transicion(string $rawId, string $operacion, string $codigoError): array
    {
        $id = $this->idEntero($rawId);
        try {
            $ok = match ($operacion) {
                'procesar' => $this->repo->marcarProcesado($id),
                'fallar' => $this->repo->marcarFallido($id),
                default => $this->repo->reintentar($id),
            };
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        if (!$ok) {
            if ($this->repo->buscarEvento($id) === null) {
                throw new AppException('EVENT_NOT_FOUND', 'Evento no encontrado.', 404);
            }
            throw new AppException($codigoError, 'El evento no esta en el estado requerido.', 409);
        }
        return $this->repo->buscarEvento($id) ?? throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
    }

    /**
     * @return array{data:list<array<string,mixed>>,meta:array{total:int,page:int,limit:int}}
     */
    private function listar(array $q, string $validar, string $leer, string $contar): array
    {
        $f = $this->validator->{$validar}($q);
        $page = $f['page'];
        $limit = $f['limit'];
        try {
            $data = $this->repo->{$leer}($f, $limit, ($page - 1) * $limit);
            $total = $this->repo->{$contar}($f);
        } catch (\PDOException) {
            throw new AppException('DATABASE_ERROR', 'Error interno.', 500);
        }
        return ['data' => $data, 'meta' => ['total' => $total, 'page' => $page, 'limit' => $limit]];
    }

    private function idEntero(string $raw): int
    {
        $v = trim($raw);
        if ($v === '' || preg_match('/^-?\d+$/', $v) !== 1 || (int) $v < 1) {
            throw new AppException('VALIDATION_ERROR', 'Identificador invalido.', 400);
        }
        return (int) $v;
    }
}
