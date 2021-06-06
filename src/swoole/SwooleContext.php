<?php

namespace mgboot\core\swoole;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use mgboot\common\ArrayUtils;
use mgboot\common\Swoole;
use mgboot\core\MgBoot;
use mgboot\core\mvc\RouteRule;
use mgboot\core\mvc\RouteRulesBuilder;
use function FastRoute\cachedDispatcher;
use function FastRoute\simpleDispatcher;

final class SwooleContext
{
    private static array $routeRules = [];
    private static array $routeDispatchers = [];

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
     * @return RouteRule[]
     */
    public static function getRouteRules(): array
    {
        $mapKey = MgBoot::buildGlobalVarKey();
        $routeRules = self::$routeRules[$mapKey];

        if (!ArrayUtils::isList($routeRules) || empty($routeRules)) {
            $routeRules = RouteRulesBuilder::buildRouteRules();

            if (!empty($routeRules)) {
                self::$routeRules[$mapKey] = $routeRules;
            }
        }

        return $routeRules;
    }

    public static function buildRouteDispatcher(bool $enableCache = false, string $cacheDir = ''): void
    {
        $mapKey = MgBoot::buildGlobalVarKey();

        if ($cacheDir === '' || !is_dir($cacheDir) || !is_writable($cacheDir)) {
            $cacheDir = '';
        }

        $routeRules = self::getRouteRules();

        if (!$enableCache || $cacheDir === '') {
            self::$routeDispatchers[$mapKey] = simpleDispatcher(function (RouteCollector $r) use ($routeRules) {
                foreach ($routeRules as $rule) {
                    switch ($rule->getHttpMethod()) {
                        case 'GET':
                            $r->get($rule->getRequestMapping(), $rule->getHandler());
                            break;
                        case 'POST':
                            $r->post($rule->getRequestMapping(), $rule->getHandler());
                            break;
                        case 'PUT':
                            $r->put($rule->getRequestMapping(), $rule->getHandler());
                            break;
                        case 'PATCH':
                            $r->patch($rule->getRequestMapping(), $rule->getHandler());
                            break;
                        case 'DELETE':
                            $r->delete($rule->getRequestMapping(), $rule->getHandler());
                            break;
                        default:
                            $r->get($rule->getRequestMapping(), $rule->getHandler());
                            $r->post($rule->getRequestMapping(), $rule->getHandler());
                            break;
                    }
                }
            });

            return;
        }

        $workerId = Swoole::getWorkerId();

        if ($workerId >= 0) {
            $cacheFile = $cacheDir . "/fastroute.$workerId.dat";
        } else {
            $cacheFile = $cacheDir . '/fastroute.dat';
        }

        if (is_file($cacheFile)) {
            unlink($cacheFile);
        }

        self::$routeDispatchers[$mapKey] = cachedDispatcher(function (RouteCollector $r) use ($routeRules) {
            foreach ($routeRules as $rule) {
                switch ($rule->getHttpMethod()) {
                    case 'GET':
                        $r->get($rule->getRequestMapping(), $rule->getHandler());
                        break;
                    case 'POST':
                        $r->post($rule->getRequestMapping(), $rule->getHandler());
                        break;
                    case 'PUT':
                        $r->put($rule->getRequestMapping(), $rule->getHandler());
                        break;
                    case 'PATCH':
                        $r->patch($rule->getRequestMapping(), $rule->getHandler());
                        break;
                    case 'DELETE':
                        $r->delete($rule->getRequestMapping(), $rule->getHandler());
                        break;
                    default:
                        $r->get($rule->getRequestMapping(), $rule->getHandler());
                        $r->post($rule->getRequestMapping(), $rule->getHandler());
                        break;
                }
            }
        }, compact('cacheFile'));
    }

    public static function getRouteDispatcher(): ?Dispatcher
    {
        $mapKey = MgBoot::buildGlobalVarKey();
        $dispatcher = self::$routeDispatchers[$mapKey];
        return $dispatcher instanceof Dispatcher ? $dispatcher : null;
    }
}
