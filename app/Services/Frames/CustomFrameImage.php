<?php

namespace App\Services\Frames;

use GdImage;
use Illuminate\Http\UploadedFile;

/**
 * Valida e reencoda o PNG de uma moldura personalizada (SPEC 11, OWASP A05).
 *
 * 1. finfo = image/png e getimagesize = PNG com dimensões EXATAS da orientação;
 * 2. canal alfa declarado no IHDR (tipo 4/6, ou paleta com tRNS);
 * 3. decodifica com GD e redesenha numa imagem truecolor nova (alfa preservado):
 *    o PNG gravado é gerado do zero, sem chunks extras (tEXt, iTXt, zTXt, eXIf...) nem
 *    bytes anexados depois do IEND;
 * 4. detecta a bounding box da área transparente (amostragem a cada SAMPLE_STEP px):
 *    vira a "window" do config. Sem transparência ou janela < 30% da imagem => recusa.
 */
class CustomFrameImage
{
    public const SIZES = [
        'portrait' => [1080, 1350],
        'landscape' => [1920, 1080],
    ];

    public const MAX_BYTES = 2 * 1024 * 1024;

    /** alfa do GD vai de 0 (opaco) a 127 (transparente). */
    public const TRANSPARENT_ALPHA = 100;

    public const SAMPLE_STEP = 2;

    public const MIN_WINDOW_RATIO = 0.30;

    /**
     * @return array{png: string, window: array{x:int,y:int,w:int,h:int}}
     *
     * @throws InvalidFrameImage
     */
    public function process(UploadedFile $file, string $orientation): array
    {
        [$width, $height] = self::SIZES[$orientation] ?? throw new InvalidFrameImage('invalid');

        $path = $file->getRealPath();

        if ($path === false || ! is_file($path) || $file->getSize() > self::MAX_BYTES) {
            throw new InvalidFrameImage($file->getSize() > self::MAX_BYTES ? 'too_large' : 'invalid');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        if ($finfo->file($path) !== 'image/png') {
            throw new InvalidFrameImage('not_png');
        }

        $info = @getimagesize($path);

        if ($info === false || $info[2] !== IMAGETYPE_PNG) {
            throw new InvalidFrameImage('not_png');
        }

        if ($info[0] !== $width || $info[1] !== $height) {
            throw new InvalidFrameImage('dimensions');
        }

        if (! $this->declaresAlpha($path)) {
            throw new InvalidFrameImage('no_transparency');
        }

        $source = @imagecreatefrompng($path);

        if (! $source instanceof GdImage || imagesx($source) !== $width || imagesy($source) !== $height) {
            throw new InvalidFrameImage('not_png');
        }

        $clean = $this->redraw($source, $width, $height);
        imagedestroy($source);

        try {
            $window = $this->transparentBox($clean);

            if ($window === null) {
                throw new InvalidFrameImage('no_transparency');
            }

            if ($window['w'] * $window['h'] < self::MIN_WINDOW_RATIO * $width * $height) {
                throw new InvalidFrameImage('window_too_small');
            }

            ob_start();
            imagepng($clean, null, 6);
            $png = (string) ob_get_clean();
        } finally {
            imagedestroy($clean);
        }

        return ['png' => $png, 'window' => $window];
    }

    /**
     * Imagem truecolor nova, totalmente transparente, com o desenho copiado por cima
     * sem mistura (o alfa de cada pixel é copiado como está).
     */
    public function redraw(GdImage $source, int $width, int $height): GdImage
    {
        $clean = imagecreatetruecolor($width, $height);
        imagealphablending($clean, false);
        imagesavealpha($clean, true);
        imagefill($clean, 0, 0, imagecolorallocatealpha($clean, 0, 0, 0, 127));

        if (! imageistruecolor($source)) {
            imagepalettetotruecolor($source);
        }

        imagealphablending($source, false);
        imagecopy($clean, $source, 0, 0, 0, 0, $width, $height);

        return $clean;
    }

    /**
     * Bounding box dos pixels transparentes (alfa >= TRANSPARENT_ALPHA), por amostragem.
     * Linhas/colunas inteiras sem amostra transparente ficam fora; a borda da caixa é
     * estendida até o próximo ponto amostrado (sem passar da imagem).
     *
     * @return array{x:int,y:int,w:int,h:int}|null
     */
    public function transparentBox(GdImage $image, int $step = self::SAMPLE_STEP): ?array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y += $step) {
            $rowMin = -1;
            $rowMax = -1;

            for ($x = 0; $x < $width; $x += $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) >= self::TRANSPARENT_ALPHA) {
                    $rowMin = $x;
                    break;
                }
            }

            if ($rowMin < 0) {
                continue;
            }

            for ($x = $width - 1 - (($width - 1) % $step); $x >= $rowMin; $x -= $step) {
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) >= self::TRANSPARENT_ALPHA) {
                    $rowMax = $x;
                    break;
                }
            }

            $minX = min($minX, $rowMin);
            $maxX = max($maxX, $rowMax);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
        }

        if ($maxX < 0) {
            return null;
        }

        $maxX = min($width - 1, $maxX + $step - 1);
        $maxY = min($height - 1, $maxY + $step - 1);

        return ['x' => $minX, 'y' => $minY, 'w' => $maxX - $minX + 1, 'h' => $maxY - $minY + 1];
    }

    /**
     * IHDR: byte 25 = tipo de cor. 4 (cinza+alfa) e 6 (RGBA) têm canal alfa; 3 (paleta)
     * só se houver chunk tRNS antes do primeiro IDAT.
     */
    private function declaresAlpha(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            $header = (string) fread($handle, 33);

            if (strlen($header) < 33 || substr($header, 12, 4) !== 'IHDR') {
                return false;
            }

            $colorType = ord($header[25]);

            if (in_array($colorType, [4, 6], true)) {
                return true;
            }

            // Percorre os chunks até o IDAT procurando tRNS (paleta/cinza/RGB com cor-chave).
            fseek($handle, 8);

            while (! feof($handle)) {
                $chunk = fread($handle, 8);

                if ($chunk === false || strlen($chunk) < 8) {
                    return false;
                }

                $length = unpack('N', substr($chunk, 0, 4))[1];
                $type = substr($chunk, 4, 4);

                if ($type === 'tRNS') {
                    return true;
                }

                if ($type === 'IDAT' || $type === 'IEND' || $length > self::MAX_BYTES) {
                    return false;
                }

                fseek($handle, $length + 4, SEEK_CUR);
            }

            return false;
        } finally {
            fclose($handle);
        }
    }
}
