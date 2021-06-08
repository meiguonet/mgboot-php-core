<?php

namespace mgboot\core\mvc;

use mgboot\trait\MapAbleTrait;

class HandlerFuncArgInfo
{
    use MapAbleTrait;

    private string $name = '';
    private string $type = '';
    private bool $nullable = false;
    private bool $request = false;
    private bool $jwt = false;
    private bool $clientIp = false;
    private string $httpHeaderName = '';
    private string $jwtClaimName = '';
    private string $pathVariableName = '';
    private string $requestParamName = '';
    private bool $paramMap = false;
    private array $paramMapRules = [];
    private bool $uploadedFile = false;
    private string $formFieldName = '';
    private bool $needRequestBody = false;
    private string $dtoClassName = '';

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
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return bool
     */
    public function isNullable(): bool
    {
        return $this->nullable;
    }

    /**
     * @return bool
     */
    public function isRequest(): bool
    {
        return $this->request;
    }

    /**
     * @return bool
     */
    public function isJwt(): bool
    {
        return $this->jwt;
    }

    /**
     * @return bool
     */
    public function isClientIp(): bool
    {
        return $this->clientIp;
    }

    /**
     * @return string
     */
    public function getHttpHeaderName(): string
    {
        return $this->httpHeaderName;
    }

    /**
     * @return string
     */
    public function getJwtClaimName(): string
    {
        return $this->jwtClaimName;
    }

    /**
     * @return string
     */
    public function getPathVariableName(): string
    {
        return $this->pathVariableName;
    }

    /**
     * @return string
     */
    public function getRequestParamName(): string
    {
        return $this->requestParamName;
    }

    /**
     * @return bool
     */
    public function isParamMap(): bool
    {
        return $this->paramMap;
    }

    /**
     * @return array
     */
    public function getParamMapRules(): array
    {
        return $this->paramMapRules;
    }

    /**
     * @return bool
     */
    public function isUploadedFile(): bool
    {
        return $this->uploadedFile;
    }

    /**
     * @return string
     */
    public function getFormFieldName(): string
    {
        return $this->formFieldName;
    }

    /**
     * @return bool
     */
    public function isNeedRequestBody(): bool
    {
        return $this->needRequestBody;
    }

    /**
     * @return string
     */
    public function getDtoClassName(): string
    {
        return $this->dtoClassName;
    }
}
