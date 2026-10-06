<?php
declare(strict_types=1);

namespace App\Support;

use App\Http\Auth;
use App\Repositories\AuditRepository;

/**
 * Fachada de auditoria (RF-090 operaciones criticas, RF-091 acceso a PII).
 *
 * RNF-046: solo anexar (INSERT); nunca UPDATE/DELETE.
 * RNF-050: valores_antes/valores_despues NO deben duplicar PII de pacientes;
 *          las claves sensibles se redactan antes de persistir.
 */
final class Audit
{
    /** Claves cuyo valor nunca se persiste en audit_operations (RNF-050). */
    private const SENSIBLES = [
        'password', 'password_hash', 'hash_password', 'password_confirm',
        'token', 'jwt', 'secret', 'api_key', 'apikey',
    ];

    /** PII de pacientes/recetas: se redacta siempre, tambien en su propio recurso. */
    private const PII = [
        'email', 'telefono', 'celular', 'documento', 'numero_documento',
        'identificacion', 'contacto', 'direccion', 'fecha_nacimiento', 'nombre', 'apellido',
    ];

    /**
     * RF-090: registro de una operacion critica.
     *
     * @param array<string,mixed>|null $valoresAntes
     * @param array<string,mixed>|null $valoresDespues
     */
    public static function operacion(
        string $accion,
        string $entidad,
        ?int $entidadId,
        ?array $valoresAntes,
        ?array $valoresDespues,
        ?string $motivo = null,
    ): void {
        $esPii = preg_match('#patient|prescription|pacient|recet#i', $entidad) === 1;
        (new AuditRepository())->insertarOperacion([
            'usuario_id' => self::usuarioId(),
            'accion' => $accion,
            'entidad' => mb_substr($entidad, 0, 100),
            'entidad_id' => $entidadId,
            'valores_antes' => $valoresAntes === null ? null : self::redactar($valoresAntes, $esPii),
            'valores_despues' => $valoresDespues === null ? null : self::redactar($valoresDespues, $esPii),
            'motivo' => $motivo === null ? null : mb_substr($motivo, 0, 500),
        ]);
    }

    /**
     * RF-091 / RN-13: una fila por cada acceso a datos de pacientes o recetas.
     * `accion` es el enum de audit_pii_access.
     */
    public static function pii(string $accion, ?int $pacienteId, ?int $prescriptionId, ?string $motivo = null): void
    {
        if ($pacienteId === null && $prescriptionId === null) {
            return; // chk_apii_objetivo exige al menos un objetivo
        }
        $uid = self::usuarioId();
        if ($uid === null) {
            return; // usuario_id NOT NULL: sin usuario no hay acceso PII que registrar
        }
        (new AuditRepository())->insertarAccesoPii([
            'usuario_id' => $uid,
            'accion' => $accion,
            'paciente_id' => $pacienteId,
            'prescription_id' => $prescriptionId,
            'motivo' => $motivo === null ? null : mb_substr($motivo, 0, 500),
        ]);
    }

    /**
     * Evento saliente en outbox_events, dentro de la transaccion del llamador
     * (decision 16: el envio externo queda desacoplado del commit).
     *
     * @param array<string,mixed> $payload sin PII de pacientes (RNF-050)
     */
    public static function evento(string $agregadoTipo, int $agregadoId, string $tipoEvento, array $payload): int
    {
        return (new AuditRepository())->publicarEvento($agregadoTipo, $agregadoId, $tipoEvento, $payload);
    }

    /**
     * @param array<string,mixed> $v
     * @param bool $esPii redacta tambien nombres y datos identificativos
     * @return array<string,mixed>
     */
    public static function redactar(array $v, bool $esPii = false): array
    {
        foreach ($v as $k => $val) {
            if (is_array($val)) {
                $v[$k] = self::redactar($val, $esPii);
                continue;
            }
            $clave = strtolower((string) $k);
            if (in_array($clave, self::SENSIBLES, true) || ($esPii && in_array($clave, self::PII, true))) {
                $v[$k] = '[REDACTADO]';
            }
        }
        return $v;
    }

    private static function usuarioId(): ?int
    {
        try {
            return (int) Auth::user()['id'];
        } catch (\Throwable) {
            return null;
        }
    }
}
