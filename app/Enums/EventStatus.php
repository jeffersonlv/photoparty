<?php

namespace App\Enums;

enum EventStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Live = 'live';
    case Ended = 'ended';
    case Expired = 'expired';

    public function label(): string
    {
        return __('app.event_status.'.$this->value);
    }

    public function isPublished(): bool
    {
        return $this !== self::Draft;
    }
}
