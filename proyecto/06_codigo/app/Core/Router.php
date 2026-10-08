<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Audit;

final class Router
{
    /** @var array<string,array{0:callable,1:?string}> clave "METHOD path" => [handler, permission] */
    private array $routes = [];

    /**
     * @param string|null $permission null = publica; '' = requiere token;
     *                                clave = requiere permiso RBAC (decidido en CP-BACK-02)
     */
    public function add(string $method, string $path, callable $handler, ?string $permission = null): void
    {
        $this->routes[strtoupper($method) . ' ' . $path] = [$handler, $permission];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        $route = $this->routes[$method . ' ' . $path] ?? null;
        if ($route !== null) {
            [$handler, $permission] = $route;
            Auth::guard($permission);
            $this->auditar($method, $path, []);
            $handler([]);
            return;
        }

        // Rutas con path params: /api/v1/catalog/categories/{id}
        foreach ($this->routes as $routeKey => [$routeHandler, $routePermission]) {
            [$m, $pattern] = explode(' ', $routeKey, 2);
            if ($m !== $method || !str_contains($pattern, '{')) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
            if (preg_match($regex, $path, $matches)) {
                Auth::guard($routePermission);
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $this->auditar($method, $pattern, $params);
                $routeHandler($params);
                return;
            }
        }

        Response::error('NOT_FOUND', 'Recurso no encontrado.', 404);
    }

    /**
     * RF-090: cada operacion critica (mutacion) queda registrada antes de
     * ejecutarse; si la auditoria no puede escribirse, la operacion no se
     * realiza (no hay acciones criticas sin registro).
     *
     * `valores_antes` queda en NULL: el router no conoce el estado previo de la
     * entidad. Verificado como limitacion del CP-BACK-10.
     */
    private function auditar(string $method, string $pattern, array $params): void
    {
        if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            return;
        }
        $body = [];
        if ($method !== 'DELETE') {
            try {
                $body = Request::jsonBody();
            } catch (AppException) {
                $body = [];
            }
        }
        $motivo = null;
        foreach (['motivo', 'motivo_baja', 'observacion'] as $k) {
            if (isset($body[$k]) && is_string($body[$k])) {
                $motivo = $body[$k];
                break;
            }
        }
        Audit::operacion(
            match ($method) {
                'POST' => 'creacion',
                'PUT' => 'modificacion',
                default => 'eliminacion',
            },
            $pattern,
            isset($params['id']) && preg_match('/^\d+$/', (string) $params['id']) === 1 ? (int) $params['id'] : null,
            null,
            $body === [] ? null : $body,
            $motivo
        );
    }
}
