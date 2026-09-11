<?php

namespace App\Core;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;

use function FastRoute\simpleDispatcher;

class Router
{
    /** @var array<int, array{0: string, 1: string, 2: string}> method, path, "Controller@action" */
    private array $routes = [];

    public function get(string $path, string $handler): void
    {
        $this->routes[] = ['GET', $path, $handler];
    }

    public function post(string $path, string $handler): void
    {
        $this->routes[] = ['POST', $path, $handler];
    }

    /** Merge in the routes registered by an app's routes.php (which receives a Router and calls get()/post() on it). */
    public function include(callable $registerRoutes): void
    {
        $registerRoutes($this);
    }

    public function dispatch(Request $request): Response
    {
        $dispatcher = simpleDispatcher(function (RouteCollector $r) {
            foreach ($this->routes as [$method, $path, $handler]) {
                $r->addRoute($method, $path, $handler);
            }
        });

        $result = $dispatcher->dispatch($request->method, $request->uri);

        return match ($result[0]) {
            Dispatcher::NOT_FOUND => Response::html('Not Found', 404),
            Dispatcher::METHOD_NOT_ALLOWED => Response::html('Method Not Allowed', 405),
            Dispatcher::FOUND => $this->callHandler($result[1], $result[2], $request),
        };
    }

    private function callHandler(string $handler, array $params, Request $request): Response
    {
        [$controllerClass, $action] = explode('@', $handler);
        /** @var Controller $controller */
        $controller = new $controllerClass();

        return $controller->$action($request, ...$params);
    }
}
