<?php
declare(strict_types=1);

namespace App\Controllers\Web;

/**
 * Páginas web (Views PHP en app/Views/). Fuera del alcance de la API: estas
 * rutas devuelven HTML; la API vive en routes/api.php con controladores en
 * app/Controllers/Api/. Sin SQL, sin CRUD y sin validaciones de negocio.
 */
final class PageController
{
    private const VISTAS = __DIR__ . '/../../Views';

    /** @param string $vista nombre de la vista en app/Views/ */
    public function render(string $vista, string $titulo, bool $conSelector = false): void
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        if ($vista === 'login') {
            require self::VISTAS . '/login.php';
            return;
        }

        $script = $vista;
        $actual = $path;
        $contenido = self::VISTAS . '/' . $vista . '.php';
        require self::VISTAS . '/layouts/app.php';
    }

    /** 404 HTML para rutas web inexistentes (la API responde JSON). */
    public static function noEncontrado(): void
    {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '404 Not Found';
    }
}
