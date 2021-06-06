<?php

namespace mgboot\core;

use FastRoute\Dispatcher;
use mgboot\common\ArrayUtils;
use mgboot\common\StringUtils;
use mgboot\common\Swoole;
use mgboot\core\exception\AccessTokenExpiredException;
use mgboot\core\exception\AccessTokenExpiredExceptionHandler;
use mgboot\core\exception\AccessTokenInvalidException;
use mgboot\core\exception\AccessTokenInvalidExceptionHandler;
use mgboot\core\exception\DataValidateException;
use mgboot\core\exception\DataValidateExceptionHandler;
use mgboot\core\exception\ExceptionHandler;
use mgboot\core\exception\HttpError;
use mgboot\core\exception\RequireAccessTokenException;
use mgboot\core\exception\RequireAccessTokenExceptionHandler;
use mgboot\core\fpm\FpmContext;
use mgboot\core\http\middleware\DataValidateMiddleware;
use mgboot\core\http\middleware\ExecuteTimeLogMiddleware;
use mgboot\core\http\middleware\JwtAuthMiddleware;
use mgboot\core\http\middleware\Middleware;
use mgboot\core\http\middleware\RequestLogMiddleware;
use mgboot\core\http\server\Request;
use mgboot\core\http\server\RequestHandler;
use mgboot\core\http\server\Response;
use mgboot\core\http\server\response\JsonResponse;
use mgboot\core\logger\NoopLogger;
use mgboot\core\mvc\RouteRule;
use mgboot\core\security\CorsSettings;
use mgboot\core\security\JwtSettings;
use mgboot\core\swoole\SwooleContext;
use Psr\Log\LoggerInterface;
use Throwable;

final class MgBoot
{
    private static string $controllerDir = '';
    private static ?LoggerInterface $runtimeLogger = null;
    private static bool $requestLogEnabled = false;
    private static ?LoggerInterface $requestLogLogger = null;
    private static bool $executeTimeLogEnabled = false;
    private static ?LoggerInterface $executeTimeLogLogger = null;
    private static ?CorsSettings $corsSettings = null;
    private static array $jwtSettings = [];
    private static bool $gzipOutputEnabled = true;

    /**
     * @var ExceptionHandler[]
     */
    private static array $exceptionHandlers = [];

    /**
     * @var Middleware[]
     */
    private static array $middlewares = [];

    private function __construct()
    {
    }

    private function __clone()
    {
    }

    public static function handleRequest(Request $request, Response $response): void
    {
        self::ensureBuiltinExceptionHandlersExists();

        if (strtolower($request->getMethod()) === 'options') {
            $response->withPayload(JsonResponse::withPayload(['status' => 200]))->send();
            return;
        }

        if ($request->inSwooleMode()) {
            $dispatcher = SwooleContext::getRouteDispatcher();
        } else {
            $dispatcher = FpmContext::getRouteDispatcher();
        }

        if (!($dispatcher instanceof Dispatcher)) {
            $response->withPayload(HttpError::create(400))->send();
            return;
        }

        $httpMethod = strtoupper($request->getMethod());
        $uri = $request->getRequestUrl();

        if (str_contains($uri, '?')) {
            $uri = substr($uri, 0, strpos($uri, '?'));
        }

        $uri = rawurldecode($uri);

        try {
            list($resultCode, $handlerFunc, $pathVariables) = $dispatcher->dispatch($httpMethod, $uri);

            switch ($resultCode) {
                case Dispatcher::NOT_FOUND:
                    $response->withPayload(HttpError::create(404))->send();
                    break;
                case Dispatcher::METHOD_NOT_ALLOWED:
                    $response->withPayload(HttpError::create(405))->send();
                    break;
                case Dispatcher::FOUND:
                    if (!is_string($handlerFunc) || $handlerFunc === '') {
                        $response->withPayload(HttpError::create(400))->send();
                        return;
                    }

                    if (!self::setRouteRuleToRequest($request, $httpMethod, $handlerFunc)) {
                        $response->withPayload(HttpError::create(400))->send();
                        return;
                    }

                    if (is_array($pathVariables) && !empty($pathVariables)) {
                        $request->withPathVariables($pathVariables);
                    }

                    RequestHandler::create($request, $response)->handleRequest();
                    break;
                default:
                    $response->withPayload(HttpError::create(400))->send();
                    break;
            }
        } catch (Throwable $ex) {
            $response->withPayload($ex)->send();
        }
    }

    public static function scanControllersIn(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) {
            return;
        }

        self::$controllerDir = $dir;
    }

    public static function getControllerDir(): string
    {
        return self::$controllerDir;
    }

    public static function setRuntimeLogger(LoggerInterface $logger): void
    {
        self::$runtimeLogger = $logger;
    }

    public static function getRuntimeLogger(): LoggerInterface
    {
        $logger = self::$runtimeLogger;
        return $logger instanceof LoggerInterface ? $logger : NoopLogger::create();
    }

    public static function enableRequestLog(LoggerInterface $logger): void
    {
        self::$requestLogEnabled = true;
        self::$requestLogLogger = $logger;
    }

    public static function isRequestLogEnabled(): bool
    {
        return self::$requestLogEnabled;
    }

    public static function getRequestLogLogger(): LoggerInterface
    {
        $logger = self::$requestLogLogger;
        return $logger instanceof LoggerInterface ? $logger : NoopLogger::create();
    }

    public static function enableExecuteTimeLog(LoggerInterface $logger): void
    {
        self::$executeTimeLogEnabled = true;
        self::$executeTimeLogLogger = $logger;
    }

    public static function isExecuteTimeLogEnabled(): bool
    {
        return self::$executeTimeLogEnabled;
    }

    public static function getExecuteTimeLogLogger(): LoggerInterface
    {
        $logger = self::$executeTimeLogLogger;
        return $logger instanceof LoggerInterface ? $logger : NoopLogger::create();
    }

    public static function withCorsSettings(CorsSettings $settings): void
    {
        self::$corsSettings = $settings;
    }

    public static function getCorsSettings(): CorsSettings
    {
        $settings = self::$corsSettings;
        return $settings instanceof CorsSettings ? $settings : CorsSettings::create(false);
    }

    public static function withJwtSettings(JwtSettings $settings): void
    {
        $mapKey = self::buildGlobalVarKey();

        if (!ArrayUtils::isList(self::$jwtSettings[$mapKey])) {
            self::$jwtSettings[$mapKey] = [$settings];
            return;
        }

        $idx = -1;

        /* @var JwtSettings $item */
        foreach (self::$jwtSettings[$mapKey] as $i => $item) {
            if ($item->getKey() === $settings->getKey()) {
                $idx = $i;
                break;
            }
        }

        if ($idx < 0) {
            self::$jwtSettings[$mapKey][] = $settings;
        } else {
            self::$jwtSettings[$mapKey][$idx] = $settings;
        }
    }

    public static function getJwtSettings(string $key): ?JwtSettings
    {
        $mapKey = self::buildGlobalVarKey();

        if (!ArrayUtils::isList(self::$jwtSettings[$mapKey])) {
            return null;
        }

        /* @var JwtSettings $settings */
        foreach (self::$jwtSettings[$mapKey] as $settings) {
            if ($settings->getKey() === $key) {
                return $settings;
            }
        }

        return null;
    }

    public static function disableGzipOutput(): void
    {
        self::$gzipOutputEnabled = false;
    }

    public static function isGzipOutputEnabled(): bool
    {
        return self::$gzipOutputEnabled;
    }

    public static function withExceptionHandler(ExceptionHandler $handler): void
    {
        $idx = -1;

        foreach (self::$exceptionHandlers as $i => $item) {
            if ($item->getExceptionClassName() === $handler->getExceptionClassName()) {
                $idx = $i;
                break;
            }
        }

        if ($idx < 0) {
            self::$exceptionHandlers[] = $handler;
        } else {
            self::$exceptionHandlers[$idx] = $handler;
        }
    }

    public static function getExceptionHandler(string $exceptionClassName): ?ExceptionHandler
    {
        $exceptionClassName = StringUtils::ensureLeft($exceptionClassName, "\\");

        foreach (self::$exceptionHandlers as $handler) {
            if (StringUtils::ensureLeft($handler->getExceptionClassName(), "\\") === $exceptionClassName) {
                return $handler;
            }
        }

        return null;
    }

    public static function withMiddleware(Middleware $middleware): void
    {
        self::ensureBuiltinMiddlewaresExists();

        if (self::isMiddlewaresExists(get_class($middleware))) {
            return;
        }

        $list = [];

        foreach (self::$middlewares as $mid) {
            if ($mid->getType() === Middleware::PRE_HANDLE_MIDDLEWARE) {
                $list[] = $mid;
            }
        }

        $list[] = $middleware;

        foreach (self::$middlewares as $mid) {
            if ($mid->getType() === Middleware::PRE_HANDLE_MIDDLEWARE) {
                continue;
            }

            $list[] = $mid;
        }

        self::$middlewares = $list;
    }

    public static function getMiddlewares(): array
    {
        self::ensureBuiltinMiddlewaresExists();
        return self::$middlewares;
    }

    public static function buildGlobalVarKey(): string
    {
        $workerId = Swoole::getWorkerId();
        return $workerId >= 0 ? "worker$workerId" : 'noworker';
    }

    private static function ensureBuiltinExceptionHandlersExists(): void
    {
        $handler = self::getExceptionHandler(AccessTokenExpiredException::class);

        if (!($handler instanceof ExceptionHandler)) {
            self::withExceptionHandler(AccessTokenExpiredExceptionHandler::create());
        }

        $handler = self::getExceptionHandler(AccessTokenInvalidException::class);

        if (!($handler instanceof ExceptionHandler)) {
            self::withExceptionHandler(AccessTokenInvalidExceptionHandler::create());
        }

        $handler = self::getExceptionHandler(DataValidateException::class);

        if (!($handler instanceof ExceptionHandler)) {
            self::withExceptionHandler(DataValidateExceptionHandler::create());
        }

        $handler = self::getExceptionHandler(RequireAccessTokenException::class);

        if (!($handler instanceof ExceptionHandler)) {
            self::withExceptionHandler(RequireAccessTokenExceptionHandler::create());
        }
    }

    private static function ensureBuiltinMiddlewaresExists(): void
    {
        if (!self::isMiddlewaresExists(RequestLogMiddleware::class)) {
            self::$middlewares[] = RequestLogMiddleware::create();
        }

        if (!self::isMiddlewaresExists(JwtAuthMiddleware::class)) {
            self::$middlewares[] = JwtAuthMiddleware::create();
        }

        if (!self::isMiddlewaresExists(DataValidateMiddleware::class)) {
            self::$middlewares[] = DataValidateMiddleware::create();
        }

        if (!self::isMiddlewaresExists(ExecuteTimeLogMiddleware::class)) {
            self::$middlewares[] = ExecuteTimeLogMiddleware::create();
        }
    }

    private static function isMiddlewaresExists(string $middlewareClassName): bool
    {
        $middlewareClassName = StringUtils::ensureLeft($middlewareClassName, "\\");

        foreach (self::$middlewares as $mid) {
            if (StringUtils::ensureLeft(get_class($mid), "\\") === $middlewareClassName) {
                return true;
            }
        }

        return false;
    }

    private static function setRouteRuleToRequest(Request $request, string $httpMethod, string $handlerFunc): bool
    {
        if ($request->inSwooleMode()) {
            $routeRules = SwooleContext::getRouteRules();
        } else {
            $routeRules = FpmContext::getRouteRules();
        }

        $matched = null;

        foreach ($routeRules as $rule) {
            if ($rule->getHttpMethod() === $httpMethod && $rule->getHandler() === $handlerFunc) {
                $matched = $rule;
                break;
            }
        }

        if (!($matched instanceof RouteRule)) {
            foreach ($routeRules as $rule) {
                if ($rule->getHandler() === $handlerFunc && $rule->getHttpMethod() === '') {
                    $matched = $rule;
                    break;
                }
            }
        }

        if ($matched instanceof RouteRule) {
            /** @noinspection PhpUndefinedVariableInspection */
            $request->withRouteRule($rule);
            return true;
        }

        return false;
    }
}
