<?php

namespace Tests\Support;

use App\Models\Account;
use App\Models\Frame;
use App\Models\Plan;
use Illuminate\Http\UploadedFile;

/**
 * Fixtures do módulo 8 (molduras personalizadas).
 */
final class FrameFixtures
{
    public const PORTRAIT_WINDOW = ['x' => 100, 'y' => 150, 'w' => 800, 'h' => 900];

    public const LANDSCAPE_WINDOW = ['x' => 200, 'y' => 100, 'w' => 1400, 'h' => 800];

    public static function paidAccount(bool $allowsCustom = true): Account
    {
        return Account::factory()->active(Plan::factory()->create(['allows_custom_frames' => $allowsCustom]))->create();
    }

    /**
     * PNG RGBA opaco com uma janela totalmente transparente.
     *
     * @param  array{x:int,y:int,w:int,h:int}|null  $window  null = sem área transparente
     */
    public static function png(int $width, int $height, ?array $window, bool $alphaChannel = true): string
    {
        $img = imagecreatetruecolor($width, $height);

        if ($alphaChannel) {
            imagealphablending($img, false);
            imagesavealpha($img, true);
        }

        imagefill($img, 0, 0, imagecolorallocatealpha($img, 20, 30, 120, 0));
        imagefilledrectangle($img, 0, 0, 50, 50, imagecolorallocatealpha($img, 250, 200, 0, 0));

        if ($window !== null) {
            imagefilledrectangle(
                $img,
                $window['x'],
                $window['y'],
                $window['x'] + $window['w'] - 1,
                $window['y'] + $window['h'] - 1,
                imagecolorallocatealpha($img, 0, 0, 0, 127),
            );
        }

        ob_start();
        imagepng($img);
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    public static function portraitPng(?array $window = self::PORTRAIT_WINDOW): string
    {
        return self::png(1080, 1350, $window);
    }

    public static function landscapePng(?array $window = self::LANDSCAPE_WINDOW): string
    {
        return self::png(1920, 1080, $window);
    }

    /** Insere um chunk (ex.: tEXt) logo após o IHDR, com CRC correto. */
    public static function withChunk(string $png, string $type, string $data): string
    {
        $chunk = pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
        $ihdrEnd = 8 + 4 + 4 + 13 + 4;

        return substr($png, 0, $ihdrEnd).$chunk.substr($png, $ihdrEnd);
    }

    public static function upload(string $bytes, string $name = 'frame.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    /**
     * Moldura custom gravada direto no banco (sem arquivos), para limites/isolamento.
     */
    public static function customFrame(Account $account, string $name = 'Marca', array $orientations = ['portrait']): Frame
    {
        $frame = new Frame(['name' => $name, 'type' => Frame::TYPE_CUSTOM]);
        $frame->forceFill(['account_id' => $account->id])->save();

        $dir = $account->storagePath().'/frames/'.$frame->uuid;
        $config = ['version' => 1, 'key' => $frame->uuid, 'name' => ['pt' => $name, 'en' => $name], 'orientations' => []];

        foreach ($orientations as $o) {
            $config['orientations'][$o] = [
                'width' => $o === 'portrait' ? 1080 : 1920,
                'height' => $o === 'portrait' ? 1350 : 1080,
                'background' => null,
                'window' => $o === 'portrait' ? self::PORTRAIT_WINDOW : self::LANDSCAPE_WINDOW,
                'overlay' => $o.'.png',
                'texts' => [],
                'effect' => null,
            ];
        }

        $frame->forceFill([
            'config' => $config,
            'portrait_path' => in_array('portrait', $orientations, true) ? "{$dir}/portrait.png" : null,
            'landscape_path' => in_array('landscape', $orientations, true) ? "{$dir}/landscape.png" : null,
        ])->save();

        return $frame;
    }
}
