<?php

namespace mgboot\core\fpm;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use mgboot\common\ArrayUtils;
use mgboot\core\mvc\HandlerFuncArgInfo;
use mgboot\core\mvc\RouteRule;
use mgboot\core\mvc\RouteRulesBuilder;
use Throwable;
use function FastRoute\cachedDispatcher;
use function FastRoute\simpleDispatcher;

final class FpmContext
{
    private static bool $routeRulesCacheEnabled = false;
    private static string $routeRulesCacheDir = '';
    private static ?Dispatcher $routeDispatcher = null;

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function enableRouteRulesCache(string $cacheDir): void
    {
        if ($cacheDir === '' || !is_dir($cacheDir) || !is_writable($cacheDir)) {
            return;
        }

        self::$routeRulesCacheEnabled = true;
        self::$routeRulesCacheDir = $cacheDir;
    }

    /**
     * @return RouteRule[]
     */
    public static function getRouteRules(): array
    {
        if (!self::$routeRulesCacheEnabled) {
            return RouteRulesBuilder::buildRouteRules();
        }

        list($success, $routeRules) = self::getRouteRulesFromCacheFile();

        if ($success) {
            return $routeRules;
        }

        $routeRules = RouteRulesBuilder::buildRouteRules();
        self::writeRouteRulesToCacheFile($routeRules);
        return $routeRules;
    }

    public static function buildRouteDispatcher(bool $enableCache = false, string $cacheDir = ''): void
    {
        if ($cacheDir === '' || !is_dir($cacheDir) || !is_writable($cacheDir)) {
            $cacheDir = '';
        }

        $routeRules = self::getRouteRules();

        if (!$enableCache || $cacheDir === '') {
            self::$routeDispatcher = simpleDispatcher(function (RouteCollector $r) use ($routeRules) {
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

        $cacheFile = $cacheDir . '/fastroute.dat';

        self::$routeDispatcher = cachedDispatcher(function (RouteCollector $r) use ($routeRules) {
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
        return self::$routeDispatcher;
    }

    private static function getRouteRulesFromCacheFile(): array
    {
        $cacheFile = self::$routeRulesCacheDir . '/route_rules.php';

        if (!is_file($cacheFile)) {
            return [false, []];
        }

        try {
            $items = include($cacheFile);
        } catch (Throwable) {
            $items = null;
        }

        if (!ArrayUtils::isList($items) || empty($items)) {
            return [false, []];
        }

        $routeRules = [];

        foreach ($items as $item) {
            $item['handlerFuncArgs'] = array_map(fn($it) => HandlerFuncArgInfo::create($it), $item['handlerFuncArgs']);
            $routeRules[] = RouteRule::create($item);
        }

        return $routeRules;
    }

    /**
     * @param RouteRule[] $routeRules
     */
    private static function writeRouteRulesToCacheFile(array $routeRules): void
    {
        if (empty($routeRules)) {
            return;
        }

        $cacheFile = self::$routeRulesCacheDir . '/route_rules.php';
        $fp = fopen($cacheFile, 'w');

        if (!is_resource($fp)) {
            return;
        }

        $items = [];

        foreach ($routeRules as $item) {
            $args = array_map(fn($it) => $it->toMap(), $item->getHandlerFuncArgs());
            $items[] = array_merge($item->toMap(), ['handlerFuncArgs' => $args]);
        }

        $sb = [
            "<?php\n",
            'return ' . var_export($items, true) . ";\n"
        ];

        flock($fp, LOCK_EX);
        fwrite($fp, implode('', $sb));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
