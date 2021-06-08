<?php

namespace mgboot\core\mvc;


use mgboot\trait\MapAbleTrait;

final class RouteRule
{
    use MapAbleTrait;

    private string $httpMethod = 'GET';
    private string $requestMapping = '';
    private string $handler = '';
    private string $jwtSettingsKey = '';

    /**
     * @var string[]
     */
    private array $validateRules = [];

    private bool $failfast = false;

    /**
     * @var string[]
     */
    private array $extraAnnotations = [];

    /**
     * @var HandlerFuncArgInfo[]
     */
    private array $handlerFuncArgs = [];

    private function __construct(?array $data = null)
    {
        if (empty($data)) {
            return;
        }

        $this->fromMap($data);
    }

    private function __clone()
    {
    }

    public static function create(?array $data = null): self
    {
        return new self($data);
    }

    /**
     * @return string
     */
    public function getHttpMethod(): string
    {
        return $this->httpMethod;
    }

    /**
     * @param string $httpMethod
     * @return RouteRule
     */
    public function setHttpMethod(string $httpMethod): self
    {
        $this->httpMethod = $httpMethod;
        return $this;
    }

    /**
     * @return string
     */
    public function getRequestMapping(): string
    {
        return $this->requestMapping;
    }

    /**
     * @param string $requestMapping
     * @return RouteRule
     */
    public function setRequestMapping(string $requestMapping): self
    {
        $this->requestMapping = $requestMapping;
        return $this;
    }

    /**
     * @return string
     */
    public function getHandler(): string
    {
        return $this->handler;
    }

    /**
     * @param string $handler
     * @return RouteRule
     */
    public function setHandler(string $handler): self
    {
        $this->handler = $handler;
        return $this;
    }

    /**
     * @return string
     */
    public function getJwtSettingsKey(): string
    {
        return $this->jwtSettingsKey;
    }

    /**
     * @param string $jwtSettingsKey
     * @return RouteRule
     */
    public function setJwtSettingsKey(string $jwtSettingsKey): self
    {
        $this->jwtSettingsKey = $jwtSettingsKey;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getValidateRules(): array
    {
        return $this->validateRules;
    }

    /**
     * @param array $validateRules
     * @return RouteRule
     */
    public function setValidateRules(array $validateRules): self
    {
        $this->validateRules = $validateRules;
        return $this;
    }

    /**
     * @return bool
     */
    public function isFailfast(): bool
    {
        return $this->failfast;
    }

    /**
     * @param bool $failfast
     * @return RouteRule
     */
    public function setFailfast(bool $failfast): self
    {
        $this->failfast = $failfast;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getExtraAnnotations(): array
    {
        return $this->extraAnnotations;
    }

    /**
     * @param string[] $extraAnnotations
     */
    public function setExtraAnnotations(array $extraAnnotations): void
    {
        $this->extraAnnotations = $extraAnnotations;
    }

    /**
     * @return HandlerFuncArgInfo[]
     */
    public function getHandlerFuncArgs(): array
    {
        return $this->handlerFuncArgs;
    }

    /**
     * @param HandlerFuncArgInfo[] $handlerFuncArgs
     * @return RouteRule
     */
    public function setHandlerFuncArgs(array $handlerFuncArgs): self
    {
        $this->handlerFuncArgs = $handlerFuncArgs;
        return $this;
    }
}
