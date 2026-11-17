<?php

namespace App\Services\Photos;

use App\Models\Account;
use App\Models\Event;
use App\Models\Photo;
use Illuminate\Support\Facades\Storage;

/**
 * Único ponto de decisão de qual variante de imagem uma conta pode ver (painel/galeria/moderação).
 *
 * - Conta active: thumb (~400 px) e original.
 * - Demais (trial, trial_ended, past_due, cancelled): só a prévia (lado maior 200 px, JPEG ~60);
 *   pedir thumb entrega a prévia e pedir original é negado (null => 404).
 * O telão (media.signed) não passa por aqui.
 */
class PhotoVariants
{
    public const PREVIEW_MAX_SIDE = 200;

    public const PREVIEW_QUALITY = 60;

    /**
     * Variante efetivamente servida para o pedido, ou null quando não é permitida.
     */
    public function resolve(Account $account, string $requested): ?string
    {
        if ($account->canDownloadPhotos()) {
            return in_array($requested, ['thumb', 'original'], true) ? $requested : null;
        }

        return $requested === 'thumb' ? 'preview' : null;
    }

    /**
     * Garante a prévia em disco (gera a partir do thumb com GD e cacheia). Devolve o caminho
     * relativo no disco local, ou null se não for possível gerar.
     */
    public function ensurePreview(Photo $photo): ?string
    {
        $disk = Storage::disk('local');
        $path = $photo->previewPath();

        if ($path === null || $photo->thumb_path === null || ! $disk->exists($photo->thumb_path)) {
            return null;
        }

        if ($disk->exists($path)) {
            return $path;
        }

        $source = @imagecreatefromstring((string) $disk->get($photo->thumb_path));

        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::PREVIEW_MAX_SIDE / max($width, $height));
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $target = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($target, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        $this->drawDemoBand($target, $this->labelFor($photo));

        ob_start();
        imagejpeg($target, null, self::PREVIEW_QUALITY);
        $bytes = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($target);

        if ($bytes === '' || ! $disk->put($path, $bytes)) {
            return null;
        }

        return $path;
    }

    private function labelFor(Photo $photo): string
    {
        $language = Event::withoutGlobalScopes()->whereKey($photo->event_id)->value('language');

        return str_starts_with((string) $language, 'en') ? 'DEMO' : 'DEMONSTRAÇÃO';
    }

    /**
     * Faixa diagonal semitransparente (~ -30°) de canto a canto com o texto. A fonte embutida do
     * GD (imagestring) é desenhada numa imagem pequena, ampliada e girada com imagerotate.
     */
    private function drawDemoBand(\GdImage $image, string $label): void
    {
        $w = imagesx($image);
        $h = imagesy($image);
        $angle = deg2rad(30);
        $cx = $w / 2;
        $cy = $h / 2;

        // Texto (fonte 5 = 9x15 px por caractere, Latin-1) em imagem transparente.
        $text = mb_convert_encoding($label, 'ISO-8859-1', 'UTF-8');
        $tw = strlen($text) * imagefontwidth(5);
        $th = imagefontheight(5);
        $small = imagecreatetruecolor($tw, $th);
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagefill($small, 0, 0, imagecolorallocatealpha($small, 0, 0, 0, 127));
        imagestring($small, 5, 0, 0, $text, imagecolorallocate($small, 255, 255, 255));

        // Largura do texto ao longo da faixa: ~78% do comprimento útil da diagonal central.
        $run = min($w / cos($angle), $h / sin($angle));
        $targetW = max(40, (int) round($run * 0.78));
        $targetH = max(1, (int) round($th * $targetW / $tw));
        $scaled = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefill($scaled, 0, 0, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagecopyresampled($scaled, $small, 0, 0, 0, 0, $targetW, $targetH, $tw, $th);

        // Faixa (polígono) de canto a canto, com folga além da diagonal.
        $half = $targetH * 0.85;
        $len = hypot($w, $h);
        [$dx, $dy] = [cos($angle), -sin($angle)];
        [$nx, $ny] = [sin($angle), cos($angle)];
        $points = [];
        foreach ([[-1, -1], [1, -1], [1, 1], [-1, 1]] as [$a, $b]) {
            $points[] = (int) round($cx + $dx * $len * $a + $nx * $half * $b);
            $points[] = (int) round($cy + $dy * $len * $a + $ny * $half * $b);
        }
        imagealphablending($image, true);
        imagefilledpolygon($image, $points, imagecolorallocatealpha($image, 0, 0, 0, 70));

        // Texto girado -30° (imagerotate é anti-horário) e composto no centro.
        $rotated = imagerotate($scaled, 30, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagealphablending($rotated, false);
        imagesavealpha($rotated, true);
        imagealphablending($image, true);
        imagecopy($image, $rotated, (int) round($cx - imagesx($rotated) / 2), (int) round($cy - imagesy($rotated) / 2), 0, 0, imagesx($rotated), imagesy($rotated));

        imagedestroy($small);
        imagedestroy($scaled);
        imagedestroy($rotated);
    }
}
