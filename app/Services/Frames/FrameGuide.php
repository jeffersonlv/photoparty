<?php

namespace App\Services\Frames;

use GdImage;
use Illuminate\Support\Str;

/**
 * Gabarito para download (PNG gerado com GD): área da moldura em cinza claro
 * semitransparente, janela central TRANSPARENTE (onde entra a foto, contorno vermelho)
 * e margem de segurança tracejada em azul (mantenha logos e textos dentro dela).
 * O organizador desenha por cima e envia o PNG mantendo a janela transparente.
 */
class FrameGuide
{
    /** Janela sugerida por orientação (x, y, w, h). */
    public const WINDOWS = [
        'portrait' => ['x' => 90, 'y' => 90, 'w' => 900, 'h' => 1010],
        'landscape' => ['x' => 120, 'y' => 90, 'w' => 1680, 'h' => 760],
    ];

    public const SAFE_MARGIN = 40;

    public function png(string $orientation): string
    {
        [$width, $height] = CustomFrameImage::SIZES[$orientation];
        $win = self::WINDOWS[$orientation];

        $img = imagecreatetruecolor($width, $height);
        imagealphablending($img, false);
        imagesavealpha($img, true);

        imagefill($img, 0, 0, imagecolorallocatealpha($img, 226, 232, 240, 30));

        $red = imagecolorallocatealpha($img, 220, 38, 38, 0);
        $blue = imagecolorallocatealpha($img, 37, 99, 235, 0);
        $text = imagecolorallocatealpha($img, 51, 65, 85, 0);

        // Contorno da janela (fora dela) e a janela transparente.
        imagefilledrectangle($img, $win['x'] - 4, $win['y'] - 4, $win['x'] + $win['w'] + 3, $win['y'] + $win['h'] + 3, $red);
        imagefilledrectangle($img, $win['x'], $win['y'], $win['x'] + $win['w'] - 1, $win['y'] + $win['h'] - 1, imagecolorallocatealpha($img, 0, 0, 0, 127));

        // Margem de segurança tracejada.
        imagesetstyle($img, array_merge(array_fill(0, 16, $blue), array_fill(0, 12, IMG_COLOR_TRANSPARENT)));
        imagesetthickness($img, 1);
        $m = self::SAFE_MARGIN;
        foreach ([0, 1, 2] as $d) {
            imagerectangle($img, $m + $d, $m + $d, $width - 1 - $m - $d, $height - 1 - $m - $d, IMG_COLOR_STYLED);
        }

        $belowWindow = $win['y'] + $win['h'] + 30;
        $this->label($img, __('frames.guide.window'), $win['x'] + 20, $win['y'] + 20, $red, 3);
        $this->label($img, __('frames.guide.safe'), $m + 10, $height - $m - 40, $blue, 2);
        $this->label($img, __('frames.guide.brand'), $win['x'], $belowWindow, $text, 3);
        $this->label($img, $width.' x '.$height.' px - PNG', $win['x'], $belowWindow + 60, $text, 2);

        ob_start();
        imagepng($img, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return $png;
    }

    /**
     * Texto ASCII com a fonte embutida do GD, ampliado (sem depender de arquivo de fonte).
     */
    private function label(GdImage $img, string $text, int $x, int $y, int $color, int $scale): void
    {
        $text = Str::ascii($text);
        $font = 5;
        $w = imagefontwidth($font) * strlen($text);
        $h = imagefontheight($font);

        if ($w === 0) {
            return;
        }

        $small = imagecreatetruecolor($w, $h);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        [$r, $g, $b] = [($color >> 16) & 0xFF, ($color >> 8) & 0xFF, $color & 0xFF];
        imagestring($small, $font, 0, 0, $text, imagecolorallocatealpha($small, $r, $g, $b, 0));

        imagealphablending($img, true);
        imagecopyresampled($img, $small, $x, $y, 0, 0, $w * $scale, $h * $scale, $w, $h);
        imagealphablending($img, false);
        imagedestroy($small);
    }
}
