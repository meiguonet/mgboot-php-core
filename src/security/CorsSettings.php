<?php

namespace mgboot\core\security;

use mgboot\constant\Regexp;
use mgboot\util\ArrayUtils;
use mgboot\util\StringUtils;

final class CorsSettings
{
    private bool $enabled;
    private array $allowedOrigins = ['*'];

    private array $allowedHeaders = [
        'Content-Type',
        'Content-Length',
        'Authorization',
        'Accept',
        'Accept-Encoding',
        'X-Requested-With'
    ];

    private array $allowedMethods = [
        'GET',
        'POST',
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS'
    ];

    private bool $allowCredentials = false;

    private array $exposedHeaders = [
        'Content-Length',
        'Access-Control-Allow-Origin',
        'Access-Control-Allow-Headers',
        'Cache-Control',
        'Content-Language',
        'Content-Type'
    ];

    private int $maxAge = 0;

    private function __construct(bool $enabled)
    {
        $this->enabled = $enabled;
    }

    private function __clone()
    {
    }

    public static function create(bool $enabled): self
    {
        return new self($enabled);
    }

    public function withAllowedOrigins(array|string $origins): self
    {
        $_origins = [];

        if (ArrayUtils::isStringArray($origins)) {
            $_origins = $origins;
        } else if (is_string($origins) && $origins !== '') {
            $_origins = preg_split(Regexp::COMMA_SEP, trim($origins));
        }

        if (!empty($_origins)) {
            if (in_array('*', $_origins)) {
                $_origins = ['*'];
            }

            $this->allowedOrigins = $_origins;
        }

        return $this;
    }

    public function withAllowedHeaders(array|string $headers): self
    {
        $_headers = [];

        if (ArrayUtils::isStringArray($headers)) {
            $_headers = $headers;
        } else if (is_string($headers) && $headers !== '') {
            $_headers = preg_split(Regexp::COMMA_SEP, trim($headers));
        }

        if (!empty($_headers)) {
            $this->allowedHeaders = $_headers;
        }

        return $this;
    }

    public function withAllowedMethods(array|string $methods): self
    {
        $_methods = [];

        if (ArrayUtils::isStringArray($methods)) {
            $_methods = $methods;
        } else if (is_string($methods) && $methods !== '') {
            $_methods = preg_split(Regexp::COMMA_SEP, trim($methods));
        }

        if (!empty($_methods)) {
            $this->allowedMethods = $_methods;
        }

        return $this;
    }

    public function withCredentials(): self
    {
        $this->allowCredentials = true;
        return $this;
    }

    public function withExposedHeaders(array|string $headers): self
    {
        $_headers = [];

        if (ArrayUtils::isStringArray($headers)) {
            $_headers = $headers;
        } else if (is_string($headers) && $headers !== '') {
            $_headers = preg_split(Regexp::COMMA_SEP, trim($headers));
        }

        if (!empty($_headers)) {
            $this->exposedHeaders = $_headers;
        }

        return $this;
    }

    public function withMaxAge(int|string $maxAge): self
    {
        $_maxAge = 0;

        if (is_int($maxAge) && $maxAge > 0) {
            $_maxAge = $maxAge;
        } else if (is_string($maxAge) && $maxAge !== '') {
            $_maxAge = StringUtils::toDuration($maxAge);
        }

        if ($_maxAge > 0) {
            $this->maxAge = $_maxAge;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @return string[]
     */
    public function getAllowedOrigins(): array
    {
        return $this->allowedOrigins;
    }

    /**
     * @return string[]
     */
    public function getAllowedHeaders(): array
    {
        return $this->allowedHeaders;
    }

    /**
     * @return string[]
     */
    public function getAllowedMethods(): array
    {
        return $this->allowedMethods;
    }

    /**
     * @return bool
     */
    public function isAllowCredentials(): bool
    {
        return $this->allowCredentials;
    }

    /**
     * @return string[]
     */
    public function getExposedHeaders(): array
    {
        return $this->exposedHeaders;
    }

    /**
     * @return int
     */
    public function getMaxAge(): int
    {
        return $this->maxAge;
    }
}
