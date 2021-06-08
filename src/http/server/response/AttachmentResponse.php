<?php

namespace mgboot\core\http\server\response;

use mgboot\core\exception\HttpError;
use mgboot\trait\MapAbleTrait;
use mgboot\util\FileUtils;

final class AttachmentResponse implements ResponsePayload
{
    use MapAbleTrait;

    private string $filepath = '';
    private string $buf = '';
    private string $attachmentFileName = '';

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

    public static function fromFile(string $filepath, string $attachmentFileName): self
    {
        return new self(compact('filepath', 'attachmentFileName'));
    }

    public static function fromBuffer(string $contents, string $attachmentFileName): self
    {
        $buf = $contents;
        return new self(compact('buf', 'attachmentFileName'));
    }

    public function getContentType(): string
    {
        $filepath = $this->filepath;

        if ($filepath === '' || !is_file($filepath)) {
            return 'application/octet-stream';
        }

        $mimeType = FileUtils::getMimeType($filepath, true);

        if (empty($mimeType)) {
            $mimeType = FileUtils::getMimeType($filepath);
        }

        return empty($mimeType) ? 'application/octet-stream' : $mimeType;
    }

    public function getContents(): string|HttpError
    {
        $attachmentFileName = $this->attachmentFileName;

        if (empty($attachmentFileName)) {
            return HttpError::create(400);
        }

        $buf = $this->buf;

        if ($buf !== '') {
            return "@attachment:$attachmentFileName^^^$buf";
        }

        $filepath = $this->filepath;

        if ($filepath === '' || !is_file($filepath)) {
            return HttpError::create(400);
        }

        return "@attachment:$attachmentFileName^^^file://$filepath";
    }
}
