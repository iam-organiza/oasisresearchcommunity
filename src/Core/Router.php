<?php

namespace App\Core;

use App\Helpers\ResponseHelper;
use FastRoute\RouteCollector;

class Router
{
    public static function dispatch()
    {
        $dispatcher = \FastRoute\simpleDispatcher(function (RouteCollector $r) {
            require __DIR__ . '/../routes/api.php';
        });

        $httpMethod = $_SERVER['REQUEST_METHOD'];
        $uri = $_SERVER['REQUEST_URI'];

        if (false !== $pos = strpos($uri, '?')) {
            $uri = substr($uri, 0, $pos);
        }

        $uri = rawurldecode($uri);
        $routeInfo = $dispatcher->dispatch($httpMethod, $uri);

        switch ($routeInfo[0]) {
            case \FastRoute\Dispatcher::NOT_FOUND:
                if (self::isApiRequest()) {
                    ResponseHelper::notFound();
                } else {
                    http_response_code(404);
                    header('Content-Type: text/html; charset=utf-8');
                    $notFoundPage = __DIR__ . '/../../public/orc/error-404.html';
                    if (file_exists($notFoundPage)) {
                        readfile($notFoundPage);
                    } else {
                        echo "<h1>404 Not Found</h1>";
                    }
                }
                break;
            case \FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                ResponseHelper::methodNotAllowed();
                break;
            case \FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];
                [$class, $method] = $handler;
                call_user_func_array([new $class, $method], $vars);
                break;
        }
    }

    protected static function isApiRequest(): bool
    {
        return str_starts_with($_SERVER['REQUEST_URI'], '/api/')
            || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));
    }
}
