<?php

namespace Tests\Support;

use App\Models\Event;
use App\Models\Photo;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Fotos sem arquivo em disco (telão/moderação só precisam do registro).
 */
final class PhotoFixtures
{
    public static function make(Event $event, string $status = 'approved', ?CarbonInterface $approvedAt = null): Photo
    {
        $photo = new Photo(['orientation' => 'portrait', 'filter' => 'none']);
        $photo->uuid = (string) Str::uuid();
        $photo->account_id = $event->account_id;
        $photo->event_id = $event->id;
        $photo->status = $status;
        $photo->approved_at = $status === 'approved' ? ($approvedAt ?? now()) : null;
        $photo->device_hash = str_repeat('b', 64);
        Photo::withoutEvents(fn () => $photo->save());

        return $photo;
    }

    /**
     * @return array<int, Photo>  aprovadas com approved_at crescente (1 ms de diferença)
     */
    public static function approved(Event $event, int $count): array
    {
        $base = now()->subMinutes(5);

        return array_map(
            fn (int $i) => self::make($event, 'approved', $base->copy()->addMilliseconds($i * 10)),
            range(1, $count),
        );
    }

    public static function raw(Photo $photo): Photo
    {
        return Photo::withoutGlobalScopes()->findOrFail($photo->id);
    }
}
