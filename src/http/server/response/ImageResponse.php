<?php

namespace mgboot\core\http\server\response;


use mgboot\core\exception\HttpError;
use mgboot\trait\MapAbleTrait;
use Throwable;

final class ImageResponse implements ResponsePayload
{
    use MapAbleTrait;

    private string $filepath = '';
    private string $buf = '';
    private string $mimeType = '';

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

    public static function fromFile(string $filepath): self
    {
        if (empty($filepath) || !is_file($filepath)) {
            return new self();
        }

        $imageSize = getimagesize($filepath);

        if (!is_array($imageSize) || empty($imageSize)) {
            return new self();
        }

        try {
            $mimeType = image_type_to_mime_type($imageSize[2]);

            if (empty($mimeType)) {
                return new self();
            }

            return new self(compact('filepath', 'mimeType'));
        } catch (Throwable) {
            return new self();
        }
    }

    public static function fromBuffer(string $contents, string $mimeType): self
    {
        $buf = $contents;
        return new self(compact('buf', 'mimeType'));
    }

    public function getContentType(): string
    {
        return $this->mimeType;
    }

    public function getContents(): string|HttpError
    {
        $mimeType = $this->mimeType;

        if (empty($mimeType)) {
            return HttpError::create(400);
        }

        $buf = $this->buf;

        if ($buf !== '') {
            return "@image:$buf";
        }

        $filepath = $this->filepath;

        if ($filepath === '' || !is_file($filepath)) {
            return HttpError::create(400);
        }

        return "@image:file://$filepath";
    }
}
