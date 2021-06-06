<?php

namespace mgboot\core\http\server;

use mgboot\common\Cast;

final class UploadedFile
{
    private string $formFieldName;
    private string $clientFilename;
    private string $clientMediaType;
    private string $tempFilePath = '';
    private int $error;

    private function __construct(string $formFieldName, ?array $meta)
    {
        if (empty($meta)) {
            $meta = [];
        }

        $this->formFieldName = $formFieldName;
        $this->clientFilename = Cast::toString($meta['name']);
        $this->clientMediaType = Cast::toString($meta['type']);
        $error = Cast::toInt($meta['error']);
        $this->error = $error;
        $filepath = Cast::toString($meta['tmp_name']);

        if ($error !== UPLOAD_ERR_OK || empty($filepath) || !is_file($filepath)) {
            return;
        }

        $this->tempFilePath = $filepath;
    }

    private function __clone()
    {
    }

    public static function create(string $formFieldName, ?array $meta = null): self
    {
        return new self($formFieldName, $meta);
    }

    public function moveTo(string $dstPath): void
    {
        $srcPath = $this->tempFilePath;

        if ($srcPath === '' || !is_file($srcPath)) {
            return;
        }

        try {
            copy($srcPath, $dstPath);
        } finally {
            unlink($srcPath);
        }
    }

    /**
     * @return string
     */
    public function getFormFieldName(): string
    {
        return $this->formFieldName;
    }

    /**
     * @return string
     */
    public function getClientFilename(): string
    {
        return $this->clientFilename;
    }

    /**
     * @return string
     */
    public function getClientMediaType(): string
    {
        $mimeType = $this->clientMediaType;

        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        return $mimeType;
    }

    /**
     * @return string
     */
    public function getTempFilePath(): string
    {
        return $this->tempFilePath;
    }

    /**
     * @return int
     */
    public function getError(): int
    {
        return $this->error;
    }
}
