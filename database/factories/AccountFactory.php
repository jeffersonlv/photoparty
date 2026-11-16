<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'status' => AccountStatus::Trial,
        ];
    }

    public function trial(): static
    {
        return $this->state(fn () => ['status' => AccountStatus::Trial, 'trial_used_at' => null, 'trial_ends_at' => null]);
    }

    public function trialInProgress(): static
    {
        return $this->state(fn () => [
            'status' => AccountStatus::Trial,
            'trial_used_at' => now()->subMinutes(30),
            'trial_ends_at' => now()->addMinutes(150),
        ]);
    }

    public function trialEnded(): static
    {
        return $this->state(fn () => [
            'status' => AccountStatus::TrialEnded,
            'trial_used_at' => now()->subHours(5),
            'trial_ends_at' => now()->subHours(2),
        ]);
    }

    public function active(?Plan $plan = null): static
    {
        return $this->state(fn () => [
            'status' => AccountStatus::Active,
            'plan_id' => $plan?->id ?? Plan::factory(),
        ]);
    }

    public function status(AccountStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
