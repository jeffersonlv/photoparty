<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDay()->startOfHour();

        return [
            'account_id' => Account::factory(),
            'name' => fake()->sentence(3),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'moderation' => 'pre',
            'is_trial' => false,
            'enabled_filters' => config('photoparty.filters'),
            'screen_settings' => config('photoparty.screen_defaults'),
            'language' => 'pt_BR',
            'status' => EventStatus::Draft,
        ];
    }

    public function trial(): static
    {
        return $this->state(function () {
            $startsAt = now()->addMinutes(30);

            return [
                'is_trial' => true,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->copy()->addHours(2),
            ];
        });
    }

    /**
     * Publicado e acontecendo agora.
     */
    public function live(): static
    {
        return $this->state(fn () => [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHours(2),
            'published_at' => now()->subHours(2),
            'expires_at' => now()->addDays(30),
            'status' => EventStatus::Live,
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now(),
            'expires_at' => now()->addDays(31),
            'status' => EventStatus::Scheduled,
        ]);
    }
}
