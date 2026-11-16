<?php

use App\Models\Event;
use App\Models\Frame;
use Database\Seeders\FrameSeeder;

it('hides system frames deactivated by the admin from events', function () {
    $this->seed(FrameSeeder::class);

    $event = Event::factory()->create();
    $event->attachDefaultFrames();
    expect($event->activeFrames())->toHaveCount(4);

    Frame::withoutGlobalScopes()->where('template_key', 'floral')->update(['is_active' => false]);

    expect($event->fresh()->activeFrames()->pluck('template_key')->all())->not->toContain('floral')
        ->and($event->fresh()->activeFrames())->toHaveCount(3);

    $newEvent = Event::factory()->create();
    $newEvent->attachDefaultFrames();
    expect($newEvent->frames()->withoutGlobalScopes()->pluck('template_key')->all())->not->toContain('floral');
});
