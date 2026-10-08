<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Error de API con código del contrato OpenAPI (VALIDATION_ERROR, NOT_FOUND,
 * DUPLICATE_NAME, HIERARCHY_CYCLE, DATABASE_ERROR, INTERNAL_ERROR).
 * El Front Controller la captura antes que \Throwable y emite el envelope.
 */
final class AppException extends \RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $status = 500,
    ) {
        parent::__construct($message);
    }
}
