<?php

namespace mgboot\core\http\server;

use mgboot\common\StringUtils;
use mgboot\core\exception\HttpError;
use mgboot\core\http\middleware\Middleware;
use mgboot\core\MgBoot;
use mgboot\core\mvc\HandlerFuncArgsInjector;
use mgboot\core\mvc\RoutingContext;
use Throwable;

final class RequestHandler
{
    private Request $request;
    private Response $response;

    private function __construct(Request $request, Response $response)
    {
        $this->request = $request;
        $this->response = $response;
    }

    private function __clone()
    {
    }

    public static function create(Request $request, Response $response): self
    {
        return new self($request, $response);
    }

    public function handleRequest(): void
    {
        $stages = [];

        foreach (MgBoot::getMiddlewares() as $mid) {
            if ($mid->getType() !== Middleware::PRE_HANDLE_MIDDLEWARE) {
                continue;
            }

            $stages = function (RoutingContext $rc) use ($mid) {
                $mid->preHandle($rc);
            };
        }

        $request = $this->request;
        $routeRule = $request->getRouteRule();

        $stages[] = function (RoutingContext $rc) use ($routeRule) {
            if (!$rc->next()) {
                return;
            }

            list($clazz, $methodName) = explode('@', $routeRule->getHandler());
            $clazz = StringUtils::ensureLeft($clazz, "\\");

            try {
                $bean = new $clazz();
            } catch (Throwable) {
                $bean = null;
            }

            if (!is_object($bean)) {
                $rc->getResponse()->withPayload(HttpError::create(400));
                $rc->next(false);
                return;
            }

            if (!method_exists($bean, $methodName)) {
                $rc->getResponse()->withPayload(HttpError::create(400));
                $rc->next(false);
                return;
            }

            try {
                $args = HandlerFuncArgsInjector::inject($rc->getRequest());
            } catch (Throwable $ex) {
                $rc->getResponse()->withPayload($ex);
                $rc->next(false);
                return;
            }

            try {
                $payload = empty($args)
                    ? call_user_func([$bean, $methodName])
                    : call_user_func([$bean, $methodName], ...$args);

                $rc->getResponse()->withPayload($payload);
            } catch (Throwable $ex) {
                $rc->getResponse()->withPayload($ex);
                $rc->next(false);
            }
        };

        foreach (MgBoot::getMiddlewares() as $mid) {
            if ($mid->getType() !== Middleware::POST_HANDLE_MIDDLEWARE) {
                continue;
            }

            $stages = function (RoutingContext $rc) use ($mid) {
                $mid->postHandle($rc);
            };
        }

        $response = $this->response;
        $ctx = RoutingContext::create($request, $response);

        foreach ($stages as $stage) {
            try {
                $stage($ctx);
            } catch (Throwable $ex) {
                $response->withPayload($ex);
                break;
            }
        }

        $response->send();
    }
}
