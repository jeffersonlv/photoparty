<?php

namespace App\Services\Frames;

use RuntimeException;

/**
 * PNG de moldura recusado. A mensagem é a chave em lang/*\/frames.php (errors.*).
 */
class InvalidFrameImage extends RuntimeException
{
    public function reason(): string
    {
        return $this->getMessage();
    }
}
