<?php

namespace Tests\Feature\Routes;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteIntegrityTest extends TestCase
{
    public function test_all_registered_routes_have_valid_callable_actions(): void
    {
        $routes = Route::getRoutes();
        $deadRoutes = [];

        foreach ($routes as $route) {
            $action = $route->getAction();

            if (isset($action['controller'])) {
                $controller = $action['controller'];
                if (is_string($controller) && str_contains($controller, '@')) {
                    [$class, $method] = explode('@', $controller);
                    if (! class_exists($class)) {
                        $deadRoutes[] = "Class does not exist: {$class} for route {$route->uri()}";
                    } elseif (! method_exists($class, $method)) {
                        $deadRoutes[] = "Method does not exist: {$class}@{$method} for route {$route->uri()}";
                    }
                }
            }
        }

        $this->assertEmpty($deadRoutes, "Found dead routes in application:\n".implode("\n", $deadRoutes));
    }
}
