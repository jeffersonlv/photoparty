<?php

namespace Tests\Support;

use App\Models\Account;
use App\Models\Event;
use App\Models\Photo;
use Database\Seeders\FrameSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fixtures dos testes de upload/mídia (módulos 3-4).
 */
final class GuestUpload
{
    /** Gera imagem real com GD no formato pedido. */
    public static function image(int $width, int $height, string $format = 'jpeg'): string
    {
        $img = imagecreatetruecolor($width, $height);
        imagefill($img, 0, 0, imagecolorallocate($img, 200, 60, 90));
        imagefilledrectangle($img, 10, 10, (int) ($width / 2), (int) ($height / 2), imagecolorallocate($img, 20, 120, 220));

        ob_start();
        match ($format) {
            'png' => imagepng($img),
            'webp' => imagewebp($img),
            'gif' => imagegif($img),
            default => imagejpeg($img, null, 85),
        };
        $bytes = (string) ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    /**
     * JPEG com segmento APP1/EXIF (inclui marcador GPS falso) logo após o SOI.
     */
    public static function jpegWithExif(int $width, int $height, string $marker): string
    {
        $jpeg = self::image($width, $height);

        $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x00".$marker;
        $payload = "Exif\x00\x00".$tiff;
        $segment = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
    }

    public static function file(string $bytes, string $name = 'photo.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    /**
     * Payload válido de upload (retrato por padrão).
     *
     * @return array<string, mixed>
     */
    public static function payload(array $overrides = [], string $orientation = 'portrait'): array
    {
        [$w, $h] = $orientation === 'portrait' ? [1080, 1350] : [1920, 1080];
        [$tw, $th] = $orientation === 'portrait' ? [320, 400] : [400, 225];

        return array_merge([
            'photo' => self::file(self::image($w, $h)),
            'thumb' => self::file(self::image($tw, $th), 'thumb.jpg'),
            'frame_key' => '',
            'filter' => 'none',
            'orientation' => $orientation,
            'guest_name' => '',
            'guest_texts' => '{}',
        ], $overrides);
    }

    /** Evento ao vivo de uma conta (com os modelos do sistema já registrados). */
    public static function liveEvent(?Account $account = null, array $attributes = []): Event
    {
        (new FrameSeeder())->run();

        return Event::factory()
            ->for($account ?? Account::factory()->active()->create())
            ->live()
            ->create($attributes);
    }

    /**
     * Foto gravada direto (registro + arquivos no disco fake), sem passar pelo upload.
     */
    public static function storedPhoto(Event $event, string $status = 'approved', string $deviceHash = ''): Photo
    {
        $account = Account::query()->findOrFail($event->account_id);
        $uuid = (string) Str::uuid();
        $base = $account->storagePath().'/events/'.$event->uuid;

        $photo = new Photo(['orientation' => 'portrait', 'filter' => 'none']);
        $photo->uuid = $uuid;
        $photo->account_id = $event->account_id;
        $photo->event_id = $event->id;
        $photo->status = $status;
        $photo->approved_at = $status === 'approved' ? now() : null;
        $photo->device_hash = $deviceHash !== '' ? $deviceHash : str_repeat('a', 64);
        $photo->original_path = "{$base}/original/{$uuid}.jpg";
        $photo->thumb_path = "{$base}/thumb/{$uuid}.jpg";
        Photo::withoutEvents(fn () => $photo->save());

        Storage::disk('local')->put($photo->original_path, self::image(1080, 1350));
        Storage::disk('local')->put($photo->thumb_path, self::image(320, 400));

        return $photo;
    }

    public static function storeUrl(Event $event, ?string $token = null): string
    {
        return route('guest.photos.store', ['slug' => $event->slug, 't' => $token ?? $event->uploadToken()]);
    }

    public static function pageUrl(Event $event, ?string $token = null): string
    {
        return route('guest.show', ['slug' => $event->slug, 't' => $token ?? $event->uploadToken()]);
    }
}
