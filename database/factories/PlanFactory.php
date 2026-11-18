<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Plano '.fake()->unique()->word();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'price' => 49.00,
            'currency' => 'EUR',
            'max_events_per_month' => 10,
            'max_photos_per_event' => 1000,
            'retention_days' => 30,
            'allows_custom_frames' => true,
            'allows_geofence' => false,
            'white_label' => false,
            'is_public' => true,
        ];
    }
}
