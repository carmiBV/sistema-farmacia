<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Response;
use App\Core\Database;

final class HealthController
{
    public function check(): void
    {
        try {
            Database::pdo()->query('SELECT 1');
        } catch (\Throwable) {
            Response::error('DATABASE_ERROR', 'Base de datos no disponible.', 503);
            return;
        }

        Response::json([
            'success' => true,
            'data' => [
                'status' => 'ok',
                'database' => 'up',
                'time' => gmdate('c'),
            ],
        ]);
    }
}
