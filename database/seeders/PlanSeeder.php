<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Planos de exemplo. Preços ilustrativos; o admin ajusta depois (módulo 9).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'event',
                'name' => 'Evento avulso',
                'price' => 39.00,
                'max_events_per_month' => 1,
                'max_photos_per_event' => 500,
                'retention_days' => 30,
                'allows_custom_frames' => false,
                'white_label' => false,
                'sort_order' => 1,
            ],
            [
                'slug' => 'pro',
                'name' => 'Profissional',
                'price' => 79.00,
                'max_events_per_month' => 8,
                'max_photos_per_event' => 2000,
                'retention_days' => 60,
                'allows_custom_frames' => true,
                'white_label' => false,
                'sort_order' => 2,
            ],
            [
                'slug' => 'agency',
                'name' => 'Agência',
                'price' => 199.00,
                'max_events_per_month' => 30,
                'max_photos_per_event' => 5000,
                'retention_days' => 90,
                'allows_custom_frames' => true,
                'white_label' => true,
                'sort_order' => 3,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::query()->updateOrCreate(
                ['slug' => $plan['slug']],
                $plan + ['currency' => 'EUR', 'is_public' => true],
            );
        }
    }
}
