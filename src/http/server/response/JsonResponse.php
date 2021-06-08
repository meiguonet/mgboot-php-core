<?php

namespace mgboot\core\http\server\response;

use mgboot\core\exception\HttpError;
use mgboot\util\JsonUtils;
use mgboot\util\StringUtils;
use Throwable;

final class JsonResponse implements ResponsePayload
{
    private mixed $payload = null;

    private function __construct(mixed $payload = null)
    {
        if ($payload === null) {
            return;
        }

        $this->payload = $payload;
    }

    private function __clone()
    {
    }

    public static function withPayload(mixed $payload): self
    {
        return new self($payload);
    }

    public function getContentType(): string
    {
        return 'application/json; charset=utf-8';
    }

    public function getContents(): string|HttpError
    {
        $payload = $this->payload;

        if (is_string($payload)) {
            return StringUtils::isJson($payload) ? $payload : HttpError::create(400);
        }

        if (is_array($payload)) {
            $json = JsonUtils::toJson($payload);
            return StringUtils::isJson($json) ? $json : HttpError::create(400);
        }

        if (is_object($payload)) {
            if (method_exists($payload, 'toMap')) {
                try {
                    $json = JsonUtils::toJson($payload->toMap());
                } catch (Throwable) {
                    $json = '';
                }

                if (StringUtils::isJson($json)) {
                    return $json;
                }
            }

            $map1 = get_object_vars($payload);

            if (!empty($map1)) {
                $json = JsonUtils::toJson($map1);

                if (StringUtils::isJson($json)) {
                    return $json;
                }
            }
        }

        return HttpError::create(400);
    }
}
