<?php

namespace mgboot\core\http\server\response;

use mgboot\common\ArrayUtils;
use mgboot\common\JsonUtils;
use mgboot\core\exception\HttpError;
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
            if (str_starts_with($payload, '{') && str_ends_with($payload, '}')) {
                return $payload;
            }

            if (str_starts_with($payload, '[') && str_ends_with($payload, ']')) {
                return $payload;
            }

            return HttpError::create(400);
        }

        if (is_array($payload)) {
            $contents = JsonUtils::toJson($payload);

            if (str_starts_with($contents, '{') && str_ends_with($contents, '}')) {
                return $contents;
            }

            if (str_starts_with($contents, '[') && str_ends_with($contents, ']')) {
                return $contents;
            }

            return HttpError::create(400);
        }

        if (is_object($payload)) {
            if (method_exists($payload, 'toMap')) {
                try {
                    $contents = JsonUtils::toJson($payload->toMap());
                } catch (Throwable) {
                    $contents = '';
                }

                if (str_starts_with($contents, '{') && str_ends_with($contents, '}')) {
                    return $contents;
                }
            }

            $map1 = get_object_vars($payload);

            if (ArrayUtils::isAssocArray($map1)) {
                $contents = JsonUtils::toJson($map1);

                if (str_starts_with($contents, '{') && str_ends_with($contents, '}')) {
                    return $contents;
                }
            }
        }

        return HttpError::create(400);
    }
}
