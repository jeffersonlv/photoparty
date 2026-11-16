<?php

namespace App\Services\Photos;

use Illuminate\Http\UploadedFile;

/**
 * Validação e reencode das imagens enviadas pelo convidado (OWASP A05).
 *
 * 1. Tipo real pelo conteúdo: finfo (MIME) e getimagesize (cabeçalho de imagem) precisam
 *    concordar e estar em JPEG/PNG/WebP. Extensão e Content-Type do cliente são ignorados.
 * 2. Dimensões coerentes com a orientação (foto) ou lado maior ≤ 400 px (miniatura).
 * 3. Reencode com GD para JPEG: só os pixels sobrevivem. EXIF/GPS, perfis, comentários e
 *    qualquer conteúdo embutido (ex.: PHP anexado ao fim do arquivo) são descartados.
 */
class ImageSanitizer
{
    public const ALLOWED = [
        'image/jpeg' => IMAGETYPE_JPEG,
        'image/png' => IMAGETYPE_PNG,
        'image/webp' => IMAGETYPE_WEBP,
    ];

    public const SIZES = [
        'portrait' => [1080, 1350],
        'landscape' => [1920, 1080],
    ];

    public const THUMB_MAX_SIDE = 400;

    public const PHOTO_QUALITY = 85;

    public const THUMB_QUALITY = 80;

    /**
     * Valida a foto final. Devolve a chave de tradução do erro ou null.
     */
    public function photoError(UploadedFile $file, string $orientation): ?string
    {
        $info = $this->inspect($file);

        if ($info === null) {
            return 'upload.errors.not_image';
        }

        [$width, $height] = $info;
        [$expectedW, $expectedH] = self::SIZES[$orientation] ?? [0, 0];

        return $width === $expectedW && $height === $expectedH ? null : 'upload.errors.photo_dimensions';
    }

    /**
     * Valida a miniatura: lado maior ≤ 400 px e mesma orientação da foto.
     */
    public function thumbError(UploadedFile $file, string $orientation): ?string
    {
        $info = $this->inspect($file);

        if ($info === null) {
            return 'upload.errors.not_image';
        }

        [$width, $height] = $info;

        if (max($width, $height) > self::THUMB_MAX_SIDE || min($width, $height) < 1) {
            return 'upload.errors.thumb_dimensions';
        }

        $isPortrait = $height > $width;

        return ($orientation === 'portrait') === $isPortrait ? null : 'upload.errors.thumb_dimensions';
    }

    /**
     * Reencoda para JPEG (bytes). Lança RuntimeException se o GD não conseguir decodificar.
     */
    public function toJpeg(UploadedFile $file, int $quality): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if ($source === false) {
            throw new \RuntimeException('GD não conseguiu decodificar a imagem.');
        }

        $width = imagesx($source);
        $height = imagesy($source);

        // Canvas novo em truecolor com fundo branco (PNG/WebP com transparência).
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagecopy($canvas, $source, 0, 0, 0, 0, $width, $height);
        imagedestroy($source);

        imageinterlace($canvas, true);

        ob_start();
        $ok = imagejpeg($canvas, null, $quality);
        $bytes = (string) ob_get_clean();
        imagedestroy($canvas);

        if (! $ok || $bytes === '') {
            throw new \RuntimeException('GD não conseguiu gerar o JPEG.');
        }

        return $bytes;
    }

    /**
     * [largura, altura] se o arquivo for imagem permitida (finfo e getimagesize de acordo).
     *
     * @return array{0:int,1:int}|null
     */
    private function inspect(UploadedFile $file): ?array
    {
        $path = $file->getRealPath();

        if ($path === false || ! is_file($path) || filesize($path) === 0) {
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        if (! is_string($mime) || ! array_key_exists($mime, self::ALLOWED)) {
            return null;
        }

        $size = @getimagesize($path);

        if ($size === false || ($size[2] ?? null) !== self::ALLOWED[$mime]) {
            return null;
        }

        return [(int) $size[0], (int) $size[1]];
    }
}
